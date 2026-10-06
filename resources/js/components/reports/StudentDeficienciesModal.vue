<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { AlertCircle, AlertTriangle, CheckCircle2, Copy, FileWarning, FolderKanban, ListOrdered, Printer, Sparkles, UserX, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Assessment = {
    id: number;
    type: 'activity' | 'laboratory' | 'quiz' | 'exam' | string;
    title: string;
    conducted_on: string | null;
    max_points: string | number;
};
type ProjectItem = {
    id: number;
    type: 'project' | 'reporting' | 'group_activity';
    title: string;
    conducted_on: string | null;
    max_points: string | number;
};
type Category = { raw_earned?: number; bonus_earned?: number; earned: number; possible: number; percentage: number | null; missing: number };
type ProjectSummary = { count: number; earned: number; possible: number; percentage: number | null; missing: number };
type AttendanceSummary = {
    total_sessions: number;
    present_count: number;
    late_count: number;
    absent_count: number;
    earned_points: number;
    possible_points: number;
    percentage: number | null;
};
type Recitation = { count: number; avg_score: number | null; percentage: number | null; bonus_points?: number };

export type StudentRow = {
    id: number;
    student_number: string;
    full_name: string;
    scores: Record<number, string | null>;
    remarks?: Record<number, string | null>;
    categories: Record<'activity' | 'quiz' | 'exam', Category>;
    group_activity_scores?: Record<number, number | null>;
    project_scores: Record<number, number | null>;
    project_notes?: Record<number, string | null>;
    projectSummary: ProjectSummary;
    attendance: AttendanceSummary;
    recitation: Recitation;
};

export type ActivityLogEntry = {
    id: number;
    key: string;
    title: string;
    type: 'activity' | 'quiz' | 'laboratory' | 'exam' | 'project' | 'reporting' | 'group_activity';
    typeLabel: string;
    conducted_on: string | null;
    score: number | null;
    max_points: number;
    pct: number | null;
    status: 'passed' | 'failed' | 'missing';
    remarks?: string | null;
    isProject?: boolean;
};

const props = withDefaults(
    defineProps<{
        student: StudentRow | null;
        assessments: Assessment[];
        groupActivities?: ProjectItem[];
        projects: ProjectItem[];
        gradingWeights: Record<string, number>;
        sectionName?: string;
        subjectCode?: string;
        open: boolean;
        initialTab?: 'activity_log' | 'deficiencies' | 'missing' | 'failing';
    }>(),
    {
        groupActivities: () => [],
        initialTab: 'activity_log',
    },
);

const emit = defineEmits<{
    (e: 'close'): void;
}>();

type ActiveTab = 'activity_log' | 'deficiencies' | 'missing' | 'failing';
const currentTab = ref<ActiveTab>(props.initialTab || 'activity_log');
const logCategoryFilter = ref<'all' | 'activity' | 'quiz' | 'laboratory' | 'exam' | 'project' | 'deficiencies'>('all');
const copied = ref(false);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            currentTab.value = props.initialTab || 'activity_log';
            logCategoryFilter.value = 'all';
        }
    },
);

const readableDate = (date: string | null) => {
    if (!date) return 'No date set';
    try {
        return new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(`${date}T00:00:00`));
    } catch {
        return date;
    }
};

const percentToGrade = (pct: number | null): string => {
    if (pct === null) return '—';
    const val = Math.round(pct * 100) / 100;
    if (val >= 97.0) return '1.00';
    if (val >= 94.0) return '1.25';
    if (val >= 91.0) return '1.50';
    if (val >= 88.0) return '1.75';
    if (val >= 85.0) return '2.00';
    if (val >= 82.0) return '2.25';
    if (val >= 79.0) return '2.50';
    if (val >= 76.0) return '2.75';
    if (val >= 75.0) return '3.00';
    return '5.00';
};

const isFailingGrade = (grade: string) => {
    if (grade === '—') return false;
    const n = parseFloat(grade);
    return n > 3.0;
};

// Compute base coursework weighted percentage
const overallPercentage = computed<number | null>(() => {
    if (!props.student) return null;
    if (props.student.weighted_grade !== null && props.student.weighted_grade !== undefined) {
        return props.student.weighted_grade;
    }
    const w = props.gradingWeights;
    let totalWeight = 0;
    let weighted = 0;

    if (props.student.categories.activity?.percentage !== null && (w.activity ?? 0) > 0) {
        weighted += props.student.categories.activity.percentage * (w.activity / 100);
        totalWeight += w.activity;
    }
    if (props.student.categories.quiz?.percentage !== null && (w.quiz ?? 0) > 0) {
        weighted += props.student.categories.quiz.percentage * (w.quiz / 100);
        totalWeight += w.quiz;
    }
    if (props.student.categories.exam?.percentage !== null && (w.exam ?? 0) > 0) {
        weighted += props.student.categories.exam.percentage * (w.exam / 100);
        totalWeight += w.exam;
    }
    if (props.student.projectSummary?.percentage !== null && (w.project ?? 0) > 0) {
        weighted += props.student.projectSummary.percentage * (w.project / 100);
        totalWeight += w.project;
    }
    if (props.student.attendance?.percentage !== null && (w.attendance ?? 0) > 0) {
        weighted += props.student.attendance.percentage * (w.attendance / 100);
        totalWeight += w.attendance;
    }

    if (totalWeight === 0) return null;
    return Math.min(100, Math.round(weighted * 100) / 100);
});

const overallGrade = computed(() => {
    if (props.student?.scale_grade && props.student.scale_grade !== '—') {
        return props.student.scale_grade;
    }
    return percentToGrade(overallPercentage.value);
});

// Uncomplied / Missing Assessments (score is null)
const uncompliedAssessments = computed(() => {
    if (!props.student) return [];
    return props.assessments.filter((a) => {
        const val = props.student?.scores[a.id];
        return val === null || val === undefined || val === '';
    });
});

const passingRates = computed(() => ({
    quiz: Number(props.gradingWeights?.passing_rates?.quiz ?? 75),
    activity: Number(props.gradingWeights?.passing_rates?.activity ?? 75),
    project: Number(props.gradingWeights?.passing_rates?.project ?? 75),
    exam: Number(props.gradingWeights?.passing_rates?.exam ?? 75),
}));

const getPassingRateForAssessment = (type: string): number => {
    return (passingRates.value as Record<string, number>)[type] ?? 75;
};

const getPassingRateForProject = (type: string): number => {
    if (type === 'group_activity') {
        return passingRates.value.activity ?? 75;
    }
    return passingRates.value.project ?? 75;
};

// Failing Assessments (score recorded but below category threshold)
const failingAssessments = computed(() => {
    if (!props.student) return [];
    return props.assessments
        .filter((a) => {
            const val = props.student?.scores[a.id];
            if (val === null || val === undefined || val === '') return false;
            const score = parseFloat(String(val));
            const max = parseFloat(String(a.max_points));
            const threshold = getPassingRateForAssessment(a.type) / 100;
            return max > 0 && score / max < threshold;
        })
        .map((a) => {
            const score = parseFloat(String(props.student!.scores[a.id]));
            const max = parseFloat(String(a.max_points));
            const pct = Math.round((score / max) * 1000) / 10;
            return {
                ...a,
                score,
                max,
                pct,
            };
        });
});

const allProjectItems = computed(() => [...props.groupActivities, ...props.projects]);

const getStudentProjectScore = (item: ProjectItem): number | null | undefined => {
    if (!props.student) return undefined;
    if (item.type === 'group_activity') {
        return props.student.group_activity_scores?.[item.id] !== undefined
            ? props.student.group_activity_scores[item.id]
            : props.student.project_scores?.[item.id];
    }
    return props.student.project_scores?.[item.id];
};

// Uncomplied Projects & Group Activities
const uncompliedProjects = computed(() => {
    if (!props.student) return [];
    return allProjectItems.value.filter((p) => {
        const val = getStudentProjectScore(p);
        return val === null || val === undefined;
    });
});

// Failing Projects & Group Activities
const failingProjects = computed(() => {
    if (!props.student) return [];
    return allProjectItems.value
        .filter((p) => {
            const val = getStudentProjectScore(p);
            if (val === null || val === undefined) return false;
            const score = Number(val);
            const max = typeof p.max_points === 'number' ? p.max_points : parseFloat(String(p.max_points || 100));
            const threshold = getPassingRateForProject(p.type) / 100;
            return max > 0 && score / max < threshold;
        })
        .map((p) => {
            const score = Number(getStudentProjectScore(p));
            const max = typeof p.max_points === 'number' ? p.max_points : parseFloat(String(p.max_points || 100));
            const pct = Math.round((score / max) * 1000) / 10;
            return {
                ...p,
                score,
                max,
                pct,
            };
        });
});

const totalMissingCount = computed(() => uncompliedAssessments.value.length + uncompliedProjects.value.length);
const totalFailingCount = computed(() => failingAssessments.value.length + failingProjects.value.length);
const totalDeficiencies = computed(() => totalMissingCount.value + totalFailingCount.value);

const allActivityLogs = computed<ActivityLogEntry[]>(() => {
    if (!props.student) return [];
    const list: ActivityLogEntry[] = [];

    for (const a of props.assessments) {
        const val = props.student.scores[a.id];
        const max = parseFloat(String(a.max_points)) || 0;
        let score: number | null = null;
        let pct: number | null = null;
        let status: 'passed' | 'failed' | 'missing' = 'missing';

        if (val !== null && val !== undefined && val !== '') {
            score = parseFloat(String(val));
            pct = max > 0 ? Math.round((score / max) * 1000) / 10 : 0;
            status = pct >= getPassingRateForAssessment(a.type) ? 'passed' : 'failed';
        }

        list.push({
            id: a.id,
            key: `assessment-${a.id}`,
            title: a.title,
            type: a.type as any,
            typeLabel: a.type === 'laboratory' ? 'Laboratory' : a.type.charAt(0).toUpperCase() + a.type.slice(1),
            conducted_on: a.conducted_on,
            score,
            max_points: max,
            pct,
            status,
            remarks: props.student.remarks?.[a.id] || null,
            isProject: false,
        });
    }

    for (const p of allProjectItems.value) {
        const val = getStudentProjectScore(p);
        const max = typeof p.max_points === 'number' ? p.max_points : parseFloat(String(p.max_points || 100));
        let score: number | null = null;
        let pct: number | null = null;
        let status: 'passed' | 'failed' | 'missing' = 'missing';

        if (val !== null && val !== undefined) {
            score = Number(val);
            pct = max > 0 ? Math.round((score / max) * 1000) / 10 : 0;
            status = pct >= getPassingRateForProject(p.type) ? 'passed' : 'failed';
        }

        list.push({
            id: p.id,
            key: `project-${p.type}-${p.id}`,
            title: p.title,
            type: p.type as any,
            typeLabel: p.type === 'group_activity' ? 'Group Activity' : p.type === 'reporting' ? 'Reporting' : 'Project',
            conducted_on: p.conducted_on,
            score,
            max_points: max,
            pct,
            status,
            remarks: props.student.project_notes?.[p.id] || null,
            isProject: true,
        });
    }

    return list;
});

const filteredActivityLogs = computed(() => {
    if (logCategoryFilter.value === 'all') return allActivityLogs.value;
    if (logCategoryFilter.value === 'deficiencies') {
        return allActivityLogs.value.filter((item) => item.status !== 'passed');
    }
    if (logCategoryFilter.value === 'project') {
        return allActivityLogs.value.filter((item) => item.type === 'project' || item.type === 'reporting' || item.type === 'group_activity');
    }
    return allActivityLogs.value.filter((item) => item.type === logCategoryFilter.value);
});

const typeBadgeClass = (type: string) => {
    if (type === 'group_activity') return 'bg-emerald-700 text-white';
    if (type === 'project') return 'bg-teal-700 text-white';
    return 'bg-indigo-700 text-white';
};

const typeLabel = (type: string) => {
    if (type === 'group_activity') return 'Group Activity';
    if (type === 'project') return 'Project';
    return 'Reporting';
};

// Copy text summary for student intervention
const copySummary = () => {
    if (!props.student) return;
    const lines: string[] = [];
    lines.push(`Academic Standing Notice`);
    lines.push(`Student: ${props.student.full_name} (${props.student.student_number})`);
    if (props.subjectCode) lines.push(`Course: ${props.subjectCode} - ${props.sectionName || ''}`);
    lines.push(`Current Running Grade: ${overallPercentage.value !== null ? `${overallPercentage.value}%` : 'N/A'} (Grade: ${overallGrade.value})`);
    lines.push(`----------------------------------------`);

    if (totalDeficiencies.value === 0) {
        lines.push(`Status: All activities, quizzes, exams, and projects are complied and passing!`);
    } else {
        if (uncompliedAssessments.value.length > 0 || uncompliedProjects.value.length > 0) {
            lines.push(`\n[UNCOMPLIED / MISSING ACTIVITIES & PROJECTS]`);
            for (const a of uncompliedAssessments.value) {
                lines.push(`- [${a.type.toUpperCase()}] ${a.title} (Max: ${a.max_points} pts) · Missing`);
            }
            for (const p of uncompliedProjects.value) {
                lines.push(`- [PROJECT] ${p.title} (Max: ${p.max_points} pts) · Uncomplied`);
            }
        }

        if (failingAssessments.value.length > 0 || failingProjects.value.length > 0) {
            lines.push(`\n[FAILING SCORES (< 75%)]`);
            for (const a of failingAssessments.value) {
                lines.push(`- [${a.type.toUpperCase()}] ${a.title}: ${a.score}/${a.max} (${a.pct}%) · Below 75%`);
            }
            for (const p of failingProjects.value) {
                lines.push(`- [PROJECT] ${p.title}: ${p.score}/${p.max} (${p.pct}%) · Below 75%`);
            }
        }

        if (props.student.attendance?.absent_count > 0) {
            lines.push(`\n[ATTENDANCE]`);
            lines.push(
                `- Absences: ${props.student.attendance.absent_count} | Late: ${props.student.attendance.late_count} (${props.student.attendance.percentage}% Attendance)`,
            );
        }

        lines.push(`\nPlease comply and coordinate with your instructor regarding make-up requirements.`);
    }

    navigator.clipboard.writeText(lines.join('\n'));
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2500);
};

const printSlip = () => {
    window.print();
};
</script>

<template>
    <div
        v-if="open && student"
        v-modal-focus
        class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/75 p-4 backdrop-blur-sm duration-200 animate-in fade-in print:static print:bg-transparent print:p-0"
    >
        <div
            class="paper-card relative flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden border border-border/90 bg-card p-6 shadow-2xl duration-200 animate-in zoom-in-95 print:max-h-none print:border-none print:shadow-none"
            role="dialog"
            aria-modal="true"
            :aria-label="`Academic deficiency file for ${student.full_name}`"
        >
            <!-- Modal Header -->
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-border/80 pb-4 print:border-b-2 print:border-black">
                <div>
                    <div class="flex items-center gap-2">
                        <span
                            class="shadow-xs mx-1 inline-flex shrink-0 items-center whitespace-nowrap rounded bg-primary px-2.5 py-0.5 font-mono text-xs font-semibold text-white"
                        >
                            {{ student.student_number }}
                        </span>
                        <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground"> Academic Deficiency File </span>
                    </div>
                    <h2 class="mt-1 text-2xl font-bold text-foreground print:text-xl">{{ student.full_name }}</h2>
                    <p v-if="subjectCode || sectionName" class="text-xs text-muted-foreground">
                        {{ subjectCode }} <span v-if="sectionName">· {{ sectionName }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-2 print:hidden">
                    <button
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-secondary/50 px-2.5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                        :title="copied ? 'Copied!' : 'Copy deficiency summary for student'"
                        @click="copySummary"
                    >
                        <CheckCircle2 v-if="copied" class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                        <Copy v-else class="size-3.5 text-muted-foreground" />
                        <span>{{ copied ? 'Copied!' : 'Copy report' }}</span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-secondary/50 px-2.5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                        title="Print intervention slip"
                        @click="printSlip"
                    >
                        <Printer class="size-3.5 text-muted-foreground" />
                        <span>Print</span>
                    </button>

                    <Button type="button" variant="ghost" size="icon" class="size-8 rounded-lg" title="Close" @click="emit('close')">
                        <X class="size-4" />
                    </Button>
                </div>
            </div>

            <!-- Grade & Deficiency KPI Summary Banner -->
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <!-- Running Grade % -->
                <div class="rounded-xl border border-border/80 bg-secondary/30 p-3 text-center">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Running Grade</span>
                    <span class="mt-0.5 block font-mono text-xl font-bold text-foreground">
                        {{ overallPercentage !== null ? `${overallPercentage}%` : '—' }}
                    </span>
                </div>

                <!-- Numerical Grade -->
                <div
                    class="shadow-xs rounded-xl border p-3 text-center"
                    :class="isFailingGrade(overallGrade) ? 'border-rose-800 bg-rose-700 text-white' : 'border-emerald-800 bg-emerald-700 text-white'"
                >
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-white/90">Grade (1.0–5.0)</span>
                    <span class="mt-0.5 block font-mono text-xl font-bold text-white">
                        {{ overallGrade }}
                    </span>
                </div>

                <!-- Missing / Uncomplied Count -->
                <div
                    class="rounded-xl border p-3 text-center"
                    :class="
                        totalMissingCount > 0
                            ? 'shadow-xs border-amber-800 bg-amber-700 text-white'
                            : 'border-border/80 bg-secondary/30 text-muted-foreground'
                    "
                >
                    <span
                        class="block text-[10px] font-bold uppercase tracking-wider"
                        :class="totalMissingCount > 0 ? 'text-white/90' : 'text-muted-foreground'"
                        >Uncomplied Items</span
                    >
                    <span class="mt-0.5 block font-mono text-xl font-bold" :class="totalMissingCount > 0 ? 'text-white' : ''">
                        {{ totalMissingCount }}
                    </span>
                </div>

                <!-- Failing Scores Count -->
                <div
                    class="rounded-xl border p-3 text-center"
                    :class="
                        totalFailingCount > 0
                            ? 'shadow-xs border-rose-800 bg-rose-700 text-white'
                            : 'border-border/80 bg-secondary/30 text-muted-foreground'
                    "
                >
                    <span
                        class="block text-[10px] font-bold uppercase tracking-wider"
                        :class="totalFailingCount > 0 ? 'text-white/90' : 'text-muted-foreground'"
                        >Failing Tasks</span
                    >
                    <span class="mt-0.5 block font-mono text-xl font-bold" :class="totalFailingCount > 0 ? 'text-white' : ''">
                        {{ totalFailingCount }}
                    </span>
                </div>
            </div>

            <!-- Navigation Tabs (Hidden on Print) -->
            <div class="mt-4 flex flex-wrap items-center gap-1 border-b border-border/80 pb-2 text-xs print:hidden">
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium transition-colors"
                    :class="
                        currentTab === 'activity_log'
                            ? 'shadow-xs bg-primary text-white'
                            : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                    "
                    @click="currentTab = 'activity_log'"
                >
                    <ListOrdered class="size-3.5" />
                    <span>Activity & Score Log ({{ allActivityLogs.length }})</span>
                </button>

                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium transition-colors"
                    :class="
                        currentTab === 'deficiencies'
                            ? 'shadow-xs bg-rose-700 text-white'
                            : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                    "
                    @click="currentTab = 'deficiencies'"
                >
                    <AlertTriangle class="size-3.5" />
                    <span>All Deficiencies ({{ totalDeficiencies }})</span>
                </button>

                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium transition-colors"
                    :class="
                        currentTab === 'missing'
                            ? 'shadow-xs bg-amber-700 text-white'
                            : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                    "
                    @click="currentTab = 'missing'"
                >
                    <span>Uncomplied ({{ totalMissingCount }})</span>
                </button>

                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium transition-colors"
                    :class="
                        currentTab === 'failing'
                            ? 'shadow-xs bg-rose-700 text-white'
                            : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                    "
                    @click="currentTab = 'failing'"
                >
                    <span>Failing Scores ({{ totalFailingCount }})</span>
                </button>
            </div>

            <!-- Scrollable Content -->
            <div class="mt-4 flex-1 space-y-6 overflow-y-auto pr-1">
                <!-- ALL COMPLIED & PASSING EMPTY STATE -->
                <div
                    v-if="totalDeficiencies === 0 && (currentTab === 'deficiencies' || currentTab === 'missing' || currentTab === 'failing')"
                    class="rounded-2xl border border-emerald-800 bg-emerald-700 p-8 text-center text-white shadow-sm"
                >
                    <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-emerald-800 text-white">
                        <Sparkles class="size-6" />
                    </div>
                    <h3 class="mt-3 text-lg font-bold text-white">No Deficiencies Found!</h3>
                    <p class="mt-1 text-xs text-emerald-100">
                        This student has submitted all assigned coursework, activities, quizzes, exams, and projects with passing marks (75% or
                        above).
                    </p>
                </div>

                <!-- TAB: DEFICIENCIES OR MISSING -->
                <template v-if="currentTab === 'deficiencies' || currentTab === 'missing'">
                    <!-- Uncomplied Assessments Section -->
                    <div v-if="uncompliedAssessments.length > 0" class="space-y-3">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="size-4 text-amber-600 dark:text-amber-400" />
                            <h3 class="text-sm font-bold text-foreground">Uncomplied / Missing Coursework ({{ uncompliedAssessments.length }})</h3>
                        </div>

                        <div class="divide-y divide-border/60 rounded-xl border border-border/80 bg-secondary/20">
                            <div
                                v-for="item in uncompliedAssessments"
                                :key="item.id"
                                class="flex items-center justify-between gap-4 px-4 py-3 text-xs"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="shadow-2xs mx-1 inline-flex shrink-0 items-center whitespace-nowrap rounded px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-white"
                                            :class="
                                                item.type === 'exam'
                                                    ? 'bg-purple-700'
                                                    : item.type === 'quiz'
                                                      ? 'bg-blue-700'
                                                      : item.type === 'laboratory'
                                                        ? 'bg-cyan-700'
                                                        : 'bg-emerald-700'
                                            "
                                        >
                                            {{ item.type === 'laboratory' ? 'Lab' : item.type }}
                                        </span>
                                        <span class="font-semibold text-foreground">{{ item.title }}</span>
                                    </div>
                                    <span class="mt-0.5 block text-[11px] text-muted-foreground">
                                        Conducted: {{ readableDate(item.conducted_on) }}
                                    </span>
                                </div>

                                <div class="text-right">
                                    <span
                                        class="shadow-xs mx-1 inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full border border-amber-800 bg-amber-700 px-2.5 py-1 font-mono text-xs font-bold text-white"
                                    >
                                        <FileWarning class="size-3 shrink-0" />
                                        <span>Missing (0/{{ item.max_points }})</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Uncomplied Projects Section -->
                    <div v-if="uncompliedProjects.length > 0" class="space-y-3">
                        <div class="flex items-center gap-2">
                            <FolderKanban class="size-4 text-teal-600 dark:text-teal-400" />
                            <h3 class="text-sm font-bold text-foreground">Uncomplied Projects & Reporting ({{ uncompliedProjects.length }})</h3>
                        </div>

                        <div class="divide-y divide-border/60 rounded-xl border border-border/80 bg-secondary/20">
                            <div v-for="item in uncompliedProjects" :key="item.id" class="flex items-center justify-between gap-4 px-4 py-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="shadow-2xs mx-1 inline-flex shrink-0 items-center whitespace-nowrap rounded px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-white"
                                            :class="typeBadgeClass(item.type)"
                                        >
                                            {{ typeLabel(item.type) }}
                                        </span>
                                        <span class="font-semibold text-foreground">{{ item.title }}</span>
                                    </div>
                                    <span class="mt-0.5 block text-[11px] text-muted-foreground">
                                        Conducted: {{ readableDate(item.conducted_on) }}
                                    </span>
                                </div>

                                <div class="text-right">
                                    <span
                                        class="shadow-xs mx-1 inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full border border-amber-800 bg-amber-700 px-2.5 py-1 font-mono text-xs font-bold text-white"
                                    >
                                        <FileWarning class="size-3 shrink-0" />
                                        <span>Uncomplied (0/{{ item.max_points }})</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- TAB: DEFICIENCIES OR FAILING -->
                <template v-if="currentTab === 'deficiencies' || currentTab === 'failing'">
                    <!-- Failing Assessments Section -->
                    <div v-if="failingAssessments.length > 0" class="space-y-3">
                        <div class="flex items-center gap-2">
                            <AlertCircle class="size-4 text-rose-600 dark:text-rose-400" />
                            <h3 class="text-sm font-bold text-foreground">Failing Assessment Scores ({{ failingAssessments.length }})</h3>
                        </div>

                        <div class="divide-y divide-border/60 rounded-xl border border-border/80 bg-secondary/20">
                            <div v-for="item in failingAssessments" :key="item.id" class="flex items-center justify-between gap-4 px-4 py-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="shadow-2xs mx-1 inline-flex shrink-0 items-center whitespace-nowrap rounded px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-white"
                                            :class="
                                                item.type === 'exam'
                                                    ? 'bg-purple-700'
                                                    : item.type === 'quiz'
                                                      ? 'bg-blue-700'
                                                      : item.type === 'laboratory'
                                                        ? 'bg-cyan-700'
                                                        : 'bg-emerald-700'
                                            "
                                        >
                                            {{ item.type === 'laboratory' ? 'Lab' : item.type }}
                                        </span>
                                        <span class="font-semibold text-foreground">{{ item.title }}</span>
                                    </div>
                                    <span class="mt-0.5 block text-[11px] text-muted-foreground">
                                        Conducted: {{ readableDate(item.conducted_on) }}
                                    </span>
                                    <p v-if="student?.remarks?.[item.id]" class="mt-1 text-[11px] font-medium text-amber-700 dark:text-amber-300">
                                        Justification: "{{ student.remarks[item.id] }}"
                                    </p>
                                </div>

                                <div class="text-right font-mono">
                                    <span
                                        class="shadow-xs mx-1 inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full border border-rose-800 bg-rose-700 px-2.5 py-1 text-xs font-bold text-white"
                                    >
                                        {{ item.score }}/{{ item.max }} ({{ item.pct }}%)
                                    </span>
                                    <span class="mt-0.5 block text-[10px] text-muted-foreground"
                                        >Passing: {{ getPassingRateForAssessment(item.type) }}%</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Failing Projects Section -->
                    <div v-if="failingProjects.length > 0" class="space-y-3">
                        <div class="flex items-center gap-2">
                            <AlertCircle class="size-4 text-rose-600 dark:text-rose-400" />
                            <h3 class="text-sm font-bold text-foreground">Failing Project Scores ({{ failingProjects.length }})</h3>
                        </div>

                        <div class="divide-y divide-border/60 rounded-xl border border-border/80 bg-secondary/20">
                            <div v-for="item in failingProjects" :key="item.id" class="flex items-center justify-between gap-4 px-4 py-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="shadow-2xs mx-1 inline-flex shrink-0 items-center whitespace-nowrap rounded px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-white"
                                            :class="typeBadgeClass(item.type)"
                                        >
                                            {{ typeLabel(item.type) }}
                                        </span>
                                        <span class="font-semibold text-foreground">{{ item.title }}</span>
                                    </div>
                                    <span class="mt-0.5 block text-[11px] text-muted-foreground">
                                        Conducted: {{ readableDate(item.conducted_on) }}
                                    </span>
                                    <p
                                        v-if="student?.project_notes?.[item.id]"
                                        class="mt-1 text-[11px] font-medium text-amber-700 dark:text-amber-300"
                                    >
                                        Justification: "{{ student.project_notes[item.id] }}"
                                    </p>
                                </div>

                                <div class="text-right font-mono">
                                    <span
                                        class="shadow-xs mx-1 inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full border border-rose-800 bg-rose-700 px-2.5 py-1 text-xs font-bold text-white"
                                    >
                                        {{ item.score }}/{{ item.max }} ({{ item.pct }}%)
                                    </span>
                                    <span class="mt-0.5 block text-[10px] text-muted-foreground"
                                        >Passing: {{ getPassingRateForProject(item.type) }}%</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Attendance Deficiencies Summary -->
                <div v-if="student.attendance && (student.attendance.absent_count > 0 || student.attendance.late_count > 0)" class="space-y-3">
                    <div class="flex items-center gap-2">
                        <UserX class="size-4 text-cyan-600 dark:text-cyan-400" />
                        <h3 class="text-sm font-bold text-foreground">Attendance Standing</h3>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-xl border border-border/80 bg-secondary/20 p-3 text-center">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Total Sessions</span>
                            <span class="mt-0.5 block font-mono text-base font-bold text-foreground">{{ student.attendance.total_sessions }}</span>
                        </div>
                        <div class="shadow-xs rounded-xl border border-emerald-800 bg-emerald-700 p-3 text-center">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-white/90">Present</span>
                            <span class="mt-0.5 block font-mono text-base font-bold text-white">{{ student.attendance.present_count }}</span>
                        </div>
                        <div class="shadow-xs rounded-xl border border-amber-800 bg-amber-700 p-3 text-center">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-white/90">Late</span>
                            <span class="mt-0.5 block font-mono text-base font-bold text-white">{{ student.attendance.late_count }}</span>
                        </div>
                        <div class="shadow-xs rounded-xl border border-rose-800 bg-rose-700 p-3 text-center">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-white/90">Absent</span>
                            <span class="mt-0.5 block font-mono text-base font-bold text-white">{{ student.attendance.absent_count }}</span>
                        </div>
                    </div>
                </div>

                <!-- TAB: ACTIVITY & SCORE LOG -->
                <template v-if="currentTab === 'activity_log'">
                    <div class="space-y-4">
                        <!-- Category Sub-filter Pills -->
                        <div class="flex flex-wrap items-center gap-1.5 border-b border-border/60 pb-3">
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors"
                                :class="
                                    logCategoryFilter === 'all'
                                        ? 'shadow-2xs bg-zinc-800 text-white'
                                        : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground'
                                "
                                @click="logCategoryFilter = 'all'"
                            >
                                All Items ({{ allActivityLogs.length }})
                            </button>
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors"
                                :class="
                                    logCategoryFilter === 'activity'
                                        ? 'shadow-xs bg-emerald-700 text-white'
                                        : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground'
                                "
                                @click="logCategoryFilter = 'activity'"
                            >
                                Activities ({{ allActivityLogs.filter((i) => i.type === 'activity').length }})
                            </button>
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors"
                                :class="
                                    logCategoryFilter === 'quiz'
                                        ? 'shadow-xs bg-blue-700 text-white'
                                        : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground'
                                "
                                @click="logCategoryFilter = 'quiz'"
                            >
                                Quizzes ({{ allActivityLogs.filter((i) => i.type === 'quiz').length }})
                            </button>
                            <button
                                v-if="allActivityLogs.some((i) => i.type === 'laboratory')"
                                type="button"
                                class="inline-flex shrink-0 items-center whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors"
                                :class="
                                    logCategoryFilter === 'laboratory'
                                        ? 'shadow-xs bg-cyan-700 text-white'
                                        : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground'
                                "
                                @click="logCategoryFilter = 'laboratory'"
                            >
                                Laboratories ({{ allActivityLogs.filter((i) => i.type === 'laboratory').length }})
                            </button>
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors"
                                :class="
                                    logCategoryFilter === 'exam'
                                        ? 'shadow-xs bg-purple-700 text-white'
                                        : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground'
                                "
                                @click="logCategoryFilter = 'exam'"
                            >
                                Exams ({{ allActivityLogs.filter((i) => i.type === 'exam').length }})
                            </button>
                            <button
                                v-if="allActivityLogs.some((i) => i.isProject)"
                                type="button"
                                class="inline-flex shrink-0 items-center whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors"
                                :class="
                                    logCategoryFilter === 'project'
                                        ? 'shadow-xs bg-teal-700 text-white'
                                        : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground'
                                "
                                @click="logCategoryFilter = 'project'"
                            >
                                Projects & Reports ({{ allActivityLogs.filter((i) => i.isProject).length }})
                            </button>
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors"
                                :class="
                                    logCategoryFilter === 'deficiencies'
                                        ? 'shadow-xs bg-rose-700 text-white'
                                        : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground'
                                "
                                @click="logCategoryFilter = 'deficiencies'"
                            >
                                Deficiencies Only ({{ allActivityLogs.filter((i) => i.status !== 'passed').length }})
                            </button>
                        </div>

                        <!-- Individual Activity List / Log -->
                        <div
                            v-if="filteredActivityLogs.length > 0"
                            class="divide-y divide-border/60 rounded-xl border border-border/80 bg-secondary/20"
                        >
                            <div
                                v-for="item in filteredActivityLogs"
                                :key="item.key"
                                class="flex flex-col gap-2 p-3.5 text-xs transition-colors hover:bg-secondary/40 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="shadow-2xs mx-1 inline-flex shrink-0 items-center whitespace-nowrap rounded px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-white"
                                            :class="
                                                item.type === 'exam'
                                                    ? 'bg-purple-700'
                                                    : item.type === 'quiz'
                                                      ? 'bg-blue-700'
                                                      : item.type === 'laboratory'
                                                        ? 'bg-cyan-700'
                                                        : item.type === 'group_activity'
                                                          ? 'bg-emerald-700'
                                                          : item.type === 'reporting'
                                                            ? 'bg-indigo-700'
                                                            : item.type === 'project'
                                                              ? 'bg-teal-700'
                                                              : 'bg-emerald-700'
                                            "
                                        >
                                            {{ item.typeLabel }}
                                        </span>
                                        <h4 class="font-semibold text-foreground">{{ item.title }}</h4>
                                    </div>

                                    <div class="mt-1 flex flex-wrap items-center gap-3 text-[11px] text-muted-foreground">
                                        <span>Conducted: {{ readableDate(item.conducted_on) }}</span>
                                        <span v-if="item.remarks" class="font-medium text-amber-700 dark:text-amber-300">
                                            Feedback: "{{ item.remarks }}"
                                        </span>
                                    </div>
                                </div>

                                <div class="shrink-0 text-left font-mono sm:text-right">
                                    <template v-if="item.status === 'passed'">
                                        <span
                                            class="shadow-xs mx-1 inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full border border-emerald-800 bg-emerald-700 px-2.5 py-1 text-xs font-bold text-white"
                                        >
                                            <CheckCircle2 class="size-3 shrink-0" />
                                            {{ item.score }}/{{ item.max_points }} ({{ item.pct }}%)
                                        </span>
                                        <span class="mt-0.5 block text-[10px] font-semibold text-emerald-700 dark:text-emerald-400"
                                            >Passed · 75%+</span
                                        >
                                    </template>
                                    <template v-else-if="item.status === 'failed'">
                                        <span
                                            class="shadow-xs mx-1 inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full border border-rose-800 bg-rose-700 px-2.5 py-1 text-xs font-bold text-white"
                                        >
                                            <AlertCircle class="size-3 shrink-0" />
                                            {{ item.score }}/{{ item.max_points }} ({{ item.pct }}%)
                                        </span>
                                        <span class="mt-0.5 block text-[10px] font-semibold text-rose-700 dark:text-rose-400"
                                            >Failed · Below 75%</span
                                        >
                                    </template>
                                    <template v-else>
                                        <span
                                            class="shadow-xs mx-1 inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full border border-amber-800 bg-amber-700 px-2.5 py-1 text-xs font-bold text-white"
                                        >
                                            <AlertTriangle class="size-3 shrink-0" />
                                            Uncomplied / 0 / {{ item.max_points }}
                                        </span>
                                        <span class="mt-0.5 block text-[10px] font-semibold text-amber-700 dark:text-amber-400"
                                            >Missing submission</span
                                        >
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div v-else class="rounded-xl border border-dashed border-border/80 p-8 text-center text-xs text-muted-foreground">
                            No coursework items found matching this filter.
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
