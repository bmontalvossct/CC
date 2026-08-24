<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link, router } from '@inertiajs/vue3';
import { ArrowRight, Calendar, ChevronLeft, ChevronRight, Clock, LoaderCircle, Trash2, UserCheck, UserX } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Student = {
    id: number;
    student_number: string;
    name: string;
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

const props = defineProps<{
    sectionId: number;
    sessions: Session[];
    students: Student[];
}>();

const studentMap = computed(() => {
    const map = new Map<number, Student>();
    for (const student of props.students) {
        map.set(student.id, student);
    }
    return map;
});

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

// Calendar State: Year and Month
const today = new Date();
const currentYear = ref(today.getFullYear());
const currentMonth = ref(today.getMonth()); // 0-indexed

// Selected date string (YYYY-MM-DD)
const selectedDate = ref<string>(today.toLocaleDateString('en-CA'));

// Sessions indexed by date string (YYYY-MM-DD)
const sessionsByDate = computed(() => {
    const map = new Map<string, Session[]>();
    for (const session of props.sessions) {
        const dateKey = session.session_date;
        if (!map.has(dateKey)) {
            map.set(dateKey, []);
        }
        map.get(dateKey)!.push(session);
    }
    return map;
});

// Month Title (e.g. August 2026)
const monthTitle = computed(() => {
    const date = new Date(currentYear.value, currentMonth.value, 1);
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

// Navigation handlers
const prevMonth = () => {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value -= 1;
    } else {
        currentMonth.value -= 1;
    }
};

const nextMonth = () => {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value += 1;
    } else {
        currentMonth.value += 1;
    }
};

const goToToday = () => {
    currentYear.value = today.getFullYear();
    currentMonth.value = today.getMonth();
    selectedDate.value = today.toLocaleDateString('en-CA');
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

// Generate calendar grid days
const calendarDays = computed(() => {
    const year = currentYear.value;
    const month = currentMonth.value;

    const firstDayIndex = new Date(year, month, 1).getDay(); // 0 is Sunday
    // Adjust so week starts on Monday (0: Mon, 1: Tue, ..., 6: Sun)
    const adjustedFirstDay = firstDayIndex === 0 ? 6 : firstDayIndex - 1;

    const totalDays = new Date(year, month + 1, 0).getDate();
    const prevMonthTotalDays = new Date(year, month, 0).getDate();

    const days = [];

    // Previous month padding days
    for (let i = adjustedFirstDay - 1; i >= 0; i--) {
        const dayNumber = prevMonthTotalDays - i;
        const prevMonthDate = new Date(year, month - 1, dayNumber);
        const dateString = prevMonthDate.toLocaleDateString('en-CA');
        days.push({
            dayNumber,
            dateString,
            isCurrentMonth: false,
            isToday: false,
            sessions: sessionsByDate.value.get(dateString) || [],
        });
    }

    // Current month days
    for (let dayNumber = 1; dayNumber <= totalDays; dayNumber++) {
        const currentDate = new Date(year, month, dayNumber);
        const dateString = currentDate.toLocaleDateString('en-CA');
        const isToday = dateString === today.toLocaleDateString('en-CA');
        days.push({
            dayNumber,
            dateString,
            isCurrentMonth: true,
            isToday,
            sessions: sessionsByDate.value.get(dateString) || [],
        });
    }

    // Next month padding days to make full rows of 7
    const remaining = (7 - (days.length % 7)) % 7;
    for (let dayNumber = 1; dayNumber <= remaining; dayNumber++) {
        const nextMonthDate = new Date(year, month + 1, dayNumber);
        const dateString = nextMonthDate.toLocaleDateString('en-CA');
        days.push({
            dayNumber,
            dateString,
            isCurrentMonth: false,
            isToday: false,
            sessions: sessionsByDate.value.get(dateString) || [],
        });
    }

    return days;
});

// Selected Date sessions and student breakdown
const selectedDateSessions = computed(() => {
    return sessionsByDate.value.get(selectedDate.value) || [];
});

const selectedDateFormatted = computed(() => {
    if (!selectedDate.value) return '';
    const parts = selectedDate.value.split('-');
    if (parts.length !== 3) return selectedDate.value;
    const date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    return date.toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
});

const activeSelectedSession = ref<Session | null>(null);

// Get currently viewed session for selected date (defaults to first session on that date)
const currentSessionForDate = computed<Session | null>(() => {
    const sessions = selectedDateSessions.value;
    if (!sessions.length) return null;
    if (activeSelectedSession.value && sessions.some((s) => s.id === activeSelectedSession.value?.id)) {
        return activeSelectedSession.value;
    }
    return sessions[0];
});

// Breakdown of students for current session
const sessionStudentsBreakdown = computed(() => {
    const session = currentSessionForDate.value;
    if (!session) {
        return { present: [], late: [], absent: [] };
    }

    const present: Student[] = [];
    const late: Student[] = [];
    const absent: Student[] = [];

    for (const record of session.records) {
        const student = studentMap.value.get(record.student_id);
        if (!student) continue;

        if (record.status === 'present') {
            present.push(student);
        } else if (record.status === 'late') {
            late.push(student);
        } else if (record.status === 'absent') {
            absent.push(student);
        }
    }

    return { present, late, absent };
});

const selectDay = (dateStr: string) => {
    selectedDate.value = dateStr;
    const sessions = sessionsByDate.value.get(dateStr);
    if (sessions && sessions.length > 0) {
        activeSelectedSession.value = sessions[0];
    } else {
        activeSelectedSession.value = null;
    }
};

const weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
</script>

<template>
    <div class="grid items-start gap-6 lg:grid-cols-12">
        <!-- Calendar Grid Card -->
        <section class="paper-card p-6 shadow-sm lg:col-span-7">
            <!-- Calendar Navigation Header -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/80 pb-4">
                <div class="flex items-center gap-2.5">
                    <span class="grid size-10 place-items-center rounded-xl bg-primary text-white">
                        <Calendar class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-medium text-foreground">{{ monthTitle }}</h2>
                        <p class="text-sm text-muted-foreground">Select any date to review student attendance breakdown</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" class="h-8 rounded-lg px-3 text-xs font-medium" @click="goToToday">
                        Today
                    </Button>
                    <div class="flex items-center gap-1">
                        <Button type="button" variant="outline" size="icon" class="size-8 rounded-lg" title="Previous month" @click="prevMonth">
                            <ChevronLeft class="size-4" />
                        </Button>
                        <Button type="button" variant="outline" size="icon" class="size-8 rounded-lg" title="Next month" @click="nextMonth">
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Day of Week Headers -->
            <div class="mt-4 grid grid-cols-7 gap-1 text-center">
                <div v-for="day in weekDays" :key="day" class="py-1 text-sm font-medium uppercase tracking-wider text-muted-foreground">
                    {{ day }}
                </div>
            </div>

            <!-- Calendar Days Grid -->
            <div class="mt-1 grid grid-cols-7 gap-1">
                <button
                    v-for="cell in calendarDays"
                    :key="cell.dateString"
                    type="button"
                    class="group relative flex min-h-[5rem] flex-col justify-between rounded-xl border p-1.5 text-left transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    :class="[
                        selectedDate === cell.dateString
                            ? 'border-primary bg-primary/5 ring-2 ring-primary/40'
                            : cell.isCurrentMonth
                              ? 'border-border/70 bg-card hover:bg-secondary/40'
                              : 'border-transparent bg-muted/20 opacity-40 hover:opacity-70',
                    ]"
                    @click="selectDay(cell.dateString)"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="flex size-7 items-center justify-center rounded-full text-xs font-medium leading-none"
                            :class="[
                                cell.isToday
                                    ? 'bg-primary text-white'
                                    : selectedDate === cell.dateString
                                      ? 'border border-primary text-primary'
                                      : 'text-foreground',
                            ]"
                        >
                            {{ cell.dayNumber }}
                        </span>
                        <span v-if="cell.sessions.length > 0" class="size-2 rounded-full bg-primary" />
                    </div>

                    <!-- Session preview pill indicators inside day cell -->
                    <div v-if="cell.sessions.length > 0" class="mt-1 space-y-0.5">
                        <div
                            v-for="s in cell.sessions.slice(0, 1)"
                            :key="s.id"
                            class="flex items-center justify-between rounded px-1.5 py-0.5 text-[11px] font-medium"
                            :class="s.absent_count > 0 ? 'bg-primary text-white' : 'bg-emerald-800 text-white'"
                        >
                            <span class="truncate">{{ s.present_count }} pres</span>
                            <span v-if="s.absent_count > 0" class="rounded bg-rose-700 px-1 text-[10px] text-white"> {{ s.absent_count }} abs </span>
                        </div>
                        <div v-if="cell.sessions.length > 1" class="text-right text-[10px] font-medium text-muted-foreground">
                            +{{ cell.sessions.length - 1 }} more
                        </div>
                    </div>
                </button>
            </div>
        </section>

        <!-- Selected Date Attendance Details Panel -->
        <section class="paper-card p-6 shadow-sm lg:col-span-5">
            <div class="border-b border-border/80 pb-4">
                <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Date Details</span>
                <h3 class="mt-0.5 text-xl font-medium text-foreground">{{ selectedDateFormatted }}</h3>
            </div>

            <!-- If sessions exist on selected date -->
            <div v-if="selectedDateSessions.length > 0" class="mt-4 space-y-5">
                <!-- Session Selector if multiple sessions on same day -->
                <div v-if="selectedDateSessions.length > 1" class="flex flex-wrap items-center gap-2">
                    <span class="text-sm text-muted-foreground">Session:</span>
                    <button
                        v-for="(sess, idx) in selectedDateSessions"
                        :key="sess.id"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            currentSessionForDate?.id === sess.id
                                ? 'shadow-xs bg-primary text-white'
                                : 'border border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card'
                        "
                        @click="activeSelectedSession = sess"
                    >
                        {{ sess.starts_at }} – {{ sess.ends_at }} (Session {{ idx + 1 }})
                    </button>
                </div>

                <!-- Session summary card -->
                <div v-if="currentSessionForDate" class="rounded-xl border border-border/80 bg-secondary/30 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2 text-sm text-muted-foreground">
                            <Clock class="size-4" />
                            <span
                                >{{ currentSessionForDate.starts_at }} – {{ currentSessionForDate.ends_at }} ({{
                                    currentSessionForDate.duration_minutes
                                }}
                                mins)</span
                            >
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                title="Delete roll call on this day"
                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-100 hover:text-rose-800 dark:text-rose-400 dark:hover:bg-rose-950/60"
                                @click="confirmDeleteSession(currentSessionForDate)"
                            >
                                <Trash2 class="size-3.5" />
                                <span>Delete</span>
                            </button>
                            <Link
                                :href="`/attendance/${currentSessionForDate.id}`"
                                prefetch="hover"
                                class="inline-flex items-center gap-1 text-sm font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                            >
                                <span>Open live check</span>
                                <ArrowRight class="size-3.5" />
                            </Link>
                        </div>
                    </div>
                    <p v-if="currentSessionForDate.notes" class="mt-2 text-sm italic text-muted-foreground">
                        Note: {{ currentSessionForDate.notes }}
                    </p>

                    <!-- KPI summary badges -->
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-700 px-3 py-1 text-xs font-medium text-white shadow-sm">
                            <UserCheck class="size-3.5" /> {{ sessionStudentsBreakdown.present.length }} Present
                        </span>
                        <span
                            v-if="sessionStudentsBreakdown.late.length > 0"
                            class="inline-flex items-center gap-1.5 rounded-full bg-amber-700 px-3 py-1 text-xs font-medium text-white shadow-sm"
                        >
                            <Clock class="size-3.5" /> {{ sessionStudentsBreakdown.late.length }} Late
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-700 px-3 py-1 text-xs font-medium text-white shadow-sm">
                            <UserX class="size-3.5" /> {{ sessionStudentsBreakdown.absent.length }} Absent
                        </span>
                    </div>
                </div>

                <!-- Absent Students List -->
                <div>
                    <div class="flex items-center justify-between border-b border-border/70 pb-2">
                        <span class="flex items-center gap-1.5 text-sm font-medium text-rose-700 dark:text-rose-400">
                            <UserX class="size-4" /> Absent Students ({{ sessionStudentsBreakdown.absent.length }})
                        </span>
                    </div>
                    <ul v-if="sessionStudentsBreakdown.absent.length > 0" class="mt-2 max-h-48 divide-y divide-border/60 overflow-y-auto pr-1">
                        <li
                            v-for="student in sessionStudentsBreakdown.absent"
                            :key="student.id"
                            class="flex items-center justify-between py-2 text-sm"
                        >
                            <span class="truncate font-medium text-foreground">{{ formatStudentDisplayName(student) }}</span>
                            <span class="rounded bg-rose-700 px-2 py-0.5 font-mono text-xs text-white">
                                {{ student.student_number }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-muted-foreground">None. Perfect attendance for this session.</p>
                </div>

                <!-- Late Students List (if any) -->
                <div v-if="sessionStudentsBreakdown.late.length > 0">
                    <div class="flex items-center justify-between border-b border-border/70 pb-2">
                        <span class="flex items-center gap-1.5 text-sm font-medium text-amber-700 dark:text-amber-400">
                            <Clock class="size-4" /> Late Students ({{ sessionStudentsBreakdown.late.length }})
                        </span>
                    </div>
                    <ul class="mt-2 max-h-40 divide-y divide-border/60 overflow-y-auto pr-1">
                        <li v-for="student in sessionStudentsBreakdown.late" :key="student.id" class="flex items-center justify-between py-2 text-sm">
                            <span class="truncate font-medium text-foreground">{{ formatStudentDisplayName(student) }}</span>
                            <span class="rounded bg-amber-700 px-2 py-0.5 font-mono text-xs text-white">
                                {{ student.student_number }}
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- Present Students List -->
                <div>
                    <div class="flex items-center justify-between border-b border-border/70 pb-2">
                        <span class="flex items-center gap-1.5 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                            <UserCheck class="size-4" /> Present Students ({{ sessionStudentsBreakdown.present.length }})
                        </span>
                    </div>
                    <ul v-if="sessionStudentsBreakdown.present.length > 0" class="mt-2 max-h-48 divide-y divide-border/60 overflow-y-auto pr-1">
                        <li
                            v-for="student in sessionStudentsBreakdown.present"
                            :key="student.id"
                            class="flex items-center justify-between py-2 text-sm"
                        >
                            <span class="truncate font-medium text-foreground">{{ formatStudentDisplayName(student) }}</span>
                            <span class="rounded bg-emerald-700 px-2 py-0.5 font-mono text-xs text-white">
                                {{ student.student_number }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-muted-foreground">No students marked present for this session.</p>
                </div>
            </div>

            <!-- If no session on selected date -->
            <div v-else class="py-12 text-center text-sm text-muted-foreground">
                <p>No roll call session recorded on this date.</p>
                <p class="mt-1">Pick a highlighted day from the calendar or start a new roll call on the left.</p>
            </div>
        </section>

        <!-- Delete Session Confirmation Modal -->
        <div
            v-if="sessionToDelete"
            class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
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
                        <p class="text-xs text-muted-foreground">
                            {{ sessionToDelete.session_date }} · {{ sessionToDelete.starts_at }} – {{ sessionToDelete.ends_at }}
                        </p>
                    </div>
                </div>

                <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                    Are you sure you want to delete the attendance roll call for this day? All student attendance records and logs for this session
                    will be permanently removed.
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
    </div>
</template>
