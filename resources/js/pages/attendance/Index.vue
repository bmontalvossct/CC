<script setup lang="ts">
import AttendanceCalendar from '@/components/attendance/AttendanceCalendar.vue';
import StudentAttendanceModal, { type StudentSummary } from '@/components/attendance/StudentAttendanceModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowRight, Calendar, CalendarCheck2, CalendarDays, CheckCircle2, Clock3, History, LoaderCircle, Plus, Search, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Summary = { sessions: number; present: number; absent: number; rate: number | null; attended_hours: number };
type Section = {
    id: number;
    subject_code: string;
    subject_title: string;
    name: string;
    term: { name: string; school_year: string };
    default_schedule: { starts_at: string; ends_at: string } | null;
};

type SessionRecord = {
    student_id: number;
    status: 'present' | 'late' | 'absent';
    attended_minutes: number;
};

type Session = {
    id: number;
    session_date: string;
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
    notes?: string | null;
    records_count: number;
    present_count: number;
    late_count: number;
    absent_count: number;
    records: SessionRecord[];
};

type Student = {
    id: number;
    student_number: string;
    name: string;
};

const props = defineProps<{
    section: Section;
    referenceDate: string;
    periodSummaries: Record<'week' | 'month' | 'term', Summary>;
    studentSummaries: StudentSummary[];
    sessions: Session[];
    students?: Student[];
}>();

// Navigation Tabs
type ActiveTab = 'overview' | 'calendar' | 'summaries';
const currentTab = ref<ActiveTab>('overview');

// Student Details Modal
const selectedStudent = ref<StudentSummary | null>(null);
const isModalOpen = ref(false);

const openStudentModal = (student: StudentSummary) => {
    selectedStudent.value = student;
    isModalOpen.value = true;
};

const closeStudentModal = () => {
    isModalOpen.value = false;
    selectedStudent.value = null;
};

// Session Deletion
const sessionToDelete = ref<Session | null>(null);
const isDeletingSession = ref(false);

const confirmDeleteSession = (session: Session) => {
    sessionToDelete.value = session;
};

const deleteSession = () => {
    if (!sessionToDelete.value) return;
    isDeletingSession.value = true;
    router.delete(`/attendance/${sessionToDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeletingSession.value = false;
            sessionToDelete.value = null;
        },
    });
};

// Form for starting a roll-call session
const form = useForm({
    session_date: new Date().toLocaleDateString('en-CA'),
    starts_at: props.section.default_schedule?.starts_at?.slice(0, 5) ?? '08:00',
    ends_at: props.section.default_schedule?.ends_at?.slice(0, 5) ?? '09:00',
    notes: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sections', href: '/sections' },
    { title: props.section.subject_code, href: `/sections/${props.section.id}` },
    { title: 'Attendance', href: `/sections/${props.section.id}/attendance` },
];

const summaryCards = computed(() => [
    { label: 'This week', value: props.periodSummaries.week, icon: CalendarDays, color: 'text-blue-700 dark:text-blue-400 bg-blue-500/10' },
    { label: 'This month', value: props.periodSummaries.month, icon: Clock3, color: 'text-purple-700 dark:text-purple-400 bg-purple-500/10' },
    {
        label: props.section.term.name,
        value: props.periodSummaries.term,
        icon: CheckCircle2,
        color: 'text-emerald-700 dark:text-emerald-400 bg-emerald-500/10',
    },
]);

const readableDate = (date: string) => new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(new Date(`${date}T00:00:00`));

function changeReferenceDate(event: Event) {
    router.get(
        `/sections/${props.section.id}/attendance`,
        { reference_date: (event.target as HTMLInputElement).value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

// Student list for calendar (falls back to studentSummaries if students prop is missing)
const calendarStudents = computed<Student[]>(() => {
    if (props.students && props.students.length > 0) {
        return props.students;
    }
    return props.studentSummaries.map((s) => ({
        id: s.id,
        student_number: s.student_number,
        name: s.name,
    }));
});

// Student Table Filter and Search
const searchQuery = ref('');
const filterOption = ref<'all' | 'absences' | 'late'>('all');

const filteredStudentSummaries = computed(() => {
    let list = props.studentSummaries;

    if (filterOption.value === 'absences') {
        list = list.filter((s) => s.absent_count > 0);
    } else if (filterOption.value === 'late') {
        list = list.filter((s) => s.late_count > 0);
    }

    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return list;

    return list.filter((s) => s.name.toLowerCase().includes(q) || s.student_number.toLowerCase().includes(q));
});

const formatStudentDisplayName = (student: StudentSummary | { name?: string; first_name?: string; last_name?: string } | null | undefined) => {
    if (!student) return '—';
    if ('last_name' in student && 'first_name' in student && student.last_name && student.first_name) {
        return `${student.last_name}, ${student.first_name}`;
    }
    if (student.name) {
        if (student.name.includes(',')) return student.name;
        const parts = student.name.trim().split(/\s+/);
        if (parts.length > 1) {
            const last = parts.pop();
            return `${last}, ${parts.join(' ')}`;
        }
        return student.name;
    }
    return '—';
};
</script>

<template>
    <Head :title="`${section.subject_code} attendance - ClassCheck`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="page-enter mx-auto flex w-full max-w-[1360px] flex-1 flex-col gap-6 px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <!-- Header Card -->
            <header
                class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 shadow-sm sm:p-8"
            >
                <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="badge-primary font-mono">{{ section.subject_code }}</span>
                            <span class="badge-muted">{{ section.term.name }} {{ section.term.school_year }}</span>
                        </div>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ section.name }} · Attendance</h1>
                        <p class="mt-1 text-sm text-muted-foreground">{{ section.subject_title }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                            <span>Reference date</span>
                            <Input class="h-9 w-auto rounded-xl text-xs" type="date" :model-value="referenceDate" @change="changeReferenceDate" />
                        </label>
                    </div>
                </div>
            </header>

            <!-- Navigation Tabs (Overview, Calendar, Student Summaries) -->
            <div class="flex flex-wrap items-center gap-2 border-b border-border/80 pb-3">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition-all"
                    :class="
                        currentTab === 'overview'
                            ? 'shadow-xs bg-primary text-white'
                            : 'border border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card'
                    "
                    @click="currentTab = 'overview'"
                >
                    <History class="size-4" />
                    <span>Roll Call & Overview</span>
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition-all"
                    :class="
                        currentTab === 'calendar'
                            ? 'shadow-xs bg-primary text-white'
                            : 'border border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card'
                    "
                    @click="currentTab = 'calendar'"
                >
                    <Calendar class="size-4" />
                    <span>Attendance Calendar</span>
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition-all"
                    :class="
                        currentTab === 'summaries'
                            ? 'shadow-xs bg-primary text-white'
                            : 'border border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card'
                    "
                    @click="currentTab = 'summaries'"
                >
                    <Users class="size-4" />
                    <span>Student Summaries ({{ studentSummaries.length }})</span>
                </button>
            </div>

            <!-- TAB 1: OVERVIEW & SESSIONS -->
            <div v-if="currentTab === 'overview'" class="space-y-6">
                <!-- Summary KPI Cards -->
                <section class="grid gap-4 sm:grid-cols-3" aria-label="Attendance period summaries">
                    <article
                        v-for="card in summaryCards"
                        :key="card.label"
                        class="paper-card flex flex-col justify-between p-6 transition-all hover:border-primary/40 hover:shadow-md"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">{{ card.label }}</span>
                            <span :class="['grid size-10 place-items-center rounded-xl', card.color]">
                                <component :is="card.icon" class="size-5" />
                            </span>
                        </div>
                        <div class="mt-6 flex items-baseline justify-between">
                            <p class="text-3xl font-semibold tracking-tight sm:text-4xl">
                                {{ card.value.rate === null ? '—' : `${card.value.rate}%` }}
                            </p>
                            <span class="text-sm font-medium text-muted-foreground">{{ card.value.sessions }} sessions</span>
                        </div>
                        <p class="mt-2 text-sm font-medium text-muted-foreground">
                            <span class="font-semibold text-emerald-700 dark:text-emerald-400">{{ card.value.present }}</span> present ·
                            <span class="font-semibold text-rose-700 dark:text-rose-400">{{ card.value.absent }}</span> absent
                        </p>
                    </article>
                </section>

                <!-- Start Session Form & Recent Sessions Grid -->
                <section class="grid items-start gap-6 lg:grid-cols-[24rem_1fr]">
                    <!-- Start Attendance Session Form -->
                    <form class="paper-card p-6 shadow-sm" @submit.prevent="form.post(`/sections/${section.id}/attendance`)">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-xl bg-primary text-white">
                                <Plus class="size-5" />
                            </span>
                            <div>
                                <h2 class="text-lg font-semibold text-foreground">Start roll call</h2>
                                <p class="text-xs text-muted-foreground">All students initialize as present.</p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4">
                            <div class="grid gap-1.5">
                                <Label for="session-date" class="text-sm font-semibold">Meeting date</Label>
                                <Input id="session-date" v-model="form.session_date" type="date" required class="h-11 rounded-xl text-sm" />
                                <InputError class="mt-1 text-xs" :message="form.errors.session_date" />
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div class="grid gap-1.5">
                                    <Label for="starts" class="text-sm font-semibold">Start time</Label>
                                    <Input id="starts" v-model="form.starts_at" type="time" required class="h-11 rounded-xl text-sm" />
                                    <InputError class="mt-1 text-xs" :message="form.errors.starts_at" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for="ends" class="text-sm font-semibold">End time</Label>
                                    <Input id="ends" v-model="form.ends_at" type="time" required class="h-11 rounded-xl text-sm" />
                                    <InputError class="mt-1 text-xs" :message="form.errors.ends_at" />
                                </div>
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="notes" class="text-sm font-semibold">
                                    Session notes <span class="font-normal text-muted-foreground">(optional)</span>
                                </Label>
                                <textarea
                                    id="notes"
                                    v-model="form.notes"
                                    rows="2"
                                    maxlength="2000"
                                    placeholder="e.g. Lab activity / quiz day"
                                    class="rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                />
                                <InputError class="mt-1 text-xs" :message="form.errors.notes" />
                            </div>

                            <Button type="submit" class="ink-button mt-2 !h-11 !w-full text-sm font-semibold" :disabled="form.processing">
                                <CalendarCheck2 class="size-4.5" />
                                <span>{{ form.processing ? 'Starting session...' : 'Launch live check' }}</span>
                            </Button>
                        </div>
                    </form>

                    <!-- Recent Sessions Timeline -->
                    <div class="paper-card p-6 shadow-sm">
                        <div class="flex items-center justify-between border-b border-border/80 pb-4">
                            <div class="flex items-center gap-2">
                                <History class="size-5 text-emerald-700 dark:text-emerald-400" />
                                <h2 class="text-lg font-semibold text-foreground">Recent roll-call sessions</h2>
                            </div>
                            <span class="badge-muted text-xs">{{ sessions.length }} sessions</span>
                        </div>

                        <div v-if="sessions.length" class="max-h-96 divide-y divide-border/60 overflow-y-auto">
                            <Link
                                v-for="session in sessions"
                                :key="session.id"
                                :href="`/attendance/${session.id}`"
                                prefetch="hover"
                                class="group flex items-center justify-between gap-4 rounded-xl px-3 py-4 transition-colors hover:bg-secondary/60"
                            >
                                <div>
                                    <p class="text-base font-semibold transition-colors group-hover:text-primary">
                                        {{ readableDate(session.session_date) }}
                                    </p>
                                    <p class="mt-0.5 text-sm text-muted-foreground">
                                        {{ session.starts_at }} – {{ session.ends_at }} · {{ session.duration_minutes }} mins
                                    </p>
                                </div>
                                <div class="flex items-center gap-3 text-right">
                                    <div class="space-y-0.5">
                                        <span class="font-mono text-sm font-semibold text-emerald-700 dark:text-emerald-400">
                                            {{ session.present_count }}/{{ session.records_count }} present
                                        </span>
                                        <span v-if="session.absent_count > 0" class="block font-mono text-xs text-rose-700 dark:text-rose-400">
                                            {{ session.absent_count }} absent
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        title="Delete roll call on this day"
                                        class="grid size-8 place-items-center rounded-lg text-muted-foreground transition-colors hover:bg-rose-100 hover:text-rose-700 dark:hover:bg-rose-950/60 dark:hover:text-rose-400"
                                        @click.prevent.stop="confirmDeleteSession(session)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                    <ArrowRight
                                        class="size-4.5 text-muted-foreground transition-all group-hover:translate-x-0.5 group-hover:text-primary"
                                    />
                                </div>
                            </Link>
                        </div>
                        <div v-else class="py-12 text-center text-sm text-muted-foreground">
                            No attendance sessions logged yet. Start the first session on the left.
                        </div>
                    </div>
                </section>
            </div>

            <!-- TAB 2: ATTENDANCE CALENDAR -->
            <div v-else-if="currentTab === 'calendar'">
                <AttendanceCalendar :section-id="section.id" :sessions="sessions" :students="calendarStudents" />
            </div>

            <!-- TAB 3: STUDENT CUMULATIVE SUMMARIES WITH DRILLDOWN -->
            <div v-else class="space-y-4">
                <section class="paper-card overflow-hidden p-6 shadow-sm">
                    <div class="flex flex-col justify-between gap-4 border-b border-border/80 pb-4 md:flex-row md:items-center">
                        <div>
                            <h2 class="text-xl font-semibold text-foreground">Cumulative Student Attendance & Grading</h2>
                            <p class="text-sm text-muted-foreground">
                                Max 3 absences allowed per term. Late arrivals award half points (0.5 pt). Click any row to view student file.
                            </p>
                        </div>

                        <!-- Search and Filter Controls -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <div class="relative min-w-[240px]">
                                <Search class="absolute left-3 top-3 size-4 text-muted-foreground" />
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search by student name or ID..."
                                    class="w-full rounded-xl border border-input bg-background py-2 pl-9 pr-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                />
                            </div>

                            <div class="flex items-center gap-1 rounded-xl border border-border/80 bg-secondary/30 p-1">
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="
                                        filterOption === 'all' ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-amber-400 hover:text-white'
                                    "
                                    @click="filterOption = 'all'"
                                >
                                    All ({{ studentSummaries.length }})
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="filterOption === 'absences' ? 'bg-rose-700 text-white' : 'text-muted-foreground hover:text-foreground'"
                                    @click="filterOption = 'absences'"
                                >
                                    With Absences
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="filterOption === 'late' ? 'bg-amber-700 text-white' : 'text-muted-foreground hover:text-foreground'"
                                    @click="filterOption = 'late'"
                                >
                                    With Late (0.5 pt)
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Students Table -->
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[840px] text-base">
                            <thead>
                                <tr
                                    class="border-b border-border/80 bg-secondary/40 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                                >
                                    <th class="rounded-l-lg px-4 py-3.5">Student</th>
                                    <th class="px-4 py-3.5">Absences (Max 3)</th>
                                    <th class="px-4 py-3.5">Late (0.5 pt)</th>
                                    <th class="px-4 py-3.5">Grade Score</th>
                                    <th class="px-4 py-3.5">Term Rate</th>
                                    <th class="rounded-r-lg px-4 py-3.5 text-right">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr
                                    v-for="student in filteredStudentSummaries"
                                    :key="student.id"
                                    class="cursor-pointer transition-colors hover:bg-secondary/40"
                                    @click="openStudentModal(student)"
                                >
                                    <td class="px-4 py-3.5">
                                        <p class="text-base font-semibold text-foreground">{{ formatStudentDisplayName(student) }}</p>
                                        <p class="font-mono text-sm text-muted-foreground">{{ student.student_number }}</p>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div>
                                            <span
                                                class="font-mono text-sm font-semibold"
                                                :class="
                                                    student.absent_count > 3
                                                        ? 'text-rose-700 dark:text-rose-400'
                                                        : student.absent_count === 3 || student.absent_count === 2
                                                          ? 'text-amber-700 dark:text-amber-400'
                                                          : 'text-muted-foreground'
                                                "
                                            >
                                                {{ student.absent_count }} / 3 used
                                            </span>
                                            <span
                                                v-if="student.absent_count > 3"
                                                class="block text-xs font-semibold text-rose-700 dark:text-rose-400"
                                            >
                                                Allowance exceeded!
                                            </span>
                                            <span
                                                v-else-if="student.absent_count === 3"
                                                class="block text-xs font-semibold text-amber-700 dark:text-amber-400"
                                            >
                                                Limit reached (0 left)
                                            </span>
                                            <span v-else-if="student.absent_count === 2" class="block text-xs text-amber-700 dark:text-amber-400">
                                                1 absence left
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span
                                            class="font-mono text-sm font-semibold"
                                            :class="student.late_count > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-muted-foreground'"
                                        >
                                            {{ student.late_count }} late
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 font-mono text-sm font-semibold">
                                        <div>
                                            <span
                                                :class="
                                                    (student.grade_rate ?? student.overall.rate) === null
                                                        ? 'text-muted-foreground'
                                                        : (student.grade_rate ?? student.overall.rate) >= 90
                                                          ? 'text-emerald-700 dark:text-emerald-400'
                                                          : (student.grade_rate ?? student.overall.rate) >= 75
                                                            ? 'text-amber-700 dark:text-amber-400'
                                                            : 'text-rose-700 dark:text-rose-400'
                                                "
                                            >
                                                {{
                                                    (student.grade_rate ?? student.overall.rate) === null
                                                        ? '—'
                                                        : `${student.grade_rate ?? student.overall.rate}%`
                                                }}
                                            </span>
                                            <span class="block text-xs text-muted-foreground">
                                                {{ student.earned_points ?? student.present_count }}/{{
                                                    student.possible_points ?? student.overall.sessions
                                                }}
                                                pts
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 font-mono text-sm font-semibold text-foreground">
                                        {{ student.term.rate === null ? '—' : `${student.term.rate}%` }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            class="h-8 rounded-lg px-3 text-xs font-medium"
                                            @click.stop="openStudentModal(student)"
                                        >
                                            <span>View file</span>
                                            <ArrowRight class="size-3.5" />
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="!filteredStudentSummaries.length">
                                    <td colspan="7" class="py-12 text-center text-sm text-muted-foreground">
                                        No students found matching your criteria.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Student Attendance Modal Drilldown -->
            <StudentAttendanceModal :student="selectedStudent" :open="isModalOpen" @close="closeStudentModal" />
        </main>

        <!-- Delete Session Confirmation Modal -->
        <div
            v-if="sessionToDelete"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            role="dialog"
            aria-modal="true"
            @click.self="sessionToDelete = null"
        >
            <div class="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-2xl animate-in fade-in zoom-in-95">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-rose-100 dark:bg-rose-950/60">
                        <Trash2 class="size-5 text-rose-600 dark:text-rose-400" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-foreground">Delete Roll Call</h3>
                        <p class="text-xs text-muted-foreground">{{ readableDate(sessionToDelete.session_date) }} · {{ sessionToDelete.starts_at }} – {{ sessionToDelete.ends_at }}</p>
                    </div>
                </div>

                <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                    Are you sure you want to delete the attendance roll call for this day? All student attendance records and logs for this session will be permanently removed.
                </p>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-border bg-background px-4 py-2 text-sm font-semibold text-foreground transition-colors hover:bg-secondary"
                        :disabled="isDeletingSession"
                        @click="sessionToDelete = null"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rose-700 disabled:opacity-50"
                        :disabled="isDeletingSession"
                        @click="deleteSession"
                    >
                        <LoaderCircle v-if="isDeletingSession" class="size-4 animate-spin" />
                        <span>{{ isDeletingSession ? 'Deleting...' : 'Yes, Delete Roll Call' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
