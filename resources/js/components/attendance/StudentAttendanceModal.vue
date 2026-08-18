<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, Clock, UserX, X } from 'lucide-vue-next';

type DayRecord = {
    session_id: number;
    date: string;
    time: string;
    notes?: string | null;
    duration_minutes: number;
};

type Summary = {
    sessions: number;
    present: number;
    absent: number;
    rate: number | null;
    attended_hours: number;
};

export type StudentSummary = {
    id: number;
    student_number: string;
    name: string;
    week: Summary;
    month: Summary;
    term: Summary;
    overall: Summary;
    absent_days: DayRecord[];
    late_days: DayRecord[];
    absent_count: number;
    late_count: number;
    present_count: number;
    total_sessions?: number;
    earned_points?: number;
    possible_points?: number;
    grade_rate?: number | null;
    absences_allowed?: number;
    absences_remaining?: number;
    absence_status?: 'good' | 'warning' | 'limit_reached' | 'exceeded';
};

defineProps<{
    student: StudentSummary | null;
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const readableDate = (date: string) => {
    if (!date) return '';
    return new Intl.DateTimeFormat('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
};
</script>

<template>
    <div
        v-if="open && student"
        class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 backdrop-blur-sm duration-200 animate-in fade-in"
        @click.self="emit('close')"
    >
        <div
            class="paper-card relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden border border-border/90 p-6 shadow-2xl duration-200 animate-in zoom-in-95"
            role="dialog"
            aria-modal="true"
            :aria-label="`Attendance summary for ${student.name}`"
        >
            <!-- Modal Header -->
            <div class="flex items-start justify-between border-b border-border/80 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-primary px-2.5 py-1 font-mono text-sm text-white">
                            {{ student.student_number }}
                        </span>
                        <span class="text-sm text-muted-foreground">Student Attendance File</span>
                    </div>
                    <h2 class="mt-1 text-2xl font-semibold text-foreground">{{ student.name }}</h2>
                </div>

                <Button type="button" variant="outline" size="icon" class="size-8 rounded-lg" title="Close" @click="emit('close')">
                    <X class="size-4" />
                </Button>
            </div>

            <!-- Scrollable Modal Content -->
            <div class="flex-1 space-y-6 overflow-y-auto py-4 pr-1">
                <!-- Attendance Grading & Allowance Banner -->
                <div
                    class="rounded-xl border p-4"
                    :class="
                        student.absence_status === 'exceeded'
                            ? 'border-rose-500/40 bg-rose-500/10'
                            : student.absence_status === 'limit_reached'
                              ? 'border-amber-500/40 bg-amber-500/10'
                              : student.absence_status === 'warning'
                                ? 'border-amber-500/30 bg-amber-500/5'
                                : 'border-border/80 bg-secondary/30'
                    "
                >
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Absence Allowance (3 Max Allowed)
                            </span>
                            <p
                                class="mt-0.5 text-base font-semibold"
                                :class="
                                    student.absence_status === 'exceeded'
                                        ? 'text-rose-700 dark:text-rose-400'
                                        : student.absence_status === 'limit_reached' || student.absence_status === 'warning'
                                          ? 'text-amber-700 dark:text-amber-400'
                                          : 'text-foreground'
                                "
                            >
                                <template v-if="student.absence_status === 'exceeded'">
                                    {{ student.absent_count }}/3 absences used — Exceeded 3 absences allowance!
                                </template>
                                <template v-else-if="student.absence_status === 'limit_reached'">
                                    3/3 absences used — Maximum absence limit reached (0 remaining)
                                </template>
                                <template v-else-if="student.absence_status === 'warning'">
                                    2/3 absences used — Warning: 1 absence remaining
                                </template>
                                <template v-else>
                                    {{ student.absent_count }}/3 absences used ({{ student.absences_remaining ?? 3 - student.absent_count }}
                                    remaining)
                                </template>
                            </p>
                        </div>
                        <div class="text-left sm:text-right">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Grading Policy</span>
                            <p class="text-xs text-muted-foreground">Present: 1.0 pt · Late: 0.5 pt · Absent: 0 pt</p>
                        </div>
                    </div>
                </div>

                <!-- Summary KPI Highlights -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-border/80 bg-secondary/30 p-3.5 text-center">
                        <span class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground">Grade Score</span>
                        <p class="mt-1 font-mono text-3xl font-semibold text-foreground">{{ student.grade_rate ?? student.overall.rate ?? '—' }}%</p>
                        <span class="text-xs text-muted-foreground">
                            {{ student.earned_points ?? student.present_count }}/{{ student.possible_points ?? student.overall.sessions }} pts
                        </span>
                    </div>

                    <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3.5 text-center">
                        <span class="block text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400"
                            >Present (1 pt)</span
                        >
                        <p class="mt-1 font-mono text-3xl font-semibold text-emerald-700 dark:text-emerald-400">
                            {{ student.present_count }}
                        </p>
                        <span class="text-xs text-muted-foreground">sessions</span>
                    </div>

                    <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-3.5 text-center">
                        <span class="block text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">Late (0.5 pt)</span>
                        <p class="mt-1 font-mono text-3xl font-semibold text-amber-700 dark:text-amber-400">
                            {{ student.late_count }}
                        </p>
                        <span class="text-xs text-muted-foreground">sessions</span>
                    </div>

                    <div class="rounded-xl border border-rose-500/20 bg-rose-500/5 p-3.5 text-center">
                        <span class="block text-xs font-semibold uppercase tracking-wider text-rose-700 dark:text-rose-400">Absent (0 pt)</span>
                        <p class="mt-1 font-mono text-3xl font-semibold text-rose-700 dark:text-rose-400">
                            {{ student.absent_count }}
                        </p>
                        <span class="text-xs text-muted-foreground">/ 3 max allowed</span>
                    </div>
                </div>

                <!-- Exact Absent Days Breakdown -->
                <section class="space-y-3">
                    <div class="flex items-center justify-between border-b border-border/80 pb-2">
                        <div class="flex items-center gap-2">
                            <UserX class="size-4.5 text-rose-700 dark:text-rose-400" />
                            <h3 class="text-base font-semibold text-foreground">Absent Days</h3>
                        </div>
                        <span
                            class="font-mono text-sm font-semibold"
                            :class="student.absent_days.length > 0 ? 'text-rose-700 dark:text-rose-400' : 'text-muted-foreground'"
                        >
                            {{ student.absent_days.length }} days
                        </span>
                    </div>

                    <div v-if="student.absent_days.length > 0" class="space-y-2">
                        <div
                            v-for="day in student.absent_days"
                            :key="day.session_id"
                            class="flex items-center justify-between rounded-xl border border-rose-500/20 bg-rose-500/5 p-3.5"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-base font-medium text-foreground">{{ readableDate(day.date) }}</span>
                                    <span class="text-sm font-semibold text-rose-700 dark:text-rose-400">Absent</span>
                                </div>
                                <p class="mt-0.5 text-sm text-muted-foreground">{{ day.time }} ({{ day.duration_minutes }} mins)</p>
                                <p v-if="day.notes" class="mt-1 text-sm italic text-muted-foreground">Note: {{ day.notes }}</p>
                            </div>

                            <Link
                                :href="`/attendance/${day.session_id}`"
                                prefetch="hover"
                                class="shadow-xs inline-flex items-center gap-1 rounded-lg border border-primary bg-white px-3 py-1.5 text-sm font-medium text-primary transition-colors hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            >
                                <span>Session</span>
                                <ArrowRight class="size-3.5" />
                            </Link>
                        </div>
                    </div>
                    <div v-else class="rounded-xl border border-border/70 bg-secondary/20 p-4 text-center text-sm text-muted-foreground">
                        <CheckCircle2 class="mx-auto mb-1 size-5 text-emerald-600 dark:text-emerald-400" />
                        No absences on record.
                    </div>
                </section>

                <!-- Exact Late Days Breakdown -->
                <section class="space-y-3">
                    <div class="flex items-center justify-between border-b border-border/80 pb-2">
                        <div class="flex items-center gap-2">
                            <Clock class="size-4.5 text-amber-700 dark:text-amber-400" />
                            <h3 class="text-base font-medium text-foreground">Late Days</h3>
                        </div>
                        <span
                            class="font-mono text-sm font-medium"
                            :class="student.late_days.length > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-muted-foreground'"
                        >
                            {{ student.late_days.length }} days
                        </span>
                    </div>

                    <div v-if="student.late_days.length > 0" class="space-y-2">
                        <div
                            v-for="day in student.late_days"
                            :key="day.session_id"
                            class="flex items-center justify-between rounded-xl border border-amber-500/20 bg-amber-500/5 p-3.5"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-base font-medium text-foreground">{{ readableDate(day.date) }}</span>
                                    <span class="text-sm font-medium text-amber-700 dark:text-amber-400">Late</span>
                                </div>
                                <p class="mt-0.5 text-sm text-muted-foreground">{{ day.time }} ({{ day.duration_minutes }} mins)</p>
                                <p v-if="day.notes" class="mt-1 text-sm italic text-muted-foreground">Note: {{ day.notes }}</p>
                            </div>

                            <Link
                                :href="`/attendance/${day.session_id}`"
                                prefetch="hover"
                                class="shadow-xs inline-flex items-center gap-1 rounded-lg border border-primary bg-white px-3 py-1.5 text-sm font-medium text-primary transition-colors hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            >
                                <span>Session</span>
                                <ArrowRight class="size-3.5" />
                            </Link>
                        </div>
                    </div>
                    <div v-else class="rounded-xl border border-border/70 bg-secondary/20 p-4 text-center text-sm text-muted-foreground">
                        <CheckCircle2 class="mx-auto mb-1 size-5 text-emerald-600 dark:text-emerald-400" />
                        No late arrivals recorded.
                    </div>
                </section>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end border-t border-border/80 pt-3">
                <Button type="button" variant="secondary" class="h-9 px-4 text-sm font-semibold" @click="emit('close')"> Close File </Button>
            </div>
        </div>
    </div>
</template>
