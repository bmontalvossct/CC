<script setup lang="ts">
import { Armchair } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    block: any;
    selectedSeatId?: number | null;
}>();
const emit = defineEmits<{
    selectStudent: [student: any];
    selectSeat: [seatId: number];
}>();

const rows = computed(() =>
    Array.from({ length: props.block.internal_rows }, (_, index) =>
        props.block.seats.filter((seat: any) => seat.row_number === index + 1).sort((a: any, b: any) => a.column_number - b.column_number),
    ),
);
const hasColumnAisle = (position: number) => (props.block.aisle_after_columns ?? []).includes(position);
const hasRowAisle = (position: number) => (props.block.aisle_after_rows ?? []).includes(position);
const chooseSeat = (seat: any) => {
    if (seat.student) emit('selectStudent', seat.student);
    else emit('selectSeat', seat.id);
};
</script>

<template>
    <article class="rounded-2xl border border-[#e5e7eb] bg-[#f5f5f7]/50 p-4">
        <p v-if="block.label && block.label !== 'Classroom'" class="mb-3 text-xs font-bold uppercase tracking-[0.12em] text-[#86868b]">
            {{ block.label }}
        </p>
        <template v-for="(seats, rowIndex) in rows" :key="rowIndex">
            <div class="flex items-stretch">
                <template v-for="seat in seats" :key="seat.id">
                    <button
                        type="button"
                        :disabled="seat.is_disabled"
                        :aria-label="seat.student ? `${seat.student.full_name}, ${seat.label}` : `${seat.label}, available chair`"
                        :aria-pressed="!seat.student && selectedSeatId === seat.id"
                        class="group min-h-16 min-w-14 flex-1 rounded-lg border p-2 transition"
                        :class="
                            seat.is_disabled
                                ? 'border-transparent bg-[repeating-linear-gradient(135deg,#e5e7eb,#e5e7eb_4px,transparent_4px,transparent_8px)] opacity-40'
                                : seat.student
                                  ? 'border-[#0071e3] bg-[#0071e3] text-white shadow-md hover:-translate-y-1'
                                  : selectedSeatId === seat.id
                                    ? 'scale-[1.03] border-[#0071e3] bg-[#bfdbfe] text-[#1d1d1f] shadow-lg ring-4 ring-[#dbeafe]'
                                    : 'border-[#e5e7eb] bg-white text-[#86868b] hover:border-[#0071e3] hover:text-[#0066cc]'
                        "
                        @click="chooseSeat(seat)"
                    >
                        <Armchair class="mx-auto size-5" />
                        <span class="mt-1 block truncate text-[10px] font-bold">{{ seat.student ? seat.student.first_name : seat.label }}</span>
                        <span v-if="!seat.student && selectedSeatId === seat.id" class="mt-1 block text-[9px] font-bold uppercase">Selected</span>
                    </button>
                    <div
                        v-if="seat.column_number < block.internal_columns"
                        class="shrink-0"
                        :class="
                            hasColumnAisle(seat.column_number)
                                ? 'mx-2 w-8 rounded-md border-x-2 border-dashed border-[#0071e3] bg-[#eaf4ff]/70'
                                : 'w-2'
                        "
                        :aria-label="hasColumnAisle(seat.column_number) ? 'Vertical aisle' : undefined"
                    />
                </template>
            </div>
            <div
                v-if="rowIndex + 1 < block.internal_rows"
                :class="
                    hasRowAisle(rowIndex + 1)
                        ? 'my-2 grid h-8 place-items-center rounded-md border-y-2 border-dashed border-[#0071e3] bg-[#eaf4ff]/70 text-[9px] font-bold uppercase tracking-wider text-[#0066cc]'
                        : 'h-2'
                "
            >
                <span v-if="hasRowAisle(rowIndex + 1)">Aisle</span>
            </div>
        </template>
    </article>
</template>
