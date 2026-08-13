<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { Armchair, CheckCircle2, Columns3, GripHorizontal, GripVertical, LoaderCircle, Rows3 } from 'lucide-vue-next';
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
    <section class="rounded-2xl border border-[#e5e7eb] bg-[#ffffff] p-5">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#0066cc]">Room setup</p>
            <h3 class="mt-1 text-xl font-bold text-[#1d1d1f]">Rows, columns, and aisles</h3>
            <p class="mt-1 text-sm leading-relaxed text-[#6e6e73]">Change the chair grid or aisles and the classroom floor updates automatically.</p>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="grid gap-1.5">
                <Label for="floor-rows" class="flex items-center gap-1.5"><Rows3 class="size-4" /> Chair rows</Label>
                <Input id="floor-rows" v-model.number="form.rows" type="number" min="1" max="20" />
            </div>
            <div class="grid gap-1.5">
                <Label for="floor-columns" class="flex items-center gap-1.5"><Columns3 class="size-4" /> Chair columns</Label>
                <Input id="floor-columns" v-model.number="form.columns" type="number" min="1" max="20" />
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-[#dbeafe] bg-[#f5f9ff] p-3">
            <p class="text-xs font-bold text-[#515154]">Drag an aisle into the preview</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <button
                    type="button"
                    draggable="true"
                    class="flex cursor-grab items-center gap-2 rounded-lg border border-[#bfdbfe] bg-white px-3 py-2 text-xs font-bold text-[#515154] shadow-sm active:cursor-grabbing"
                    @dragstart="startDrag($event, 'column')"
                    @dragend="dragging = null"
                >
                    <GripVertical class="size-4 text-[#0071e3]" /> Vertical aisle
                </button>
                <button
                    type="button"
                    draggable="true"
                    class="flex cursor-grab items-center gap-2 rounded-lg border border-[#bfdbfe] bg-white px-3 py-2 text-xs font-bold text-[#515154] shadow-sm active:cursor-grabbing"
                    @dragstart="startDrag($event, 'row')"
                    @dragend="dragging = null"
                >
                    <GripHorizontal class="size-4 text-[#0071e3]" /> Horizontal aisle
                </button>
            </div>
            <p class="mt-2 text-[11px] text-[#86868b]">You can also click any gap to add or remove an aisle.</p>
        </div>

        <div class="mt-4 overflow-x-auto rounded-xl border border-[#e5e7eb] bg-[#f5f5f7] p-3">
            <div class="mb-3 rounded-lg bg-[#1d1d1f] py-2 text-center text-[10px] font-bold uppercase tracking-[0.18em] text-white">Front board</div>
            <div class="min-w-max">
                <template v-for="row in Number(form.rows) || 0" :key="row">
                    <div class="flex items-center">
                        <template v-for="column in Number(form.columns) || 0" :key="`${row}-${column}`">
                            <div
                                class="grid size-11 shrink-0 place-items-center rounded-lg border border-[#e5e7eb] bg-white text-[#86868b] shadow-sm"
                                :title="`Row ${row}, column ${column}`"
                            >
                                <Armchair class="size-4" />
                                <span class="text-[8px] font-bold">R{{ row }}C{{ column }}</span>
                            </div>
                            <button
                                v-if="column < Number(form.columns)"
                                type="button"
                                :draggable="hasAisle('column', column)"
                                :aria-label="`${hasAisle('column', column) ? 'Move or remove' : 'Add'} vertical aisle after column ${column}`"
                                :aria-pressed="hasAisle('column', column)"
                                class="mx-1 flex h-11 shrink-0 items-center justify-center rounded-md border-2 border-dashed transition"
                                :class="
                                    hasAisle('column', column)
                                        ? 'w-9 cursor-grab border-[#0071e3] bg-[#eaf4ff] text-[#0066cc]'
                                        : 'w-3 border-[#e5e7eb] bg-white/70 hover:w-7 hover:border-[#2997ff]'
                                "
                                @click="toggleAisle('column', column)"
                                @dragstart="startDrag($event, 'column', column)"
                                @dragend="dragging = null"
                                @dragover.prevent
                                @drop.prevent="dropAisle('column', column)"
                            >
                                <GripVertical v-if="hasAisle('column', column)" class="size-4" />
                            </button>
                        </template>
                    </div>
                    <button
                        v-if="row < Number(form.rows)"
                        type="button"
                        :draggable="hasAisle('row', row)"
                        :aria-label="`${hasAisle('row', row) ? 'Move or remove' : 'Add'} horizontal aisle after row ${row}`"
                        :aria-pressed="hasAisle('row', row)"
                        class="my-1 flex w-full items-center justify-center rounded-md border-2 border-dashed transition"
                        :class="
                            hasAisle('row', row)
                                ? 'h-9 cursor-grab border-[#0071e3] bg-[#eaf4ff] text-[#0066cc]'
                                : 'h-3 border-[#e5e7eb] bg-white/70 hover:h-7 hover:border-[#2997ff]'
                        "
                        @click="toggleAisle('row', row)"
                        @dragstart="startDrag($event, 'row', row)"
                        @dragend="dragging = null"
                        @dragover.prevent
                        @drop.prevent="dropAisle('row', row)"
                    >
                        <GripHorizontal v-if="hasAisle('row', row)" class="size-4" />
                    </button>
                </template>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between text-xs text-[#86868b]">
            <span>{{ chairCount }} chairs</span>
            <span
                >{{ form.aisle_after_columns.length + form.aisle_after_rows.length }}
                {{ form.aisle_after_columns.length + form.aisle_after_rows.length === 1 ? 'aisle' : 'aisles' }}</span
            >
        </div>
        <p class="mt-2 text-[11px] leading-relaxed text-[#86868b]">Students stay in the same row and column whenever that chair still exists.</p>
        <InputError
            class="mt-2"
            :message="form.errors.rows || form.errors.columns || form.errors.aisle_after_rows || form.errors.aisle_after_columns"
        />
        <div class="mt-4 flex items-center gap-2 rounded-xl bg-[#f5f5f7] px-3 py-2.5 text-xs font-semibold text-[#515154]" aria-live="polite">
            <LoaderCircle v-if="form.processing" class="size-4 animate-spin text-[#0071e3]" />
            <CheckCircle2 v-else class="size-4 text-[#0071e3]" />
            <span v-if="form.processing">Updating classroom floor...</span>
            <span v-else-if="form.recentlySuccessful">Classroom floor updated.</span>
            <span v-else>Changes are saved automatically.</span>
        </div>
    </section>
</template>
