<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Columns3,
    LoaderCircle,
    Plus,
    RotateCcw,
    Rows3,
    Sparkles,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

interface FloorPlan {
    rows: number;
    columns: number;
    aisle_after_rows: number[];
    aisle_after_columns: number[];
}

const props = defineProps<{
    sectionId: number;
    initialPlan: FloorPlan;
}>();

const form = useForm({
    rows: props.initialPlan.rows,
    columns: props.initialPlan.columns,
    aisle_after_rows: [...props.initialPlan.aisle_after_rows],
    aisle_after_columns: [...props.initialPlan.aisle_after_columns],
});

const inputRows = ref(props.initialPlan.rows);
const inputColumns = ref(props.initialPlan.columns);
const validDimensions = computed(
    () =>
        Number.isInteger(Number(inputRows.value)) &&
        Number.isInteger(Number(inputColumns.value)) &&
        Number(inputRows.value) >= 1 &&
        Number(inputRows.value) <= 20 &&
        Number(inputColumns.value) >= 1 &&
        Number(inputColumns.value) <= 20
);

const applyDimensions = () => {
    if (!validDimensions.value) return;
    form.rows = inputRows.value;
    form.columns = inputColumns.value;
};

const resetSeating = () => {
    if (confirm('Are you sure you want to reset all seating arrangements? Students will become unseated.')) {
        router.post(`/sections/${props.sectionId}/seats/reset`, {}, { preserveScroll: true });
    }
};

const queuedWhileSaving = ref(false);
const chairCount = computed(() => Number(form.rows || 0) * Number(form.columns || 0));
const validPlan = computed(
    () =>
        Number.isInteger(Number(form.rows)) &&
        Number.isInteger(Number(form.columns)) &&
        Number(form.rows) >= 1 &&
        Number(form.rows) <= 20 &&
        Number(form.columns) >= 1 &&
        Number(form.columns) <= 20,
);
let autosaveTimer: ReturnType<typeof setTimeout> | null = null;

const save = () => {
    if (!validPlan.value) return;
    if (form.processing) {
        queuedWhileSaving.value = true;
        return;
    }

    form.put(`/sections/${props.sectionId}/floor-plan`, {
        preserveScroll: true,
        onFinish: () => {
            if (queuedWhileSaving.value) {
                queuedWhileSaving.value = false;
                queueSave(100);
            }
        },
    });
};

const queueSave = (delay = 650) => {
    if (autosaveTimer) clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(save, delay);
};

watch(
    () => [form.rows, form.columns, form.aisle_after_rows.join(','), form.aisle_after_columns.join(',')],
    () => {
        const validRowAisles = form.aisle_after_rows.filter((position) => position < Number(form.rows));
        const validColumnAisles = form.aisle_after_columns.filter((position) => position < Number(form.columns));
        if (validRowAisles.length !== form.aisle_after_rows.length) form.aisle_after_rows = validRowAisles;
        if (validColumnAisles.length !== form.aisle_after_columns.length) form.aisle_after_columns = validColumnAisles;
        queueSave();
    },
);

watch(
    () => props.initialPlan,
    (newPlan) => {
        form.rows = newPlan.rows;
        form.columns = newPlan.columns;
        form.aisle_after_rows = [...newPlan.aisle_after_rows];
        form.aisle_after_columns = [...newPlan.aisle_after_columns];
        inputRows.value = newPlan.rows;
        inputColumns.value = newPlan.columns;
    },
    { deep: true }
);

onBeforeUnmount(() => {
    if (autosaveTimer) clearTimeout(autosaveTimer);
});
</script>

<template>
    <section class="paper-card p-6">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-primary">
                <Sparkles class="size-3.5" />
                <span>Room architecture</span>
            </div>
            <h3 class="mt-1 text-xl font-bold tracking-tight text-foreground">Floor planner</h3>
            <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                Adjust dimensions and aisles below. Changes sync automatically to the live floor.
            </p>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="grid gap-1.5">
                <Label for="floor-rows" class="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                    <Rows3 class="size-3.5 text-primary" /> Chair rows
                </Label>
                <Input id="floor-rows" v-model.number="inputRows" type="number" min="1" max="20" class="h-9 text-sm" />
            </div>
            <div class="grid gap-1.5">
                <Label for="floor-columns" class="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                    <Columns3 class="size-3.5 text-primary" /> Chair columns
                </Label>
                <Input id="floor-columns" v-model.number="inputColumns" type="number" min="1" max="20" class="h-9 text-sm" />
            </div>
        </div>

        <div class="mt-5 flex flex-wrap justify-center gap-3">
            <button
                type="button"
                class="inline-flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-primary px-5 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 disabled:opacity-50"
                :disabled="!validDimensions"
                @click="applyDimensions"
            >
                <Plus class="size-4" /> Add
            </button>
            <button
                type="button"
                class="inline-flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-destructive px-5 text-sm font-medium text-destructive-foreground shadow-sm transition-colors hover:bg-destructive/90"
                @click="resetSeating"
            >
                <RotateCcw class="size-4" /> Reset
            </button>
        </div>
        <div class="mt-5 flex items-center justify-between text-xs text-muted-foreground">
            <span class="font-normal">{{ chairCount }} total chairs</span>
            <span class="font-normal">
                {{ form.aisle_after_columns.length + form.aisle_after_rows.length }}
                {{ form.aisle_after_columns.length + form.aisle_after_rows.length === 1 ? 'aisle' : 'aisles' }}
            </span>
        </div>

        <InputError
            class="mt-2"
            :message="form.errors.rows || form.errors.columns || form.errors.aisle_after_rows || form.errors.aisle_after_columns"
        />

        <div
            class="mt-4 flex items-center gap-2 rounded-xl bg-secondary/80 px-3.5 py-2.5 text-xs font-medium text-secondary-foreground border border-border/50"
            aria-live="polite"
        >
            <LoaderCircle v-if="form.processing" class="size-4 animate-spin text-primary" />
            <CheckCircle2 v-else class="size-4 text-emerald-600 dark:text-emerald-400" />
            <span v-if="form.processing">Syncing classroom layout...</span>
            <span v-else-if="form.recentlySuccessful">Layout saved.</span>
            <span v-else>Changes save automatically.</span>
        </div>
    </section>
</template>
