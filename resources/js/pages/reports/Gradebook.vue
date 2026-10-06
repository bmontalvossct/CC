<script setup lang="ts">
import OralPointsOverrideModal from '@/components/reports/OralPointsOverrideModal.vue';
import StudentDeficienciesModal from '@/components/reports/StudentDeficienciesModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    ArrowUpDown,
    Calendar,
    Download,
    FileSpreadsheet,
    LayoutGrid,
    Mic,
    Presentation,
    Printer,
    RotateCcw,
    Save,
    Settings,
    Trophy,
    Wand2,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type Assessment = {
    id: number;
    type: 'activity' | 'laboratory' | 'quiz' | 'exam';
    term_period?: 'midterm' | 'final' | null;
    computed_period?: 'midterm' | 'final';
    title: string;
    conducted_on: string | null;
    max_points: string | number;
};

type ProjectItem = {
    id: number;
    type: 'project' | 'reporting' | 'group_activity';
    term_period?: 'midterm' | 'final' | null;
    computed_period?: 'midterm' | 'final';
    project_number?: string | null;
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
    excused_count?: number;
    absent_count: number;
    present_dates?: string[];
    late_dates?: string[];
    excused_dates?: string[];
    earned_points: number;
    possible_points: number;
    percentage: number | null;
};
type Recitation = { count: number; total_score?: number; avg_score: number | null; percentage: number | null; bonus_points?: number };

type PeriodMetrics = {
    weighted_grade: number | null;
    scale_grade: string;
    categories: Record<string, Category>;
    attendance: AttendanceSummary;
    recitation: Recitation;
    projectSummary: ProjectSummary;
};

type Row = {
    id: number;
    student_number: string;
    full_name: string;
    scores: Record<number, string | null>;
    remarks?: Record<number, string | null>;
    categories: Record<'activity' | 'laboratory' | 'quiz' | 'exam', Category>;
    group_activity_scores?: Record<number, number | null>;
    project_scores: Record<number, number | null>;
    project_notes?: Record<number, string | null>;
    projectSummary: ProjectSummary;
    attendance: AttendanceSummary;
    recitation: Recitation;
    weighted_grade: number | null;
    scale_grade: string;
    is_passing?: boolean | null;
    midterm?: PeriodMetrics;
    final_period?: PeriodMetrics;
};

const props = withDefaults(
    defineProps<{
        section: { id: number; name: string; subject_code?: string; subject_title: string };
        assessments: Assessment[];
        groupActivities?: ProjectItem[];
        projects?: ProjectItem[];
        rows: Row[];
        categorySummary: Record<string, { count: number; possible: number }>;
        midtermCategorySummary?: Record<string, { count: number; possible: number }>;
        finalCategorySummary?: Record<string, { count: number; possible: number }>;
        projectSummary?: { count: number; possible: number };
        attendanceSummary?: { total_sessions: number; midterm_sessions?: number; final_sessions?: number };
        gradingWeights: Record<string, any>;
        reportingFrequency?: 'once_per_sem' | 'twice_per_sem';
        midtermExam?: { id: number; title: string; conducted_on?: string; max_points?: any } | null;
        printMode: boolean;
    }>(),
    {
        groupActivities: () => [],
        projects: () => [],
        reportingFrequency: 'once_per_sem',
        midtermExam: null,
    },
);

const page = usePage<any>();
const types = ['activity', 'laboratory', 'quiz', 'exam'] as const;
const activeTypes = computed(() => {
    return types.filter(
        (t) => t !== 'laboratory' || (props.categorySummary.laboratory?.count ?? 0) > 0 || (props.gradingWeights.laboratory ?? 0) > 0,
    );
});
const showWeightsEditor = ref(false);
const activePeriodTab = ref<'all' | 'midterm' | 'final'>('all');
const viewMode = ref<'summary' | 'detailed'>('summary');

// Column Sorting (DataTable Style)
const sortColumn = ref<string>('student');
const sortDirection = ref<'asc' | 'desc'>('asc');

const toggleSort = (colKey: string) => {
    if (sortColumn.value === colKey) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = colKey;
        // Default direction: student names, student number and scale grades default to asc; numeric scores/percentages default to desc
        if (colKey === 'student' || colKey === 'student_number' || colKey === 'scale_grade') {
            sortDirection.value = 'asc';
        } else {
            sortDirection.value = 'desc';
        }
    }
};

const getScaleNumeric = (g: string): number => {
    if (g === '1.00') return 1.0;
    if (g === '1.25') return 1.25;
    if (g === '1.50') return 1.5;
    if (g === '1.75') return 1.75;
    if (g === '2.00') return 2.0;
    if (g === '2.25') return 2.25;
    if (g === '2.50') return 2.5;
    if (g === '2.75') return 2.75;
    if (g === '3.00') return 3.0;
    if (g === '5.00' || g === 'INC') return 5.0;
    return 99; // uncomputed / missing
};

const getSortValue = (row: Row, key: string): number | string => {
    if (key === 'student') {
        return row.last_name ? `${row.last_name}, ${row.first_name || ''}` : row.full_name;
    }
    if (key === 'student_number') {
        return row.student_number || '';
    }
    if (key === 'activity') {
        return row.categories.activity?.percentage !== null && row.categories.activity?.percentage !== undefined
            ? row.categories.activity.percentage
            : -999999;
    }
    if (key === 'quiz') {
        return row.categories.quiz?.percentage !== null && row.categories.quiz?.percentage !== undefined ? row.categories.quiz.percentage : -999999;
    }
    if (key === 'laboratory') {
        return row.categories.laboratory?.percentage !== null && row.categories.laboratory?.percentage !== undefined
            ? row.categories.laboratory.percentage
            : -999999;
    }
    if (key === 'exam') {
        return row.categories.exam?.percentage !== null && row.categories.exam?.percentage !== undefined ? row.categories.exam.percentage : -999999;
    }
    if (key === 'project') {
        return row.projectSummary?.percentage !== null && row.projectSummary?.percentage !== undefined ? row.projectSummary.percentage : -999999;
    }
    if (key === 'attendance') {
        return row.attendance?.percentage !== null && row.attendance?.percentage !== undefined ? row.attendance.percentage : -999999;
    }
    if (key === 'recitation') {
        return row.recitation?.bonus_points ?? 0;
    }
    if (key === 'midterm') {
        return row.midterm?.weighted_grade !== null && row.midterm?.weighted_grade !== undefined ? row.midterm.weighted_grade : -999999;
    }
    if (key === 'final_period') {
        return row.final_period?.weighted_grade !== null && row.final_period?.weighted_grade !== undefined
            ? row.final_period.weighted_grade
            : -999999;
    }
    if (key === 'weighted_grade') {
        return row.weighted_grade !== null && row.weighted_grade !== undefined ? row.weighted_grade : -999999;
    }
    if (key === 'scale_grade') {
        return getScaleNumeric(row.scale_grade);
    }
    if (key.startsWith('assessment-')) {
        const id = Number(key.replace('assessment-', ''));
        const v = row.scores[id];
        return v !== null && v !== undefined && v !== '' ? Number(v) : -999999;
    }
    if (key.startsWith('gact-')) {
        const id = Number(key.replace('gact-', ''));
        const v = row.group_activity_scores?.[id];
        return v !== null && v !== undefined ? Number(v) : -999999;
    }
    if (key.startsWith('project-')) {
        const id = Number(key.replace('project-', ''));
        const v = row.project_scores?.[id];
        return v !== null && v !== undefined ? Number(v) : -999999;
    }
    return 0;
};

const sortedRows = computed(() => {
    const list = [...props.rows];
    const key = sortColumn.value;
    const dir = sortDirection.value === 'asc' ? 1 : -1;

    list.sort((a, b) => {
        const valA = getSortValue(a, key);
        const valB = getSortValue(b, key);

        if (typeof valA === 'string' && typeof valB === 'string') {
            return dir * valA.localeCompare(valB, undefined, { sensitivity: 'base' });
        }

        const numA = Number(valA);
        const numB = Number(valB);

        if (numA < numB) return -1 * dir;
        if (numA > numB) return 1 * dir;

        // Fallback secondary sort by student name ascending
        const nameA = a.last_name ? `${a.last_name}, ${a.first_name || ''}` : a.full_name;
        const nameB = b.last_name ? `${b.last_name}, ${b.first_name || ''}` : b.full_name;
        return nameA.localeCompare(nameB, undefined, { sensitivity: 'base' });
    });

    return list;
});

const groupActivitiesList = computed(() => props.groupActivities || []);
const projectsList = computed(() => props.projects || []);

// Filtered assessments and projects based on active period tab
const filteredAssessments = computed(() => {
    if (activePeriodTab.value === 'all') return props.assessments;
    return props.assessments.filter((a) => a.computed_period === activePeriodTab.value);
});

const filteredGroupActivities = computed(() => {
    if (activePeriodTab.value === 'all') return groupActivitiesList.value;
    return groupActivitiesList.value.filter((g) => g.computed_period === activePeriodTab.value);
});

const filteredProjects = computed(() => {
    if (activePeriodTab.value === 'all') return projectsList.value;
    return projectsList.value.filter((p) => {
        if (p.type === 'reporting' && (props.reportingFrequency === 'once_per_sem' || weightsForm.reporting_frequency === 'once_per_sem')) {
            return activePeriodTab.value === 'final';
        }
        return p.computed_period === activePeriodTab.value;
    });
});

// Student Activity & Deficiencies Modal
const selectedStudent = ref<Row | null>(null);
const isModalOpen = ref(false);
const modalInitialTab = ref<'activity_log' | 'deficiencies'>('activity_log');

const openStudentModal = (student: Row, tab: 'activity_log' | 'deficiencies' = 'activity_log') => {
    selectedStudent.value = student;
    modalInitialTab.value = tab;
    isModalOpen.value = true;
};

const closeStudentModal = () => {
    isModalOpen.value = false;
    selectedStudent.value = null;
};

// Oral Points Override Modal
const showOralOverrideModal = ref(false);
const selectedStudentForOral = ref<Row | null>(null);

const openOralOverrideModal = (student: Row | null = null) => {
    selectedStudentForOral.value = student;
    showOralOverrideModal.value = true;
};

const passingRates = computed(() => ({
    quiz: Number(props.gradingWeights?.passing_rates?.quiz ?? 75),
    activity: Number(props.gradingWeights?.passing_rates?.activity ?? 75),
    project: Number(props.gradingWeights?.passing_rates?.project ?? 75),
    exam: Number(props.gradingWeights?.passing_rates?.exam ?? 75),
}));

const countDeficiencies = (row: Row): number => {
    let count = 0;
    const rates = passingRates.value;
    for (const a of props.assessments) {
        const val = row.scores[a.id];
        if (val === null || val === undefined || val === '') {
            count++;
        } else {
            const score = parseFloat(String(val));
            const max = parseFloat(String(a.max_points));
            const threshold = ((rates as Record<string, number>)[a.type] ?? 75) / 100;
            if (max > 0 && score / max < threshold) {
                count++;
            }
        }
    }
    for (const g of groupActivitiesList.value) {
        const val = row.group_activity_scores?.[g.id];
        if (val === null || val === undefined) {
            count++;
        } else {
            const score = Number(val);
            const max = typeof g.max_points === 'number' ? g.max_points : parseFloat(String(g.max_points || 100));
            const threshold = (rates.activity ?? 75) / 100;
            if (max > 0 && score / max < threshold) {
                count++;
            }
        }
    }
    for (const p of projectsList.value) {
        const val = row.project_scores?.[p.id];
        if (val === null || val === undefined) {
            count++;
        } else {
            const score = Number(val);
            const max = typeof p.max_points === 'number' ? p.max_points : parseFloat(String(p.max_points || 100));
            const threshold = (rates.project ?? 75) / 100;
            if (max > 0 && score / max < threshold) {
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
    laboratory: props.gradingWeights.laboratory ?? 0,
    quiz: props.gradingWeights.quiz ?? 20,
    exam: props.gradingWeights.exam ?? 25,
    project: props.gradingWeights.project ?? 20,
    attendance: props.gradingWeights.attendance ?? 15,
    recitation: props.gradingWeights.recitation ?? 5,
    reporting_frequency: props.gradingWeights.reporting_frequency ?? props.reportingFrequency ?? 'once_per_sem',
    midterm_weight: props.gradingWeights.midterm_weight ?? 50,
    final_weight: props.gradingWeights.final_weight ?? 50,
});

watch(
    () => props.gradingWeights,
    (newWeights) => {
        if (!newWeights || showWeightsEditor.value) return;
        weightsForm.activity = newWeights.activity ?? 20;
        weightsForm.laboratory = newWeights.laboratory ?? 0;
        weightsForm.quiz = newWeights.quiz ?? 20;
        weightsForm.exam = newWeights.exam ?? 25;
        weightsForm.project = newWeights.project ?? 20;
        weightsForm.attendance = newWeights.attendance ?? 15;
        weightsForm.recitation = newWeights.recitation ?? 5;
        weightsForm.reporting_frequency = newWeights.reporting_frequency ?? 'once_per_sem';
        weightsForm.midterm_weight = newWeights.midterm_weight ?? 50;
        weightsForm.final_weight = newWeights.final_weight ?? 50;
    },
    { deep: true },
);

const coreWeightsTotal = computed(
    () =>
        Number(weightsForm.activity || 0) +
        Number(weightsForm.laboratory || 0) +
        Number(weightsForm.quiz || 0) +
        Number(weightsForm.exam || 0) +
        Number(weightsForm.project || 0) +
        Number(weightsForm.attendance || 0),
);

const weightsValid = computed(() => coreWeightsTotal.value === 100 || coreWeightsTotal.value + Number(weightsForm.recitation || 0) === 100);

const applyPreset = (preset: { activity: number; laboratory?: number; quiz: number; exam: number; project: number; attendance: number }) => {
    weightsForm.activity = preset.activity;
    weightsForm.laboratory = preset.laboratory ?? 0;
    weightsForm.quiz = preset.quiz;
    weightsForm.exam = preset.exam;
    weightsForm.project = preset.project;
    weightsForm.attendance = preset.attendance;
    saveError.value = null;
    weightsForm.clearErrors();
};

const autoBalanceTo100 = () => {
    const act = Number(weightsForm.activity) || 0;
    const lab = Number(weightsForm.laboratory) || 0;
    const quiz = Number(weightsForm.quiz) || 0;
    const exam = Number(weightsForm.exam) || 0;
    const proj = Number(weightsForm.project) || 0;
    const att = Number(weightsForm.attendance) || 0;

    const sum = act + lab + quiz + exam + proj + att;
    if (sum === 0) {
        applyPreset({ activity: 20, laboratory: 0, quiz: 20, exam: 25, project: 20, attendance: 15 });
        return;
    }

    const rawAct = Math.round((act / sum) * 100);
    const rawLab = Math.round((lab / sum) * 100);
    const rawQuiz = Math.round((quiz / sum) * 100);
    const rawExam = Math.round((exam / sum) * 100);
    const rawProj = Math.round((proj / sum) * 100);
    const rawAtt = Math.max(0, 100 - (rawAct + rawLab + rawQuiz + rawExam + rawProj));

    weightsForm.activity = rawAct;
    weightsForm.laboratory = rawLab;
    weightsForm.quiz = rawQuiz;
    weightsForm.exam = rawExam;
    weightsForm.project = rawProj;
    weightsForm.attendance = rawAtt;
    saveError.value = null;
    weightsForm.clearErrors();
};

const resetToCurrent = () => {
    weightsForm.activity = props.gradingWeights.activity ?? 20;
    weightsForm.laboratory = props.gradingWeights.laboratory ?? 0;
    weightsForm.quiz = props.gradingWeights.quiz ?? 20;
    weightsForm.exam = props.gradingWeights.exam ?? 25;
    weightsForm.project = props.gradingWeights.project ?? 20;
    weightsForm.attendance = props.gradingWeights.attendance ?? 15;
    weightsForm.recitation = props.gradingWeights.recitation ?? 5;
    weightsForm.reporting_frequency = props.gradingWeights.reporting_frequency ?? props.reportingFrequency ?? 'once_per_sem';
    weightsForm.midterm_weight = props.gradingWeights.midterm_weight ?? 50;
    weightsForm.final_weight = props.gradingWeights.final_weight ?? 50;
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

const isFailing = (grade: string) => {
    if (grade === '—') return false;
    const n = parseFloat(grade);
    return n > 3.0;
};

const gradeDisplay = (grade: string) => {
    if (grade === '—' || !grade) return '—';
    return grade;
};

// High contrast grade badge background helper (dark solid color with white text)
const gradeBadgeBg = (grade: string) => {
    if (grade === '—') return 'border-border bg-secondary text-foreground';
    if (isFailing(grade) || grade === 'INC') return 'border-rose-800 bg-rose-700 text-white';
    const n = parseFloat(grade);
    if (n <= 1.5) return 'border-emerald-800 bg-emerald-700 text-white';
    if (n <= 2.5) return 'border-blue-800 bg-blue-700 text-white';
    return 'border-amber-800 bg-amber-700 text-white';
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
            <div class="mx-auto max-w-[1680px]">
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

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Period Selector Tabs -->
                        <div class="flex items-center rounded-xl border border-border/80 bg-secondary/50 p-1">
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1 text-xs font-semibold transition-colors"
                                :class="
                                    activePeriodTab === 'all' ? 'shadow-xs bg-card text-foreground' : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="activePeriodTab = 'all'"
                            >
                                All / Semestral
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-1 rounded-lg px-3 py-1 text-xs font-semibold transition-colors"
                                :class="
                                    activePeriodTab === 'midterm' ? 'shadow-xs bg-card text-primary' : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="activePeriodTab = 'midterm'"
                            >
                                <span>Midterm Period</span>
                                <span v-if="midtermExam" class="py-0.2 rounded bg-primary/10 px-1 font-mono text-[9px] text-primary">Exam</span>
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1 text-xs font-semibold transition-colors"
                                :class="
                                    activePeriodTab === 'final' ? 'shadow-xs bg-card text-foreground' : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="activePeriodTab = 'final'"
                            >
                                Final Period
                            </button>
                        </div>

                        <button
                            type="button"
                            class="shadow-xs inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-border bg-card px-3.5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="openOralOverrideModal()"
                        >
                            <Mic class="size-3.5 text-muted-foreground" />
                            <span>Oral Points</span>
                        </button>
                        <button
                            type="button"
                            class="shadow-xs inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-border bg-card px-3.5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="showWeightsEditor = !showWeightsEditor"
                        >
                            <Settings class="size-3.5 text-primary" />
                            <span>Rubrics & Reporting</span>
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

                <!-- Rubrics Weights & Reporting Policy Editor Panel -->
                <section
                    v-if="showWeightsEditor && !printMode"
                    class="paper-card mb-6 p-6 shadow-sm duration-200 animate-in slide-in-from-top-2 print:hidden"
                >
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <Trophy class="size-4 text-primary" />
                                <h3 class="text-base font-bold text-foreground">Grading rubrics, midterms & reporting workflow</h3>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Coursework is calculated periodically: items up to the Midterm Exam form the Midterm Grade, while subsequent items
                                form the Final Period Grade. Semestral Grade combines both periods (50/50).
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

                    <!-- Oral Reporting Frequency Option -->
                    <div class="mb-5 rounded-2xl border border-teal-500/30 bg-teal-500/5 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <Presentation class="size-4 text-teal-600 dark:text-teal-400" />
                                    <span class="text-sm font-bold text-foreground">Oral Reporting Frequency & Grade Allocation</span>
                                </div>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    Define whether students report once per semester or twice (Midterms & Finals).
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-xl border px-3 py-1.5 text-xs font-semibold transition-all"
                                    :class="
                                        weightsForm.reporting_frequency === 'once_per_sem'
                                            ? 'shadow-xs border-teal-500 bg-teal-600 text-white'
                                            : 'border-border bg-card text-foreground hover:bg-secondary'
                                    "
                                    @click="weightsForm.reporting_frequency = 'once_per_sem'"
                                >
                                    1 Report / Sem (Recorded in Finals)
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border px-3 py-1.5 text-xs font-semibold transition-all"
                                    :class="
                                        weightsForm.reporting_frequency === 'twice_per_sem'
                                            ? 'shadow-xs border-teal-500 bg-teal-600 text-white'
                                            : 'border-border bg-card text-foreground hover:bg-secondary'
                                    "
                                    @click="weightsForm.reporting_frequency = 'twice_per_sem'"
                                >
                                    2 Reports / Sem (Midterm & Finals)
                                </button>
                            </div>
                        </div>
                        <p class="mt-2 text-[11px] text-teal-800 dark:text-teal-300">
                            {{
                                weightsForm.reporting_frequency === 'once_per_sem'
                                    ? '✓ 1 Report per semester selected: Students report once. Report scores are credited to the Final Grade period so students presenting later in the term are not marked missing in Midterms.'
                                    : '✓ 2 Reports per semester selected: Students present twice (one oral presentation in Midterms and one in Finals).'
                            }}
                        </p>
                    </div>

                    <!-- Quick Presets -->
                    <div class="mb-5 flex flex-wrap items-center gap-2 border-y border-border/60 py-3">
                        <span class="text-xs font-semibold text-muted-foreground">Quick Presets:</span>
                        <button
                            type="button"
                            class="rounded-lg border border-border/80 bg-secondary/50 px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="applyPreset({ activity: 15, laboratory: 20, quiz: 15, exam: 20, project: 15, attendance: 15 })"
                        >
                            Lecture + Lab (15-20-15-20-15-15)
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border/80 bg-secondary/50 px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="applyPreset({ activity: 20, laboratory: 0, quiz: 20, exam: 25, project: 20, attendance: 15 })"
                        >
                            Standard (20-20-25-20-15)
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border/80 bg-secondary/50 px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="applyPreset({ activity: 15, laboratory: 0, quiz: 15, exam: 40, project: 15, attendance: 15 })"
                        >
                            Exam Focus (15-15-40-15-15)
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border/80 bg-secondary/50 px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="applyPreset({ activity: 15, laboratory: 0, quiz: 15, exam: 20, project: 35, attendance: 15 })"
                        >
                            Project Focus (15-15-20-35-15)
                        </button>
                    </div>

                    <!-- Error Alert -->
                    <div
                        v-if="saveError || (weightsForm.errors as any).weights"
                        class="mb-5 flex items-start gap-2.5 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-700 dark:text-rose-400"
                    >
                        <AlertCircle class="mt-0.5 size-4 shrink-0" />
                        <div>
                            <p class="font-semibold">{{ saveError || (weightsForm.errors as any).weights }}</p>
                        </div>
                    </div>

                    <form class="space-y-5" @submit.prevent="saveWeights">
                        <!-- Core Coursework Row -->
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-foreground"> Core Coursework Components </span>
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

                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
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
                                    <span class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400">
                                        Laboratory
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <input
                                            v-model.number="weightsForm.laboratory"
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
                                        Awarded as additional bonus points added directly into student Activities scores. Maximum points denominator
                                        is not increased.
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
                                {{
                                    weightsValid
                                        ? 'Core weights balanced (100%)'
                                        : `Total is ${coreWeightsTotal}% — needs ${100 - coreWeightsTotal > 0 ? `+${100 - coreWeightsTotal}%` : `${100 - coreWeightsTotal}%`}`
                                }}
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
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="badge-primary font-mono font-medium">{{ section.subject_code }}</span>
                            <span class="badge-muted">{{ section.name }}</span>
                            <span
                                class="inline-flex items-center gap-1 rounded-full border border-teal-500/30 bg-teal-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-teal-700 dark:text-teal-300 print:hidden"
                            >
                                <Presentation class="size-3" />
                                {{ reportingFrequency === 'once_per_sem' ? '1 Report/Sem (In Finals)' : '2 Reports/Sem (Mid & Fin)' }}
                            </span>
                        </div>
                        <div v-if="midtermExam" class="flex items-center gap-1.5 text-xs text-muted-foreground print:text-black">
                            <Calendar class="size-3.5 text-primary" />
                            <span
                                >Midterm Exam: <strong>{{ midtermExam.title }}</strong></span
                            >
                            <span v-if="midtermExam.conducted_on" class="font-mono text-[11px]">({{ midtermExam.conducted_on }})</span>
                        </div>
                    </div>

                    <h1 class="mt-2 text-2xl font-medium tracking-tight sm:text-3xl print:text-xl">{{ section.subject_title }}</h1>
                    <p class="mt-1 text-xs text-muted-foreground print:text-black">
                        Weighted gradebook with Midterm Grade, Final Period Grade, and Semestral Grade (1.0–5.0). Core: Activities
                        {{ gradingWeights.activity }}%, Quizzes {{ gradingWeights.quiz }}%, Major Exams {{ gradingWeights.exam }}%, Project /
                        Reporting {{ gradingWeights.project }}%, Attendance {{ gradingWeights.attendance }}% · Oral Recitation: +{{
                            gradingWeights.recitation ?? 5
                        }}
                        bonus pts.
                    </p>
                </header>

                <!-- Category Summary Cards -->
                <section class="my-6 grid gap-4 sm:grid-cols-3 lg:grid-cols-7 print:grid-cols-7">
                    <div v-for="type in types" :key="type" class="paper-card p-4 print:rounded-none print:border print:border-black print:bg-white">
                        <span
                            class="font-mono text-[10px] font-medium uppercase tracking-wider"
                            :class="
                                type === 'exam'
                                    ? 'text-purple-600 dark:text-purple-400'
                                    : type === 'quiz'
                                      ? 'text-blue-600 dark:text-blue-400'
                                      : type === 'laboratory'
                                        ? 'text-cyan-600 dark:text-cyan-400'
                                        : 'text-emerald-600 dark:text-emerald-400'
                            "
                        >
                            {{ type === 'laboratory' ? 'Lab' : type }} · {{ gradingWeights[type] ?? 0 }}%
                        </span>
                        <p class="mt-2 text-xl font-medium tracking-tight">{{ categorySummary[type]?.count ?? 0 }} items</p>
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
                        <p class="mt-0.5 text-[11px] font-normal text-muted-foreground">Max denominator not increased</p>
                    </div>
                </section>

                <!-- Responsive Gradebook Table -->
                <div class="paper-card overflow-hidden p-0 shadow-sm print:rounded-none print:border print:border-black print:shadow-none">
                    <!-- View Mode Switcher Header -->
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/70 bg-secondary/30 px-4 py-3 print:hidden">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="mr-1 text-xs font-semibold text-muted-foreground">View Mode:</span>
                            <div class="shadow-2xs inline-flex rounded-xl border border-border/80 bg-card p-0.5">
                                <button
                                    type="button"
                                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                                    :class="
                                        viewMode === 'summary'
                                            ? 'shadow-xs bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                                    "
                                    @click="viewMode = 'summary'"
                                >
                                    <LayoutGrid class="size-3.5" />
                                    <span>Summary View (Compact)</span>
                                </button>
                                <button
                                    type="button"
                                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                                    :class="
                                        viewMode === 'detailed'
                                            ? 'shadow-xs bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                                    "
                                    @click="viewMode = 'detailed'"
                                >
                                    <FileSpreadsheet class="size-3.5" />
                                    <span>Detailed View (All Individual Items)</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 text-xs text-muted-foreground">
                            <button
                                v-if="sortColumn !== 'student' || sortDirection !== 'asc'"
                                type="button"
                                class="shadow-2xs inline-flex items-center gap-1 rounded-md border border-border bg-card px-2.5 py-1 text-[11px] font-medium text-foreground transition-colors hover:bg-secondary"
                                title="Reset sorting back to student alphabetical order"
                                @click="
                                    sortColumn = 'student';
                                    sortDirection = 'asc';
                                "
                            >
                                <RotateCcw class="size-3 text-muted-foreground" />
                                <span>Reset Sort</span>
                            </button>
                            <span v-if="viewMode === 'summary'" class="hidden md:inline">
                                💡 Overall category totals summarized. Click any column header to sort.
                            </span>
                            <span class="font-mono text-xs font-medium">{{ rows.length }} Students</span>
                        </div>
                    </div>

                    <div class="scrollbar-thin max-h-[calc(100vh-14rem)] min-h-[420px] overflow-auto print:max-h-none print:overflow-visible">
                        <table class="w-full border-separate border-spacing-0 text-left text-xs">
                            <thead
                                class="shadow-2xs sticky top-0 z-20 border-b border-border/80 bg-secondary/95 text-[11px] uppercase tracking-wider text-muted-foreground backdrop-blur-md print:static print:bg-gray-100 print:text-black"
                            >
                                <!-- SUMMARY VIEW HEADER -->
                                <tr v-if="viewMode === 'summary'">
                                    <th
                                        class="group/th shadow-xs sticky left-0 top-0 z-30 min-w-52 cursor-pointer select-none border-b border-r border-border/80 bg-card/95 px-4 py-3 backdrop-blur-md transition-colors hover:bg-secondary/80 print:static print:bg-gray-100"
                                        :title="`Click to sort by Student (${sortColumn === 'student' ? (sortDirection === 'asc' ? 'A to Z' : 'Z to A') : 'click to sort'})`"
                                        @click="toggleSort('student')"
                                    >
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-bold text-foreground">Student</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'student' && sortDirection === 'asc'"
                                                class="size-3.5 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'student' && sortDirection === 'desc'"
                                                class="size-3.5 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3.5 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="block text-[9px] font-normal lowercase text-muted-foreground print:hidden">
                                            (click student to view log)
                                        </span>
                                    </th>

                                    <!-- Activities Summary Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l border-emerald-500/30 bg-emerald-500/10 px-3 py-3 text-center backdrop-blur-md transition-colors hover:bg-emerald-500/20"
                                        :title="`Click to sort by Activities (${sortColumn === 'activity' ? sortDirection : 'click to sort'})`"
                                        @click="toggleSort('activity')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span
                                                class="font-mono text-[9px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400"
                                            >
                                                Activities
                                            </span>
                                            <ArrowUp
                                                v-if="sortColumn === 'activity' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'activity' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="mt-0.5 block font-medium text-foreground">
                                            {{ categorySummary.activity?.possible ?? 0 }} pts max
                                        </span>
                                        <span class="font-mono text-[10px] text-muted-foreground">{{ gradingWeights.activity }}% weight</span>
                                    </th>

                                    <!-- Quizzes Summary Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l border-blue-500/30 bg-blue-500/10 px-3 py-3 text-center backdrop-blur-md transition-colors hover:bg-blue-500/20"
                                        :title="`Click to sort by Quizzes (${sortColumn === 'quiz' ? sortDirection : 'click to sort'})`"
                                        @click="toggleSort('quiz')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="font-mono text-[9px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                                                Quizzes
                                            </span>
                                            <ArrowUp v-if="sortColumn === 'quiz' && sortDirection === 'asc'" class="size-3 shrink-0 text-primary" />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'quiz' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="mt-0.5 block font-medium text-foreground">
                                            {{ categorySummary.quiz?.possible ?? 0 }} pts max
                                        </span>
                                        <span class="font-mono text-[10px] text-muted-foreground">{{ gradingWeights.quiz }}% weight</span>
                                    </th>

                                    <!-- Laboratory Summary Column (if present) -->
                                    <th
                                        v-if="(categorySummary.laboratory?.count ?? 0) > 0 || (gradingWeights.laboratory ?? 0) > 0"
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l border-cyan-500/30 bg-cyan-500/10 px-3 py-3 text-center backdrop-blur-md transition-colors hover:bg-cyan-500/20"
                                        :title="`Click to sort by Laboratory (${sortColumn === 'laboratory' ? sortDirection : 'click to sort'})`"
                                        @click="toggleSort('laboratory')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="font-mono text-[9px] font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400">
                                                Laboratory
                                            </span>
                                            <ArrowUp
                                                v-if="sortColumn === 'laboratory' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'laboratory' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="mt-0.5 block font-medium text-foreground">
                                            {{ categorySummary.laboratory?.possible ?? 0 }} pts max
                                        </span>
                                        <span class="font-mono text-[10px] text-muted-foreground">{{ gradingWeights.laboratory }}% weight</span>
                                    </th>

                                    <!-- Major Exams Summary Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l border-purple-500/30 bg-purple-500/10 px-3 py-3 text-center backdrop-blur-md transition-colors hover:bg-purple-500/20"
                                        :title="`Click to sort by Major Exams (${sortColumn === 'exam' ? sortDirection : 'click to sort'})`"
                                        @click="toggleSort('exam')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span
                                                class="font-mono text-[9px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400"
                                            >
                                                Major Exams
                                            </span>
                                            <ArrowUp v-if="sortColumn === 'exam' && sortDirection === 'asc'" class="size-3 shrink-0 text-primary" />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'exam' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="mt-0.5 block font-medium text-foreground">
                                            {{ categorySummary.exam?.possible ?? 0 }} pts max
                                        </span>
                                        <span class="font-mono text-[10px] text-muted-foreground">{{ gradingWeights.exam }}% weight</span>
                                    </th>

                                    <!-- Project / Reporting Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l border-teal-500/30 bg-teal-500/10 px-3 py-3 text-center backdrop-blur-md transition-colors hover:bg-teal-500/20"
                                        :title="`Click to sort by Projects & Reports (${sortColumn === 'project' ? sortDirection : 'click to sort'})`"
                                        @click="toggleSort('project')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="font-mono text-[9px] font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400">
                                                Project / Report
                                            </span>
                                            <ArrowUp
                                                v-if="sortColumn === 'project' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'project' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="mt-0.5 block font-medium text-foreground"> {{ projectSummary?.possible ?? 0 }} pts max </span>
                                        <span class="font-mono text-[10px] text-muted-foreground">{{ gradingWeights.project }}% weight</span>
                                    </th>

                                    <!-- Attendance Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-24 cursor-pointer select-none border-b border-l border-border bg-secondary/95 px-2.5 py-3 text-center font-medium text-cyan-600 backdrop-blur-md transition-colors hover:bg-secondary dark:text-cyan-400"
                                        :title="`Click to sort by Attendance (${sortColumn === 'attendance' ? sortDirection : 'click to sort'})`"
                                        @click="toggleSort('attendance')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="font-mono text-[9px] uppercase tracking-wider">Attendance</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'attendance' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'attendance' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="mt-0.5 block font-medium text-foreground">{{ gradingWeights.attendance }}%</span>
                                    </th>

                                    <!-- Oral Recitation Bonus Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l-2 border-amber-500/40 bg-amber-500/15 px-2.5 py-3 text-center font-bold text-amber-700 backdrop-blur-md transition-colors hover:bg-amber-500/25 dark:text-amber-400"
                                        :title="`Click to sort by Oral Bonus (${sortColumn === 'recitation' ? sortDirection : 'click to sort'})`"
                                        @click="toggleSort('recitation')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span>Oral Bonus</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'recitation' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'recitation' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="block text-[9px] font-normal text-amber-600 dark:text-amber-300">
                                            +{{ gradingWeights.recitation ?? 5 }} pts → Activities
                                        </span>
                                    </th>

                                    <!-- Midterm Grade Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l-2 border-purple-500/40 bg-purple-500/15 px-3 py-3 text-center font-bold text-purple-900 backdrop-blur-md transition-colors hover:bg-purple-500/25 dark:text-purple-300"
                                        :title="`Click to sort by Midterm Grade (${sortColumn === 'midterm' ? sortDirection : 'click to sort'})`"
                                        @click="toggleSort('midterm')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span>Midterm Grade</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'midterm' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'midterm' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="block text-[9px] font-normal text-purple-700 dark:text-purple-400"> Tasks & Exam (50%) </span>
                                    </th>

                                    <!-- Final Period Grade Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l-2 border-indigo-500/40 bg-indigo-500/15 px-3 py-3 text-center font-bold text-indigo-900 backdrop-blur-md transition-colors hover:bg-indigo-500/25 dark:text-indigo-300"
                                        :title="`Click to sort by Final Period (${sortColumn === 'final_period' ? sortDirection : 'click to sort'})`"
                                        @click="toggleSort('final_period')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span>Final Period</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'final_period' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'final_period' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="block text-[9px] font-normal text-indigo-700 dark:text-indigo-400"> Tasks & Reports (50%) </span>
                                    </th>

                                    <!-- Semestral Final Grade Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l-2 border-primary/40 bg-primary/20 px-3 py-3 text-center font-bold text-foreground backdrop-blur-md transition-colors hover:bg-primary/30"
                                        :title="`Click to sort by Semestral Percentage (${sortColumn === 'weighted_grade' ? sortDirection : 'click to sort'})`"
                                        @click="toggleSort('weighted_grade')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span>Semestral %</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'weighted_grade' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'weighted_grade' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="block text-[9px] font-normal text-muted-foreground"> Combined Grade </span>
                                    </th>

                                    <!-- Rating Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-24 cursor-pointer select-none border-b border-l-2 border-primary/40 bg-primary/20 px-3 py-3 text-center font-bold text-foreground backdrop-blur-md transition-colors hover:bg-primary/30"
                                        :title="`Click to sort by Rating (${sortColumn === 'scale_grade' ? sortDirection : 'click to sort'})`"
                                        @click="toggleSort('scale_grade')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span>Rating</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'scale_grade' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'scale_grade' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                    </th>
                                </tr>

                                <!-- DETAILED RAW VIEW HEADER -->
                                <tr v-else>
                                    <th
                                        class="group/th shadow-xs sticky left-0 top-0 z-30 min-w-52 cursor-pointer select-none border-b border-r border-border/80 bg-card/95 px-4 py-3 backdrop-blur-md transition-colors hover:bg-secondary/80 print:static print:bg-gray-100"
                                        :title="`Click to sort by Student (${sortColumn === 'student' ? sortDirection : 'click to sort'})`"
                                        @click="toggleSort('student')"
                                    >
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-bold text-foreground">Student</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'student' && sortDirection === 'asc'"
                                                class="size-3.5 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'student' && sortDirection === 'desc'"
                                                class="size-3.5 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3.5 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="block text-[9px] font-normal lowercase text-muted-foreground print:hidden">
                                            (click student to view tasks)
                                        </span>
                                    </th>
                                    <!-- Assessment columns -->
                                    <th
                                        v-for="item in filteredAssessments"
                                        :key="item.id"
                                        class="group/th sticky top-0 z-20 min-w-24 cursor-pointer select-none border-b border-l border-border/60 bg-secondary/95 px-3 py-3 text-center backdrop-blur-md transition-colors hover:bg-secondary"
                                        :title="`Click to sort by ${item.title}`"
                                        @click="toggleSort(`assessment-${item.id}`)"
                                    >
                                        <div class="flex items-center justify-center gap-0.5">
                                            <span
                                                class="block font-mono text-[9px] font-medium uppercase tracking-wider"
                                                :class="
                                                    item.type === 'exam'
                                                        ? 'text-purple-600 dark:text-purple-400'
                                                        : item.type === 'quiz'
                                                          ? 'text-blue-600 dark:text-blue-400'
                                                          : item.type === 'laboratory'
                                                            ? 'text-cyan-600 dark:text-cyan-400'
                                                            : 'text-emerald-600 dark:text-emerald-400'
                                                "
                                            >
                                                {{ item.type === 'laboratory' ? 'Lab' : item.type }}
                                                <span v-if="item.computed_period" class="ml-1 opacity-70"
                                                    >({{ item.computed_period === 'midterm' ? 'Mid' : 'Fin' }})</span
                                                >
                                            </span>
                                            <ArrowUp
                                                v-if="sortColumn === `assessment-${item.id}` && sortDirection === 'asc'"
                                                class="size-2.5 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === `assessment-${item.id}` && sortDirection === 'desc'"
                                                class="size-2.5 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-2.5 shrink-0 text-muted-foreground/30 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="mx-auto mt-0.5 block max-w-24 truncate font-medium text-foreground">{{ item.title }}</span>
                                        <span class="font-mono text-[10px] text-muted-foreground">/ {{ item.max_points }}</span>
                                    </th>
                                    <!-- Group Activity columns -->
                                    <th
                                        v-for="item in filteredGroupActivities"
                                        :key="`group-act-${item.id}`"
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l border-emerald-500/30 bg-emerald-500/10 px-3 py-3 text-center backdrop-blur-md transition-colors hover:bg-emerald-500/20"
                                        :title="`Click to sort by ${item.title}`"
                                        @click="toggleSort(`gact-${item.id}`)"
                                    >
                                        <div class="flex items-center justify-center gap-0.5">
                                            <span
                                                class="block font-mono text-[9px] font-medium uppercase tracking-wider text-emerald-600 dark:text-emerald-400"
                                            >
                                                Group Act
                                                <span v-if="item.computed_period" class="ml-1 opacity-70"
                                                    >({{ item.computed_period === 'midterm' ? 'Mid' : 'Fin' }})</span
                                                >
                                            </span>
                                            <ArrowUp
                                                v-if="sortColumn === `gact-${item.id}` && sortDirection === 'asc'"
                                                class="size-2.5 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === `gact-${item.id}` && sortDirection === 'desc'"
                                                class="size-2.5 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-2.5 shrink-0 text-muted-foreground/30 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="mx-auto mt-0.5 block max-w-28 truncate font-medium text-foreground">{{ item.title }}</span>
                                        <span class="font-mono text-[10px] text-muted-foreground">/ {{ item.max_points }}</span>
                                    </th>
                                    <!-- Project columns -->
                                    <th
                                        v-for="item in filteredProjects"
                                        :key="`project-${item.id}`"
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l border-teal-500/30 bg-teal-500/10 px-3 py-3 text-center backdrop-blur-md transition-colors hover:bg-teal-500/20"
                                        :title="`Click to sort by ${item.title}`"
                                        @click="toggleSort(`project-${item.id}`)"
                                    >
                                        <div class="flex items-center justify-center gap-0.5">
                                            <span
                                                class="block font-mono text-[9px] font-medium uppercase tracking-wider text-teal-600 dark:text-teal-400"
                                            >
                                                {{ item.type === 'project' ? 'Project' : 'Report' }}
                                                <span
                                                    v-if="item.type === 'reporting' && reportingFrequency === 'once_per_sem'"
                                                    class="ml-1 opacity-80"
                                                    >(Finals)</span
                                                >
                                                <span v-else-if="item.computed_period" class="ml-1 opacity-70"
                                                    >({{ item.computed_period === 'midterm' ? 'Mid' : 'Fin' }})</span
                                                >
                                            </span>
                                            <ArrowUp
                                                v-if="sortColumn === `project-${item.id}` && sortDirection === 'asc'"
                                                class="size-2.5 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === `project-${item.id}` && sortDirection === 'desc'"
                                                class="size-2.5 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-2.5 shrink-0 text-muted-foreground/30 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="mx-auto mt-0.5 block max-w-28 truncate font-medium text-foreground">{{ item.title }}</span>
                                        <span class="font-mono text-[10px] text-muted-foreground">/ {{ item.max_points }}</span>
                                    </th>
                                    <!-- Standard category totals with % beside overall score -->
                                    <th
                                        v-for="type in activeTypes"
                                        :key="`total-${type}`"
                                        class="group/th sticky top-0 z-20 min-w-32 cursor-pointer select-none border-b border-l-2 border-border bg-secondary/95 px-3 py-3 text-center backdrop-blur-md transition-colors hover:bg-secondary"
                                        :title="`Click to sort by ${type} total`"
                                        @click="toggleSort(type)"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span
                                                class="block font-mono text-[9px] font-bold uppercase tracking-wider"
                                                :class="
                                                    type === 'exam'
                                                        ? 'text-purple-600 dark:text-purple-400'
                                                        : type === 'quiz'
                                                          ? 'text-blue-600 dark:text-blue-400'
                                                          : type === 'laboratory'
                                                            ? 'text-cyan-600 dark:text-cyan-400'
                                                            : 'text-emerald-600 dark:text-emerald-400'
                                                "
                                            >
                                                {{
                                                    type === 'laboratory'
                                                        ? 'Lab Total'
                                                        : type === 'exam'
                                                          ? 'Exams Total'
                                                          : type === 'activity'
                                                            ? 'Activities Total'
                                                            : 'Quizzes Total'
                                                }}
                                            </span>
                                            <ArrowUp v-if="sortColumn === type && sortDirection === 'asc'" class="size-3 shrink-0 text-primary" />
                                            <ArrowDown
                                                v-else-if="sortColumn === type && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="mt-0.5 block font-mono text-[10px] text-muted-foreground">
                                            / {{ categorySummary[type]?.possible ?? 0 }} ({{ gradingWeights[type] }}%)
                                        </span>
                                    </th>
                                    <!-- Project Total -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-32 cursor-pointer select-none border-b border-l-2 border-teal-500/30 bg-teal-500/10 px-3 py-3 text-center backdrop-blur-md transition-colors hover:bg-teal-500/20"
                                        :title="`Click to sort by Project total`"
                                        @click="toggleSort('project')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span
                                                class="block font-mono text-[9px] font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400"
                                            >
                                                Proj Total
                                            </span>
                                            <ArrowUp
                                                v-if="sortColumn === 'project' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'project' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                        <span class="mt-0.5 block font-mono text-[10px] text-muted-foreground">
                                            / {{ projectSummary?.possible ?? 0 }} ({{ gradingWeights.project }}%)
                                        </span>
                                    </th>
                                    <!-- Attendance % -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-20 cursor-pointer select-none border-b border-l-2 border-border bg-secondary/95 px-2.5 py-3 text-center font-medium text-cyan-600 backdrop-blur-md transition-colors hover:bg-secondary dark:text-cyan-400"
                                        :title="`Click to sort by Attendance`"
                                        @click="toggleSort('attendance')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span>Att %</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'attendance' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'attendance' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                    </th>
                                    <!-- Oral Recitation Bonus Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l-2 border-amber-500/40 bg-amber-500/15 px-2.5 py-3 text-center font-bold text-amber-700 backdrop-blur-md transition-colors hover:bg-amber-500/25 dark:text-amber-400"
                                        :title="`Click to sort by Oral Bonus`"
                                        @click="toggleSort('recitation')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span>Oral Bonus</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'recitation' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'recitation' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                    </th>
                                    <!-- Midterm Grade Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l-2 border-purple-500/40 bg-purple-500/15 px-3 py-3 text-center font-bold text-purple-900 backdrop-blur-md transition-colors hover:bg-purple-500/25 dark:text-purple-300"
                                        :title="`Click to sort by Midterm Grade`"
                                        @click="toggleSort('midterm')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span>Midterm Grade</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'midterm' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'midterm' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                    </th>
                                    <!-- Final Period Grade Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l-2 border-indigo-500/40 bg-indigo-500/15 px-3 py-3 text-center font-bold text-indigo-900 backdrop-blur-md transition-colors hover:bg-indigo-500/25 dark:text-indigo-300"
                                        :title="`Click to sort by Final Period Grade`"
                                        @click="toggleSort('final_period')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span>Final Period</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'final_period' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'final_period' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                    </th>
                                    <!-- Semestral Final Grade Column -->
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-28 cursor-pointer select-none border-b border-l-2 border-primary/40 bg-primary/20 px-3 py-3 text-center font-bold text-foreground backdrop-blur-md transition-colors hover:bg-primary/30"
                                        :title="`Click to sort by Semestral Percentage`"
                                        @click="toggleSort('weighted_grade')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span>Semestral %</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'weighted_grade' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'weighted_grade' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                    </th>
                                    <th
                                        class="group/th sticky top-0 z-20 min-w-24 cursor-pointer select-none border-b border-l-2 border-primary/40 bg-primary/20 px-3 py-3 text-center font-bold text-foreground backdrop-blur-md transition-colors hover:bg-primary/30"
                                        :title="`Click to sort by Rating`"
                                        @click="toggleSort('scale_grade')"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span>Rating</span>
                                            <ArrowUp
                                                v-if="sortColumn === 'scale_grade' && sortDirection === 'asc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowDown
                                                v-else-if="sortColumn === 'scale_grade' && sortDirection === 'desc'"
                                                class="size-3 shrink-0 text-primary"
                                            />
                                            <ArrowUpDown
                                                v-else
                                                class="size-3 shrink-0 text-muted-foreground/40 opacity-0 transition-opacity group-hover/th:opacity-100"
                                            />
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in sortedRows" :key="row.id" class="break-inside-avoid transition-colors hover:bg-secondary/30">
                                    <!-- SUMMARY VIEW BODY -->
                                    <template v-if="viewMode === 'summary'">
                                        <td
                                            class="backdrop-blur-xs group/student sticky left-0 z-10 cursor-pointer border-b border-r border-border/50 bg-card/95 px-4 py-3 transition-colors hover:bg-secondary/80 print:static print:bg-white"
                                            title="Click to view detailed activity logs and student standing"
                                            @click="openStudentModal(row, 'activity_log')"
                                        >
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="min-w-0">
                                                    <span
                                                        class="block truncate font-medium text-foreground transition-colors group-hover/student:text-primary group-hover/student:underline"
                                                    >
                                                        {{ row.full_name }}
                                                    </span>
                                                    <span class="font-mono text-[10px] text-muted-foreground">{{ row.student_number }}</span>
                                                </div>
                                                <span
                                                    v-if="hasDeficiencies(row)"
                                                    class="shadow-2xs shrink-0 rounded-full border border-rose-800 bg-rose-700 px-1.5 py-0.5 text-[9px] font-bold text-white print:hidden"
                                                    title="Has missing or failing items"
                                                >
                                                    {{ countDeficiencies(row) }} def
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Activities Summary Cell -->
                                        <td
                                            class="whitespace-nowrap border-b border-l border-border/60 bg-secondary/10 px-3 py-3 text-center font-mono"
                                        >
                                            <div class="inline-flex items-baseline justify-center gap-1">
                                                <span class="text-xs font-bold text-foreground">
                                                    {{ row.categories.activity?.earned ?? 0 }} / {{ row.categories.activity?.possible ?? 0 }}
                                                </span>
                                                <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                                    ({{
                                                        row.categories.activity?.percentage !== null ? `${row.categories.activity.percentage}%` : '—'
                                                    }})
                                                </span>
                                            </div>
                                            <span
                                                v-if="row.categories.activity?.bonus_earned && row.categories.activity.bonus_earned > 0"
                                                class="block text-[9px] text-amber-700 dark:text-amber-400"
                                            >
                                                +{{ row.categories.activity.bonus_earned }} oral bonus
                                            </span>
                                        </td>

                                        <!-- Quizzes Summary Cell -->
                                        <td
                                            class="whitespace-nowrap border-b border-l border-border/60 bg-secondary/10 px-3 py-3 text-center font-mono"
                                        >
                                            <div class="inline-flex items-baseline justify-center gap-1">
                                                <span class="text-xs font-bold text-foreground">
                                                    {{ row.categories.quiz?.earned ?? 0 }} / {{ row.categories.quiz?.possible ?? 0 }}
                                                </span>
                                                <span class="text-xs font-semibold text-blue-700 dark:text-blue-400">
                                                    ({{ row.categories.quiz?.percentage !== null ? `${row.categories.quiz.percentage}%` : '—' }})
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Laboratory Summary Cell (if present) -->
                                        <td
                                            v-if="(categorySummary.laboratory?.count ?? 0) > 0 || (gradingWeights.laboratory ?? 0) > 0"
                                            class="whitespace-nowrap border-b border-l border-border/60 bg-secondary/10 px-3 py-3 text-center font-mono"
                                        >
                                            <div class="inline-flex items-baseline justify-center gap-1">
                                                <span class="text-xs font-bold text-foreground">
                                                    {{ row.categories.laboratory?.earned ?? 0 }} / {{ row.categories.laboratory?.possible ?? 0 }}
                                                </span>
                                                <span class="text-xs font-semibold text-cyan-700 dark:text-cyan-400">
                                                    ({{
                                                        row.categories.laboratory?.percentage !== null
                                                            ? `${row.categories.laboratory.percentage}%`
                                                            : '—'
                                                    }})
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Major Exams Summary Cell -->
                                        <td
                                            class="whitespace-nowrap border-b border-l border-border/60 bg-secondary/10 px-3 py-3 text-center font-mono"
                                        >
                                            <div class="inline-flex items-baseline justify-center gap-1">
                                                <span class="text-xs font-bold text-foreground">
                                                    {{ row.categories.exam?.earned ?? 0 }} / {{ row.categories.exam?.possible ?? 0 }}
                                                </span>
                                                <span class="text-xs font-semibold text-purple-700 dark:text-purple-400">
                                                    ({{ row.categories.exam?.percentage !== null ? `${row.categories.exam.percentage}%` : '—' }})
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Project / Reporting Cell -->
                                        <td
                                            class="whitespace-nowrap border-b border-l border-teal-500/20 bg-teal-500/5 px-3 py-3 text-center font-mono"
                                        >
                                            <div class="inline-flex items-baseline justify-center gap-1">
                                                <span class="text-xs font-bold text-foreground">
                                                    {{ row.projectSummary?.earned ?? 0 }} / {{ row.projectSummary?.possible ?? 0 }}
                                                </span>
                                                <span class="text-xs font-semibold text-teal-700 dark:text-teal-400">
                                                    ({{ row.projectSummary?.percentage !== null ? `${row.projectSummary.percentage}%` : '—' }})
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Attendance Cell -->
                                        <td class="border-b border-l border-border/60 bg-secondary/10 px-2.5 py-3 text-center font-mono">
                                            <span class="block text-xs font-bold text-cyan-700 dark:text-cyan-400">
                                                {{ row.attendance?.percentage !== null ? `${row.attendance.percentage}%` : '—' }}
                                            </span>
                                            <span class="block text-[10px] text-muted-foreground">
                                                {{ row.attendance?.present_count ?? 0 }}/{{ row.attendance?.total_sessions ?? 0 }} sess
                                            </span>
                                        </td>

                                        <!-- Oral Recitation Bonus Cell -->
                                        <td
                                            class="group/oral relative border-b border-l-2 border-amber-500/30 bg-amber-500/5 px-2.5 py-3 text-center font-mono"
                                        >
                                            <template v-if="row.recitation && row.recitation.count > 0 && row.recitation.avg_score !== null">
                                                <span
                                                    class="shadow-2xs inline-flex items-center gap-1 rounded-full bg-emerald-700 px-2.5 py-0.5 text-xs font-bold text-white"
                                                >
                                                    +{{ row.recitation.bonus_points ?? 0 }} pts
                                                </span>
                                                <span class="mt-0.5 block text-[9px] text-muted-foreground">
                                                    {{ row.recitation.count }} rec ({{ row.recitation.avg_score }}/10)
                                                </span>
                                            </template>
                                            <span v-else class="text-xs text-muted-foreground">—</span>

                                            <button
                                                v-if="!printMode"
                                                type="button"
                                                class="mt-1 inline-flex items-center gap-1 rounded border border-border bg-card px-1.5 py-0.5 text-[9px] font-medium text-muted-foreground opacity-0 transition-opacity hover:bg-secondary hover:text-foreground group-hover/oral:opacity-100 print:hidden"
                                                title="Override oral points for this student"
                                                @click.stop="openOralOverrideModal(row)"
                                            >
                                                <Mic class="size-2.5" />
                                                <span>Override</span>
                                            </button>
                                        </td>

                                        <!-- Midterm Grade Cell -->
                                        <td class="border-b border-l-2 border-purple-500/30 bg-purple-500/5 px-3 py-3 text-center font-mono">
                                            <div v-if="row.midterm?.weighted_grade !== null && row.midterm?.weighted_grade !== undefined">
                                                <span class="block text-xs font-bold text-purple-900 dark:text-purple-300">
                                                    {{ row.midterm.weighted_grade }}%
                                                </span>
                                                <span
                                                    class="shadow-2xs mt-0.5 inline-block rounded px-1.5 py-0.5 text-[10px] font-bold"
                                                    :class="gradeBadgeBg(row.midterm.scale_grade)"
                                                >
                                                    {{ row.midterm.scale_grade }}
                                                </span>
                                            </div>
                                            <span v-else class="text-xs text-muted-foreground">—</span>
                                        </td>

                                        <!-- Final Period Grade Cell -->
                                        <td class="border-b border-l-2 border-indigo-500/30 bg-indigo-500/5 px-3 py-3 text-center font-mono">
                                            <div v-if="row.final_period?.weighted_grade !== null && row.final_period?.weighted_grade !== undefined">
                                                <span class="block text-xs font-bold text-indigo-900 dark:text-indigo-300">
                                                    {{ row.final_period.weighted_grade }}%
                                                </span>
                                                <span
                                                    class="shadow-2xs mt-0.5 inline-block rounded px-1.5 py-0.5 text-[10px] font-bold"
                                                    :class="gradeBadgeBg(row.final_period.scale_grade)"
                                                >
                                                    {{ row.final_period.scale_grade }}
                                                </span>
                                            </div>
                                            <span v-else class="text-xs text-muted-foreground">—</span>
                                        </td>

                                        <!-- Semestral Final % -->
                                        <td
                                            class="border-b border-l-2 border-primary/30 bg-primary/10 px-3 py-3 text-center font-mono text-sm font-bold text-foreground"
                                        >
                                            <span>{{ row.weighted_grade !== null ? `${row.weighted_grade}%` : '—' }}</span>
                                        </td>

                                        <!-- Rating -->
                                        <td class="border-b border-l-2 border-primary/30 bg-primary/10 px-3 py-3 text-center">
                                            <span
                                                class="shadow-xs inline-flex min-w-[52px] items-center justify-center rounded-full border px-2.5 py-1 text-xs font-bold"
                                                :class="gradeBadgeBg(row.scale_grade)"
                                            >
                                                {{ gradeDisplay(row.scale_grade) }}
                                            </span>
                                        </td>
                                    </template>

                                    <!-- DETAILED RAW VIEW BODY -->
                                    <template v-else>
                                        <td
                                            class="backdrop-blur-xs group/student sticky left-0 z-10 cursor-pointer border-b border-r border-border/50 bg-card/95 px-4 py-3 transition-colors hover:bg-secondary/80 print:static print:bg-white"
                                            title="Click to view failing or uncomplied activities and projects"
                                            @click="openStudentModal(row, 'activity_log')"
                                        >
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="min-w-0">
                                                    <span
                                                        class="block truncate font-medium text-foreground transition-colors group-hover/student:text-primary group-hover/student:underline"
                                                    >
                                                        {{ row.full_name }}
                                                    </span>
                                                    <span class="font-mono text-[10px] text-muted-foreground">{{ row.student_number }}</span>
                                                </div>
                                                <span
                                                    v-if="hasDeficiencies(row)"
                                                    class="shadow-2xs shrink-0 rounded-full border border-rose-800 bg-rose-700 px-1.5 py-0.5 text-[9px] font-bold text-white print:hidden"
                                                    title="Has missing or failing items"
                                                >
                                                    {{ countDeficiencies(row) }} def
                                                </span>
                                            </div>
                                        </td>
                                        <!-- Standard scores -->
                                        <td
                                            v-for="item in filteredAssessments"
                                            :key="item.id"
                                            class="border-b border-l border-border/60 px-3 py-3 text-center font-mono text-xs"
                                            :class="row.scores[item.id] === null ? 'text-muted-foreground/60' : 'font-medium text-foreground'"
                                            :title="row.remarks?.[item.id] ? `Remarks / Justification: ${row.remarks[item.id]}` : undefined"
                                        >
                                            <div class="relative inline-flex items-center justify-center gap-0.5">
                                                <span>{{ row.scores[item.id] ?? '—' }}</span>
                                                <span
                                                    v-if="row.remarks?.[item.id]"
                                                    class="inline-block size-1.5 shrink-0 rounded-full bg-primary"
                                                    :title="`Remarks: ${row.remarks[item.id]}`"
                                                />
                                            </div>
                                        </td>
                                        <!-- Group Activity scores -->
                                        <td
                                            v-for="item in filteredGroupActivities"
                                            :key="`score-gact-${item.id}`"
                                            class="border-b border-l border-emerald-500/20 bg-emerald-500/5 px-3 py-3 text-center font-mono text-xs"
                                            :class="
                                                row.group_activity_scores?.[item.id] === null || row.group_activity_scores?.[item.id] === undefined
                                                    ? 'text-muted-foreground/60'
                                                    : 'font-medium text-foreground'
                                            "
                                        >
                                            <span>{{ row.group_activity_scores?.[item.id] ?? '—' }}</span>
                                        </td>
                                        <!-- Project scores -->
                                        <td
                                            v-for="item in filteredProjects"
                                            :key="`score-proj-${item.id}`"
                                            class="border-b border-l border-teal-500/20 bg-teal-500/5 px-3 py-3 text-center font-mono text-xs"
                                            :class="row.project_scores[item.id] === null ? 'text-muted-foreground/60' : 'font-medium text-foreground'"
                                            :title="
                                                row.project_notes?.[item.id] ? `Remarks / Justification: ${row.project_notes[item.id]}` : undefined
                                            "
                                        >
                                            <div class="relative inline-flex items-center justify-center gap-0.5">
                                                <span>{{
                                                    item.type === 'group_activity'
                                                        ? (row.group_activity_scores?.[item.id] ?? row.project_scores[item.id] ?? '—')
                                                        : (row.project_scores[item.id] ?? '—')
                                                }}</span>
                                                <span
                                                    v-if="row.project_notes?.[item.id]"
                                                    class="inline-block size-1.5 shrink-0 rounded-full bg-primary"
                                                    :title="`Remarks: ${row.project_notes[item.id]}`"
                                                />
                                            </div>
                                        </td>
                                        <!-- Category Subtotal: Activities Total with % in () beside overall score -->
                                        <td
                                            v-for="type in activeTypes"
                                            :key="`subtotal-${type}`"
                                            class="whitespace-nowrap border-b border-l-2 border-border bg-secondary/15 px-3 py-3 text-center font-mono"
                                        >
                                            <div class="inline-flex items-baseline justify-center gap-1">
                                                <span class="text-xs font-bold text-foreground">
                                                    {{ row.categories[type]?.earned ?? 0 }} / {{ row.categories[type]?.possible ?? 0 }}
                                                </span>
                                                <span
                                                    class="text-xs font-semibold"
                                                    :class="
                                                        type === 'exam'
                                                            ? 'text-purple-700 dark:text-purple-400'
                                                            : type === 'quiz'
                                                              ? 'text-blue-700 dark:text-blue-400'
                                                              : type === 'laboratory'
                                                                ? 'text-cyan-700 dark:text-cyan-400'
                                                                : 'text-emerald-700 dark:text-emerald-400'
                                                    "
                                                >
                                                    ({{ row.categories[type]?.percentage !== null ? `${row.categories[type]?.percentage}%` : '—' }})
                                                </span>
                                            </div>
                                            <span
                                                v-if="
                                                    type === 'activity' &&
                                                    row.categories.activity?.bonus_earned &&
                                                    row.categories.activity.bonus_earned > 0
                                                "
                                                class="mt-0.5 block text-[9px] font-semibold text-emerald-700 dark:text-emerald-400"
                                                title="Includes oral bonus added to activity score"
                                            >
                                                +{{ row.categories.activity.bonus_earned }} oral
                                            </span>
                                        </td>
                                        <!-- Project total with % in () beside overall score -->
                                        <td class="whitespace-nowrap border-b border-l-2 border-border bg-teal-500/5 px-3 py-3 text-center font-mono">
                                            <div class="inline-flex items-baseline justify-center gap-1">
                                                <span class="text-xs font-bold text-foreground">
                                                    {{ row.projectSummary?.earned ?? 0 }} / {{ row.projectSummary?.possible ?? 0 }}
                                                </span>
                                                <span class="text-xs font-semibold text-teal-700 dark:text-teal-400">
                                                    ({{ row.projectSummary?.percentage !== null ? `${row.projectSummary.percentage}%` : '—' }})
                                                </span>
                                            </div>
                                        </td>
                                        <!-- Attendance percentage -->
                                        <td
                                            class="border-b border-l-2 border-border bg-secondary/20 px-2.5 py-3 text-center font-mono text-xs font-medium text-cyan-700 dark:text-cyan-400"
                                        >
                                            {{ row.attendance?.percentage !== null ? `${row.attendance.percentage}%` : '—' }}
                                        </td>
                                        <!-- Recitation Bonus Points Cell -->
                                        <td
                                            class="group/oral relative border-b border-l-2 border-amber-500/30 bg-amber-500/5 px-2.5 py-3 text-center font-mono"
                                        >
                                            <template v-if="row.recitation && row.recitation.count > 0 && row.recitation.avg_score !== null">
                                                <span
                                                    class="shadow-2xs inline-flex items-center gap-1 rounded-full bg-emerald-700 px-2.5 py-0.5 text-xs font-bold text-white"
                                                >
                                                    +{{ row.recitation.bonus_points ?? 0 }} pts
                                                </span>
                                                <span class="mt-0.5 block text-[9px] text-muted-foreground">
                                                    {{ row.recitation.count }} rec ({{ row.recitation.avg_score }}/10)
                                                </span>
                                            </template>
                                            <span v-else class="text-xs text-muted-foreground">—</span>

                                            <button
                                                v-if="!printMode"
                                                type="button"
                                                class="mt-1 inline-flex items-center gap-1 rounded border border-border bg-card px-1.5 py-0.5 text-[9px] font-medium text-muted-foreground opacity-0 transition-opacity hover:bg-secondary hover:text-foreground group-hover/oral:opacity-100 print:hidden"
                                                title="Override oral points for this student"
                                                @click.stop="openOralOverrideModal(row)"
                                            >
                                                <Mic class="size-2.5" />
                                                <span>Override</span>
                                            </button>
                                        </td>

                                        <!-- Midterm Grade Cell -->
                                        <td class="border-b border-l-2 border-purple-500/30 bg-purple-500/5 px-3 py-3 text-center font-mono">
                                            <div v-if="row.midterm?.weighted_grade !== null && row.midterm?.weighted_grade !== undefined">
                                                <span class="block text-xs font-bold text-purple-900 dark:text-purple-300">
                                                    {{ row.midterm.weighted_grade }}%
                                                </span>
                                                <span
                                                    class="shadow-2xs mt-0.5 inline-block rounded px-1.5 py-0.5 text-[10px] font-bold"
                                                    :class="gradeBadgeBg(row.midterm.scale_grade)"
                                                >
                                                    {{ row.midterm.scale_grade }}
                                                </span>
                                            </div>
                                            <span v-else class="text-xs text-muted-foreground">—</span>
                                        </td>

                                        <!-- Final Period Grade Cell -->
                                        <td class="border-b border-l-2 border-indigo-500/30 bg-indigo-500/5 px-3 py-3 text-center font-mono">
                                            <div v-if="row.final_period?.weighted_grade !== null && row.final_period?.weighted_grade !== undefined">
                                                <span class="block text-xs font-bold text-indigo-900 dark:text-indigo-300">
                                                    {{ row.final_period.weighted_grade }}%
                                                </span>
                                                <span
                                                    class="shadow-2xs mt-0.5 inline-block rounded px-1.5 py-0.5 text-[10px] font-bold"
                                                    :class="gradeBadgeBg(row.final_period.scale_grade)"
                                                >
                                                    {{ row.final_period.scale_grade }}
                                                </span>
                                            </div>
                                            <span v-else class="text-xs text-muted-foreground">—</span>
                                        </td>

                                        <!-- Semestral Final % -->
                                        <td
                                            class="border-b border-l-2 border-primary/30 bg-primary/10 px-3 py-3 text-center font-mono text-sm font-bold text-foreground"
                                        >
                                            <span>{{ row.weighted_grade !== null ? `${row.weighted_grade}%` : '—' }}</span>
                                        </td>
                                        <td class="border-b border-l-2 border-primary/30 bg-primary/10 px-3 py-3 text-center">
                                            <span
                                                class="shadow-xs inline-flex min-w-[52px] items-center justify-center rounded-full border px-2.5 py-1 text-xs font-bold"
                                                :class="gradeBadgeBg(row.scale_grade)"
                                            >
                                                {{ gradeDisplay(row.scale_grade) }}
                                            </span>
                                        </td>
                                    </template>
                                </tr>
                                <tr v-if="!rows.length">
                                    <td
                                        :colspan="
                                            viewMode === 'summary'
                                                ? 12
                                                : 7 +
                                                  filteredAssessments.length +
                                                  filteredGroupActivities.length +
                                                  filteredProjects.length +
                                                  activeTypes.length
                                        "
                                        class="py-12 text-center text-xs text-muted-foreground"
                                    >
                                        No students are enrolled in this section.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Grading Scale Legend & Calculation Guide -->
                <div class="paper-card mt-6 space-y-4 p-5 print:rounded-none print:border print:border-black print:bg-white">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">College Grading Scale & Period Computation</h3>
                        <span class="font-mono text-[11px] text-muted-foreground">
                            Semestral Grade = (50% Midterm Grade) + (50% Final Period Grade)
                        </span>
                    </div>

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
                            class="shadow-2xs rounded-lg border px-2 py-2 transition-colors"
                            :class="
                                entry.grade === 'INC' ? 'border-rose-800 bg-rose-700 text-white' : 'border-border/60 bg-secondary/30 text-foreground'
                            "
                        >
                            <span class="block font-medium" :class="entry.grade === 'INC' ? 'text-white' : 'text-foreground'">{{ entry.grade }}</span>
                            <span :class="entry.grade === 'INC' ? 'text-rose-100' : 'text-muted-foreground'">{{ entry.range }}</span>
                        </div>
                    </div>
                </div>

                <p class="mt-4 text-[11px] text-muted-foreground print:text-[8px]">
                    Note: Activities, quizzes, and attendance up to the Midterm Exam compute the Midterm Grade. Subsequent tasks and reports compute
                    the Final Period Grade. Oral recitations award bonus points directly to activities without increasing max possible points.
                </p>
            </div>
        </main>

        <!-- Student Deficiencies & Activity Log Detail Modal -->
        <StudentDeficienciesModal
            :student="selectedStudent"
            :assessments="assessments"
            :group-activities="groupActivitiesList"
            :projects="projectsList"
            :grading-weights="gradingWeights"
            :section-name="section.name"
            :subject-code="section.subject_code"
            :open="isModalOpen"
            :initial-tab="modalInitialTab"
            @close="closeStudentModal"
        />

        <!-- Manual Oral Points Override Modal -->
        <OralPointsOverrideModal
            :open="showOralOverrideModal"
            :section="section"
            :students="rows"
            :preselected-student="selectedStudentForOral"
            :grading-weights="gradingWeights"
            @close="showOralOverrideModal = false"
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
