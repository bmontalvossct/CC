<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Armchair, User, GripVertical, GripHorizontal } from 'lucide-vue-next';
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
    const currentAisles = axis === 'row'
        ? [...(props.block.aisle_after_rows ?? [])]
        : [...(props.block.aisle_after_columns ?? [])];

    const updatedAisles = currentAisles.includes(position)
        ? currentAisles.filter((item: number) => item !== position)
        : [...currentAisles, position];

    emit('updateAisles', { axis, values: updatedAisles });
};

const activeSeatMenuId = ref<number | null>(null);
const activeDragOverSeatId = ref<number | null>(null);

const rows = computed(() =>
    Array.from({ length: props.block.internal_rows }, (_, index) =>
        props.block.seats
            .filter((seat: any) => seat.row_number === index + 1)
            .sort((a: any, b: any) => a.column_number - b.column_number),
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
    } catch (e) {
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

        <TooltipProvider :delay-duration="200">
            <template v-for="(seats, rowIndex) in rows" :key="rowIndex">
                <div class="flex items-stretch">
                    <template v-for="seat in seats" :key="seat.id">
                        <div 
                            class="relative flex-1 min-w-[3.75rem] flex flex-col items-stretch"
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
                                        :aria-label="seat.student ? `${seat.student.full_name}, ${seat.label}` : `${seat.label}, available chair`"
                                        :aria-pressed="!seat.student && selectedSeatId === seat.id"
                                        class="group relative min-h-[4.75rem] w-full flex-1 rounded-xl border-2 p-2 transition-all duration-150 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                        :class="[
                                            seat.is_disabled
                                                ? 'border-transparent bg-[repeating-linear-gradient(135deg,hsl(var(--border)),hsl(var(--border))_4px,transparent_4px,transparent_8px)] opacity-30 cursor-not-allowed'
                                                : selectedSeatId === seat.id
                                                  ? 'scale-[1.03] border-primary bg-primary/15 text-foreground ring-2 ring-primary/30 shadow'
                                                  : 'border-primary/80 bg-primary text-primary-foreground shadow-sm hover:-translate-y-0.5 hover:shadow-md',
                                            activeDragOverSeatId === seat.id && !seat.is_disabled
                                                ? 'border-dashed border-primary bg-primary/20 scale-[1.02] shadow-md ring-2 ring-primary/30'
                                                : ''
                                        ]"
                                        @click.stop="chooseSeat(seat)"
                                    >
                                        <div class="flex flex-col items-center justify-center h-full text-center">
                                            <span class="flex size-7 items-center justify-center rounded-full bg-white/20 text-[10px] font-extrabold text-white">
                                                {{ initials(seat.student.first_name + ' ' + seat.student.last_name) }}
                                            </span>
                                            <span class="mt-1 block max-w-[4.5rem] truncate text-[11px] font-bold leading-tight text-white">
                                                {{ seat.student.first_name }}
                                            </span>
                                            <span class="text-[9px] font-mono opacity-80 leading-none mt-0.5 text-white/90">
                                                {{ seat.label }}
                                            </span>
                                        </div>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent side="top" :side-offset="10" class="flex flex-col items-center gap-2 p-3 z-[100] shadow-lg">
                                    <img v-if="seat.student.photo_url" :src="seat.student.photo_url" alt="" class="size-14 rounded-full object-cover shadow-sm" />
                                    <div v-else class="flex size-14 items-center justify-center rounded-full bg-primary/20 text-lg font-bold text-primary shadow-sm">
                                        {{ initials(seat.student.first_name + ' ' + seat.student.last_name) }}
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm font-bold leading-tight">{{ seat.student.first_name }} {{ seat.student.last_name }}</p>
                                        <p class="text-[10px] uppercase text-muted-foreground mt-0.5">{{ sectionName || 'Student' }}</p>
                                    </div>
                                </TooltipContent>
                            </Tooltip>

                            <button
                                v-else
                                type="button"
                                :disabled="seat.is_disabled"
                                :aria-label="`${seat.label}, available chair`"
                                :aria-pressed="selectedSeatId === seat.id"
                                class="group relative min-h-[4.75rem] w-full flex-1 rounded-xl border-2 p-2 transition-all duration-150 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                :class="[
                                    seat.is_disabled
                                        ? 'border-transparent bg-[repeating-linear-gradient(135deg,hsl(var(--border)),hsl(var(--border))_4px,transparent_4px,transparent_8px)] opacity-30 cursor-not-allowed'
                                        : selectedSeatId === seat.id
                                            ? 'scale-[1.03] border-primary bg-primary/15 text-foreground ring-2 ring-primary/30 shadow'
                                            : 'border-border/90 bg-card text-muted-foreground hover:border-primary/60 hover:text-foreground hover:bg-secondary/60',
                                    activeDragOverSeatId === seat.id && !seat.is_disabled
                                        ? 'border-dashed border-primary bg-primary/20 scale-[1.02] shadow-md ring-2 ring-primary/30'
                                        : ''
                                ]"
                                @click.stop="chooseSeat(seat)"
                            >
                                <div class="flex flex-col items-center justify-center h-full text-center">
                                    <Armchair class="size-4.5 transition-transform group-hover:scale-110" />
                                    <span class="mt-1 block font-mono text-[10px] font-semibold text-foreground/80">
                                        {{ seat.label }}
                                    </span>
                                    <span v-if="selectedSeatId === seat.id" class="mt-0.5 block text-[8px] font-black uppercase text-primary tracking-wider">
                                        Selected
                                    </span>
                                </div>
                            </button>

                            <!-- Dropdown Menu for vacant seat -->
                            <div 
                                v-if="activeSeatMenuId === seat.id && unseatedStudents && unseatedStudents.length" 
                                class="absolute left-1/2 top-full z-50 mt-1 w-48 -translate-x-1/2 rounded-xl border border-border bg-popover p-1 shadow-md text-popover-foreground animate-in fade-in slide-in-from-top-1 duration-150"
                            >
                                <p class="px-2 py-1 text-[9px] font-bold uppercase tracking-wider text-muted-foreground border-b border-border/60 mb-1">Assign Student</p>
                                <div class="max-h-36 overflow-y-auto">
                                    <button
                                        v-for="student in unseatedStudents"
                                        :key="student.id"
                                        type="button"
                                        class="w-full rounded-lg px-2 py-1 text-left text-xs font-semibold hover:bg-accent hover:text-accent-foreground truncate"
                                        @click.stop="assignStudent(student, seat.id)"
                                    >
                                        {{ student.full_name }}
                                    </button>
                                </div>
                                <div class="border-t border-border/60 mt-1 pt-1">
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

                    <!-- Column aisle divider button -->
                    <button
                        v-if="seat.column_number < block.internal_columns"
                        type="button"
                        class="shrink-0 transition-all flex items-center justify-center border-2 border-dashed rounded-lg"
                        :class="
                            hasColumnAisle(seat.column_number)
                                ? 'mx-2 w-7 border-primary/40 bg-primary/5 text-primary hover:border-rose-500/50 hover:bg-rose-500/10'
                                : 'w-2.5 border-transparent hover:w-6 hover:border-primary/50 hover:bg-secondary/40'
                        "
                        :aria-label="`${hasColumnAisle(seat.column_number) ? 'Remove' : 'Add'} vertical aisle after column ${seat.column_number}`"
                        @click.stop="toggleAisle('column', seat.column_number)"
                    >
                        <GripVertical v-if="hasColumnAisle(seat.column_number)" class="size-3.5 opacity-60" />
                    </button>
                </template>
            </div>

            <!-- Row aisle divider button -->
            <button
                v-if="rowIndex + 1 < block.internal_rows"
                type="button"
                class="w-full transition-all flex items-center justify-center border-2 border-dashed rounded-lg"
                :class="
                    hasRowAisle(rowIndex + 1)
                        ? 'my-2 h-7 border-primary/40 bg-primary/5 text-primary hover:border-rose-500/50 hover:bg-rose-500/10 text-[9px] font-medium uppercase tracking-widest'
                        : 'h-2.5 border-transparent hover:h-6 hover:border-primary/50 hover:bg-secondary/40'
                "
                :aria-label="`${hasRowAisle(rowIndex + 1) ? 'Remove' : 'Add'} horizontal aisle after row ${rowIndex + 1}`"
                @click.stop="toggleAisle('row', rowIndex + 1)"
            >
                <span v-if="hasRowAisle(rowIndex + 1)" class="flex items-center gap-2">
                    <GripHorizontal class="size-3.5 opacity-60" />
                    <span>Aisle</span>
                </span>
            </button>
            </template>
        </TooltipProvider>
    </article>
</template>
