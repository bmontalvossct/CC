<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import {
    Armchair,
    CheckCircle2,
    Columns3,
    GripHorizontal,
    GripVertical,
    LoaderCircle,
    Plus,
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

type Axis = 'row' | 'column';
const dragging = ref<{ axis: Axis; from: number | null } | null>(null);
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

onBeforeUnmount(() => {
    if (autosaveTimer) clearTimeout(autosaveTimer);
});

const positions = (axis: Axis) => (axis === 'row' ? form.aisle_after_rows : form.aisle_after_columns);
const hasAisle = (axis: Axis, position: number) => positions(axis).includes(position);

const setPositions = (axis: Axis, values: number[]) => {
    const sorted = [...new Set(values)].sort((a, b) => a - b);
    if (axis === 'row') form.aisle_after_rows = sorted;
    else form.aisle_after_columns = sorted;
};

const axisSize = (axis: Axis) => Number(axis === 'row' ? form.rows : form.columns) || 0;
const canAddAisle = (axis: Axis) => positions(axis).length < Math.max(0, axisSize(axis) - 1);
const addAisle = (axis: Axis) => {
    const lastPosition = axisSize(axis) - 1;
    if (lastPosition < 1) return;

    const center = (lastPosition + 1) / 2;
    const nextPosition = Array.from({ length: lastPosition }, (_, index) => index + 1)
        .sort((left, right) => Math.abs(left - center) - Math.abs(right - center) || left - right)
        .find((position) => !hasAisle(axis, position));

    if (nextPosition !== undefined) setPositions(axis, [...positions(axis), nextPosition]);
};

const startDrag = (event: DragEvent, axis: Axis, from: number | null = null) => {
    dragging.value = { axis, from };
    event.dataTransfer?.setData('text/plain', axis);
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
};

const dropAisle = (axis: Axis, position: number) => {
    if (!dragging.value || dragging.value.axis !== axis) return;
    const next = positions(axis).filter((item) => item !== dragging.value?.from);
    if (!next.includes(position)) next.push(position);
    setPositions(axis, next);
    dragging.value = null;
};

const toggleAisle = (axis: Axis, position: number) => {
    const current = positions(axis);
    setPositions(axis, current.includes(position) ? current.filter((item) => item !== position) : [...current, position]);
};
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
                <Label for="floor-rows" class="flex items-center gap-1.5 text-xs font-semibold">
                    <Rows3 class="size-3.5 text-primary" /> Chair rows
                </Label>
                <Input id="floor-rows" v-model.number="form.rows" type="number" min="1" max="20" class="h-9 text-sm" />
            </div>
            <div class="grid gap-1.5">
                <Label for="floor-columns" class="flex items-center gap-1.5 text-xs font-semibold">
                    <Columns3 class="size-3.5 text-primary" /> Chair columns
                </Label>
                <Input id="floor-columns" v-model.number="form.columns" type="number" min="1" max="20" class="h-9 text-sm" />
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-border/80 bg-secondary/40 p-3.5">
            <p class="text-xs font-bold text-foreground">Add walkway aisles</p>
            <div class="mt-2.5 grid gap-2 sm:grid-cols-2">
                <div
                    draggable="true"
                    class="flex cursor-grab items-center justify-between gap-2 rounded-lg border border-border bg-card p-2 text-xs font-medium text-card-foreground shadow-xs active:cursor-grabbing hover:border-primary/40 transition-colors"
                    @dragstart="startDrag($event, 'column')"
                    @dragend="dragging = null"
                >
                    <span class="flex items-center gap-1.5"><GripVertical class="size-4 text-primary" /> Vertical</span>
                    <button
                        type="button"
                        class="inline-flex h-7 items-center gap-1 rounded-full bg-primary px-2.5 text-[11px] font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-40 hover:bg-primary/90 transition-colors"
                        :disabled="!canAddAisle('column')"
                        @click="addAisle('column')"
                    >
                        <Plus class="size-3" /> Add
                    </button>
                </div>
                <div
                    draggable="true"
                    class="flex cursor-grab items-center justify-between gap-2 rounded-lg border border-border bg-card p-2 text-xs font-medium text-card-foreground shadow-xs active:cursor-grabbing hover:border-primary/40 transition-colors"
                    @dragstart="startDrag($event, 'row')"
                    @dragend="dragging = null"
                >
                    <span class="flex items-center gap-1.5"><GripHorizontal class="size-4 text-primary" /> Horizontal</span>
                    <button
                        type="button"
                        class="inline-flex h-7 items-center gap-1 rounded-full bg-primary px-2.5 text-[11px] font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-40 hover:bg-primary/90 transition-colors"
                        :disabled="!canAddAisle('row')"
                        @click="addAisle('row')"
                    >
                        <Plus class="size-3" /> Add
                    </button>
                </div>
            </div>
            <p class="mt-2 text-[11px] text-muted-foreground">
                Drag an aisle handle or click any gap in the preview below.
            </p>
        </div>

        <!-- Interactive Room Preview -->
        <div class="mt-4 overflow-x-auto rounded-xl border border-border/80 bg-secondary/30 p-3.5">
            <div class="mb-3 rounded-lg bg-zinc-900 dark:bg-zinc-800 py-1.5 text-center text-[10px] font-extrabold uppercase tracking-[0.2em] text-white shadow-xs">
                Teaching wall / front
            </div>
            <div class="min-w-max flex flex-col items-center">
                <template v-for="row in Number(form.rows) || 0" :key="row">
                    <div class="flex items-center">
                        <template v-for="column in Number(form.columns) || 0" :key="`${row}-${column}`">
                            <div
                                class="grid size-9 shrink-0 place-items-center rounded-lg border border-border bg-card text-muted-foreground shadow-xs"
                                :title="`Row ${row}, column ${column}`"
                            >
                                <Armchair class="size-3.5" />
                                <span class="text-[7.5px] font-mono font-bold leading-none">R{{ row }}C{{ column }}</span>
                            </div>
                            <button
                                v-if="column < Number(form.columns)"
                                type="button"
                                :draggable="hasAisle('column', column)"
                                :aria-label="`${hasAisle('column', column) ? 'Move or remove' : 'Add'} vertical aisle after column ${column}`"
                                :aria-pressed="hasAisle('column', column)"
                                class="mx-1 flex h-9 shrink-0 items-center justify-center rounded-md border-2 border-dashed transition-all"
                                :class="
                                    hasAisle('column', column)
                                        ? 'w-7 cursor-grab border-primary bg-primary/10 text-primary'
                                        : 'w-2.5 border-border bg-card/60 hover:w-6 hover:border-primary/50'
                                "
                                @click="toggleAisle('column', column)"
                                @dragstart="startDrag($event, 'column', column)"
                                @dragend="dragging = null"
                                @dragover.prevent
                                @drop.prevent="dropAisle('column', column)"
                            >
                                <GripVertical v-if="hasAisle('column', column)" class="size-3.5" />
                            </button>
                        </template>
                    </div>
                    <button
                        v-if="row < Number(form.rows)"
                        type="button"
                        :draggable="hasAisle('row', row)"
                        :aria-label="`${hasAisle('row', row) ? 'Move or remove' : 'Add'} horizontal aisle after row ${row}`"
                        :aria-pressed="hasAisle('row', row)"
                        class="my-1 flex w-full items-center justify-center rounded-md border-2 border-dashed transition-all"
                        :class="
                            hasAisle('row', row)
                                ? 'h-7 cursor-grab border-primary bg-primary/10 text-primary'
                                : 'h-2.5 border-border bg-card/60 hover:h-6 hover:border-primary/50'
                        "
                        @click="toggleAisle('row', row)"
                        @dragstart="startDrag($event, 'row', row)"
                        @dragend="dragging = null"
                        @dragover.prevent
                        @drop.prevent="dropAisle('row', row)"
                    >
                        <GripHorizontal v-if="hasAisle('row', row)" class="size-3.5" />
                    </button>
                </template>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between text-xs text-muted-foreground">
            <span class="font-medium">{{ chairCount }} total chairs</span>
            <span class="font-medium">
                {{ form.aisle_after_columns.length + form.aisle_after_rows.length }}
                {{ form.aisle_after_columns.length + form.aisle_after_rows.length === 1 ? 'aisle' : 'aisles' }}
            </span>
        </div>

        <InputError
            class="mt-2"
            :message="form.errors.rows || form.errors.columns || form.errors.aisle_after_rows || form.errors.aisle_after_columns"
        />

        <div
            class="mt-4 flex items-center gap-2 rounded-xl bg-secondary/80 px-3.5 py-2.5 text-xs font-semibold text-secondary-foreground border border-border/50"
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
