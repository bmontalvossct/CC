<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, Clock3, LoaderCircle, UserRound, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type RecordData = { id: number; status: 'present' | 'absent'; attended_minutes: number };
type Student = { id: number; student_number: string; name: string; photo_url: string | null };
type Seat = {
    id: number;
    label: string;
    row_number: number;
    column_number: number;
    is_disabled: boolean;
    block: { id: number; label: string; row: number; column: number };
    student: Student | null;
    record: RecordData | null;
};
type Unseated = { student: Student; record: RecordData };
const props = defineProps<{
    section: { id: number; subject_code: string; subject_title: string; name: string };
    session: {
        id: number;
        session_date: string;
        starts_at: string;
        ends_at: string;
        duration_minutes: number;
        notes: string | null;
        present_count: number;
        total_count: number;
    };
    seats: Seat[];
    unseated: Unseated[];
}>();
const localSeats = ref(props.seats);
const localUnseated = ref(props.unseated);
const saveState = ref<Record<number, 'saving' | 'saved' | 'error'>>({});
const presentCount = computed(
    () => [...localSeats.value.map((x) => x.record), ...localUnseated.value.map((x) => x.record)].filter((x) => x?.status === 'present').length,
);
const blocks = computed(() => {
    const grouped = new Map<number, { id: number; label: string; row: number; column: number; seats: Seat[] }>();
    for (const seat of localSeats.value) {
        if (!grouped.has(seat.block.id)) grouped.set(seat.block.id, { ...seat.block, seats: [] });
        grouped.get(seat.block.id)!.seats.push(seat);
    }
    return [...grouped.values()].sort((a, b) => a.row - b.row || a.column - b.column);
});
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sections', href: '/sections' },
    { title: props.section.subject_code, href: `/sections/${props.section.id}` },
    { title: 'Attendance', href: `/sections/${props.section.id}/attendance` },
    { title: props.session.session_date, href: `/attendance/${props.session.id}` },
];
const initials = (name: string) =>
    name
        .split(/[ ,]+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
function xsrfToken() {
    const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : '';
}
async function toggle(record: RecordData | null) {
    if (!record || saveState.value[record.id] === 'saving') return;
    const oldStatus = record.status;
    record.status = oldStatus === 'present' ? 'absent' : 'present';
    saveState.value[record.id] = 'saving';
    try {
        const response = await fetch(`/attendance-records/${record.id}`, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            body: JSON.stringify({ status: record.status }),
        });
        if (!response.ok) throw new Error('Unable to save');
        const payload = await response.json();
        record.attended_minutes = payload.record.attended_minutes;
        saveState.value[record.id] = 'saved';
        window.setTimeout(() => {
            if (saveState.value[record.id] === 'saved') delete saveState.value[record.id];
        }, 1600);
    } catch {
        record.status = oldStatus;
        saveState.value[record.id] = 'error';
    }
}
</script>

<template>
    <Head :title="`${section.subject_code} · ${session.session_date}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
            <header class="flex flex-col justify-between gap-4 border-b pb-6 sm:flex-row sm:items-end">
                <div>
                    <Link
                        :href="`/sections/${section.id}/attendance`"
                        class="mb-3 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                        ><ArrowLeft class="size-4" /> Attendance register</Link
                    >
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-muted-foreground">Classroom check</p>
                    <h1 class="mt-1 text-3xl font-bold">{{ section.subject_code }} · {{ section.name }}</h1>
                    <p class="mt-1 text-muted-foreground">
                        {{ session.session_date }} · {{ session.starts_at }}–{{ session.ends_at }} · {{ session.duration_minutes }} minutes
                    </p>
                </div>
                <div class="rounded-xl border bg-card px-5 py-3 text-right shadow-sm">
                    <p class="text-2xl font-bold">{{ presentCount }}/{{ session.total_count }}</p>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Present now</p>
                </div>
            </header>
            <div class="flex flex-wrap gap-4 rounded-lg border bg-muted/30 px-4 py-3 text-sm">
                <span class="flex items-center gap-2"><span class="size-3 rounded-full bg-[#0071e3]" /> Present</span
                ><span class="flex items-center gap-2"><span class="size-3 rounded-full bg-[#d93025]" /> Absent</span
                ><span class="text-muted-foreground">Select an occupied chair to change attendance. Changes save automatically.</span>
            </div>
            <p v-if="session.notes" class="rounded-lg border-l-4 border-primary bg-primary/5 p-4 text-sm">
                <strong>Session note:</strong> {{ session.notes }}
            </p>

            <section class="overflow-x-auto rounded-2xl border bg-card p-5 shadow-sm" aria-label="Interactive classroom attendance map">
                <div
                    class="mx-auto mb-8 w-2/3 rounded-lg border-2 border-foreground/20 bg-muted px-4 py-2 text-center text-xs font-semibold uppercase tracking-[0.25em] text-muted-foreground"
                >
                    Front of classroom
                </div>
                <div
                    class="grid min-w-[680px] gap-6"
                    :style="{ gridTemplateColumns: `repeat(${Math.max(1, ...blocks.map((b) => b.column))}, minmax(0, 1fr))` }"
                >
                    <article
                        v-for="block in blocks"
                        :key="block.id"
                        class="rounded-xl border border-dashed p-4"
                        :style="{ gridColumn: block.column, gridRow: block.row }"
                    >
                        <h2 class="mb-3 text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ block.label }}</h2>
                        <div
                            class="grid gap-2"
                            :style="{ gridTemplateColumns: `repeat(${Math.max(1, ...block.seats.map((s) => s.column_number))}, minmax(5rem, 1fr))` }"
                        >
                            <button
                                v-for="seat in block.seats"
                                :key="seat.id"
                                type="button"
                                :disabled="seat.is_disabled || !seat.record"
                                :aria-label="seat.student ? `${seat.student.name}, ${seat.record?.status}` : `${seat.label}, empty`"
                                :aria-pressed="seat.record?.status === 'present'"
                                class="relative min-h-24 rounded-xl border-2 p-2 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-default"
                                :class="
                                    seat.is_disabled
                                        ? 'border-transparent bg-muted/20 opacity-30'
                                        : !seat.student
                                          ? 'border-dashed bg-muted/30 text-muted-foreground'
                                          : seat.record?.status === 'present'
                                            ? 'border-[#0071e3] bg-[#f5f9ff] text-[#0066cc] dark:bg-[#0066cc]/40 dark:text-[#f5f9ff]'
                                            : 'border-[#d93025] bg-[#fff5f4] text-[#d93025] dark:bg-[#d93025]/40 dark:text-[#fff5f4]'
                                "
                                @click="toggle(seat.record)"
                            >
                                <template v-if="seat.student"
                                    ><div class="flex items-start gap-2">
                                        <img
                                            v-if="seat.student.photo_url"
                                            :src="seat.student.photo_url"
                                            alt=""
                                            class="size-8 rounded-full object-cover"
                                        /><span
                                            v-else
                                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-background/80 text-[10px] font-bold"
                                            >{{ initials(seat.student.name) }}</span
                                        ><span class="min-w-0"
                                            ><span class="block truncate text-xs font-semibold">{{ seat.student.name }}</span
                                            ><span class="block truncate text-[10px] opacity-70">{{ seat.student.student_number }}</span></span
                                        >
                                    </div>
                                    <span class="mt-2 flex items-center gap-1 text-[10px] font-bold uppercase"
                                        ><LoaderCircle v-if="saveState[seat.record!.id] === 'saving'" class="size-3 animate-spin" /><X
                                            v-else-if="seat.record?.status === 'absent'"
                                            class="size-3"
                                        /><Check v-else class="size-3" />{{
                                            saveState[seat.record!.id] === 'saving'
                                                ? 'Saving'
                                                : saveState[seat.record!.id] === 'error'
                                                  ? 'Retry'
                                                  : seat.record?.status
                                        }}</span
                                    ></template
                                >
                                <span v-else class="flex h-full items-center justify-center text-xs">{{
                                    seat.is_disabled ? 'Unavailable' : seat.label
                                }}</span>
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <section v-if="unseated.length" class="rounded-xl border bg-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <UserRound class="size-5 text-muted-foreground" />
                    <div>
                        <h2 class="font-semibold">Unseated students</h2>
                        <p class="text-xs text-muted-foreground">They are still part of this attendance snapshot.</p>
                    </div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="item in localUnseated"
                        :key="item.student.id"
                        type="button"
                        class="flex items-center justify-between rounded-lg border-2 p-3 text-left focus-visible:ring-2 focus-visible:ring-ring"
                        :class="
                            item.record.status === 'present'
                                ? 'border-[#0071e3] bg-[#f5f9ff] dark:bg-[#0066cc]/40'
                                : 'border-[#d93025] bg-[#fff5f4] dark:bg-[#d93025]/40'
                        "
                        @click="toggle(item.record)"
                    >
                        <span
                            ><span class="block text-sm font-semibold">{{ item.student.name }}</span
                            ><span class="block text-xs opacity-70">{{ item.student.student_number }}</span></span
                        ><LoaderCircle v-if="saveState[item.record.id] === 'saving'" class="size-4 animate-spin" /><Check
                            v-else-if="item.record.status === 'present'"
                            class="size-4"
                        /><X v-else class="size-4" />
                    </button>
                </div>
            </section>
            <footer class="flex items-center gap-2 text-xs text-muted-foreground">
                <Clock3 class="size-4" /> Present students receive {{ session.duration_minutes }} attended minutes; absent students receive zero.
            </footer>
        </main>
    </AppLayout>
</template>
