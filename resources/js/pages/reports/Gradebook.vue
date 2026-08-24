<script setup lang="ts">
import StudentDeficienciesModal from '@/components/reports/StudentDeficienciesModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, Download, Printer, RotateCcw, Save, Settings, Trophy, Wand2, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type Assessment = { id: number; type: 'activity' | 'quiz' | 'exam'; title: string; conducted_on: string; max_points: string };
type ProjectItem = { id: number; type: 'project' | 'reporting' | 'group_activity'; project_number?: string | null; title: string; conducted_on: string | null; max_points: string | number };
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

type Row = {
    id: number;
    student_number: string;
    full_name: string;
    scores: Record<number, string | null>;
    categories: Record<'activity' | 'quiz' | 'exam', Category>;
    group_activity_scores?: Record<number, number | null>;
    project_scores: Record<number, number | null>;
    projectSummary: ProjectSummary;
    attendance: AttendanceSummary;
    recitation: Recitation;
};

const props = withDefaults(
    defineProps<{
        section: { id: number; name: string; subject_code?: string; subject_title: string };
        assessments: Assessment[];
        groupActivities?: ProjectItem[];
        projects?: ProjectItem[];
        rows: Row[];
        categorySummary: Record<string, { count: number; possible: number }>;
        projectSummary?: { count: number; possible: number };
        attendanceSummary?: { total_sessions: number };
        gradingWeights: Record<string, number>;
        printMode: boolean;
    }>(),
    {
        groupActivities: () => [],
        projects: () => [],
    },
);

const page = usePage<any>();
const types = ['activity', 'quiz', 'exam'] as const;
const showWeightsEditor = ref(false);

const groupActivitiesList = computed(() => props.groupActivities || []);
const projectsList = computed(() => props.projects || []);

// Student Deficiencies Modal
const selectedStudent = ref<Row | null>(null);
const isModalOpen = ref(false);

const openStudentModal = (student: Row) => {
    selectedStudent.value = student;
    isModalOpen.value = true;
};

const closeStudentModal = () => {
    isModalOpen.value = false;
    selectedStudent.value = null;
};

const countDeficiencies = (row: Row): number => {
    let count = 0;
    // Missing or failing individual assessments
    for (const a of props.assessments) {
        const val = row.scores[a.id];
        if (val === null || val === undefined || val === '') {
            count++;
        } else {
            const score = parseFloat(String(val));
            const max = parseFloat(String(a.max_points));
            if (max > 0 && score / max < 0.75) {
                count++;
            }
        }
    }
    // Missing or failing group activities (which count under Activity)
    for (const g of groupActivitiesList.value) {
        const val = row.group_activity_scores?.[g.id];
        if (val === null || val === undefined) {
            count++;
        } else {
            const score = Number(val);
            const max = typeof g.max_points === 'number' ? g.max_points : parseFloat(String(g.max_points || 100));
            if (max > 0 && score / max < 0.75) {
                count++;
            }
        }
    }
    // Missing or failing projects
    for (const p of projectsList.value) {
        const val = row.project_scores?.[p.id];
        if (val === null || val === undefined) {
            count++;
        } else {
            const score = Number(val);
            const max = typeof p.max_points === 'number' ? p.max_points : parseFloat(String(p.max_points || 100));
            if (max > 0 && score / max < 0.75) {
                count++;
            }
        }
    }
    return count;
};

const hasDeficiencies = (row: Row): boolean => {
    return countDeficiencies(row) > 0;
};

// Rubric Weights & Bonus State
const saveError = ref<string | null>(null);

const weightsForm = useForm({
    activity: props.gradingWeights.activity ?? 20,
    quiz: props.gradingWeights.quiz ?? 20,
    exam: props.gradingWeights.exam ?? 25,
    project: props.gradingWeights.project ?? 20,
    attendance: props.gradingWeights.attendance ?? 15,
    recitation: props.gradingWeights.recitation ?? 5,
});

watch(
    () => props.gradingWeights,
    (newWeights) => {
        if (!newWeights || showWeightsEditor.value) return;
        weightsForm.activity = newWeights.activity ?? 20;
        weightsForm.quiz = newWeights.quiz ?? 20;
        weightsForm.exam = newWeights.exam ?? 25;
        weightsForm.project = newWeights.project ?? 20;
        weightsForm.attendance = newWeights.attendance ?? 15;
        weightsForm.recitation = newWeights.recitation ?? 5;
    },
    { deep: true },
);

const coreWeightsTotal = computed(
    () =>
        Number(weightsForm.activity || 0) +
        Number(weightsForm.quiz || 0) +
        Number(weightsForm.exam || 0) +
        Number(weightsForm.project || 0) +
        Number(weightsForm.attendance || 0),
);

const weightsValid = computed(() => coreWeightsTotal.value === 100 || coreWeightsTotal.value + Number(weightsForm.recitation || 0) === 100);

const applyPreset = (preset: { activity: number; quiz: number; exam: number; project: number; attendance: number }) => {
    weightsForm.activity = preset.activity;
    weightsForm.quiz = preset.quiz;
    weightsForm.exam = preset.exam;
    weightsForm.project = preset.project;
    weightsForm.attendance = preset.attendance;
    saveError.value = null;
    weightsForm.clearErrors();
};

const autoBalanceTo100 = () => {
    const act = Number(weightsForm.activity) || 0;
    const quiz = Number(weightsForm.quiz) || 0;
    const exam = Number(weightsForm.exam) || 0;
    const proj = Number(weightsForm.project) || 0;
    const att = Number(weightsForm.attendance) || 0;

    const sum = act + quiz + exam + proj + att;
    if (sum === 0) {
        applyPreset({ activity: 20, quiz: 20, exam: 25, project: 20, attendance: 15 });
        return;
    }

    const rawAct = Math.round((act / sum) * 100);
    const rawQuiz = Math.round((quiz / sum) * 100);
    const rawExam = Math.round((exam / sum) * 100);
    const rawProj = Math.round((proj / sum) * 100);
    const rawAtt = Math.max(0, 100 - (rawAct + rawQuiz + rawExam + rawProj));

    weightsForm.activity = rawAct;
    weightsForm.quiz = rawQuiz;
    weightsForm.exam = rawExam;
    weightsForm.project = rawProj;
    weightsForm.attendance = rawAtt;
    saveError.value = null;
    weightsForm.clearErrors();
};

const resetToCurrent = () => {
    weightsForm.activity = props.gradingWeights.activity ?? 20;
    weightsForm.quiz = props.gradingWeights.quiz ?? 20;
    weightsForm.exam = props.gradingWeights.exam ?? 25;
    weightsForm.project = props.gradingWeights.project ?? 20;
    weightsForm.attendance = props.gradingWeights.attendance ?? 15;
    weightsForm.recitation = props.gradingWeights.recitation ?? 5;
    saveError.value = null;
    weightsForm.clearErrors();
};

const saveWeights = () => {
    saveError.value = null;
    if (!weightsValid.value) {
        saveError.value = `Core coursework total is currently ${coreWeightsTotal.value}%. Core weights must equal exactly 100% before saving. Click 'Auto-Adjust to 100%' below to balance automatically.`;
        return;
    }

    weightsForm.put(`/sections/${props.section.id}/grading-weights`, {
        preserveScroll: true,
        onSuccess: () => {
            showWeightsEditor.value = false;
            saveError.value = null;
        },
        onError: (errors) => {
            saveError.value = Object.values(errors).join(' ');
        },
    });
};

// Philippine college grading scale: percentage → 1.0–5.0
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

const isFailing = (grade: string) => {
    if (grade === '—') return false;
    const n = parseFloat(grade);
    return n > 3.0;
};

const gradeDisplay = (grade: string) => {
    if (grade === '—') return '—';
    return isFailing(grade) ? 'INC' : grade;
};

// Compute base coursework weighted percentage per student (out of 100%)
const computeBase = (row: Row): number | null => {
    const w = props.gradingWeights;
    let totalWeight = 0;
    let weighted = 0;

    // Activities (Includes oral participation bonus points)
    if (row.categories.activity?.percentage !== null && (w.activity ?? 0) > 0) {
        weighted += row.categories.activity.percentage * (w.activity / 100);
        totalWeight += w.activity;
    }
    // Quizzes
    if (row.categories.quiz?.percentage !== null && (w.quiz ?? 0) > 0) {
        weighted += row.categories.quiz.percentage * (w.quiz / 100);
        totalWeight += w.quiz;
    }
    // Exams
    if (row.categories.exam?.percentage !== null && (w.exam ?? 0) > 0) {
        weighted += row.categories.exam.percentage * (w.exam / 100);
        totalWeight += w.exam;
    }
    // Projects & Reporting
    if (row.projectSummary?.percentage !== null && (w.project ?? 0) > 0) {
        weighted += row.projectSummary.percentage * (w.project / 100);
        totalWeight += w.project;
    }
    // Attendance
    if (row.attendance?.percentage !== null && (w.attendance ?? 0) > 0) {
        weighted += row.attendance.percentage * (w.attendance / 100);
        totalWeight += w.attendance;
    }

    if (totalWeight === 0) return null;
    return Math.min(100, round(weighted, 2));
};

// Compute earned oral recitation additional points added to activities (+bonus pts)
const computeBonus = (row: Row): number => {
    if (row.categories.activity?.bonus_earned !== undefined) {
        return row.categories.activity.bonus_earned;
    }
    const bonusCap = props.gradingWeights.recitation ?? 5;
    if (!row.recitation || row.recitation.avg_score === null || bonusCap <= 0) return 0;
    return round((row.recitation.avg_score / 10) * bonusCap, 2);
};

// Compute final overall grade (oral bonus is already part of the activity score)
const computeOverall = (row: Row): number | null => {
    return computeBase(row);
};

const round = (val: number, decimals: number) => {
    const factor = Math.pow(10, decimals);
    return Math.round(val * factor) / factor;
};

// Grade color helper
const gradeClass = (grade: string) => {
    if (grade === '—') return 'text-muted-foreground';
    if (isFailing(grade)) return 'text-rose-600 dark:text-rose-400';
    const n = parseFloat(grade);
    if (n <= 1.5) return 'text-emerald-600 dark:text-emerald-400';
    if (n <= 2.5) return 'text-primary';
    return 'text-amber-600 dark:text-amber-400';
};

onMounted(() => {
    if (props.printMode) window.setTimeout(() => window.print(), 250);
});
</script>

<template>
    <Head :title="`Gradebook · ${section.name} - ClassCheck`" />
    <component
        :is="printMode ? 'div' : AppLayout"
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Gradebook', href: '#' },
        ]"
    >
        <main class="min-h-screen bg-background p-5 text-foreground md:p-8 print:bg-white print:p-0 print:text-black">
            <div class="mx-auto max-w-[1600px]">
                <!-- Flash Message -->
                <div
                    v-if="page.props.flash?.success"
                    class="shadow-xs mb-6 rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-medium text-primary print:hidden"
                >
                    {{ page.props.flash.success }}
                </div>

                <!-- Top Toolbar (Hidden on Print) -->
                <div v-if="!printMode" class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
                    <Link
                        :href="`/sections/${section.id}/assessments`"
                        prefetch="hover"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-primary"
                    >
                        <ArrowLeft class="size-3.5" /> Back to assessments & projects
                    </Link>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="shadow-xs inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-border bg-card px-3.5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="showWeightsEditor = !showWeightsEditor"
                        >
                            <Settings class="size-3.5 text-primary" />
                            <span>Rubrics & Bonus Weights</span>
                        </button>
                        <a
                            :href="`/sections/${section.id}/exports/gradebook`"
                            class="shadow-xs inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-border bg-card px-3.5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                        >
                            <Download class="size-3.5 text-muted-foreground" />
                            <span>Export CSV</span>
                        </a>
                        <a
                            :href="`/sections/${section.id}/reports/gradebook/print`"
                            target="_blank"
                            class="ink-button !h-9 !rounded-xl !px-3.5 text-xs"
                        >
                            <Printer class="size-3.5" />
                            <span>Print view</span>
                        </a>
                    </div>
                </div>

                <!-- Rubrics Weights Editor Panel -->
                <section
                    v-if="showWeightsEditor && !printMode"
                    class="paper-card mb-6 p-6 shadow-sm duration-200 animate-in slide-in-from-top-2 print:hidden"
                >
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <Trophy class="size-4 text-primary" />
                                <h3 class="text-base font-bold text-foreground">Grading rubrics & oral recitation bonus</h3>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Core components (Activities, Quizzes, Major Exams, Projects/Reporting, and Attendance) must total 100%. Oral
                                recitations award additional bonus points directly added to student Activities.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="grid size-7 place-items-center rounded-lg text-muted-foreground hover:bg-secondary hover:text-foreground"
                            @click="showWeightsEditor = false"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <!-- Quick Presets -->
                    <div class="mb-5 flex flex-wrap items-center gap-2 border-y border-border/60 py-3">
                        <span class="text-xs font-semibold text-muted-foreground">Quick Presets:</span>
                        <button
                            type="button"
                            class="rounded-lg border border-border/80 bg-secondary/50 px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="applyPreset({ activity: 20, quiz: 20, exam: 25, project: 20, attendance: 15 })"
                        >
                            Standard (20-20-25-20-15)
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border/80 bg-secondary/50 px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="applyPreset({ activity: 15, quiz: 15, exam: 40, project: 15, attendance: 15 })"
                        >
                            Exam Focus (15-15-40-15-15)
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border/80 bg-secondary/50 px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="applyPreset({ activity: 15, quiz: 15, exam: 20, project: 35, attendance: 15 })"
                        >
                            Project Focus (15-15-20-35-15)
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border/80 bg-secondary/50 px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="applyPreset({ activity: 35, quiz: 20, exam: 20, project: 15, attendance: 10 })"
                        >
                            Activity Focus (35-20-20-15-10)
                        </button>
                    </div>

                    <!-- Error Alert -->
                    <div
                        v-if="saveError || weightsForm.errors.weights"
                        class="mb-5 flex items-start gap-2.5 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-700 dark:text-rose-400"
                    >
                        <AlertCircle class="size-4 shrink-0 mt-0.5" />
                        <div>
                            <p class="font-semibold">{{ saveError || weightsForm.errors.weights }}</p>
                        </div>
                    </div>

                    <form class="space-y-5" @submit.prevent="saveWeights">
                        <!-- Core Coursework Row -->
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-foreground">
                                    Core Coursework Components
                                </span>
                                <span
                                    class="rounded-md px-2 py-0.5 font-mono text-xs font-bold"
                                    :class="
                                        weightsValid
                                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                            : 'bg-rose-500/10 text-rose-700 dark:text-rose-400'
                                    "
                                >
                                    Total: {{ coreWeightsTotal }}% {{ weightsValid ? '✓' : '(Must be 100%)' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                <label class="rounded-xl border border-border/80 bg-secondary/30 p-3">
                                    <span class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                        Activities
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <input
                                            v-model.number="weightsForm.activity"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="w-full rounded-lg border border-input bg-background px-3 py-1.5 text-center text-sm font-bold focus-visible:ring-2 focus-visible:ring-primary"
                                        />
                                        <span class="text-xs text-muted-foreground">%</span>
                                    </div>
                                </label>
                                <label class="rounded-xl border border-border/80 bg-secondary/30 p-3">
                                    <span class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                                        Quizzes
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <input
                                            v-model.number="weightsForm.quiz"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="w-full rounded-lg border border-input bg-background px-3 py-1.5 text-center text-sm font-bold focus-visible:ring-2 focus-visible:ring-primary"
                                        />
                                        <span class="text-xs text-muted-foreground">%</span>
                                    </div>
                                </label>
                                <label class="rounded-xl border border-border/80 bg-secondary/30 p-3">
                                    <span class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">
                                        Major Exams
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <input
                                            v-model.number="weightsForm.exam"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="w-full rounded-lg border border-input bg-background px-3 py-1.5 text-center text-sm font-bold focus-visible:ring-2 focus-visible:ring-primary"
                                        />
                                        <span class="text-xs text-muted-foreground">%</span>
                                    </div>
                                </label>
                                <label class="rounded-xl border border-border/80 bg-secondary/30 p-3">
                                    <span class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400">
                                        Project / Report
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <input
                                            v-model.number="weightsForm.project"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="w-full rounded-lg border border-input bg-background px-3 py-1.5 text-center text-sm font-bold focus-visible:ring-2 focus-visible:ring-primary"
                                        />
                                        <span class="text-xs text-muted-foreground">%</span>
                                    </div>
                                </label>
                                <label class="rounded-xl border border-border/80 bg-secondary/30 p-3">
                                    <span class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400">
                                        Attendance
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <input
                                            v-model.number="weightsForm.attendance"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="w-full rounded-lg border border-input bg-background px-3 py-1.5 text-center text-sm font-bold focus-visible:ring-2 focus-visible:ring-primary"
                                        />
                                        <span class="text-xs text-muted-foreground">%</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Oral Recitation Additional Points Row -->
                        <div class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <span class="flex items-center gap-1.5 font-bold text-amber-700 dark:text-amber-400">
                                        Oral Participation (Bonus Points Added to Activities)
                                    </span>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        Awarded as additional bonus points added directly into student Activities scores. Maximum points
                                        denominator is not increased, so non-called students are never penalized.
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-foreground">Bonus Points:</span>
                                    <div class="flex items-center gap-1">
                                        <span class="text-xs font-bold text-amber-600">+</span>
                                        <input
                                            v-model.number="weightsForm.recitation"
                                            type="number"
                                            min="0"
                                            class="w-20 rounded-lg border border-amber-500/40 bg-background px-3 py-1.5 text-center text-sm font-bold text-amber-600 focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-amber-400"
                                        />
                                        <span class="text-xs font-bold text-amber-600 dark:text-amber-400">pts</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Footer / Actions -->
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-border/80 pt-4">
                        <div class="flex items-center gap-3">
                            <button
                                v-if="!weightsValid"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-1.5 text-xs font-bold text-amber-700 transition-colors hover:bg-amber-500/20 dark:text-amber-400"
                                @click="autoBalanceTo100"
                            >
                                <Wand2 class="size-3.5" />
                                <span>Auto-Adjust to 100%</span>
                            </button>
                            <span
                                class="text-xs font-medium"
                                :class="weightsValid ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                            >
                                {{ weightsValid ? 'Core weights balanced (100%)' : `Total is ${coreWeightsTotal}% — needs ${100 - coreWeightsTotal > 0 ? `+${100 - coreWeightsTotal}%` : `${100 - coreWeightsTotal}%`}` }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-xl border border-border bg-card px-3.5 py-2 text-xs font-medium text-muted-foreground hover:bg-secondary hover:text-foreground"
                                @click="resetToCurrent"
                            >
                                <RotateCcw class="mr-1 inline size-3" />
                                Reset
                            </button>
                            <button
                                type="button"
                                :disabled="weightsForm.processing"
                                class="ink-button !h-9 !rounded-xl !px-4 text-xs font-bold"
                                @click="saveWeights"
                            >
                                <Save class="size-3.5" />
                                <span>{{ weightsForm.processing ? 'Saving…' : 'Save Weights' }}</span>
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Header Block -->
                <header
                    class="rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 shadow-sm sm:p-8 print:rounded-none print:border-b-2 print:border-black print:bg-white print:p-0 print:text-black"
                >
                    <div class="flex items-center gap-2">
                        <span class="badge-primary font-mono font-medium">{{ section.subject_code }}</span>
                        <span class="badge-muted">{{ section.name }}</span>
                    </div>
                    <h1 class="mt-2 text-2xl font-medium tracking-tight sm:text-3xl print:text-xl">{{ section.subject_title }}</h1>
                    <p class="mt-1 text-xs text-muted-foreground print:text-black">
                        Weighted gradebook with college grading scale (1.0–5.0). Core: Activities {{ gradingWeights.activity }}%, Quizzes
                        {{ gradingWeights.quiz }}%, Major Exams {{ gradingWeights.exam }}%, Project / Reporting {{ gradingWeights.project }}%,
                        Attendance {{ gradingWeights.attendance }}% · Oral Recitation: +{{ gradingWeights.recitation ?? 5 }} bonus pts added
                        to Activities.
                    </p>
                </header>

                <!-- Category Summary Cards -->
                <section class="my-6 grid gap-4 sm:grid-cols-3 lg:grid-cols-6 print:grid-cols-6">
                    <div v-for="type in types" :key="type" class="paper-card p-4 print:rounded-none print:border print:border-black print:bg-white">
                        <span
                            class="font-mono text-[10px] font-medium uppercase tracking-wider"
                            :class="
                                type === 'exam'
                                    ? 'text-purple-600 dark:text-purple-400'
                                    : type === 'quiz'
                                      ? 'text-blue-600 dark:text-blue-400'
                                      : 'text-emerald-600 dark:text-emerald-400'
                            "
                        >
                            {{ type }} · {{ gradingWeights[type] }}%
                        </span>
                        <p class="mt-2 text-xl font-medium tracking-tight">{{ categorySummary[type].count }} items</p>
                    </div>

                    <!-- Project / Reporting Summary Card -->
                    <div class="paper-card p-4 print:rounded-none print:border print:border-black print:bg-white">
                        <span class="font-mono text-[10px] font-medium uppercase tracking-wider text-teal-600 dark:text-teal-400">
                            Project / Report · {{ gradingWeights.project }}%
                        </span>
                        <p class="mt-2 text-xl font-medium tracking-tight">{{ projectSummary?.count ?? projectsList.length }} projects</p>
                    </div>

                    <!-- Attendance Summary Card -->
                    <div class="paper-card p-4 print:rounded-none print:border print:border-black print:bg-white">
                        <span class="font-mono text-[10px] font-medium uppercase tracking-wider text-cyan-600 dark:text-cyan-400">
                            Attendance · {{ gradingWeights.attendance }}%
                        </span>
                        <p class="mt-2 text-xl font-medium tracking-tight">{{ attendanceSummary?.total_sessions ?? 0 }} sessions</p>
                        <p class="mt-0.5 text-[11px] font-normal text-muted-foreground">Present: 100% · Late: 50%</p>
                    </div>

                    <!-- Oral Participation Summary Card -->
                    <div class="paper-card border-amber-500/30 bg-amber-500/5 p-4 print:rounded-none print:border print:border-black print:bg-white">
                        <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                            Oral Bonus · +{{ gradingWeights.recitation ?? 5 }} pts
                        </span>
                        <p class="mt-2 text-xl font-bold tracking-tight text-amber-700 dark:text-amber-400">Added to Activities</p>
                        <p class="mt-0.5 text-[11px] font-normal text-muted-foreground">Max pts denominator is not increased</p>
                    </div>
                </section>

                <!-- Responsive Gradebook Table -->
                <div class="paper-card overflow-hidden p-0 shadow-sm print:rounded-none print:border print:border-black print:shadow-none">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="border-b border-border/80 bg-secondary/50 text-[11px] uppercase tracking-wider text-muted-foreground print:bg-gray-100 print:text-black"
                            >
                                <tr>
                                    <th
                                        class="backdrop-blur-xs sticky left-0 z-10 min-w-48 border-r border-border/60 bg-card/95 px-4 py-3 print:static print:bg-gray-100"
                                    >
                                        Student
                                        <span class="block text-[9px] font-normal lowercase text-muted-foreground print:hidden">(click to view tasks)</span>
                                    </th>
                                    <!-- Assessment columns -->
                                    <th v-for="item in assessments" :key="item.id" class="min-w-24 border-l border-border/60 px-3 py-3 text-center">
                                        <span
                                            class="block font-mono text-[9px] font-medium uppercase tracking-wider"
                                            :class="
                                                item.type === 'exam'
                                                    ? 'text-purple-600 dark:text-purple-400'
                                                    : item.type === 'quiz'
                                                      ? 'text-blue-600 dark:text-blue-400'
                                                      : 'text-emerald-600 dark:text-emerald-400'
                                            "
                                        >
                                            {{ item.type }}
                                        </span>
                                        <span class="mx-auto mt-0.5 block max-w-24 truncate font-medium text-foreground">{{ item.title }}</span>
                                        <span class="font-mono text-[10px] text-muted-foreground">/ {{ item.max_points }}</span>
                                    </th>
                                    <!-- Group Activity columns (Calculated in Activities) -->
                                    <th
                                        v-for="item in groupActivitiesList"
                                        :key="`group-act-${item.id}`"
                                        class="min-w-28 border-l border-emerald-500/30 bg-emerald-500/5 px-3 py-3 text-center"
                                    >
                                        <span
                                            class="block font-mono text-[9px] font-medium uppercase tracking-wider text-emerald-600 dark:text-emerald-400"
                                        >
                                            Group Act
                                        </span>
                                        <span class="mx-auto mt-0.5 block max-w-28 truncate font-medium text-foreground">{{ item.title }}</span>
                                        <span class="font-mono text-[10px] text-muted-foreground">/ {{ item.max_points }}</span>
                                    </th>
                                    <!-- Project columns -->
                                    <th
                                        v-for="item in projectsList"
                                        :key="`project-${item.id}`"
                                        class="min-w-28 border-l border-teal-500/30 bg-teal-500/5 px-3 py-3 text-center"
                                    >
                                        <span
                                            class="block font-mono text-[9px] font-medium uppercase tracking-wider text-teal-600 dark:text-teal-400"
                                        >
                                            {{ item.type === 'project' ? 'Project' : 'Report' }}
                                        </span>
                                        <span class="mx-auto mt-0.5 block max-w-28 truncate font-medium text-foreground">{{ item.title }}</span>
                                        <span class="font-mono text-[10px] text-muted-foreground">/ {{ item.max_points }}</span>
                                    </th>
                                    <!-- Standard category percentages -->
                                    <th
                                        v-for="type in types"
                                        :key="`total-${type}`"
                                        class="min-w-24 border-l-2 border-border bg-secondary/80 px-2.5 text-center font-medium capitalize text-foreground"
                                    >
                                        {{ type }} %
                                        <span v-if="type === 'activity'" class="block text-[8px] font-normal text-emerald-600 dark:text-emerald-400">
                                            w/ oral bonus
                                        </span>
                                    </th>
                                    <!-- Project % -->
                                    <th
                                        class="min-w-20 border-l-2 border-border bg-secondary/80 px-2.5 text-center font-medium text-teal-600 dark:text-teal-400"
                                    >
                                        Proj %
                                    </th>
                                    <!-- Attendance % -->
                                    <th
                                        class="min-w-20 border-l-2 border-border bg-secondary/80 px-2.5 text-center font-medium text-cyan-600 dark:text-cyan-400"
                                    >
                                        Att %
                                    </th>
                                    <!-- Oral Recitation Bonus Column -->
                                    <th
                                        class="min-w-28 border-l-2 border-amber-500/40 bg-amber-500/10 px-2.5 text-center font-bold text-amber-700 dark:text-amber-400"
                                    >
                                        Oral Bonus
                                        <span class="block text-[9px] font-normal text-amber-600 dark:text-amber-300">
                                            +{{ gradingWeights.recitation ?? 5 }} pts → Activities
                                        </span>
                                    </th>
                                    <!-- Weighted % -->
                                    <th class="min-w-28 border-l-2 border-primary/30 bg-primary/10 px-3 text-center font-bold text-foreground">
                                        Final Grade %
                                    </th>
                                    <th class="min-w-24 border-l-2 border-primary/30 bg-primary/10 px-3 text-center font-bold text-foreground">
                                        Grade
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="row in rows" :key="row.id" class="break-inside-avoid transition-colors hover:bg-secondary/30">
                                    <td
                                        class="backdrop-blur-xs group/student sticky left-0 z-10 cursor-pointer border-r border-border/50 bg-card/95 px-4 py-3 transition-colors hover:bg-secondary/80 print:static print:bg-white"
                                        title="Click to view failing or uncomplied activities and projects"
                                        @click="openStudentModal(row)"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="min-w-0">
                                                <span class="block truncate font-medium text-foreground transition-colors group-hover/student:text-primary group-hover/student:underline">
                                                    {{ row.full_name }}
                                                </span>
                                                <span class="font-mono text-[10px] text-muted-foreground">{{ row.student_number }}</span>
                                            </div>
                                            <span
                                                v-if="hasDeficiencies(row)"
                                                class="shrink-0 rounded-full border border-rose-500/30 bg-rose-500/10 px-1.5 py-0.5 text-[9px] font-bold text-rose-600 dark:text-rose-400 print:hidden"
                                                title="Has missing or failing items"
                                            >
                                                {{ countDeficiencies(row) }} def
                                            </span>
                                        </div>
                                    </td>
                                    <!-- Standard scores -->
                                    <td
                                        v-for="item in assessments"
                                        :key="item.id"
                                        class="border-l border-border/60 px-3 py-3 text-center font-mono text-xs"
                                        :class="row.scores[item.id] === null ? 'text-muted-foreground/60' : 'font-medium text-foreground'"
                                    >
                                        {{ row.scores[item.id] ?? '—' }}
                                    </td>
                                    <!-- Group Activity scores -->
                                    <td
                                        v-for="item in groupActivitiesList"
                                        :key="`score-gact-${item.id}`"
                                        class="border-l border-emerald-500/20 bg-emerald-500/5 px-3 py-3 text-center font-mono text-xs"
                                        :class="row.group_activity_scores?.[item.id] === null || row.group_activity_scores?.[item.id] === undefined ? 'text-muted-foreground/60' : 'font-medium text-foreground'"
                                    >
                                        {{
                                            row.group_activity_scores?.[item.id] !== null && row.group_activity_scores?.[item.id] !== undefined
                                                ? row.group_activity_scores[item.id]
                                                : '—'
                                        }}
                                    </td>
                                    <!-- Project scores -->
                                    <td
                                        v-for="item in projectsList"
                                        :key="`score-proj-${item.id}`"
                                        class="border-l border-teal-500/20 bg-teal-500/5 px-3 py-3 text-center font-mono text-xs"
                                        :class="row.project_scores?.[item.id] === null ? 'text-muted-foreground/60' : 'font-medium text-foreground'"
                                    >
                                        {{
                                            row.project_scores?.[item.id] !== null && row.project_scores?.[item.id] !== undefined
                                                ? row.project_scores[item.id]
                                                : '—'
                                        }}
                                    </td>
                                    <!-- Category percentages -->
                                    <td
                                        v-for="type in types"
                                        :key="type"
                                        class="border-l-2 border-border bg-secondary/20 px-2.5 py-3 text-center font-mono"
                                    >
                                        <span class="block text-xs font-medium">
                                            {{ row.categories[type]?.percentage !== null ? `${row.categories[type]?.percentage}%` : '—' }}
                                        </span>
                                        <span
                                            v-if="
                                                type === 'activity' &&
                                                row.categories.activity?.bonus_earned &&
                                                row.categories.activity.bonus_earned > 0
                                            "
                                            class="mt-0.5 inline-block text-[9px] font-semibold text-emerald-600 dark:text-emerald-400"
                                            title="Includes oral bonus added to activity score"
                                        >
                                            +{{ row.categories.activity.bonus_earned }} oral
                                        </span>
                                    </td>
                                    <!-- Project percentage -->
                                    <td
                                        class="border-l-2 border-border bg-secondary/20 px-2.5 py-3 text-center font-mono text-xs font-medium text-teal-600 dark:text-teal-400"
                                    >
                                        {{ row.projectSummary?.percentage !== null ? `${row.projectSummary.percentage}%` : '—' }}
                                    </td>
                                    <!-- Attendance percentage -->
                                    <td
                                        class="border-l-2 border-border bg-secondary/20 px-2.5 py-3 text-center font-mono text-xs font-medium text-cyan-600 dark:text-cyan-400"
                                    >
                                        {{ row.attendance?.percentage !== null ? `${row.attendance.percentage}%` : '—' }}
                                    </td>
                                    <!-- Recitation Bonus Points Cell -->
                                    <td class="border-l-2 border-amber-500/30 bg-amber-500/5 px-2.5 py-3 text-center font-mono">
                                        <template v-if="row.recitation && row.recitation.count > 0 && row.recitation.avg_score !== null">
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                            >
                                                +{{ computeBonus(row) }} pts
                                            </span>
                                            <span class="mt-0.5 block text-[9px] text-muted-foreground">
                                                {{ row.recitation.count }} rec ({{ row.recitation.avg_score }}/10)
                                            </span>
                                        </template>
                                        <span v-else class="text-xs text-muted-foreground">—</span>
                                    </td>
                                    <!-- Overall Final % -->
                                    <td
                                        class="border-l-2 border-primary/30 bg-primary/5 px-3 py-3 text-center font-mono text-sm font-bold text-foreground"
                                    >
                                        <span>{{ computeOverall(row) !== null ? `${computeOverall(row)}%` : '—' }}</span>
                                    </td>
                                    <td class="border-l-2 border-primary/30 bg-primary/5 px-3 py-3 text-center">
                                        <span
                                            class="inline-flex min-w-[52px] items-center justify-center rounded-full border px-2.5 py-1 text-xs font-medium"
                                            :class="[
                                                gradeDisplay(percentToGrade(computeOverall(row))) === 'INC'
                                                    ? 'border-rose-500/20 bg-rose-500/10 text-rose-600 dark:text-rose-400'
                                                    : gradeClass(percentToGrade(computeOverall(row))) === 'text-emerald-600 dark:text-emerald-400'
                                                      ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                      : gradeClass(percentToGrade(computeOverall(row))) === 'text-primary'
                                                        ? 'border-primary/20 bg-primary/10 text-primary'
                                                        : 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400',
                                            ]"
                                        >
                                            {{ gradeDisplay(percentToGrade(computeOverall(row))) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!rows.length">
                                    <td
                                        :colspan="5 + assessments.length + groupActivitiesList.length + projectsList.length + types.length"
                                        class="py-12 text-center text-xs text-muted-foreground"
                                    >
                                        No students are enrolled in this section.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Grading Scale Legend -->
                <div class="paper-card mt-6 p-5 print:rounded-none print:border print:border-black print:bg-white">
                    <h3 class="mb-3 text-xs font-medium uppercase tracking-wider text-muted-foreground">College Grading Scale</h3>
                    <div class="grid grid-cols-2 gap-2 text-center text-[10px] sm:grid-cols-5 lg:grid-cols-10">
                        <div
                            v-for="entry in [
                                { grade: '1.00', range: '97–100%' },
                                { grade: '1.25', range: '94–96%' },
                                { grade: '1.50', range: '91–93%' },
                                { grade: '1.75', range: '88–90%' },
                                { grade: '2.00', range: '85–87%' },
                                { grade: '2.25', range: '82–84%' },
                                { grade: '2.50', range: '79–81%' },
                                { grade: '2.75', range: '76–78%' },
                                { grade: '3.00', range: '75%' },
                                { grade: 'INC', range: 'Below 75%' },
                            ]"
                            :key="entry.grade"
                            class="rounded-lg border border-border/60 bg-secondary/30 px-2 py-2"
                            :class="entry.grade === 'INC' ? 'border-rose-500/30 bg-rose-500/5' : ''"
                        >
                            <span
                                class="block font-medium text-foreground"
                                :class="entry.grade === 'INC' ? 'text-rose-600 dark:text-rose-400' : ''"
                                >{{ entry.grade }}</span
                            >
                            <span class="text-muted-foreground">{{ entry.range }}</span>
                        </div>
                    </div>
                </div>

                <p class="mt-4 text-[11px] text-muted-foreground print:text-[8px]">
                    Note: Blank scores are counted as 0 for category percentages. INC (Incomplete) is assigned when the computed grade exceeds 3.0.
                    Oral recitations provide additional bonus points directly to the final grade.
                </p>
            </div>
        </main>

        <!-- Student Deficiencies Detail Modal -->
        <StudentDeficienciesModal
            :student="selectedStudent"
            :assessments="assessments"
            :group-activities="groupActivitiesList"
            :projects="projectsList"
            :grading-weights="gradingWeights"
            :section-name="section.name"
            :subject-code="section.subject_code"
            :open="isModalOpen"
            @close="closeStudentModal"
        />
    </component>
</template>

<style scoped>
@media print {
    @page {
        size: landscape;
        margin: 8mm;
    }
}
</style>
