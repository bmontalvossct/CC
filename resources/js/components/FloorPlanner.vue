<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, useForm } from '@inertiajs/vue3';
import {
    Check,
    CheckCircle2,
    Columns3,
    GripHorizontal,
    GripVertical,
    RotateCcw,
    Rows3,
    Save,
    Sparkles,
    Trash2,
    Undo2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
        Number(inputColumns.value) <= 20,
);

const applyDimensions = () => {
    if (!validDimensions.value) return;
    form.rows = inputRows.value;
    form.columns = inputColumns.value;
    // Clean up aisles that exceed new dimensions
    form.aisle_after_rows = form.aisle_after_rows.filter((r) => r < Number(form.rows));
    form.aisle_after_columns = form.aisle_after_columns.filter((c) => c < Number(form.columns));
};

const resetSeating = () => {
    if (confirm('Are you sure you want to reset all seating arrangements? Students will become unseated.')) {
        router.post(`/sections/${props.sectionId}/seats/reset`, {}, { preserveScroll: true });
    }
};

const revertDraft = () => {
    form.rows = props.initialPlan.rows;
    form.columns = props.initialPlan.columns;
    form.aisle_after_rows = [...props.initialPlan.aisle_after_rows];
    form.aisle_after_columns = [...props.initialPlan.aisle_after_columns];
    inputRows.value = props.initialPlan.rows;
    inputColumns.value = props.initialPlan.columns;
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

const isDirty = computed(() => {
    if (Number(inputRows.value) !== props.initialPlan.rows || Number(inputColumns.value) !== props.initialPlan.columns) {
        return true;
    }
    if (form.rows !== props.initialPlan.rows || form.columns !== props.initialPlan.columns) {
        return true;
    }
    const currentRows = [...form.aisle_after_rows].sort().join(',');
    const initialRows = [...props.initialPlan.aisle_after_rows].sort().join(',');
    const currentCols = [...form.aisle_after_columns].sort().join(',');
    const initialCols = [...props.initialPlan.aisle_after_columns].sort().join(',');
    return currentRows !== initialRows || currentCols !== initialCols;
});

const saveFloorPlan = () => {
    applyDimensions();
    if (!validPlan.value || form.processing) return;

    form.put(`/sections/${props.sectionId}/floor-plan`, {
        preserveScroll: true,
    });
};

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
    { deep: true },
);
</script>

<template>
    <section class="paper-card p-6">
        <div>
            <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-primary">
                <Sparkles class="size-3.5" />
                <span>Room architecture</span>
            </div>
            <h3 class="mt-1 text-xl font-medium tracking-tight text-foreground">Floor planner</h3>
            <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                Configure chair dimensions and aisles below. Click "Save Floor Plan" when ready to apply changes.
            </p>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="grid gap-1.5">
                <Label for="floor-rows" class="flex items-center gap-1.5 text-xs font-medium text-foreground">
                    <Rows3 class="size-3.5 text-primary" /> Chair rows
                </Label>
                <Input
                    id="floor-rows"
                    v-model.number="inputRows"
                    type="number"
                    min="1"
                    max="20"
                    class="h-9 text-sm"
                    @change="applyDimensions"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="floor-columns" class="flex items-center gap-1.5 text-xs font-medium text-foreground">
                    <Columns3 class="size-3.5 text-primary" /> Chair columns
                </Label>
                <Input
                    id="floor-columns"
                    v-model.number="inputColumns"
                    type="number"
                    min="1"
                    max="20"
                    class="h-9 text-sm"
                    @change="applyDimensions"
                />
            </div>
        </div>

        <!-- Action Controls -->
        <div class="mt-4 flex flex-wrap gap-2.5">
            <button
                type="button"
                class="group inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-4 text-sm font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white disabled:opacity-50 dark:bg-card"
                :disabled="!validDimensions || form.processing"
                @click="saveFloorPlan"
            >
                <LoadingSpinner v-if="form.processing" size="sm" variant="primary" />
                <Save v-else class="size-4 text-primary transition-colors group-hover:text-white" />
                <span>{{ form.processing ? 'Saving layout...' : 'Save Floor Plan' }}</span>
            </button>

            <button
                v-if="isDirty"
                type="button"
                class="group inline-flex h-10 items-center justify-center gap-1.5 rounded-xl border border-primary bg-white px-3.5 text-sm font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                @click="revertDraft"
                title="Discard unsaved edits"
            >
                <Undo2 class="size-4 text-primary transition-colors group-hover:text-white" />
                <span>Revert</span>
            </button>

            <button
                type="button"
                class="group inline-flex h-10 items-center justify-center gap-1.5 rounded-xl border border-rose-600 bg-white px-3.5 text-sm font-medium text-rose-700 shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                @click="resetSeating"
                title="Unseat all students"
            >
                <RotateCcw class="size-4 text-rose-700 transition-colors group-hover:text-white" />
                <span>Reset Seats</span>
            </button>
        </div>

        <!-- Interactive Aisles Management Panel -->
        <div class="mt-5 rounded-2xl border border-border/80 bg-secondary/30 p-3.5">
            <div class="flex items-center justify-between">
                <span class="flex items-center gap-1.5 text-xs font-medium text-foreground">
                    Aisles ({{ totalAislesCount }})
                </span>
                <button
                    v-if="totalAislesCount > 0"
                    type="button"
                    class="flex items-center gap-1 text-xs font-medium text-rose-700 hover:text-rose-800 hover:underline dark:text-rose-400"
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
                    class="group inline-flex items-center gap-1 rounded-lg border border-primary/20 bg-primary/10 py-1 pl-2 pr-1 text-xs font-medium text-primary"
                >
                    <GripHorizontal class="size-3 text-primary/70" />
                    <span>Row {{ r }} Aisle</span>
                    <button
                        type="button"
                        class="ml-1 rounded-md p-0.5 text-primary/70 transition-colors hover:bg-rose-500 hover:text-white"
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
                    class="group inline-flex items-center gap-1 rounded-lg border border-primary/20 bg-primary/10 py-1 pl-2 pr-1 text-xs font-medium text-primary"
                >
                    <GripVertical class="size-3 text-primary/70" />
                    <span>Col {{ c }} Aisle</span>
                    <button
                        type="button"
                        class="ml-1 rounded-md p-0.5 text-primary/70 transition-colors hover:bg-rose-500 hover:text-white"
                        :title="`Remove aisle after Column ${c}`"
                        @click="removeColAisle(c)"
                    >
                        <X class="size-3" />
                    </button>
                </div>
            </div>

            <p v-else class="mt-2 text-xs leading-relaxed text-muted-foreground">
                Click any gap between chairs in the seating chart to add or remove an aisle, then click "Save Floor Plan".
            </p>
        </div>

        <div class="mt-4 flex items-center justify-between text-xs text-muted-foreground">
            <span>{{ chairCount }} total chairs</span>
            <span>
                {{ form.aisle_after_columns.length + form.aisle_after_rows.length }}
                {{ form.aisle_after_columns.length + form.aisle_after_rows.length === 1 ? 'aisle' : 'aisles' }}
            </span>
        </div>

        <InputError
            class="mt-2"
            :message="form.errors.rows || form.errors.columns || form.errors.aisle_after_rows || form.errors.aisle_after_columns"
        />

        <!-- Status Bar -->
        <div
            class="mt-4 flex items-center gap-2 rounded-xl border border-border/80 px-3.5 py-2.5 text-xs font-medium"
            :class="isDirty ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/30' : 'bg-secondary/60 text-secondary-foreground'"
            aria-live="polite"
        >
            <LoadingSpinner v-if="form.processing" size="xs" variant="primary" />
            <CheckCircle2 v-else-if="form.recentlySuccessful" class="size-4 text-emerald-600 dark:text-emerald-400" />
            <span v-if="form.processing">Saving floor plan to server...</span>
            <span v-else-if="form.recentlySuccessful">Floor plan saved successfully.</span>
            <span v-else-if="isDirty">Unsaved changes · Click "Save Floor Plan" to apply.</span>
            <span v-else>Floor plan is up to date.</span>
        </div>
    </section>
</template>
