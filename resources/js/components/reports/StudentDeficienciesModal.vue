<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    Copy,
    FileSpreadsheet,
    FileWarning,
    FolderKanban,
    Printer,
    Sparkles,
    UserX,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Assessment = { id: number; type: 'activity' | 'quiz' | 'exam'; title: string; conducted_on: string; max_points: string };
type ProjectItem = { id: number; type: 'project' | 'reporting'; title: string; conducted_on: string | null; max_points: string | number };
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
    categories: Record<'activity' | 'quiz' | 'exam', Category>;
    project_scores: Record<number, number | null>;
    projectSummary: ProjectSummary;
    attendance: AttendanceSummary;
    recitation: Recitation;
};

const props = defineProps<{
    student: StudentRow | null;
    assessments: Assessment[];
    projects: ProjectItem[];
    gradingWeights: Record<string, number>;
    sectionName?: string;
    subjectCode?: string;
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

type ActiveTab = 'deficiencies' | 'missing' | 'failing' | 'all';
const currentTab = ref<ActiveTab>('deficiencies');
const copied = ref(false);

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
    if (pct >= 97) return '1.00';
    if (pct >= 94) return '1.25';
    if (pct >= 91) return '1.50';
    if (pct >= 88) return '1.75';
    if (pct >= 85) return '2.00';
    if (pct >= 82) return '2.25';
    if (pct >= 79) return '2.50';
    if (pct >= 76) return '2.75';
    if (pct >= 75) return '3.00';
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

const overallGrade = computed(() => percentToGrade(overallPercentage.value));

// Uncomplied / Missing Assessments (score is null)
const uncompliedAssessments = computed(() => {
    if (!props.student) return [];
    return props.assessments.filter((a) => {
        const val = props.student?.scores[a.id];
        return val === null || val === undefined || val === '';
    });
});

// Failing Assessments (score recorded but < 75%)
const failingAssessments = computed(() => {
    if (!props.student) return [];
    return props.assessments
        .filter((a) => {
            const val = props.student?.scores[a.id];
            if (val === null || val === undefined || val === '') return false;
            const score = parseFloat(String(val));
            const max = parseFloat(String(a.max_points));
            return max > 0 && score / max < 0.75;
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

// Passing Assessments
const passingAssessments = computed(() => {
    if (!props.student) return [];
    return props.assessments
        .filter((a) => {
            const val = props.student?.scores[a.id];
            if (val === null || val === undefined || val === '') return false;
            const score = parseFloat(String(val));
            const max = parseFloat(String(a.max_points));
            return max > 0 && score / max >= 0.75;
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

// Uncomplied Projects
const uncompliedProjects = computed(() => {
    if (!props.student) return [];
    return props.projects.filter((p) => {
        const val = props.student?.project_scores?.[p.id];
        return val === null || val === undefined;
    });
});

// Failing Projects
const failingProjects = computed(() => {
    if (!props.student) return [];
    return props.projects
        .filter((p) => {
            const val = props.student?.project_scores?.[p.id];
            if (val === null || val === undefined) return false;
            const score = Number(val);
            const max = typeof p.max_points === 'number' ? p.max_points : parseFloat(String(p.max_points || 100));
            return max > 0 && score / max < 0.75;
        })
        .map((p) => {
            const score = Number(props.student!.project_scores![p.id]);
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

// Passing Projects
const passingProjects = computed(() => {
    if (!props.student) return [];
    return props.projects
        .filter((p) => {
            const val = props.student?.project_scores?.[p.id];
            if (val === null || val === undefined) return false;
            const score = Number(val);
            const max = typeof p.max_points === 'number' ? p.max_points : parseFloat(String(p.max_points || 100));
            return max > 0 && score / max >= 0.75;
        })
        .map((p) => {
            const score = Number(props.student!.project_scores![p.id]);
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
        class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/75 p-4 backdrop-blur-sm duration-200 animate-in fade-in print:static print:bg-transparent print:p-0"
        @click.self="emit('close')"
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
                        <span class="rounded bg-primary px-2.5 py-0.5 font-mono text-xs font-semibold text-white">
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
                    class="rounded-xl border p-3 text-center"
                    :class="
                        isFailingGrade(overallGrade)
                            ? 'border-rose-500/30 bg-rose-500/10 text-rose-600 dark:text-rose-400'
                            : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                    "
                >
                    <span class="block text-[10px] font-bold uppercase tracking-wider">Grade (1.0–5.0)</span>
                    <span class="mt-0.5 block font-mono text-xl font-bold">
                        {{ overallGrade }}
                    </span>
                </div>

                <!-- Missing / Uncomplied Count -->
                <div
                    class="rounded-xl border p-3 text-center"
                    :class="
                        totalMissingCount > 0
                            ? 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400'
                            : 'border-border/80 bg-secondary/30 text-muted-foreground'
                    "
                >
                    <span class="block text-[10px] font-bold uppercase tracking-wider">Uncomplied Items</span>
                    <span class="mt-0.5 block font-mono text-xl font-bold">
                        {{ totalMissingCount }}
                    </span>
                </div>

                <!-- Failing Scores Count -->
                <div
                    class="rounded-xl border p-3 text-center"
                    :class="
                        totalFailingCount > 0
                            ? 'border-rose-500/30 bg-rose-500/10 text-rose-600 dark:text-rose-400'
                            : 'border-border/80 bg-secondary/30 text-muted-foreground'
                    "
                >
                    <span class="block text-[10px] font-bold uppercase tracking-wider">Failing Tasks</span>
                    <span class="mt-0.5 block font-mono text-xl font-bold">
                        {{ totalFailingCount }}
                    </span>
                </div>
            </div>

            <!-- Navigation Tabs (Hidden on Print) -->
            <div class="mt-4 flex items-center gap-1 border-b border-border/80 pb-2 text-xs print:hidden">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 font-medium transition-colors"
                    :class="
                        currentTab === 'deficiencies'
                            ? 'bg-primary text-white shadow-xs'
                            : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                    "
                    @click="currentTab = 'deficiencies'"
                >
                    All Deficiencies ({{ totalDeficiencies }})
                </button>

                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 font-medium transition-colors"
                    :class="
                        currentTab === 'missing'
                            ? 'bg-amber-600 text-white shadow-xs'
                            : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                    "
                    @click="currentTab = 'missing'"
                >
                    Uncomplied ({{ totalMissingCount }})
                </button>

                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 font-medium transition-colors"
                    :class="
                        currentTab === 'failing'
                            ? 'bg-rose-600 text-white shadow-xs'
                            : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                    "
                    @click="currentTab = 'failing'"
                >
                    Failing Scores ({{ totalFailingCount }})
                </button>

                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 font-medium transition-colors"
                    :class="
                        currentTab === 'all'
                            ? 'bg-primary text-white shadow-xs'
                            : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                    "
                    @click="currentTab = 'all'"
                >
                    Complete Log ({{ assessments.length + projects.length }})
                </button>
            </div>

            <!-- Scrollable Content -->
            <div class="mt-4 flex-1 space-y-6 overflow-y-auto pr-1">
                <!-- ALL COMPLIED & PASSING EMPTY STATE -->
                <div
                    v-if="totalDeficiencies === 0 && (currentTab === 'deficiencies' || currentTab === 'missing' || currentTab === 'failing')"
                    class="rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-8 text-center"
                >
                    <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400">
                        <Sparkles class="size-6" />
                    </div>
                    <h3 class="mt-3 text-lg font-bold text-emerald-700 dark:text-emerald-400">No Deficiencies Found!</h3>
                    <p class="mt-1 text-xs text-muted-foreground">
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
                            <h3 class="text-sm font-bold text-foreground">
                                Uncomplied / Missing Coursework ({{ uncompliedAssessments.length }})
                            </h3>
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
                                            class="rounded px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider"
                                            :class="
                                                item.type === 'exam'
                                                    ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400'
                                                    : item.type === 'quiz'
                                                      ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                                      : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                            "
                                        >
                                            {{ item.type }}
                                        </span>
                                        <span class="font-semibold text-foreground">{{ item.title }}</span>
                                    </div>
                                    <span class="mt-0.5 block text-[11px] text-muted-foreground"> Conducted: {{ readableDate(item.conducted_on) }} </span>
                                </div>

                                <div class="text-right">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 font-mono text-xs font-bold text-amber-700 dark:text-amber-400"
                                    >
                                        <FileWarning class="size-3" />
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
                            <div
                                v-for="item in uncompliedProjects"
                                :key="item.id"
                                class="flex items-center justify-between gap-4 px-4 py-3 text-xs"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="rounded bg-teal-500/10 px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400"
                                        >
                                            {{ item.type === 'project' ? 'Project' : 'Reporting' }}
                                        </span>
                                        <span class="font-semibold text-foreground">{{ item.title }}</span>
                                    </div>
                                    <span class="mt-0.5 block text-[11px] text-muted-foreground">
                                        Conducted: {{ readableDate(item.conducted_on) }}
                                    </span>
                                </div>

                                <div class="text-right">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 font-mono text-xs font-bold text-amber-700 dark:text-amber-400"
                                    >
                                        <FileWarning class="size-3" />
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
                            <h3 class="text-sm font-bold text-foreground">Failing Assessment Scores (&lt; 75%) ({{ failingAssessments.length }})</h3>
                        </div>

                        <div class="divide-y divide-border/60 rounded-xl border border-border/80 bg-secondary/20">
                            <div v-for="item in failingAssessments" :key="item.id" class="flex items-center justify-between gap-4 px-4 py-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="rounded px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider"
                                            :class="
                                                item.type === 'exam'
                                                    ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400'
                                                    : item.type === 'quiz'
                                                      ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                                      : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                            "
                                        >
                                            {{ item.type }}
                                        </span>
                                        <span class="font-semibold text-foreground">{{ item.title }}</span>
                                    </div>
                                    <span class="mt-0.5 block text-[11px] text-muted-foreground"> Conducted: {{ readableDate(item.conducted_on) }} </span>
                                </div>

                                <div class="text-right font-mono">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border border-rose-500/30 bg-rose-500/10 px-2.5 py-1 text-xs font-bold text-rose-600 dark:text-rose-400"
                                    >
                                        {{ item.score }}/{{ item.max }} ({{ item.pct }}%)
                                    </span>
                                    <span class="mt-0.5 block text-[10px] text-muted-foreground">Passing: 75%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Failing Projects Section -->
                    <div v-if="failingProjects.length > 0" class="space-y-3">
                        <div class="flex items-center gap-2">
                            <AlertCircle class="size-4 text-rose-600 dark:text-rose-400" />
                            <h3 class="text-sm font-bold text-foreground">Failing Project Scores (&lt; 75%) ({{ failingProjects.length }})</h3>
                        </div>

                        <div class="divide-y divide-border/60 rounded-xl border border-border/80 bg-secondary/20">
                            <div v-for="item in failingProjects" :key="item.id" class="flex items-center justify-between gap-4 px-4 py-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="rounded bg-teal-500/10 px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400"
                                        >
                                            {{ item.type === 'project' ? 'Project' : 'Reporting' }}
                                        </span>
                                        <span class="font-semibold text-foreground">{{ item.title }}</span>
                                    </div>
                                    <span class="mt-0.5 block text-[11px] text-muted-foreground">
                                        Conducted: {{ readableDate(item.conducted_on) }}
                                    </span>
                                </div>

                                <div class="text-right font-mono">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border border-rose-500/30 bg-rose-500/10 px-2.5 py-1 text-xs font-bold text-rose-600 dark:text-rose-400"
                                    >
                                        {{ item.score }}/{{ item.max }} ({{ item.pct }}%)
                                    </span>
                                    <span class="mt-0.5 block text-[10px] text-muted-foreground">Passing: 75%</span>
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
                            <span class="mt-0.5 block font-mono text-base font-bold">{{ student.attendance.total_sessions }}</span>
                        </div>
                        <div class="rounded-xl border border-border/80 bg-secondary/20 p-3 text-center">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Present</span>
                            <span class="mt-0.5 block font-mono text-base font-bold text-emerald-600 dark:text-emerald-400"
                                >{{ student.attendance.present_count }}</span
                            >
                        </div>
                        <div class="rounded-xl border border-border/80 bg-secondary/20 p-3 text-center">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Late</span>
                            <span class="mt-0.5 block font-mono text-base font-bold text-amber-600 dark:text-amber-400"
                                >{{ student.attendance.late_count }}</span
                            >
                        </div>
                        <div class="rounded-xl border border-border/80 bg-secondary/20 p-3 text-center">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Absent</span>
                            <span class="mt-0.5 block font-mono text-base font-bold text-rose-600 dark:text-rose-400"
                                >{{ student.attendance.absent_count }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- TAB: COMPLETE LOG -->
                <template v-if="currentTab === 'all'">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <FileSpreadsheet class="size-4 text-primary" />
                            <h3 class="text-sm font-bold text-foreground">Passing Coursework ({{ passingAssessments.length + passingProjects.length }})</h3>
                        </div>

                        <div v-if="passingAssessments.length > 0 || passingProjects.length > 0" class="divide-y divide-border/60 rounded-xl border border-border/80 bg-secondary/20">
                            <div v-for="item in passingAssessments" :key="`pass-${item.id}`" class="flex items-center justify-between gap-4 px-4 py-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded bg-emerald-500/10 px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                            {{ item.type }}
                                        </span>
                                        <span class="font-semibold text-foreground">{{ item.title }}</span>
                                    </div>
                                    <span class="mt-0.5 block text-[11px] text-muted-foreground"> Conducted: {{ readableDate(item.conducted_on) }} </span>
                                </div>

                                <div class="text-right font-mono">
                                    <span class="inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ item.score }}/{{ item.max }} ({{ item.pct }}%)
                                    </span>
                                </div>
                            </div>

                            <div v-for="item in passingProjects" :key="`pass-proj-${item.id}`" class="flex items-center justify-between gap-4 px-4 py-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded bg-teal-500/10 px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400">
                                            {{ item.type === 'project' ? 'Project' : 'Reporting' }}
                                        </span>
                                        <span class="font-semibold text-foreground">{{ item.title }}</span>
                                    </div>
                                    <span class="mt-0.5 block text-[11px] text-muted-foreground"> Conducted: {{ readableDate(item.conducted_on) }} </span>
                                </div>

                                <div class="text-right font-mono">
                                    <span class="inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ item.score }}/{{ item.max }} ({{ item.pct }}%)
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-else class="py-6 text-center text-xs text-muted-foreground">
                            No passing scores recorded yet.
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
