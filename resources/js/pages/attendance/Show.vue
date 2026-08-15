<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    CheckCircle2,
    Clock3,
    LoaderCircle,
    RotateCcw,
    UserRound,
    Users,
    X,
    XCircle,
} from 'lucide-vue-next';
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
    <Head :title="`${section.subject_code} · ${session.session_date} - Attendance`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="page-enter mx-auto flex w-full max-w-[1360px] flex-1 flex-col gap-6 px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <!-- Header Banner -->
            <header class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 sm:p-8 shadow-sm">
                <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                    <div>
                        <Link
                            :href="`/sections/${section.id}/attendance`"
                            prefetch="hover"
                            class="mb-3 inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground hover:text-primary transition-colors"
                        >
                            <ArrowLeft class="size-3.5" /> Back to attendance register
                        </Link>
                        <div class="flex items-center gap-2">
                            <span class="badge-primary font-mono font-bold">{{ section.subject_code }}</span>
                            <span class="badge-muted">{{ session.session_date }}</span>
                        </div>
                        <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ section.name }} · Live Roll Call</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ session.starts_at }} – {{ session.ends_at }} · {{ session.duration_minutes }} minutes
                        </p>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="rounded-2xl border border-border/80 bg-card/90 px-6 py-3.5 text-right shadow-sm">
                            <p class="text-3xl font-extrabold tracking-tight text-primary">
                                {{ presentCount }}<span class="text-sm font-semibold text-muted-foreground">/{{ session.total_count }}</span>
                            </p>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Present now</p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Instructions & Status Legend -->
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border/80 bg-secondary/40 px-5 py-3 text-xs font-medium">
                <div class="flex items-center gap-5">
                    <span class="inline-flex items-center gap-2 font-bold text-primary">
                        <span class="size-3 rounded-full bg-primary ring-4 ring-primary/20" /> Present
                    </span>
                    <span class="inline-flex items-center gap-2 font-bold text-rose-600 dark:text-rose-400">
                        <span class="size-3 rounded-full bg-rose-500 ring-4 ring-rose-500/20" /> Absent
                    </span>
                </div>
                <span class="text-muted-foreground">
                    Tap any occupied seat to toggle present/absent. Changes save immediately.
                </span>
            </div>

            <div v-if="session.notes" class="rounded-xl border-l-4 border-primary bg-primary/5 p-4 text-xs">
                <strong>Session note:</strong> {{ session.notes }}
            </div>

            <!-- Interactive Live Classroom Floor -->
            <section class="paper-card p-6 md:p-8 shadow-sm overflow-hidden" aria-label="Interactive classroom attendance map">
                <div class="mx-auto mb-8 w-2/3 max-w-md rounded-xl bg-gradient-to-r from-zinc-900 via-zinc-800 to-zinc-900 py-2.5 text-center text-[10px] font-extrabold uppercase tracking-[0.25em] text-white shadow-xs">
                    Front of classroom / teaching board
                </div>

                <div
                    class="w-full max-w-full overflow-x-auto overscroll-x-contain pb-4 [-webkit-overflow-scrolling:touch] [scrollbar-gutter:stable]"
                >
                    <div
                        class="grid min-w-[680px] gap-6"
                        :style="{ gridTemplateColumns: `repeat(${Math.max(1, ...blocks.map((b) => b.column))}, minmax(0, 1fr))` }"
                    >
                        <article
                            v-for="block in blocks"
                            :key="block.id"
                            class="rounded-2xl border border-dashed border-border/80 bg-secondary/30 p-4"
                            :style="{ gridColumn: block.column, gridRow: block.row }"
                        >
                            <h2 class="mb-3 text-center text-xs font-extrabold uppercase tracking-wider text-muted-foreground">{{ block.label }}</h2>
                            <div
                                class="grid gap-2.5"
                                :style="{ gridTemplateColumns: `repeat(${Math.max(1, ...block.seats.map((s) => s.column_number))}, minmax(5.5rem, 1fr))` }"
                            >
                                <button
                                    v-for="seat in block.seats"
                                    :key="seat.id"
                                    type="button"
                                    :disabled="seat.is_disabled || !seat.record"
                                    :aria-label="seat.student ? `${seat.student.name}, ${seat.record?.status}` : `${seat.label}, empty`"
                                    :aria-pressed="seat.record?.status === 'present'"
                                    class="relative min-h-[5.5rem] rounded-xl border-2 p-2.5 text-left transition-all duration-150 focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-default"
                                    :class="
                                        seat.is_disabled
                                            ? 'border-transparent bg-muted/20 opacity-30 cursor-not-allowed'
                                            : !seat.student
                                              ? 'border-dashed border-border/70 bg-card/60 text-muted-foreground'
                                              : seat.record?.status === 'present'
                                                ? 'border-primary/80 bg-primary/10 text-primary shadow-xs hover:bg-primary/15'
                                                : 'border-rose-500/80 bg-rose-500/10 text-rose-600 dark:text-rose-400 shadow-xs hover:bg-rose-500/15'
                                    "
                                    @click="toggle(seat.record)"
                                >
                                    <template v-if="seat.student">
                                        <div class="flex items-start gap-2">
                                            <img
                                                v-if="seat.student.photo_url"
                                                :src="seat.student.photo_url"
                                                alt=""
                                                class="size-8 rounded-full object-cover border border-border"
                                            />
                                            <span
                                                v-else
                                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-card font-extrabold text-[10px] shadow-xs border border-border"
                                            >
                                                {{ initials(seat.student.name) }}
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <span class="block truncate text-xs font-bold leading-tight">{{ seat.student.name }}</span>
                                                <span class="block font-mono text-[9px] text-muted-foreground">{{ seat.student.student_number }}</span>
                                            </div>
                                        </div>

                                        <div class="mt-2.5 flex items-center justify-between border-t border-border/40 pt-1.5">
                                            <span class="font-mono text-[9px] font-semibold text-muted-foreground">{{ seat.label }}</span>
                                            <span class="inline-flex items-center gap-1 font-mono text-[10px] font-extrabold uppercase">
                                                <LoaderCircle v-if="saveState[seat.record!.id] === 'saving'" class="size-3 animate-spin text-primary" />
                                                <RotateCcw v-else-if="saveState[seat.record!.id] === 'error'" class="size-3 text-rose-600" />
                                                <X v-else-if="seat.record?.status === 'absent'" class="size-3 text-rose-600 dark:text-rose-400" />
                                                <Check v-else class="size-3 text-primary" />
                                                <span>{{ seat.record?.status }}</span>
                                            </span>
                                        </div>
                                    </template>

                                    <span v-else class="flex h-full items-center justify-center text-xs font-mono font-semibold text-muted-foreground/80">
                                        {{ seat.is_disabled ? 'Unavailable' : seat.label }}
                                    </span>
                                </button>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <!-- Unseated Students Section -->
            <section v-if="unseated.length" class="paper-card p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <UserRound class="size-4 text-amber-600 dark:text-amber-400" />
                    <div>
                        <h2 class="text-base font-bold">Unseated students ({{ unseated.length }})</h2>
                        <p class="text-xs text-muted-foreground">Students enrolled without an assigned chair.</p>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="item in localUnseated"
                        :key="item.student.id"
                        type="button"
                        class="flex items-center justify-between rounded-xl border-2 p-3 text-left transition-all hover:scale-[1.01]"
                        :class="
                            item.record.status === 'present'
                                ? 'border-primary/80 bg-primary/10 text-primary'
                                : 'border-rose-500/80 bg-rose-500/10 text-rose-600 dark:text-rose-400'
                        "
                        @click="toggle(item.record)"
                    >
                        <div>
                            <span class="block text-xs font-bold">{{ item.student.name }}</span>
                            <span class="block font-mono text-[10px] text-muted-foreground">{{ item.student.student_number }}</span>
                        </div>
                        <span class="font-mono text-[10px] font-extrabold uppercase flex items-center gap-1">
                            <LoaderCircle v-if="saveState[item.record.id] === 'saving'" class="size-3.5 animate-spin" />
                            <Check v-else-if="item.record.status === 'present'" class="size-3.5" />
                            <X v-else class="size-3.5" />
                            <span>{{ item.record.status }}</span>
                        </span>
                    </button>
                </div>
            </section>

            <footer class="flex items-center gap-2 text-xs text-muted-foreground font-medium">
                <Clock3 class="size-4 text-primary" />
                <span>Present students receive {{ session.duration_minutes }} minutes; absent students receive zero.</span>
            </footer>
        </main>
    </AppLayout>
</template>
