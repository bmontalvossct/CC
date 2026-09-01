<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Check, FileCheck, LayoutGrid, LoaderCircle, RotateCcw, Search, Trash2, UserRound, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type RecordData = { id: number; status: 'present' | 'absent' | 'late'; attended_minutes: number };
type Student = {
    id: number;
    student_number: string;
    first_name?: string;
    last_name?: string;
    name: string;
    photo_url: string | null;
    absent_count?: number;
};
type Seat = {
    id: number;
    label: string;
    row_number: number;
    column_number: number;
    is_disabled: boolean;
    block: {
        id: number;
        label: string;
        row: number;
        column: number;
        internal_rows?: number;
        internal_columns?: number;
        aisle_after_rows?: number[];
        aisle_after_columns?: number[];
    };
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

const formatTime12h = (timeStr: string) => {
    if (!timeStr) return '';
    const [hStr, mStr] = timeStr.split(':');
    let hours = parseInt(hStr, 10);
    const minutes = mStr || '00';
    if (isNaN(hours)) return timeStr;
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    return `${hours}:${minutes} ${ampm}`;
};

const sortedUnseated = computed(() => {
    return [...localUnseated.value].sort((a, b) => {
        const nameA = formatStudentDisplayName(a.student).toLowerCase();
        const nameB = formatStudentDisplayName(b.student).toLowerCase();
        return nameA.localeCompare(nameB);
    });
});

// View Mode: Seating Map vs Paper Sign-in / List View
const viewMode = ref<'map' | 'list'>('map');

const allRecords = computed(() => [
    ...localSeats.value.map((x) => x.record).filter((r): r is RecordData => r !== null),
    ...localUnseated.value.map((x) => x.record),
]);

const presentCount = computed(() => allRecords.value.filter((x) => x.status === 'present').length);
const lateCount = computed(() => allRecords.value.filter((x) => x.status === 'late').length);
const absentCount = computed(() => allRecords.value.filter((x) => x.status === 'absent').length);

const blocks = computed(() => {
    const grouped = new Map<number, { id: number; label: string; row: number; column: number; seats: Seat[] }>();
    for (const seat of localSeats.value) {
        if (!grouped.has(seat.block.id)) grouped.set(seat.block.id, { ...seat.block, seats: [] });
        grouped.get(seat.block.id)!.seats.push(seat);
    }
    return [...grouped.values()].sort((a, b) => a.row - b.row || a.column - b.column);
});

// Flat student list for Paper Sign-in Sheet Roster
const rosterStudents = computed(() => {
    const list: Array<{
        student: Student;
        record: RecordData;
        seatLabel: string;
    }> = [];

    for (const seat of localSeats.value) {
        if (seat.student && seat.record) {
            list.push({
                student: seat.student,
                record: seat.record,
                seatLabel: `${seat.block.label} · Seat ${seat.label}`,
            });
        }
    }

    for (const un of localUnseated.value) {
        list.push({
            student: un.student,
            record: un.record,
            seatLabel: 'Unseated',
        });
    }

    return list.sort((a, b) => {
        if (a.student.last_name && b.student.last_name) {
            const cmp = a.student.last_name.localeCompare(b.student.last_name);
            if (cmp !== 0) return cmp;
            return (a.student.first_name || '').localeCompare(b.student.first_name || '');
        }
        return a.student.name.localeCompare(b.student.name);
    });
});

// List View Search and Filter
const searchQuery = ref('');
const filterStatus = ref<'all' | 'present' | 'late' | 'absent'>('all');

const filteredRoster = computed(() => {
    let list = rosterStudents.value;

    if (filterStatus.value !== 'all') {
        list = list.filter((item) => item.record.status === filterStatus.value);
    }

    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return list;

    return list.filter((item) => item.student.name.toLowerCase().includes(q) || item.student.student_number.toLowerCase().includes(q));
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sections', href: '/sections' },
    { title: props.section.subject_code, href: `/sections/${props.section.id}` },
    { title: 'Attendance', href: `/sections/${props.section.id}/attendance` },
    { title: props.session.session_date, href: `/attendance/${props.session.id}` },
];

const formatStudentDisplayName = (student: Student | { name?: string; first_name?: string; last_name?: string } | null | undefined) => {
    if (!student) return '—';
    if (student.last_name && student.first_name) {
        return `${student.last_name}, ${student.first_name}`;
    }
    if (student.last_name) return student.last_name;
    if (student.name) {
        if (student.name.includes(',')) return student.name;
        const parts = student.name.trim().split(/\s+/);
        if (parts.length > 1) {
            const last = parts.pop();
            return `${last}, ${parts.join(' ')}`;
        }
        return student.name;
    }
    return student.first_name || '—';
};

const initials = (name: string) =>
    (name || '')
        .replace(/,/g, ' ')
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

async function updateStatus(record: RecordData | null, newStatus: RecordData['status']) {
    if (!record || saveState.value[record.id] === 'saving') return;
    if (record.status === newStatus) return;

    const oldStatus = record.status;
    record.status = newStatus;
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

function toggle(record: RecordData | null) {
    if (!record) return;
    let newStatus: RecordData['status'] = 'present';
    if (record.status === 'present') {
        newStatus = 'absent';
    } else if (record.status === 'absent') {
        newStatus = 'late';
    } else {
        newStatus = 'present';
    }
    updateStatus(record, newStatus);
}

function startDrag(event: DragEvent, status: RecordData['status']) {
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copy';
        event.dataTransfer.setData('text/plain', status);
    }
}

function dropStatus(event: DragEvent, record: RecordData | null) {
    if (!record) return;
    const status = event.dataTransfer?.getData('text/plain') as RecordData['status'];
    if (['present', 'absent', 'late'].includes(status)) {
        updateStatus(record, status);
    }
}

// Bulk marking
const isMarkingAll = ref(false);

async function markAll(status: 'present' | 'absent') {
    if (isMarkingAll.value) return;
    const targets = rosterStudents.value.filter((item) => item.record.status !== status);
    if (targets.length === 0) return;

    isMarkingAll.value = true;
    try {
        await Promise.all(targets.map((item) => updateStatus(item.record, status)));
    } finally {
        isMarkingAll.value = false;
    }
}

const getBlockRows = (block: any) => {
    const internalRows = block.internal_rows || Math.max(1, ...(block.seats?.map((s: any) => s.row_number) ?? [1]));
    return Array.from({ length: internalRows }, (_, index) =>
        (block.seats || []).filter((seat: any) => seat.row_number === index + 1).sort((a: any, b: any) => a.column_number - b.column_number),
    );
};

const getBlockCols = (block: any) => {
    return block.internal_columns || Math.max(1, ...(block.seats?.map((s: any) => s.column_number) ?? [1]));
};

const getBlockDensity = (block: any): 'spacious' | 'compact' | 'condensed' | 'micro' => {
    const cols = getBlockCols(block);
    if (cols <= 5) return 'spacious';
    if (cols <= 8) return 'compact';
    if (cols <= 12) return 'condensed';
    return 'micro';
};

const hasColumnAisle = (block: any, position: number) => (block.aisle_after_columns ?? []).includes(position);
const hasRowAisle = (block: any, position: number) => (block.aisle_after_rows ?? []).includes(position);

const showDeleteModal = ref(false);
const isDeleting = ref(false);

function deleteSession() {
    isDeleting.value = true;
    router.delete(`/attendance/${props.session.id}`, {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`${section.subject_code} · ${session.session_date} - Attendance`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="page-enter mx-auto flex w-full max-w-[1360px] flex-1 flex-col gap-6 px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <!-- Header Banner -->
            <header
                class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 shadow-sm sm:p-8"
            >
                <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                    <div>
                        <div class="mb-3 flex items-center justify-between gap-4">
                            <Link
                                :href="`/sections/${section.id}/attendance`"
                                prefetch="hover"
                                class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted-foreground transition-colors hover:text-primary"
                            >
                                <ArrowLeft class="size-4" /> Back to attendance register
                            </Link>

                            <button
                                type="button"
                                class="shadow-xs inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-100 hover:text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-400 dark:hover:bg-rose-900/60 sm:hidden"
                                @click="showDeleteModal = true"
                            >
                                <Trash2 class="size-3.5" />
                                <span>Delete roll call</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge-primary font-mono">{{ section.subject_code }}</span>
                            <span class="badge-muted">{{ session.session_date }}</span>
                        </div>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">{{ section.name }} · Live Roll Call</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ formatTime12h(session.starts_at) }} – {{ formatTime12h(session.ends_at) }} · {{ session.duration_minutes }} minutes
                        </p>
                    </div>

                    <!-- Live KPI Tally Card & Desktop Delete Action -->
                    <div class="flex flex-col items-end gap-3">
                        <button
                            type="button"
                            class="shadow-xs hidden items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-1.5 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-100 hover:text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-400 dark:hover:bg-rose-900/60 sm:inline-flex"
                            @click="showDeleteModal = true"
                        >
                            <Trash2 class="size-3.5" />
                            <span>Delete roll call on this day</span>
                        </button>

                        <div class="flex flex-wrap items-center gap-3">
                            <div class="flex items-center gap-4 rounded-2xl border border-border/80 bg-card/90 px-6 py-3.5 shadow-sm">
                                <div class="text-center">
                                    <span class="block text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400"
                                        >Present</span
                                    >
                                    <span class="font-mono text-2xl font-semibold text-emerald-700 dark:text-emerald-400">{{ presentCount }}</span>
                                </div>
                                <div class="h-8 w-px bg-border/80" />
                                <div class="text-center">
                                    <span class="block text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400"
                                        >Late (0.5 pt)</span
                                    >
                                    <span class="font-mono text-2xl font-semibold text-amber-700 dark:text-amber-400">{{ lateCount }}</span>
                                </div>
                                <div class="h-8 w-px bg-border/80" />
                                <div class="text-center">
                                    <span class="block text-xs font-semibold uppercase tracking-wider text-rose-700 dark:text-rose-400">Absent</span>
                                    <span class="font-mono text-2xl font-semibold text-rose-700 dark:text-rose-400">{{ absentCount }}</span>
                                </div>
                                <div class="h-8 w-px bg-border/80" />
                                <div class="text-center">
                                    <span class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total</span>
                                    <span class="font-mono text-2xl font-semibold text-foreground">{{ session.total_count }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- View Switcher (Seating Map vs Paper Sign-in / List View) -->
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-border/80 pb-3">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition-all"
                        :class="
                            viewMode === 'map'
                                ? 'shadow-xs bg-primary text-white'
                                : 'border border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card'
                        "
                        @click="viewMode = 'map'"
                    >
                        <LayoutGrid class="size-4" />
                        <span>Seating Map View</span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition-all"
                        :class="
                            viewMode === 'list'
                                ? 'shadow-xs bg-primary text-white'
                                : 'border border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card'
                        "
                        @click="viewMode = 'list'"
                    >
                        <FileCheck class="size-4" />
                        <span>Paper Sign-in / List View</span>
                    </button>
                </div>

                <!-- Session Note if available -->
                <div v-if="session.notes" class="text-sm italic text-muted-foreground">Note: {{ session.notes }}</div>
            </div>

            <!-- VIEW 1: SEATING MAP VIEW -->
            <div v-if="viewMode === 'map'" class="space-y-6">
                <!-- Instructions & Status Legend -->
                <div
                    class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border/80 bg-secondary/40 px-5 py-3 text-sm font-medium"
                >
                    <div class="flex flex-wrap items-center gap-5">
                        <span
                            class="inline-flex cursor-grab items-center gap-2 text-primary active:cursor-grabbing"
                            draggable="true"
                            @dragstart="startDrag($event, 'present')"
                        >
                            <span class="size-3 rounded-full bg-primary ring-4 ring-primary/20" /> Present (1.0 pt)
                        </span>
                        <span
                            class="inline-flex cursor-grab items-center gap-2 text-rose-700 active:cursor-grabbing dark:text-rose-400"
                            draggable="true"
                            @dragstart="startDrag($event, 'absent')"
                        >
                            <span class="size-3 rounded-full bg-rose-700 ring-4 ring-rose-700/20" /> Absent (0 pt)
                        </span>
                        <span
                            class="inline-flex cursor-grab items-center gap-2 text-amber-700 active:cursor-grabbing dark:text-amber-400"
                            draggable="true"
                            @dragstart="startDrag($event, 'late')"
                        >
                            <span class="size-3 rounded-full bg-amber-700 ring-4 ring-amber-700/20" /> Late (0.5 pt)
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <span class="hidden text-xs text-muted-foreground sm:inline">
                            Tap a seat: 1st click Absent → 2nd click Late → 3rd click Present.
                        </span>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                :disabled="isMarkingAll"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-700 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition-all hover:bg-emerald-800 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                                @click="markAll('present')"
                            >
                                <LoaderCircle v-if="isMarkingAll" class="size-3.5 animate-spin" />
                                <Check v-else class="size-3.5" />
                                <span>All Present</span>
                            </button>
                            <button
                                type="button"
                                :disabled="isMarkingAll"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-rose-700 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition-all hover:bg-rose-800 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                                @click="markAll('absent')"
                            >
                                <LoaderCircle v-if="isMarkingAll" class="size-3.5 animate-spin" />
                                <X v-else class="size-3.5" />
                                <span>All Absent</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Interactive Live Classroom Floor -->
                <section class="paper-card overflow-hidden rounded-3xl p-6 shadow-sm md:p-8" aria-label="Interactive classroom attendance map">
                    <div
                        class="shadow-xs mb-6 flex items-center justify-center rounded-2xl bg-[#164e3f] px-6 py-3.5 text-center text-xs font-bold uppercase tracking-[0.25em] text-white dark:bg-[#134e48] md:text-sm"
                    >
                        Teaching Wall / Front Board
                    </div>

                    <div
                        class="w-full max-w-full overflow-x-auto overscroll-x-contain pb-4 [-webkit-overflow-scrolling:touch] [scrollbar-gutter:stable]"
                    >
                        <div
                            class="grid w-full min-w-0 gap-6"
                            :style="{ gridTemplateColumns: `repeat(${Math.max(1, ...blocks.map((b) => b.column))}, minmax(0, 1fr))` }"
                        >
                            <article
                                v-for="block in blocks"
                                :key="block.id"
                                class="shadow-xs rounded-2xl border border-border/80 bg-card transition-all"
                                :class="
                                    getBlockDensity(block) === 'spacious' ? 'p-5' : getBlockDensity(block) === 'compact' ? 'p-3.5' : 'p-2.5 sm:p-3'
                                "
                                :style="{ gridColumn: block.column, gridRow: block.row }"
                            >
                                <h2
                                    v-if="block.label && block.label !== 'Classroom'"
                                    class="mb-3 text-center text-xs font-bold uppercase tracking-wider text-muted-foreground"
                                >
                                    {{ block.label }}
                                </h2>

                                <template v-for="(seats, rowIndex) in getBlockRows(block)" :key="rowIndex">
                                    <div
                                        class="flex items-stretch first:mt-0 last:mb-0"
                                        :class="
                                            getBlockDensity(block) === 'spacious'
                                                ? 'my-3 gap-3'
                                                : getBlockDensity(block) === 'compact'
                                                  ? 'my-2 gap-2'
                                                  : getBlockDensity(block) === 'condensed'
                                                    ? 'my-1.5 gap-1.5'
                                                    : 'my-1 gap-1'
                                        "
                                    >
                                        <template v-for="seat in seats" :key="seat.id">
                                            <div
                                                class="relative flex min-w-0 flex-1 flex-col items-stretch"
                                                :class="
                                                    getBlockDensity(block) === 'spacious'
                                                        ? 'min-w-[5.5rem] sm:min-w-[6.5rem]'
                                                        : getBlockDensity(block) === 'compact'
                                                          ? 'min-w-[3.75rem] sm:min-w-[4.5rem]'
                                                          : getBlockDensity(block) === 'condensed'
                                                            ? 'min-w-[2.75rem] sm:min-w-[3.25rem]'
                                                            : 'min-w-[2rem] sm:min-w-[2.5rem]'
                                                "
                                            >
                                                <button
                                                    type="button"
                                                    :disabled="seat.is_disabled || !seat.record"
                                                    :aria-label="
                                                        seat.student
                                                            ? `${formatStudentDisplayName(seat.student)}, ${seat.record?.status}`
                                                            : `${seat.label}, empty`
                                                    "
                                                    :aria-pressed="seat.record?.status === 'present'"
                                                    class="group relative flex w-full flex-1 flex-col items-center justify-center border text-center transition-all duration-150 focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:cursor-default"
                                                    :class="[
                                                        getBlockDensity(block) === 'spacious'
                                                            ? 'min-h-[6.5rem] rounded-2xl p-2.5 sm:min-h-[7.25rem] sm:p-3'
                                                            : getBlockDensity(block) === 'compact'
                                                              ? 'min-h-[4.75rem] rounded-xl p-1.5 sm:min-h-[5.5rem] sm:p-2'
                                                              : getBlockDensity(block) === 'condensed'
                                                                ? 'min-h-[3.75rem] rounded-lg p-1 sm:min-h-[4.25rem]'
                                                                : 'min-h-[3rem] rounded-md p-0.5 sm:min-h-[3.5rem]',
                                                        seat.is_disabled
                                                            ? 'cursor-not-allowed border-transparent bg-muted/20 opacity-30'
                                                            : !seat.student
                                                              ? 'border-2 border-slate-200/90 bg-card text-muted-foreground hover:border-primary/50 dark:border-border/80'
                                                              : (seat.student.absent_count ?? 0) >= 3
                                                                ? seat.record?.status === 'present'
                                                                    ? 'shadow-xs border-rose-500 bg-[#164e3f] text-white ring-2 ring-rose-400 hover:-translate-y-0.5 hover:shadow-md hover:brightness-105 dark:bg-[#134e48]'
                                                                    : seat.record?.status === 'late'
                                                                      ? 'shadow-xs border-rose-500 bg-amber-800 text-white ring-2 ring-rose-400 hover:-translate-y-0.5 hover:shadow-md hover:brightness-105 dark:bg-amber-900'
                                                                      : 'shadow-xs border-rose-400 bg-[#500724] text-white ring-2 ring-rose-500 hover:-translate-y-0.5 hover:shadow-md hover:brightness-110 dark:bg-[#4c0519]'
                                                                : seat.record?.status === 'present'
                                                                  ? 'shadow-xs border-[#1b5d4e]/80 bg-[#164e3f] text-white hover:-translate-y-0.5 hover:shadow-md hover:brightness-105 dark:bg-[#134e48]'
                                                                  : seat.record?.status === 'late'
                                                                    ? 'shadow-xs border-amber-600/80 bg-amber-800 text-white hover:-translate-y-0.5 hover:shadow-md hover:brightness-105 dark:bg-amber-900'
                                                                    : 'shadow-xs border-rose-600/80 bg-rose-900 text-white hover:-translate-y-0.5 hover:shadow-md hover:brightness-105 dark:bg-rose-950',
                                                    ]"
                                                    @click="toggle(seat.record)"
                                                    @dragover.prevent
                                                    @drop.prevent="dropStatus($event, seat.record)"
                                                >
                                                    <template v-if="seat.student">
                                                        <!-- Scaled Photo / Avatar -->
                                                        <div
                                                            class="shadow-xs flex shrink-0 items-center justify-center overflow-hidden rounded-full ring-1 sm:ring-2"
                                                            :class="[
                                                                (seat.student.absent_count ?? 0) >= 3
                                                                    ? 'bg-rose-500/30 ring-rose-400/80'
                                                                    : 'bg-white/20 ring-white/25',
                                                                getBlockDensity(block) === 'spacious'
                                                                    ? 'size-10 sm:size-11'
                                                                    : getBlockDensity(block) === 'compact'
                                                                      ? 'size-7 sm:size-8'
                                                                      : getBlockDensity(block) === 'condensed'
                                                                        ? 'size-5 sm:size-6'
                                                                        : 'size-4 sm:size-5',
                                                            ]"
                                                        >
                                                            <img
                                                                v-if="seat.student.photo_url"
                                                                :src="seat.student.photo_url"
                                                                :alt="formatStudentDisplayName(seat.student)"
                                                                class="size-full object-cover"
                                                            />
                                                            <span
                                                                v-else
                                                                class="uppercase tracking-wider text-white"
                                                                :class="
                                                                    getBlockDensity(block) === 'spacious'
                                                                        ? 'text-xs font-black sm:text-sm'
                                                                        : getBlockDensity(block) === 'compact'
                                                                          ? 'text-[10px] font-bold sm:text-xs'
                                                                          : getBlockDensity(block) === 'condensed'
                                                                            ? 'text-[8px] font-bold sm:text-[9px]'
                                                                            : 'text-[7px] font-bold'
                                                                "
                                                            >
                                                                {{ initials(formatStudentDisplayName(seat.student)) }}
                                                            </span>
                                                        </div>

                                                        <!-- Complete Name (Full Last Name First) -->
                                                        <span
                                                            class="block w-full truncate text-center font-bold uppercase leading-tight tracking-tight text-white"
                                                            :class="
                                                                getBlockDensity(block) === 'spacious'
                                                                    ? 'mt-2 max-w-[7.5rem] text-[11px] sm:text-xs'
                                                                    : getBlockDensity(block) === 'compact'
                                                                      ? 'mt-1 max-w-[5rem] text-[9.5px] sm:text-[10.5px]'
                                                                      : getBlockDensity(block) === 'condensed'
                                                                        ? 'mt-0.5 max-w-[3.75rem] text-[8px] sm:text-[8.5px]'
                                                                        : 'mt-0.25 max-w-[2.75rem] text-[7px]'
                                                            "
                                                            :title="formatStudentDisplayName(seat.student)"
                                                        >
                                                            {{ formatStudentDisplayName(seat.student) }}
                                                        </span>

                                                        <!-- Seat Label & Status -->
                                                        <div
                                                            class="flex items-center justify-center gap-1 font-mono font-medium uppercase leading-none tracking-wider text-white/70"
                                                            :class="
                                                                getBlockDensity(block) === 'spacious'
                                                                    ? 'mt-0.5 text-[9px] sm:text-[10px]'
                                                                    : getBlockDensity(block) === 'compact'
                                                                      ? 'mt-0.5 text-[8px] sm:text-[8.5px]'
                                                                      : getBlockDensity(block) === 'condensed'
                                                                        ? 'mt-0 text-[7px] sm:text-[7.5px]'
                                                                        : 'mt-0 text-[6.5px]'
                                                            "
                                                        >
                                                            <span>{{ seat.label }}</span>
                                                            <span
                                                                v-if="(seat.student.absent_count ?? 0) >= 3"
                                                                class="py-0.2 rounded bg-rose-500/40 px-1 text-[6.5px] font-bold text-rose-200 ring-1 ring-rose-400/50 sm:text-[7px]"
                                                            >
                                                                3+ ABS
                                                            </span>
                                                            <span v-if="seat.record?.status === 'late'" class="font-bold text-amber-300">· LATE</span>
                                                            <span v-else-if="seat.record?.status === 'absent'" class="font-bold text-rose-300"
                                                                >· ABS</span
                                                            >
                                                            <LoaderCircle
                                                                v-if="saveState[seat.record!.id] === 'saving'"
                                                                class="size-3 animate-spin text-white"
                                                            />
                                                        </div>
                                                    </template>

                                                    <template v-else>
                                                        <Armchair
                                                            class="text-slate-400 transition-transform group-hover:scale-110 dark:text-muted-foreground/60"
                                                            :class="
                                                                getBlockDensity(block) === 'spacious'
                                                                    ? 'size-6'
                                                                    : getBlockDensity(block) === 'compact'
                                                                      ? 'size-4.5 sm:size-5'
                                                                      : getBlockDensity(block) === 'condensed'
                                                                        ? 'size-3.5 sm:size-4'
                                                                        : 'size-3'
                                                            "
                                                        />
                                                        <span
                                                            class="block font-mono font-semibold uppercase tracking-wider text-slate-500 dark:text-muted-foreground"
                                                            :class="
                                                                getBlockDensity(block) === 'spacious'
                                                                    ? 'mt-2 text-[10px] sm:text-[11px]'
                                                                    : getBlockDensity(block) === 'compact'
                                                                      ? 'mt-1 text-[9px] sm:text-[9.5px]'
                                                                      : getBlockDensity(block) === 'condensed'
                                                                        ? 'mt-0.5 text-[7.5px] sm:text-[8px]'
                                                                        : 'mt-0.25 text-[6.5px]'
                                                            "
                                                        >
                                                            {{ seat.is_disabled ? 'Unavailable' : seat.label }}
                                                        </span>
                                                    </template>
                                                </button>
                                            </div>

                                            <!-- Column Aisle Spacer -->
                                            <div
                                                v-if="hasColumnAisle(block, seat.column_number)"
                                                class="shrink-0 rounded-lg border-2 border-dashed border-primary/30 bg-primary/5"
                                                :class="
                                                    getBlockDensity(block) === 'spacious'
                                                        ? 'mx-2 w-7'
                                                        : getBlockDensity(block) === 'compact'
                                                          ? 'mx-1.5 w-5'
                                                          : 'mx-1 w-4'
                                                "
                                            />
                                        </template>
                                    </div>

                                    <!-- Row Aisle Spacer -->
                                    <div
                                        v-if="hasRowAisle(block, rowIndex + 1)"
                                        class="flex w-full items-center justify-center rounded-lg border-2 border-dashed border-primary/30 bg-primary/5 text-center font-semibold uppercase tracking-wider text-primary/70"
                                        :class="
                                            getBlockDensity(block) === 'spacious'
                                                ? 'my-2 h-7 text-[10px]'
                                                : getBlockDensity(block) === 'compact'
                                                  ? 'h-5.5 my-1.5 text-[9px]'
                                                  : 'h-4.5 my-1 text-[8px]'
                                        "
                                    >
                                        Aisle
                                    </div>
                                </template>
                            </article>
                        </div>
                    </div>
                </section>

                <!-- Unseated Students Section -->
                <section v-if="unseated.length" class="paper-card p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <UserRound class="size-4 text-amber-700 dark:text-amber-400" />
                        <h2 class="text-base font-semibold text-foreground">Unseated Students ({{ unseated.length }})</h2>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="item in sortedUnseated"
                            :key="item.student.id"
                            class="flex items-center justify-between rounded-xl border p-3"
                            :class="
                                (item.student.absent_count ?? 0) >= 3
                                    ? 'border-rose-500/40 bg-rose-500/5 dark:border-rose-500/30 dark:bg-rose-950/20'
                                    : 'border-border/80 bg-secondary/30'
                            "
                        >
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full border text-xs font-medium"
                                    :class="
                                        (item.student.absent_count ?? 0) >= 3
                                            ? 'border-rose-500/50 bg-rose-500/20 text-rose-700 dark:text-rose-400'
                                            : 'border-border bg-card'
                                    "
                                >
                                    {{ initials(formatStudentDisplayName(item.student)) }}
                                </span>
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-sm font-medium text-foreground">{{ formatStudentDisplayName(item.student) }}</p>
                                        <span
                                            v-if="(item.student.absent_count ?? 0) >= 3"
                                            class="py-0.2 rounded bg-rose-500/20 px-1.5 text-[9px] font-bold text-rose-700 dark:text-rose-400"
                                        >
                                            3+ ABS
                                        </span>
                                    </div>
                                    <p class="font-mono text-xs text-muted-foreground">{{ item.student.student_number }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class="rounded-lg border px-2.5 py-1 text-xs font-medium transition-colors"
                                    :class="
                                        item.record.status === 'present'
                                            ? 'border-emerald-700 bg-emerald-700 text-white'
                                            : 'border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card'
                                    "
                                    @click="updateStatus(item.record, 'present')"
                                >
                                    Present
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border px-2.5 py-1 text-xs font-medium transition-colors"
                                    :class="
                                        item.record.status === 'late'
                                            ? 'border-amber-700 bg-amber-700 text-white'
                                            : 'border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card'
                                    "
                                    @click="updateStatus(item.record, 'late')"
                                >
                                    Late
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border px-2.5 py-1 text-xs font-medium transition-colors"
                                    :class="
                                        item.record.status === 'absent'
                                            ? 'border-rose-700 bg-rose-700 text-white'
                                            : 'border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card'
                                    "
                                    @click="updateStatus(item.record, 'absent')"
                                >
                                    Absent
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- VIEW 2: PAPER SIGN-IN / LIST VIEW -->
            <div v-else class="space-y-4">
                <section class="paper-card overflow-hidden p-6 shadow-sm">
                    <!-- Controls Bar -->
                    <div class="flex flex-col justify-between gap-4 border-b border-border/80 pb-4 lg:flex-row lg:items-center">
                        <div>
                            <h2 class="text-xl font-medium text-foreground">Paper Sign-in Roster Check</h2>
                            <p class="text-sm text-muted-foreground">
                                Rapid alphabetical list view for easy attendance recording from physical signed sheets.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Search -->
                            <div class="relative min-w-[220px]">
                                <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Find student by name or ID..."
                                    class="w-full rounded-xl border border-input bg-background py-1.5 pl-9 pr-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                />
                            </div>

                            <!-- Filter Pills -->
                            <div class="flex items-center gap-1 rounded-xl border border-border/80 bg-secondary/30 p-1">
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1 text-xs font-medium transition-colors"
                                    :class="
                                        filterStatus === 'all' ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-amber-400 hover:text-white'
                                    "
                                    @click="filterStatus = 'all'"
                                >
                                    All ({{ rosterStudents.length }})
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1 text-xs font-medium transition-colors"
                                    :class="filterStatus === 'present' ? 'bg-emerald-700 text-white' : 'text-muted-foreground hover:text-foreground'"
                                    @click="filterStatus = 'present'"
                                >
                                    Present ({{ presentCount }})
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1 text-xs font-medium transition-colors"
                                    :class="filterStatus === 'late' ? 'bg-amber-700 text-white' : 'text-muted-foreground hover:text-foreground'"
                                    @click="filterStatus = 'late'"
                                >
                                    Late ({{ lateCount }})
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1 text-xs font-medium transition-colors"
                                    :class="filterStatus === 'absent' ? 'bg-rose-700 text-white' : 'text-muted-foreground hover:text-foreground'"
                                    @click="filterStatus = 'absent'"
                                >
                                    Absent ({{ absentCount }})
                                </button>
                            </div>

                            <!-- Bulk Actions -->
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    :disabled="isMarkingAll"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-700 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition-all hover:bg-emerald-800 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="markAll('present')"
                                >
                                    <LoaderCircle v-if="isMarkingAll" class="size-3.5 animate-spin" />
                                    <Check v-else class="size-3.5" />
                                    <span>All Present</span>
                                </button>
                                <button
                                    type="button"
                                    :disabled="isMarkingAll"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-rose-700 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition-all hover:bg-rose-800 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="markAll('absent')"
                                >
                                    <LoaderCircle v-if="isMarkingAll" class="size-3.5 animate-spin" />
                                    <X v-else class="size-3.5" />
                                    <span>All Absent</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Alphabetical Roster List Table -->
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[760px] text-base">
                            <thead>
                                <tr
                                    class="border-b border-border/80 bg-secondary/40 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                                >
                                    <th class="w-12 rounded-l-lg px-4 py-3">#</th>
                                    <th class="px-4 py-3">Student Name</th>
                                    <th class="px-4 py-3">Student Number</th>
                                    <th class="px-4 py-3">Seat Location</th>
                                    <th class="rounded-r-lg px-4 py-3 text-right">Attendance Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="(item, idx) in filteredRoster" :key="item.student.id" class="transition-colors hover:bg-secondary/30">
                                    <td class="px-4 py-3 font-mono text-sm text-muted-foreground">
                                        {{ idx + 1 }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <img
                                                v-if="item.student.photo_url"
                                                :src="item.student.photo_url"
                                                alt=""
                                                class="size-8 rounded-full border border-border object-cover"
                                            />
                                            <span
                                                v-else
                                                class="flex size-8 shrink-0 items-center justify-center rounded-full border text-xs font-medium"
                                                :class="
                                                    (item.student.absent_count ?? 0) >= 3
                                                        ? 'border-rose-500/50 bg-rose-500/20 text-rose-700 dark:text-rose-400'
                                                        : 'border-border bg-card'
                                                "
                                            >
                                                {{ initials(formatStudentDisplayName(item.student)) }}
                                            </span>
                                            <div class="flex items-center gap-2">
                                                <span class="text-base font-semibold text-foreground">{{
                                                    formatStudentDisplayName(item.student)
                                                }}</span>
                                                <span
                                                    v-if="(item.student.absent_count ?? 0) >= 3"
                                                    class="inline-flex items-center gap-1 rounded-md border border-rose-500/30 bg-rose-500/15 px-2 py-0.5 text-xs font-bold text-rose-700 dark:text-rose-400"
                                                >
                                                    ⚠️ {{ item.student.absent_count }} Absences
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-sm text-muted-foreground">
                                        {{ item.student.student_number }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-muted-foreground">
                                        <span class="rounded bg-secondary/60 px-2 py-0.5 font-mono">
                                            {{ item.seatLabel }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <!-- Save Indicator -->
                                            <span class="mr-1 inline-flex size-4 items-center justify-center">
                                                <LoaderCircle
                                                    v-if="saveState[item.record.id] === 'saving'"
                                                    class="size-3.5 animate-spin text-primary"
                                                />
                                                <Check
                                                    v-else-if="saveState[item.record.id] === 'saved'"
                                                    class="size-3.5 text-emerald-700 dark:text-emerald-400"
                                                />
                                                <RotateCcw v-else-if="saveState[item.record.id] === 'error'" class="size-3.5 text-rose-700" />
                                            </span>

                                            <!-- 3 Action Buttons -->
                                            <button
                                                type="button"
                                                class="rounded-lg border px-3 py-1.5 text-xs font-medium transition-all"
                                                :class="
                                                    item.record.status === 'present'
                                                        ? 'border-emerald-700 bg-emerald-700 text-white shadow-sm ring-2 ring-emerald-600/50'
                                                        : 'border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card'
                                                "
                                                @click="updateStatus(item.record, 'present')"
                                            >
                                                Present (1.0)
                                            </button>

                                            <button
                                                type="button"
                                                class="rounded-lg border px-3 py-1.5 text-xs font-medium transition-all"
                                                :class="
                                                    item.record.status === 'late'
                                                        ? 'border-amber-700 bg-amber-700 text-white shadow-sm ring-2 ring-amber-600/50'
                                                        : 'border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card'
                                                "
                                                @click="updateStatus(item.record, 'late')"
                                            >
                                                Late (0.5)
                                            </button>

                                            <button
                                                type="button"
                                                class="rounded-lg border px-3 py-1.5 text-xs font-medium transition-all"
                                                :class="
                                                    item.record.status === 'absent'
                                                        ? 'border-rose-700 bg-rose-700 text-white shadow-sm ring-2 ring-rose-600/50'
                                                        : 'border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card'
                                                "
                                                @click="updateStatus(item.record, 'absent')"
                                            >
                                                Absent (0)
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!filteredRoster.length">
                                    <td colspan="5" class="py-12 text-center text-sm text-muted-foreground">
                                        No students found matching your search.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>

        <!-- Delete Confirmation Modal -->
        <div
            v-if="showDeleteModal"
            v-modal-focus
            class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-2xl animate-in fade-in zoom-in-95">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-rose-100 dark:bg-rose-950/60">
                        <Trash2 class="size-5 text-rose-600 dark:text-rose-400" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-foreground">Delete Roll Call</h3>
                        <p class="text-xs text-muted-foreground">{{ session.session_date }} · {{ formatTime12h(session.starts_at) }} – {{ formatTime12h(session.ends_at) }}</p>
                    </div>
                </div>

                <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                    Are you sure you want to delete this roll call session? All student attendance records and times recorded for this day will be
                    permanently removed.
                </p>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-border bg-background px-4 py-2 text-sm font-semibold text-foreground transition-colors hover:bg-secondary"
                        :disabled="isDeleting"
                        @click="showDeleteModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rose-700 disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="deleteSession"
                    >
                        <LoaderCircle v-if="isDeleting" class="size-4 animate-spin" />
                        <span>{{ isDeleting ? 'Deleting...' : 'Yes, Delete Roll Call' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
