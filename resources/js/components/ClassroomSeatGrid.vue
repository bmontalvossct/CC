<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Armchair, GripHorizontal, GripVertical, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    block: any;
    selectedSeatId?: number | null;
    sectionName?: string;
    unseatedStudents?: Array<{ id: number; student_number: string; full_name: string }>;
}>();

const emit = defineEmits<{
    selectStudent: [student: any];
    selectSeat: [seatId: number];
    assignStudent: [payload: { student: any; seatId: number }];
    dragMoveStudent: [payload: { studentId: number; targetSeatId: number }];
    updateAisles: [payload: { axis: 'row' | 'column'; values: number[] }];
}>();

const toggleAisle = (axis: 'row' | 'column', position: number) => {
    const currentAisles = axis === 'row' ? [...(props.block.aisle_after_rows ?? [])] : [...(props.block.aisle_after_columns ?? [])];

    const updatedAisles = currentAisles.includes(position) ? currentAisles.filter((item: number) => item !== position) : [...currentAisles, position];

    emit('updateAisles', { axis, values: updatedAisles });
};

const activeSeatMenuId = ref<number | null>(null);
const activeDragOverSeatId = ref<number | null>(null);

const rows = computed(() =>
    Array.from({ length: props.block.internal_rows }, (_, index) =>
        props.block.seats.filter((seat: any) => seat.row_number === index + 1).sort((a: any, b: any) => a.column_number - b.column_number),
    ),
);

const hasColumnAisle = (position: number) => (props.block.aisle_after_columns ?? []).includes(position);
const hasRowAisle = (position: number) => (props.block.aisle_after_rows ?? []).includes(position);

const chooseSeat = (seat: any) => {
    if (seat.student) {
        emit('selectStudent', seat.student);
    } else {
        if (props.unseatedStudents && props.unseatedStudents.length > 0) {
            activeSeatMenuId.value = activeSeatMenuId.value === seat.id ? null : seat.id;
        } else {
            emit('selectSeat', seat.id);
        }
    }
};

const assignStudent = (student: any, seatId: number) => {
    emit('assignStudent', { student, seatId });
    activeSeatMenuId.value = null;
};

const selectForEnroll = (seatId: number) => {
    emit('selectSeat', seatId);
    activeSeatMenuId.value = null;
};

const dragStart = (event: DragEvent, student: any) => {
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', JSON.stringify({ studentId: student.id }));
    }
};

const dropSeat = (event: DragEvent, targetSeat: any) => {
    activeDragOverSeatId.value = null;
    const dataStr = event.dataTransfer?.getData('text/plain');
    if (!dataStr) return;
    try {
        const data = JSON.parse(dataStr);
        if (data.studentId) {
            emit('dragMoveStudent', { studentId: data.studentId, targetSeatId: targetSeat.id });
        }
    } catch {
        if (!isNaN(Number(dataStr))) {
            emit('dragMoveStudent', { studentId: Number(dataStr), targetSeatId: targetSeat.id });
        }
    }
};

const closeMenu = () => {
    activeSeatMenuId.value = null;
};

onMounted(() => {
    window.addEventListener('click', closeMenu);
});

onUnmounted(() => {
    window.removeEventListener('click', closeMenu);
});

const initials = (name?: string) => {
    if (!name) return '';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();
};
</script>

<template>
    <article class="rounded-2xl border border-border/80 bg-card/90 p-5 shadow-sm">
        <p v-if="block.label && block.label !== 'Classroom'" class="mb-4 text-xs font-bold uppercase tracking-[0.14em] text-muted-foreground">
            {{ block.label }}
        </p>

        <TooltipProvider :delay-duration="150">
            <template v-for="(seats, rowIndex) in rows" :key="rowIndex">
                <div class="my-3 flex items-stretch gap-3 first:mt-0 last:mb-0">
                    <template v-for="seat in seats" :key="seat.id">
                        <div
                            class="relative flex min-w-[6rem] flex-1 flex-col items-stretch sm:min-w-[7.5rem]"
                            @dragover.prevent
                            @dragenter="activeDragOverSeatId = seat.id"
                            @dragleave="activeDragOverSeatId = null"
                            @drop.prevent="dropSeat($event, seat)"
                        >
                            <Tooltip v-if="seat.student">
                                <TooltipTrigger asChild>
                                    <button
                                        type="button"
                                        :disabled="seat.is_disabled"
                                        draggable="true"
                                        @dragstart="dragStart($event, seat.student)"
                                        :aria-label="
                                            seat.student
                                                ? `${seat.student.first_name} ${seat.student.last_name}, ${seat.label}`
                                                : `${seat.label}, available chair`
                                        "
                                        :aria-pressed="!seat.student && selectedSeatId === seat.id"
                                        class="group relative min-h-[6.75rem] w-full flex-1 rounded-2xl border p-3 transition-all duration-150 focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:min-h-[7.25rem]"
                                        :class="[
                                            seat.is_disabled
                                                ? 'cursor-not-allowed border-transparent bg-[repeating-linear-gradient(135deg,hsl(var(--border)),hsl(var(--border))_4px,transparent_4px,transparent_8px)] opacity-30'
                                                : selectedSeatId === seat.id
                                                  ? 'scale-[1.03] border-emerald-400 bg-[#164e3f] text-white shadow-lg ring-2 ring-emerald-400 ring-offset-2 dark:bg-[#134e48]'
                                                  : 'shadow-xs border-[#1b5d4e]/80 bg-[#164e3f] text-white hover:-translate-y-0.5 hover:shadow-md hover:brightness-105 dark:bg-[#134e48]',
                                            activeDragOverSeatId === seat.id && !seat.is_disabled
                                                ? 'scale-[1.02] border-dashed border-white bg-[#1a5a49] shadow-md ring-2 ring-white/40'
                                                : '',
                                        ]"
                                        @click.stop="chooseSeat(seat)"
                                    >
                                        <div class="flex h-full flex-col items-center justify-center text-center">
                                            <!-- Enlarged Photo / Avatar -->
                                            <div
                                                class="shadow-xs flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white/20 ring-2 ring-white/25 sm:size-11"
                                            >
                                                <img
                                                    v-if="seat.student.photo_url"
                                                    :src="seat.student.photo_url"
                                                    :alt="`${seat.student.first_name} ${seat.student.last_name}`"
                                                    class="size-full object-cover"
                                                />
                                                <span v-else class="text-xs font-black uppercase tracking-wider text-white sm:text-sm">
                                                    {{ initials(seat.student.first_name + ' ' + seat.student.last_name) }}
                                                </span>
                                            </div>

                                            <!-- Complete Name -->
                                            <span
                                                class="mt-2 block w-full max-w-[7.5rem] truncate text-center text-[11px] font-bold uppercase leading-tight tracking-tight text-white sm:text-xs"
                                                :title="`${seat.student.first_name} ${seat.student.last_name}`"
                                            >
                                                {{ seat.student.first_name }} {{ seat.student.last_name }}
                                            </span>

                                            <!-- Seat Label -->
                                            <span
                                                class="mt-0.5 font-mono text-[9px] font-medium uppercase leading-none tracking-wider text-white/70 sm:text-[10px]"
                                            >
                                                {{ seat.label }}
                                            </span>
                                        </div>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent side="top" :side-offset="10" class="z-[100] flex flex-col items-center gap-2 p-3 shadow-lg">
                                    <img
                                        v-if="seat.student.photo_url"
                                        :src="seat.student.photo_url"
                                        alt=""
                                        class="size-16 rounded-full object-cover shadow-sm"
                                    />
                                    <div
                                        v-else
                                        class="flex size-16 items-center justify-center rounded-full bg-primary/20 text-xl font-bold text-primary shadow-sm"
                                    >
                                        {{ initials(seat.student.first_name + ' ' + seat.student.last_name) }}
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm font-bold leading-tight">{{ seat.student.first_name }} {{ seat.student.last_name }}</p>
                                        <p class="mt-0.5 text-[10px] uppercase text-muted-foreground">{{ sectionName || 'Student' }}</p>
                                        <p class="mt-1 text-[10px] font-semibold text-primary">Click to view details or unseat</p>
                                    </div>
                                </TooltipContent>
                            </Tooltip>

                            <button
                                v-else
                                type="button"
                                :disabled="seat.is_disabled"
                                :aria-label="`${seat.label}, available chair`"
                                :aria-pressed="selectedSeatId === seat.id"
                                class="group relative min-h-[6.75rem] w-full flex-1 rounded-2xl border-2 p-3 transition-all duration-150 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 sm:min-h-[7.25rem]"
                                :class="[
                                    seat.is_disabled
                                        ? 'cursor-not-allowed border-transparent bg-[repeating-linear-gradient(135deg,hsl(var(--border)),hsl(var(--border))_4px,transparent_4px,transparent_8px)] opacity-30'
                                        : selectedSeatId === seat.id
                                          ? 'scale-[1.03] border-primary bg-primary/10 text-foreground shadow ring-2 ring-primary/30'
                                          : 'border-slate-200/90 bg-card text-muted-foreground hover:border-primary/50 hover:bg-secondary/40 hover:text-foreground dark:border-border/80',
                                    activeDragOverSeatId === seat.id && !seat.is_disabled
                                        ? 'scale-[1.02] border-dashed border-primary bg-primary/20 shadow-md ring-2 ring-primary/30'
                                        : '',
                                ]"
                                @click.stop="chooseSeat(seat)"
                            >
                                <div class="flex h-full flex-col items-center justify-center text-center">
                                    <Armchair
                                        class="size-6 text-slate-400 transition-transform group-hover:scale-110 dark:text-muted-foreground/60"
                                    />
                                    <span
                                        class="mt-2 block font-mono text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-muted-foreground sm:text-[11px]"
                                    >
                                        {{ seat.label }}
                                    </span>
                                    <span
                                        v-if="selectedSeatId === seat.id"
                                        class="mt-0.5 block text-[8px] font-black uppercase tracking-wider text-primary"
                                    >
                                        Selected
                                    </span>
                                </div>
                            </button>

                            <!-- Dropdown Menu for vacant seat -->
                            <div
                                v-if="activeSeatMenuId === seat.id && unseatedStudents && unseatedStudents.length"
                                class="absolute left-1/2 top-full z-50 mt-1 w-48 -translate-x-1/2 rounded-xl border border-border bg-popover p-1 text-popover-foreground shadow-md duration-150 animate-in fade-in slide-in-from-top-1"
                            >
                                <p
                                    class="mb-1 border-b border-border/60 px-2 py-1 text-[9px] font-bold uppercase tracking-wider text-muted-foreground"
                                >
                                    Assign Student
                                </p>
                                <div class="max-h-36 overflow-y-auto">
                                    <button
                                        v-for="student in unseatedStudents"
                                        :key="student.id"
                                        type="button"
                                        class="w-full truncate rounded-lg px-2 py-1 text-left text-xs font-semibold hover:bg-accent hover:text-accent-foreground"
                                        @click.stop="assignStudent(student, seat.id)"
                                    >
                                        {{ student.full_name }}
                                    </button>
                                </div>
                                <div class="mt-1 border-t border-border/60 pt-1">
                                    <button
                                        type="button"
                                        class="w-full rounded-lg px-2 py-1 text-left text-xs font-semibold text-primary hover:bg-accent"
                                        @click.stop="selectForEnroll(seat.id)"
                                    >
                                        Select for Quick Enroll
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Interactive Column Aisle Divider Button -->
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    v-if="seat.column_number < block.internal_columns"
                                    type="button"
                                    class="group/aisle relative flex shrink-0 items-center justify-center rounded-lg border-2 border-dashed transition-all"
                                    :class="
                                        hasColumnAisle(seat.column_number)
                                            ? 'mx-2 w-8 border-primary/40 bg-primary/5 text-primary hover:border-rose-500 hover:bg-rose-500/15 hover:text-rose-600 dark:hover:text-rose-400'
                                            : 'w-2.5 border-transparent hover:w-6 hover:border-primary/50 hover:bg-secondary/60 hover:text-primary'
                                    "
                                    :aria-label="`${hasColumnAisle(seat.column_number) ? 'Remove' : 'Add'} vertical aisle after column ${seat.column_number}`"
                                    @click.stop="toggleAisle('column', seat.column_number)"
                                >
                                    <template v-if="hasColumnAisle(seat.column_number)">
                                        <GripVertical class="size-3.5 opacity-60 group-hover/aisle:hidden" />
                                        <X class="hidden size-3.5 font-bold text-rose-600 group-hover/aisle:block dark:text-rose-400" />
                                    </template>
                                    <span v-else class="text-[9px] font-bold text-primary opacity-0 group-hover/aisle:opacity-100">+</span>
                                </button>
                            </TooltipTrigger>
                            <TooltipContent side="top" class="text-xs font-medium">
                                {{
                                    hasColumnAisle(seat.column_number)
                                        ? `Click to remove Col ${seat.column_number} aisle`
                                        : `Click to add vertical aisle after Col ${seat.column_number}`
                                }}
                            </TooltipContent>
                        </Tooltip>
                    </template>
                </div>

                <!-- Interactive Row Aisle Divider Button -->
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            v-if="rowIndex + 1 < block.internal_rows"
                            type="button"
                            class="group/aisle relative flex w-full items-center justify-center rounded-lg border-2 border-dashed transition-all"
                            :class="
                                hasRowAisle(rowIndex + 1)
                                    ? 'my-2 h-7 border-primary/40 bg-primary/5 text-[10px] font-semibold uppercase tracking-wider text-primary hover:border-rose-500 hover:bg-rose-500/15 hover:text-rose-600 dark:hover:text-rose-400'
                                    : 'h-2.5 border-transparent hover:h-6 hover:border-primary/50 hover:bg-secondary/60 hover:text-primary'
                            "
                            :aria-label="`${hasRowAisle(rowIndex + 1) ? 'Remove' : 'Add'} horizontal aisle after row ${rowIndex + 1}`"
                            @click.stop="toggleAisle('row', rowIndex + 1)"
                        >
                            <span v-if="hasRowAisle(rowIndex + 1)" class="flex items-center gap-2">
                                <GripHorizontal class="size-3.5 opacity-60 group-hover/aisle:hidden" />
                                <span class="group-hover/aisle:hidden">Aisle</span>
                                <span class="hidden items-center gap-1.5 font-bold text-rose-600 group-hover/aisle:flex dark:text-rose-400">
                                    <X class="size-3.5" /> Remove Aisle
                                </span>
                            </span>
                            <span v-else class="flex items-center gap-1 text-[9px] font-bold text-primary opacity-0 group-hover/aisle:opacity-100">
                                + Add Aisle
                            </span>
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="right" class="text-xs font-medium">
                        {{
                            hasRowAisle(rowIndex + 1)
                                ? `Click to remove Row ${rowIndex + 1} aisle`
                                : `Click to add horizontal aisle after Row ${rowIndex + 1}`
                        }}
                    </TooltipContent>
                </Tooltip>
            </template>
        </TooltipProvider>
    </article>
</template>
