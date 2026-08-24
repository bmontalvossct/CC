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

const colCount = computed(() => props.block.internal_columns || (rows.value[0]?.length ?? 1));

const density = computed<'spacious' | 'compact' | 'condensed' | 'micro'>(() => {
    if (colCount.value <= 5) return 'spacious';
    if (colCount.value <= 8) return 'compact';
    if (colCount.value <= 12) return 'condensed';
    return 'micro';
});
</script>

<template>
    <article
        class="rounded-2xl border border-border/80 bg-card/90 shadow-sm transition-all"
        :class="density === 'spacious' ? 'p-5' : density === 'compact' ? 'p-3.5' : 'p-2 sm:p-3'"
    >
        <p v-if="block.label && block.label !== 'Classroom'" class="mb-3 text-xs font-bold uppercase tracking-[0.14em] text-muted-foreground">
            {{ block.label }}
        </p>

        <TooltipProvider :delay-duration="150">
            <template v-for="(seats, rowIndex) in rows" :key="rowIndex">
                <div
                    class="flex items-stretch first:mt-0 last:mb-0"
                    :class="
                        density === 'spacious'
                            ? 'my-3 gap-3'
                            : density === 'compact'
                              ? 'my-2 gap-2'
                              : density === 'condensed'
                                ? 'my-1.5 gap-1.5'
                                : 'my-1 gap-1'
                    "
                >
                    <template v-for="seat in seats" :key="seat.id">
                        <div
                            class="relative flex min-w-0 flex-1 flex-col items-stretch"
                            :class="
                                density === 'spacious'
                                    ? 'min-w-[5.5rem] sm:min-w-[6.5rem]'
                                    : density === 'compact'
                                      ? 'min-w-[3.75rem] sm:min-w-[4.5rem]'
                                      : density === 'condensed'
                                        ? 'min-w-[2.75rem] sm:min-w-[3.25rem]'
                                        : 'min-w-[2rem] sm:min-w-[2.5rem]'
                            "
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
                                                ? `${seat.student.last_name}, ${seat.student.first_name}, ${seat.label}`
                                                : `${seat.label}, available chair`
                                        "
                                        :aria-pressed="!seat.student && selectedSeatId === seat.id"
                                        class="group relative w-full flex-1 border transition-all duration-150 focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
                                        :class="[
                                            density === 'spacious'
                                                ? 'min-h-[6.5rem] rounded-2xl p-2.5 sm:min-h-[7.25rem] sm:p-3'
                                                : density === 'compact'
                                                  ? 'min-h-[4.75rem] rounded-xl p-1.5 sm:min-h-[5.5rem] sm:p-2'
                                                  : density === 'condensed'
                                                    ? 'min-h-[3.75rem] rounded-lg p-1 sm:min-h-[4.25rem]'
                                                    : 'min-h-[3rem] rounded-md p-0.5 sm:min-h-[3.5rem]',
                                            seat.is_disabled
                                                ? 'cursor-not-allowed border-transparent bg-[repeating-linear-gradient(135deg,hsl(var(--border)),hsl(var(--border))_4px,transparent_4px,transparent_8px)] opacity-30'
                                                : (seat.student.absent_count ?? 0) >= 3
                                                  ? selectedSeatId === seat.id
                                                    ? 'scale-[1.03] border-rose-400 bg-[#881337] text-white shadow-lg ring-2 ring-rose-400 ring-offset-2 dark:bg-[#7f1d1d]'
                                                    : 'shadow-xs border-rose-600/90 bg-[#881337] text-white hover:-translate-y-0.5 hover:shadow-md hover:brightness-110 dark:bg-[#4c0519]'
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
                                            <!-- Scaled Photo / Avatar -->
                                            <div
                                                class="shadow-xs flex shrink-0 items-center justify-center overflow-hidden rounded-full ring-1 sm:ring-2"
                                                :class="[
                                                    (seat.student.absent_count ?? 0) >= 3 ? 'bg-rose-500/30 ring-rose-400/80' : 'bg-white/20 ring-white/25',
                                                    density === 'spacious'
                                                        ? 'size-10 sm:size-11'
                                                        : density === 'compact'
                                                          ? 'size-7 sm:size-8'
                                                          : density === 'condensed'
                                                            ? 'size-5 sm:size-6'
                                                            : 'size-4 sm:size-5',
                                                ]"
                                            >
                                                <img
                                                    v-if="seat.student.photo_url"
                                                    :src="seat.student.photo_url"
                                                    :alt="`${seat.student.last_name}, ${seat.student.first_name}`"
                                                    class="size-full object-cover"
                                                />
                                                <span
                                                    v-else
                                                    class="uppercase tracking-wider text-white"
                                                    :class="
                                                        density === 'spacious'
                                                            ? 'text-xs font-black sm:text-sm'
                                                            : density === 'compact'
                                                              ? 'text-[10px] font-bold sm:text-xs'
                                                              : density === 'condensed'
                                                                ? 'text-[8px] font-bold sm:text-[9px]'
                                                                : 'text-[7px] font-bold'
                                                    "
                                                >
                                                    {{ initials(seat.student.last_name + ' ' + seat.student.first_name) }}
                                                </span>
                                            </div>

                                            <!-- Complete Name (Full Last Name First) -->
                                            <span
                                                class="block w-full truncate text-center font-bold uppercase leading-tight tracking-tight text-white"
                                                :class="
                                                    density === 'spacious'
                                                        ? 'mt-2 max-w-[7.5rem] text-[11px] sm:text-xs'
                                                        : density === 'compact'
                                                          ? 'mt-1 max-w-[5rem] text-[9.5px] sm:text-[10.5px]'
                                                          : density === 'condensed'
                                                            ? 'mt-0.5 max-w-[3.75rem] text-[8px] sm:text-[8.5px]'
                                                            : 'mt-0.25 max-w-[2.75rem] text-[7px]'
                                                "
                                                :title="`${seat.student.last_name}, ${seat.student.first_name}`"
                                            >
                                                {{ seat.student.last_name }}, {{ seat.student.first_name }}
                                            </span>

                                            <!-- Seat Label & 3+ Absences Indicator -->
                                            <div
                                                class="flex items-center justify-center gap-1 font-mono font-medium uppercase leading-none tracking-wider text-white/70"
                                                :class="
                                                    density === 'spacious'
                                                        ? 'mt-0.5 text-[9px] sm:text-[10px]'
                                                        : density === 'compact'
                                                          ? 'mt-0.5 text-[8px] sm:text-[8.5px]'
                                                          : density === 'condensed'
                                                            ? 'mt-0 text-[7px] sm:text-[7.5px]'
                                                            : 'mt-0 text-[6.5px]'
                                                "
                                            >
                                                <span>{{ seat.label }}</span>
                                                <span
                                                    v-if="(seat.student.absent_count ?? 0) >= 3"
                                                    class="rounded bg-rose-500/40 px-1 py-0.2 text-[6.5px] font-bold text-rose-200 ring-1 ring-rose-400/50 sm:text-[7px]"
                                                >
                                                    3+ ABS
                                                </span>
                                            </div>
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
                                        {{ initials(seat.student.last_name + ' ' + seat.student.first_name) }}
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm font-bold leading-tight">{{ seat.student.last_name }}, {{ seat.student.first_name }}</p>
                                        <p class="mt-0.5 text-[10px] uppercase text-muted-foreground">{{ sectionName || 'Student' }}</p>
                                        <div
                                            v-if="(seat.student.absent_count ?? 0) >= 3"
                                            class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-rose-500/15 px-2.5 py-0.5 text-[11px] font-bold text-rose-600 dark:text-rose-400 ring-1 ring-rose-500/30"
                                        >
                                            <span>⚠️ {{ seat.student.absent_count }}/3 Absences (Limit Reached)</span>
                                        </div>
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
                                class="group relative w-full flex-1 border-2 transition-all duration-150 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                :class="[
                                    density === 'spacious'
                                        ? 'min-h-[6.5rem] rounded-2xl p-2.5 sm:min-h-[7.25rem] sm:p-3'
                                        : density === 'compact'
                                          ? 'min-h-[4.75rem] rounded-xl p-1.5 sm:min-h-[5.5rem] sm:p-2'
                                          : density === 'condensed'
                                            ? 'min-h-[3.75rem] rounded-lg p-1 sm:min-h-[4.25rem]'
                                            : 'min-h-[3rem] rounded-md p-0.5 sm:min-h-[3.5rem]',
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
                                        class="text-slate-400 transition-transform group-hover:scale-110 dark:text-muted-foreground/60"
                                        :class="
                                            density === 'spacious'
                                                ? 'size-6'
                                                : density === 'compact'
                                                  ? 'size-4.5 sm:size-5'
                                                  : density === 'condensed'
                                                    ? 'size-3.5 sm:size-4'
                                                    : 'size-3'
                                        "
                                    />
                                    <span
                                        class="block font-mono font-semibold uppercase tracking-wider text-slate-500 dark:text-muted-foreground"
                                        :class="
                                            density === 'spacious'
                                                ? 'mt-2 text-[10px] sm:text-[11px]'
                                                : density === 'compact'
                                                  ? 'mt-1 text-[9px] sm:text-[9.5px]'
                                                  : density === 'condensed'
                                                    ? 'mt-0.5 text-[7.5px] sm:text-[8px]'
                                                    : 'mt-0.25 text-[6.5px]'
                                        "
                                    >
                                        {{ seat.label }}
                                    </span>
                                    <span
                                        v-if="selectedSeatId === seat.id"
                                        class="block font-black uppercase tracking-wider text-primary"
                                        :class="
                                            density === 'spacious'
                                                ? 'mt-0.5 text-[8px]'
                                                : density === 'compact'
                                                  ? 'mt-0.5 text-[7.5px]'
                                                  : density === 'condensed'
                                                    ? 'text-[7px]'
                                                    : 'text-[6px]'
                                        "
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
                                            ? density === 'spacious'
                                                ? 'mx-2 w-7 border-primary/40 bg-primary/5 text-primary hover:border-rose-500 hover:bg-rose-500/15 hover:text-rose-600 dark:hover:text-rose-400'
                                                : density === 'compact'
                                                  ? 'mx-1.5 w-5 border-primary/40 bg-primary/5 text-primary hover:border-rose-500 hover:bg-rose-500/15 hover:text-rose-600 dark:hover:text-rose-400'
                                                  : 'mx-1 w-4 border-primary/40 bg-primary/5 text-primary hover:border-rose-500 hover:bg-rose-500/15 hover:text-rose-600 dark:hover:text-rose-400'
                                            : density === 'spacious'
                                              ? 'w-2 border-transparent hover:w-5 hover:border-primary/50 hover:bg-secondary/60 hover:text-primary'
                                              : 'w-1.5 border-transparent hover:w-3.5 hover:border-primary/50 hover:bg-secondary/60 hover:text-primary'
                                    "
                                    :aria-label="`${hasColumnAisle(seat.column_number) ? 'Remove' : 'Add'} vertical aisle after column ${seat.column_number}`"
                                    @click.stop="toggleAisle('column', seat.column_number)"
                                >
                                    <template v-if="hasColumnAisle(seat.column_number)">
                                        <GripVertical class="size-3 opacity-60 group-hover/aisle:hidden" />
                                        <X class="hidden size-3 font-bold text-rose-600 group-hover/aisle:block dark:text-rose-400" />
                                    </template>
                                    <span v-else class="text-[8px] font-bold text-primary opacity-0 group-hover/aisle:opacity-100">+</span>
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
                                    ? density === 'spacious'
                                        ? 'my-2 h-7 border-primary/40 bg-primary/5 text-[10px] font-semibold uppercase tracking-wider text-primary hover:border-rose-500 hover:bg-rose-500/15 hover:text-rose-600 dark:hover:text-rose-400'
                                        : density === 'compact'
                                          ? 'my-1.5 h-5.5 border-primary/40 bg-primary/5 text-[9px] font-semibold uppercase tracking-wider text-primary hover:border-rose-500 hover:bg-rose-500/15 hover:text-rose-600 dark:hover:text-rose-400'
                                          : 'my-1 h-4.5 border-primary/40 bg-primary/5 text-[8px] font-semibold uppercase tracking-wider text-primary hover:border-rose-500 hover:bg-rose-500/15 hover:text-rose-600 dark:hover:text-rose-400'
                                    : density === 'spacious'
                                      ? 'h-2 border-transparent hover:h-5 hover:border-primary/50 hover:bg-secondary/60 hover:text-primary'
                                      : 'h-1.5 border-transparent hover:h-4 hover:border-primary/50 hover:bg-secondary/60 hover:text-primary'
                            "
                            :aria-label="`${hasRowAisle(rowIndex + 1) ? 'Remove' : 'Add'} horizontal aisle after row ${rowIndex + 1}`"
                            @click.stop="toggleAisle('row', rowIndex + 1)"
                        >
                            <span v-if="hasRowAisle(rowIndex + 1)" class="flex items-center gap-1.5">
                                <GripHorizontal class="size-3 opacity-60 group-hover/aisle:hidden" />
                                <span class="group-hover/aisle:hidden">Aisle</span>
                                <span class="hidden items-center gap-1 font-bold text-rose-600 group-hover/aisle:flex dark:text-rose-400">
                                    <X class="size-3" /> Remove Aisle
                                </span>
                            </span>
                            <span v-else class="flex items-center gap-1 text-[8px] font-bold text-primary opacity-0 group-hover/aisle:opacity-100">
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
