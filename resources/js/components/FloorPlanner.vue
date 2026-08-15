<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Columns3,
    GripHorizontal,
    GripVertical,
    LoaderCircle,
    Plus,
    RotateCcw,
    Rows3,
    Sparkles,
    Trash2,
    X,
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

const removeRowAisle = (rowNumber: number) => {
    form.aisle_after_rows = form.aisle_after_rows.filter((r) => r !== rowNumber);
};

const removeColAisle = (colNumber: number) => {
    form.aisle_after_columns = form.aisle_after_columns.filter((c) => c !== colNumber);
};

const clearAllAisles = () => {
    form.aisle_after_rows = [];
    form.aisle_after_columns = [];
};

const totalAislesCount = computed(() => form.aisle_after_rows.length + form.aisle_after_columns.length);

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

        <div class="mt-4 flex flex-wrap justify-center gap-2.5">
            <button
                type="button"
                class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-xl bg-primary px-4 text-xs font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-primary/90 disabled:opacity-50"
                :disabled="!validDimensions"
                @click="applyDimensions"
            >
                <Plus class="size-3.5" /> Apply Size
            </button>
            <button
                type="button"
                class="inline-flex h-9 items-center justify-center gap-1.5 whitespace-nowrap rounded-xl border border-destructive/30 bg-destructive/10 px-4 text-xs font-semibold text-destructive shadow-xs transition-colors hover:bg-destructive hover:text-destructive-foreground"
                @click="resetSeating"
                title="Unseat all students"
            >
                <RotateCcw class="size-3.5" /> Reset Seats
            </button>
        </div>

        <!-- Interactive Aisles Management Panel -->
        <div class="mt-5 rounded-2xl border border-border/80 bg-secondary/30 p-3.5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-foreground flex items-center gap-1.5">
                    Aisles ({{ totalAislesCount }})
                </span>
                <button
                    v-if="totalAislesCount > 0"
                    type="button"
                    class="text-[11px] font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400 hover:underline flex items-center gap-1"
                    @click="clearAllAisles"
                >
                    <Trash2 class="size-3" /> Clear all
                </button>
            </div>

            <div v-if="totalAislesCount > 0" class="mt-2.5 flex flex-wrap gap-1.5">
                <!-- Horizontal Row Aisle Chips -->
                <div
                    v-for="r in form.aisle_after_rows"
                    :key="`row-${r}`"
                    class="inline-flex items-center gap-1 rounded-lg border border-primary/20 bg-primary/10 pl-2 pr-1 py-1 text-[11px] font-medium text-primary shadow-2xs group"
                >
                    <GripHorizontal class="size-3 text-primary/70" />
                    <span>Row {{ r }} Aisle</span>
                    <button
                        type="button"
                        class="ml-1 rounded-md p-0.5 text-primary/70 hover:bg-rose-500 hover:text-white transition-colors"
                        :title="`Remove aisle after Row ${r}`"
                        @click="removeRowAisle(r)"
                    >
                        <X class="size-3" />
                    </button>
                </div>

                <!-- Vertical Column Aisle Chips -->
                <div
                    v-for="c in form.aisle_after_columns"
                    :key="`col-${c}`"
                    class="inline-flex items-center gap-1 rounded-lg border border-primary/20 bg-primary/10 pl-2 pr-1 py-1 text-[11px] font-medium text-primary shadow-2xs group"
                >
                    <GripVertical class="size-3 text-primary/70" />
                    <span>Col {{ c }} Aisle</span>
                    <button
                        type="button"
                        class="ml-1 rounded-md p-0.5 text-primary/70 hover:bg-rose-500 hover:text-white transition-colors"
                        :title="`Remove aisle after Column ${c}`"
                        @click="removeColAisle(c)"
                    >
                        <X class="size-3" />
                    </button>
                </div>
            </div>

            <p v-else class="mt-2 text-[11px] text-muted-foreground leading-relaxed">
                Click any gap between chairs in the seating chart to add or remove an aisle.
            </p>
        </div>

        <div class="mt-4 flex items-center justify-between text-xs text-muted-foreground">
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
