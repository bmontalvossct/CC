<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Armchair, User } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    block: any;
    selectedSeatId?: number | null;
    sectionName?: string;
}>();

const emit = defineEmits<{
    selectStudent: [student: any];
    selectSeat: [seatId: number];
}>();

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
    if (seat.student) emit('selectStudent', seat.student);
    else emit('selectSeat', seat.id);
};

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
                        <Tooltip v-if="seat.student">
                            <TooltipTrigger asChild>
                                <button
                        type="button"
                        :disabled="seat.is_disabled"
                        :aria-label="seat.student ? `${seat.student.full_name}, ${seat.label}` : `${seat.label}, available chair`"
                        :aria-pressed="!seat.student && selectedSeatId === seat.id"
                        class="group relative min-h-[4.75rem] min-w-[3.75rem] flex-1 rounded-xl border-2 p-2 transition-all duration-150 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        :class="
                            seat.is_disabled
                                ? 'border-transparent bg-[repeating-linear-gradient(135deg,hsl(var(--border)),hsl(var(--border))_4px,transparent_4px,transparent_8px)] opacity-30 cursor-not-allowed'
                                : seat.student
                                  ? 'border-primary/80 bg-primary text-primary-foreground shadow-sm hover:-translate-y-0.5 hover:shadow-md'
                                  : selectedSeatId === seat.id
                                    ? 'scale-[1.03] border-primary bg-primary/15 text-foreground ring-2 ring-primary/30 shadow'
                                    : 'border-border/90 bg-card text-muted-foreground hover:border-primary/60 hover:text-foreground hover:bg-secondary/60'
                        "
                        @click="chooseSeat(seat)"
                    >
                        <div v-if="seat.student" class="flex flex-col items-center justify-center h-full text-center">
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
                            class="group relative min-h-[4.75rem] min-w-[3.75rem] flex-1 rounded-xl border-2 p-2 transition-all duration-150 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                            :class="
                                seat.is_disabled
                                    ? 'border-transparent bg-[repeating-linear-gradient(135deg,hsl(var(--border)),hsl(var(--border))_4px,transparent_4px,transparent_8px)] opacity-30 cursor-not-allowed'
                                    : selectedSeatId === seat.id
                                        ? 'scale-[1.03] border-primary bg-primary/15 text-foreground ring-2 ring-primary/30 shadow'
                                        : 'border-border/90 bg-card text-muted-foreground hover:border-primary/60 hover:text-foreground hover:bg-secondary/60'
                            "
                            @click="chooseSeat(seat)"
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

                    <!-- Column aisle divider -->
                    <div
                        v-if="seat.column_number < block.internal_columns"
                        class="shrink-0 transition-all"
                        :class="
                            hasColumnAisle(seat.column_number)
                                ? 'mx-2.5 w-7 rounded-lg border-x-2 border-dashed border-primary/40 bg-primary/5 flex items-center justify-center'
                                : 'w-2'
                        "
                        :aria-label="hasColumnAisle(seat.column_number) ? 'Vertical aisle' : undefined"
                    />
                </template>
            </div>

            <!-- Row aisle divider -->
            <div
                v-if="rowIndex + 1 < block.internal_rows"
                :class="
                    hasRowAisle(rowIndex + 1)
                        ? 'my-2.5 grid h-7 place-items-center rounded-lg border-y-2 border-dashed border-primary/40 bg-primary/5 text-[9px] font-extrabold uppercase tracking-widest text-primary'
                        : 'h-2'
                "
            >
                <span v-if="hasRowAisle(rowIndex + 1)">Aisle</span>
            </div>
            </template>
        </TooltipProvider>
    </article>
</template>
