<script setup lang="ts">
import type { GradingFormulaSettings } from '@/types/grading';
import {
    AlertCircle,
    AlertTriangle,
    BookOpen,
    Check,
    CheckCircle2,
    ChevronDown,
    Copy,
    Flag,
    Printer,
    Search,
    TrendingUp,
    UserX,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

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
type PeriodGrade = { weighted_grade: number | null; scale_grade?: string };

export type StudentRow = {
    id: number;
    student_number: string;
    full_name: string;
    scores: Record<number, string | null>;
    score_absences?: Record<number, boolean>;
    remarks?: Record<number, string | null>;
    categories: Record<'activity' | 'quiz' | 'exam', Category> & Partial<Record<'laboratory', Category>>;
    group_activity_scores?: Record<number, number | null>;
    project_scores: Record<number, number | null>;
    project_notes?: Record<number, string | null>;
    projectSummary: ProjectSummary;
    attendance: AttendanceSummary;
    recitation: Recitation;
    weighted_grade?: number | null;
    scale_grade?: string;
    midterm?: PeriodGrade;
    final_period?: PeriodGrade;
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
    status: 'passed' | 'failed' | 'missing' | 'absent';
    remarks?: string | null;
    isProject?: boolean;
};

const props = withDefaults(
    defineProps<{
        student: StudentRow | null;
        assessments: Assessment[];
        groupActivities?: ProjectItem[];
        projects: ProjectItem[];
        gradingWeights: {
            activity?: number;
            quiz?: number;
            exam?: number;
            project?: number;
            attendance?: number;
            passing_rates?: Record<string, number>;
        };
        gradingFormula?: GradingFormulaSettings;
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
const searchQuery = ref('');
const searchOpen = ref(false);
const searchInput = ref<HTMLInputElement | null>(null);
const attendanceExpanded = ref(false);
type CategoryKey = 'quiz' | 'activity' | 'exam' | 'laboratory' | 'project';
const expandedCategories = ref<CategoryKey[]>([]);
const toggleCategory = (key: CategoryKey) => {
    expandedCategories.value = expandedCategories.value.includes(key)
        ? expandedCategories.value.filter((value) => value !== key)
        : [...expandedCategories.value, key];
};
const formatPoints = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(value);
const tabs = computed(() => [
    { value: 'activity_log' as const, label: 'Activity log', count: allActivityLogs.value.length },
    { value: 'deficiencies' as const, label: 'All deficiencies', count: totalDeficiencies.value },
    { value: 'missing' as const, label: 'Uncomplied', count: totalMissingCount.value },
    { value: 'failing' as const, label: 'Failing scores', count: totalFailingCount.value },
]);
const logFilters = computed(() => [
    { value: 'all' as const, label: 'All types' },
    { value: 'activity' as const, label: 'Activities' },
    { value: 'quiz' as const, label: 'Quizzes' },
    ...(allActivityLogs.value.some((item) => item.type === 'laboratory') ? [{ value: 'laboratory' as const, label: 'Laboratories' }] : []),
    { value: 'exam' as const, label: 'Exams' },
    ...(allActivityLogs.value.some((item) => item.type === 'project' || item.type === 'reporting')
        ? [{ value: 'project' as const, label: 'Projects & reports' }]
        : []),
]);
const toggleSearch = async () => {
    searchOpen.value = !searchOpen.value;
    if (searchOpen.value) {
        await nextTick();
        searchInput.value?.focus();
    } else {
        searchQuery.value = '';
    }
};
const periodGrade = (period?: PeriodGrade) => {
    if (period?.weighted_grade === null || period?.weighted_grade === undefined) return '-';
    if (period.scale_grade !== undefined) return ['—', '-'].includes(period.scale_grade) ? '-' : period.scale_grade;
    return percentToGrade(period.weighted_grade);
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            currentTab.value = props.initialTab || 'activity_log';
            logCategoryFilter.value = 'all';
            searchQuery.value = '';
            searchOpen.value = false;
            attendanceExpanded.value = false;
            expandedCategories.value =
                props.initialTab !== 'activity_log'
                    ? categoryGroups.value.filter((group) => group.visibleItems.length).map((group) => group.key)
                    : [];
            copied.value = false;
        }
    },
);

const readableDate = (date: string | null) => {
    if (!date) return 'No date set';
    try {
        const parsed = new Date(/^\d{4}-\d{2}-\d{2}$/.test(date) ? `${date}T00:00:00+08:00` : date);
        if (Number.isNaN(parsed.getTime())) return '-';
        return new Intl.DateTimeFormat('en-US', { month: 'short', day: '2-digit', year: 'numeric', timeZone: 'Asia/Manila' }).format(parsed);
    } catch {
        return '-';
    }
};

const percentToGrade = (pct: number | null): string => {
    if (pct === null) return '—';
    const ratio = Math.max(0, Math.min(100, pct)) / 100;
    const formula = props.gradingFormula;
    const worst = Number(formula?.worst_grade ?? 5);
    const best = Number(formula?.best_grade ?? 1);
    const rawGrade = worst - (worst - best) * ratio;
    const decimals = Number(formula?.decimal_places ?? 1);
    if ((formula?.replace_failing_grade ?? true) && rawGrade > Number(formula?.passing_grade ?? 3))
        return Number(formula?.failed_grade ?? 5).toFixed(decimals);
    const factor = 10 ** decimals;
    const scaled = Math.round(rawGrade * factor * 1e8) / 1e8;
    const mode = formula?.final_rounding ?? 'down';
    return ((mode === 'up' ? Math.ceil(scaled) : mode === 'nearest' ? Math.round(scaled) : Math.floor(scaled)) / factor).toFixed(decimals);
};

// Compute base coursework weighted percentage
const overallPercentage = computed<number | null>(() => {
    if (!props.student) return null;
    if (props.student.weighted_grade !== null && props.student.weighted_grade !== undefined) {
        return props.student.weighted_grade;
    }
    const w = {
        activity: Number(props.gradingWeights.activity ?? 0),
        quiz: Number(props.gradingWeights.quiz ?? 0),
        exam: Number(props.gradingWeights.exam ?? 0),
        project: Number(props.gradingWeights.project ?? 0),
        attendance: Number(props.gradingWeights.attendance ?? 0),
    };
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
    if (props.student?.scale_grade) {
        return props.student.scale_grade;
    }
    return percentToGrade(overallPercentage.value);
});

// Uncomplied / Missing Assessments (score is null)
const uncompliedAssessments = computed(() => {
    if (!props.student) return [];
    return props.assessments.filter((a) => {
        if (props.student?.score_absences?.[a.id]) return false;
        const val = props.student?.scores[a.id];
        return val === null || val === undefined || val === '';
    });
});

const passingRates = computed(() => ({
    quiz: Number(props.gradingWeights?.passing_rates?.quiz ?? 50),
    activity: Number(props.gradingWeights?.passing_rates?.activity ?? 50),
    project: Number(props.gradingWeights?.passing_rates?.project ?? 50),
    exam: Number(props.gradingWeights?.passing_rates?.exam ?? 50),
}));

const getPassingRateForAssessment = (type: string): number => {
    return (passingRates.value as Record<string, number>)[type] ?? 50;
};

const getPassingRateForProject = (type: string): number => {
    if (type === 'group_activity') {
        return passingRates.value.activity ?? 50;
    }
    return passingRates.value.project ?? 50;
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
        let status: ActivityLogEntry['status'] = props.student.score_absences?.[a.id] ? 'absent' : 'missing';

        if (status !== 'absent' && val !== null && val !== undefined && val !== '') {
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
    const query = searchQuery.value.trim().toLocaleLowerCase();
    return allActivityLogs.value.filter((item) => {
        if (currentTab.value === 'deficiencies' && item.status === 'passed') return false;
        if (currentTab.value === 'missing' && item.status !== 'missing') return false;
        if (currentTab.value === 'failing' && item.status !== 'failed') return false;
        if (logCategoryFilter.value === 'project' && !['project', 'reporting'].includes(item.type)) return false;
        if (logCategoryFilter.value === 'activity' && !['activity', 'group_activity'].includes(item.type)) return false;
        if (!['all', 'project', 'activity', 'deficiencies'].includes(logCategoryFilter.value) && item.type !== logCategoryFilter.value) return false;
        return (
            !query ||
            [item.title, item.typeLabel, item.remarks, readableDate(item.conducted_on), item.status === 'missing' ? 'uncomplied' : item.status].some(
                (value) => value?.toLocaleLowerCase().includes(query),
            )
        );
    });
});

const isFiltering = computed(() => currentTab.value !== 'activity_log' || logCategoryFilter.value !== 'all' || searchQuery.value.trim() !== '');
const categoryGroups = computed(() => {
    const definitions: { key: CategoryKey; label: string }[] = [
        { key: 'quiz', label: 'Quizzes' },
        { key: 'activity', label: 'Activities' },
        { key: 'exam', label: 'Exams' },
        { key: 'laboratory', label: 'Laboratories' },
        { key: 'project', label: 'Projects & reports' },
    ];
    const belongsTo = (item: ActivityLogEntry, key: CategoryKey) =>
        key === 'activity'
            ? item.type === 'activity' || item.type === 'group_activity'
            : key === 'project'
              ? item.type === 'project' || item.type === 'reporting'
              : item.type === key;
    return definitions
        .map((group) => {
            const items = allActivityLogs.value.filter((item) => belongsTo(item, group.key));
            const summary = group.key === 'project' ? props.student?.projectSummary : props.student?.categories[group.key];
            return {
                ...group,
                items,
                visibleItems: filteredActivityLogs.value.filter((item) => belongsTo(item, group.key)),
                earned: summary?.earned ?? items.reduce((total, item) => total + (item.score ?? 0), 0),
                possible: summary?.possible ?? items.reduce((total, item) => total + item.max_points, 0),
                bonus: group.key === 'project' ? 0 : (props.student?.categories[group.key]?.bonus_earned ?? 0),
            };
        })
        .filter((group) =>
            isFiltering.value ? group.visibleItems.length > 0 : group.items.length > 0 || ['quiz', 'activity', 'exam'].includes(group.key),
        );
});

watch([currentTab, logCategoryFilter, searchQuery], () => {
    expandedCategories.value = isFiltering.value ? categoryGroups.value.map((group) => group.key) : [];
});

// Copy text summary for student intervention
const copySummary = () => {
    if (!props.student) return;
    const lines: string[] = [];
    lines.push(`Academic Standing Notice`);
    lines.push(`Student: ${props.student.full_name} (${props.student.student_number})`);
    if (props.subjectCode) lines.push(`Course: ${props.subjectCode} - ${props.sectionName || ''}`);
    lines.push(`Current Running Grade: ${overallPercentage.value !== null ? `${overallPercentage.value}%` : 'N/A'} (Grade: ${overallGrade.value})`);
    lines.push(`Midterm Grade: ${periodGrade(props.student.midterm)}`);
    lines.push(`Final Grade (Final Period): ${periodGrade(props.student.final_period)}`);
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
            lines.push(`\n[FAILING SCORES]`);
            for (const a of failingAssessments.value) {
                lines.push(`- [${a.type.toUpperCase()}] ${a.title}: ${a.score}/${a.max} (${a.pct}%) · Below ${getPassingRateForAssessment(a.type)}%`);
            }
            for (const p of failingProjects.value) {
                lines.push(`- [PROJECT] ${p.title}: ${p.score}/${p.max} (${p.pct}%) · Below ${getPassingRateForProject(p.type)}%`);
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
    <div v-if="open && student" v-modal-focus class="meta-theme grade-overlay" @keydown.esc="emit('close')">
        <section class="grade-dialog" role="dialog" aria-modal="true" :aria-label="`Grade summary for ${student.full_name}`">
            <header class="grade-header">
                <div class="student-identity">
                    <div class="min-w-0">
                        <p class="eyebrow">Grade summary</p>
                        <h2>{{ student.full_name }}</h2>
                        <p v-if="subjectCode || sectionName" class="course-label">
                            {{ subjectCode }}<span v-if="subjectCode && sectionName"> · </span>{{ sectionName }}
                        </p>
                    </div>
                </div>
                <div class="header-actions print:hidden">
                    <button
                        type="button"
                        class="icon-action"
                        :aria-label="copied ? 'Report copied' : 'Copy report'"
                        :title="copied ? 'Report copied' : 'Copy report'"
                        @click="copySummary"
                    >
                        <Check v-if="copied" class="size-4" /><Copy v-else class="size-4" />
                    </button>
                    <button type="button" class="icon-action" aria-label="Print report" title="Print report" @click="printSlip">
                        <Printer class="size-4" />
                    </button>
                    <button type="button" class="icon-action" aria-label="Close grade summary" title="Close" @click="emit('close')">
                        <X class="size-4" />
                    </button>
                    <span class="sr-only" role="status">{{ copied ? 'Report copied to clipboard' : '' }}</span>
                </div>
            </header>

            <nav class="grade-tabs print:hidden" aria-label="Grade summary sections">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    :class="{ active: currentTab === tab.value }"
                    :aria-pressed="currentTab === tab.value"
                    @click="currentTab = tab.value"
                >
                    {{ tab.label }}<span>{{ tab.count }}</span>
                </button>
            </nav>

            <div class="log-toolbar print:hidden">
                <label class="filter-label"
                    ><span class="sr-only">Filter by activity type</span
                    ><select v-model="logCategoryFilter">
                        <option v-for="filter in logFilters" :key="filter.value" :value="filter.value">{{ filter.label }}</option>
                    </select></label
                >
                <span class="result-count">{{ filteredActivityLogs.length }} {{ filteredActivityLogs.length === 1 ? 'item' : 'items' }}</span>
                <button type="button" class="search-action" :aria-expanded="searchOpen" aria-controls="coursework-search" @click="toggleSearch">
                    <Search class="size-4" /><span>Search</span>
                </button>
            </div>
            <div v-if="searchOpen" id="coursework-search" class="log-search print:hidden">
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <input
                    ref="searchInput"
                    v-model="searchQuery"
                    type="search"
                    aria-label="Search coursework"
                    placeholder="Search title, type, date or feedback…"
                />
            </div>

            <div class="grade-content">
                <div v-if="filteredActivityLogs.length || !isFiltering" class="category-list">
                    <section
                        v-for="(group, groupIndex) in categoryGroups"
                        :key="group.key"
                        class="category-group"
                        :data-category="group.key"
                        :data-expanded="expandedCategories.includes(group.key)"
                    >
                        <h3>
                            <button
                                type="button"
                                class="category-toggle"
                                :aria-expanded="expandedCategories.includes(group.key)"
                                :aria-controls="'grade-category-' + group.key"
                                @click="toggleCategory(group.key)"
                            >
                                <span class="category-index" aria-hidden="true">{{ String(groupIndex + 1).padStart(2, '0') }}</span>
                                <span class="category-heading"
                                    ><span>{{ group.label }}</span
                                    ><small
                                        >{{ group.items.length }} {{ group.items.length === 1 ? 'item' : 'items'
                                        }}<template v-if="group.bonus > 0"> &middot; +{{ formatPoints(group.bonus) }} bonus points</template></small
                                    ></span
                                >
                                <span class="category-total"
                                    >{{ formatPoints(group.earned) }}<small> / {{ formatPoints(group.possible) }}</small
                                    ><span class="sr-only">points</span></span
                                >
                                <ChevronDown class="category-chevron size-4" :class="{ expanded: expandedCategories.includes(group.key) }" />
                            </button>
                        </h3>
                        <div v-show="expandedCategories.includes(group.key)" :id="'grade-category-' + group.key" class="category-details">
                            <div v-if="group.visibleItems.length" class="activity-list">
                                <article v-for="item in group.visibleItems" :key="item.key" class="activity-row">
                                    <div class="activity-main">
                                        <span class="activity-type" :data-type="item.type">{{ item.typeLabel }}</span>
                                        <h3>{{ item.title }}</h3>
                                        <p class="activity-date">Conducted {{ readableDate(item.conducted_on) }}</p>
                                    </div>
                                    <div class="activity-result" :class="item.status">
                                        <template v-if="item.status === 'absent'"
                                            ><span class="result-label"><AlertCircle class="size-3.5" />Absent</span></template
                                        >
                                        <template v-else-if="item.status === 'missing'"
                                            ><span class="result-label"><AlertTriangle class="size-3.5" />Uncomplied</span></template
                                        >
                                        <template v-else>
                                            <span class="score-value"
                                                >{{ item.score }}<span class="score-max"> / {{ item.max_points }}</span></span
                                            >
                                            <span class="result-label"
                                                ><CheckCircle2 v-if="item.status === 'passed'" class="size-3.5" /><AlertCircle
                                                    v-else
                                                    class="size-3.5"
                                                />{{ item.pct }}% · {{ item.status === 'passed' ? 'Passed' : 'Failed' }}</span
                                            >
                                        </template>
                                    </div>
                                    <div class="activity-feedback">
                                        <span>Feedback</span>
                                        <p :class="{ 'feedback-empty': !item.remarks?.trim() }">
                                            {{ item.remarks?.trim() || 'No feedback recorded.' }}
                                        </p>
                                    </div>
                                </article>
                            </div>
                            <p v-else class="category-empty">No items recorded in this category.</p>
                        </div>
                    </section>
                </div>
                <div v-else class="log-empty">
                    <Search v-if="searchQuery" class="size-5" /><CheckCircle2 v-else class="size-5" />
                    <h3>
                        {{
                            searchQuery
                                ? 'No matching coursework'
                                : currentTab === 'activity_log'
                                  ? 'No coursework to display'
                                  : 'No items in this section'
                        }}
                    </h3>
                    <p>
                        {{
                            searchQuery
                                ? 'Try another title, type or date, or change the activity filter.'
                                : 'Assigned coursework and recorded scores appear here.'
                        }}
                    </p>
                    <button
                        v-if="searchQuery || logCategoryFilter !== 'all'"
                        type="button"
                        class="search-action print:hidden"
                        @click="
                            searchQuery = '';
                            logCategoryFilter = 'all';
                        "
                    >
                        Clear filters
                    </button>
                </div>

                <dl class="grade-overview">
                    <div>
                        <dt><TrendingUp class="size-4" />Running grade</dt>
                        <dd>{{ overallPercentage !== null ? overallPercentage + '%' : '-' }}</dd>
                        <span class="grade-detail"
                            >{{ overallGrade === '—' ? '-' : overallGrade }} <span class="text-muted-foreground">/ 5.0</span></span
                        >
                    </div>
                    <div>
                        <dt><BookOpen class="size-4" />Midterm grade</dt>
                        <dd>{{ periodGrade(student.midterm) }}</dd>
                        <span v-if="student.midterm?.weighted_grade != null" class="grade-detail">{{ student.midterm.weighted_grade }}%</span>
                    </div>
                    <div>
                        <dt><Flag class="size-4" />Final grade</dt>
                        <dd>{{ periodGrade(student.final_period) }}</dd>
                        <span v-if="student.final_period?.weighted_grade != null" class="grade-detail"
                            >{{ student.final_period.weighted_grade }}% · Final period</span
                        >
                    </div>
                </dl>

                <section v-if="student.attendance" class="attendance-section">
                    <button
                        type="button"
                        class="attendance-toggle"
                        :aria-expanded="attendanceExpanded"
                        aria-controls="grade-attendance-details"
                        @click="attendanceExpanded = !attendanceExpanded"
                    >
                        <UserX class="size-4" /><span>Attendance standing</span
                        ><span class="attendance-preview"
                            >{{ student.attendance.absent_count }} absent · {{ student.attendance.late_count }} late</span
                        ><ChevronDown class="attendance-chevron size-4" :class="{ expanded: attendanceExpanded }" />
                    </button>
                    <dl v-show="attendanceExpanded" id="grade-attendance-details" class="attendance-details">
                        <div>
                            <dt>Sessions</dt>
                            <dd>{{ student.attendance.total_sessions }}</dd>
                        </div>
                        <div>
                            <dt>Present</dt>
                            <dd>{{ student.attendance.present_count }}</dd>
                        </div>
                        <div>
                            <dt>Late</dt>
                            <dd>{{ student.attendance.late_count }}</dd>
                        </div>
                        <div>
                            <dt>Absent</dt>
                            <dd>{{ student.attendance.absent_count }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </section>
    </div>
</template>

<style scoped>
.grade-overlay {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: grid;
    place-items: center;
    padding: 24px;
    background: rgb(16 18 21 / 0.56);
    backdrop-filter: blur(5px);
}
.grade-dialog {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 800px;
    max-height: 92dvh;
    overflow: hidden;
    border: 1px solid rgb(255 255 255 / 0.18);
    border-radius: 16px;
    background: hsl(var(--card));
    color: hsl(var(--foreground));
    box-shadow: 0 24px 80px rgb(0 0 0 / 0.24);
}
.grade-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    padding: 28px;
    border-top: 6px solid hsl(var(--primary));
    background: #242527;
    color: #fff;
}
.student-identity {
    min-width: 0;
}
.grade-header .eyebrow {
    margin-bottom: 10px;
    color: hsl(var(--primary));
    font-size: 10px;
    font-weight: 600;
    line-height: 1.4;
    text-transform: uppercase;
    letter-spacing: 0.13em;
}
.grade-header h2 {
    color: #fff;
    font-size: 28px;
    font-weight: 600;
    line-height: 1.16;
    letter-spacing: -0.04em;
    overflow-wrap: anywhere;
}
.course-label {
    margin-top: 10px;
    color: rgb(255 255 255 / 0.68);
    font-size: 12px;
    letter-spacing: 0.015em;
}
.header-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.icon-action {
    display: inline-grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border: 1px solid rgb(255 255 255 / 0.12);
    border-radius: 7px;
    background: rgb(255 255 255 / 0.04);
    color: rgb(255 255 255 / 0.8);
    transition:
        background-color 0.16s ease,
        color 0.16s ease;
}
.icon-action:hover {
    background: hsl(var(--primary));
    color: hsl(var(--primary-foreground));
}
.grade-dialog button:focus-visible,
.grade-dialog select:focus-visible,
.log-search:focus-within {
    outline: 2px solid hsl(var(--ring));
    outline-offset: 3px;
}
.grade-header button:focus-visible {
    outline-color: hsl(var(--primary));
}
.grade-tabs {
    display: flex;
    flex-shrink: 0;
    gap: 20px;
    margin: 0 28px;
    overflow-x: auto;
    border-bottom: 1px solid hsl(var(--border));
}
.grade-tabs button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 16px 0 13px;
    border-bottom: 3px solid transparent;
    color: hsl(var(--muted-foreground));
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
    transition:
        border-color 0.16s ease,
        color 0.16s ease;
}
.grade-tabs button:hover {
    color: hsl(var(--foreground));
}
.grade-tabs button.active {
    border-bottom-color: hsl(var(--primary));
    color: hsl(var(--foreground));
    font-weight: 600;
}
.grade-tabs button > span {
    color: hsl(var(--muted-foreground));
    font-size: 11px;
    font-variant-numeric: tabular-nums;
}
.grade-tabs button.active > span {
    color: hsl(var(--primary-text));
}
.log-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 28px;
}
.filter-label select {
    max-width: 190px;
    padding: 8px 30px 8px 0;
    border: 0;
    border-bottom: 1px solid hsl(var(--border));
    border-radius: 0;
    background-color: transparent;
    color: hsl(var(--foreground));
    font-size: 12px;
    box-shadow: none;
}
.result-count {
    color: hsl(var(--muted-foreground));
    font-size: 11px;
}
.search-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    margin-left: auto;
    padding: 9px 13px;
    border: 1px solid hsl(var(--primary) / 0.5);
    border-radius: 7px;
    background: hsl(var(--primary));
    color: hsl(var(--primary-foreground));
    font-size: 12px;
    font-weight: 600;
}
.search-action:hover {
    filter: brightness(0.96);
}
.log-search {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    margin: 0 28px 16px;
    padding: 0 12px;
    border: 1px solid hsl(var(--border));
    border-radius: 7px;
    background: hsl(var(--background));
}
.log-search input {
    width: 100%;
    min-width: 0;
    padding: 11px 0;
    border: none;
    background: transparent;
    color: hsl(var(--foreground));
    font-size: 13px;
    box-shadow: none;
    outline: none;
}
.grade-content {
    min-height: 0;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 0 28px 24px;
    background: hsl(var(--card));
}
.category-list {
    border-top: 2px solid hsl(var(--foreground) / 0.85);
    border-bottom: 1px solid hsl(var(--border));
}
.category-group {
    --category-hue: 215;
    background: hsl(var(--card));
}
.category-group + .category-group {
    border-top: 1px solid hsl(var(--border));
}
.category-group[data-category='activity'] {
    --category-hue: 152;
}
.category-group[data-category='exam'] {
    --category-hue: 273;
}
.category-group[data-category='laboratory'] {
    --category-hue: 185;
}
.category-group[data-category='project'] {
    --category-hue: 174;
}
.category-group[data-expanded='true'] > h3 {
    background: hsl(var(--accent));
}
.category-toggle {
    display: grid;
    grid-template-columns: 28px minmax(0, 1fr) auto 18px;
    align-items: center;
    gap: 14px;
    width: 100%;
    padding: 21px 12px;
    text-align: left;
    transition: background-color 0.16s ease;
}
.category-toggle:hover {
    background: hsl(var(--accent) / 0.6);
}
.category-toggle:focus-visible {
    outline-offset: -4px !important;
}
.category-index {
    align-self: start;
    margin-top: 4px;
    color: hsl(var(--primary-text));
    font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.04em;
}
.category-heading {
    display: grid;
    gap: 6px;
    min-width: 0;
    font-size: 18px;
    font-weight: 600;
    letter-spacing: -0.025em;
    line-height: 1.25;
}
.category-heading small {
    color: hsl(var(--muted-foreground));
    font-size: 11px;
    font-weight: 400;
    line-height: 1.4;
    letter-spacing: 0;
}
.category-total {
    color: hsl(var(--foreground));
    font-size: 31px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.045em;
    line-height: 1.15;
    white-space: nowrap;
}
.category-total small {
    color: hsl(var(--muted-foreground));
    font-size: 15px;
    font-weight: 400;
    letter-spacing: 0;
}
.category-chevron {
    color: hsl(var(--muted-foreground));
    transition: transform 0.18s ease;
}
.category-chevron.expanded {
    transform: rotate(180deg);
}
.category-details {
    padding: 0 18px 0 54px;
    border-top: 1px solid hsl(var(--border));
    background: hsl(var(--background) / 0.65);
}
.category-empty {
    padding: 20px 0;
    color: hsl(var(--muted-foreground));
    font-size: 13px;
}
.activity-list {
    display: block;
}
.activity-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    column-gap: 24px;
    padding: 20px 0;
    border-bottom: 1px solid hsl(var(--border));
    break-inside: avoid;
}
.activity-row:last-child {
    border-bottom: 0;
}
.activity-type {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
    color: hsl(var(--type-hue, 215) 42% 36%);
    font-size: 10px;
    font-weight: 600;
    line-height: 1.3;
    letter-spacing: 0.025em;
}
.activity-type::before {
    content: '';
    width: 5px;
    height: 5px;
    flex-shrink: 0;
    border-radius: 50%;
    background: currentColor;
}
.activity-type[data-type='activity'] {
    --type-hue: 152;
}
.activity-type[data-type='quiz'] {
    --type-hue: 215;
}
.activity-type[data-type='laboratory'] {
    --type-hue: 185;
}
.activity-type[data-type='exam'] {
    --type-hue: 273;
}
.activity-type[data-type='project'] {
    --type-hue: 174;
}
.activity-type[data-type='reporting'] {
    --type-hue: 236;
}
.activity-type[data-type='group_activity'] {
    --type-hue: 32;
}
:global(.dark .grade-dialog .activity-type) {
    color: hsl(var(--type-hue, 215) 52% 74%);
}
.activity-main h3 {
    color: hsl(var(--foreground));
    font-size: 15px;
    font-weight: 600;
    line-height: 1.45;
    letter-spacing: -0.015em;
    overflow-wrap: anywhere;
}
.activity-date {
    margin-top: 6px;
    color: hsl(var(--muted-foreground));
    font-size: 11px;
}
.activity-result {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    align-self: start;
    gap: 5px;
    padding-top: 18px;
    font-variant-numeric: tabular-nums;
}
.score-value {
    color: hsl(var(--foreground));
    font-size: 22px;
    font-weight: 600;
    line-height: 1.3;
    white-space: nowrap;
}
.score-max {
    color: inherit;
    opacity: 0.75;
    font-size: 13px;
    font-weight: 400;
}
.result-label {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 10px;
    line-height: 1.4;
    white-space: nowrap;
}
.passed .result-label,
.passed .score-value {
    color: #397356;
}
.failed .result-label,
.failed .score-value {
    color: #a34251;
}
.missing .result-label {
    color: #876222;
    font-weight: 500;
}
:global(.dark .grade-dialog .passed .result-label),
:global(.dark .grade-dialog .passed .score-value) {
    color: #a3c6b5;
}
:global(.dark .grade-dialog .failed .result-label),
:global(.dark .grade-dialog .failed .score-value) {
    color: #dfa6ac;
}
:global(.dark .grade-dialog .missing .result-label) {
    color: #d6be96;
}
.activity-feedback {
    grid-column: 1 / -1;
    margin-top: 14px;
    padding-left: 12px;
    border-left: 2px solid hsl(var(--primary));
    overflow-wrap: anywhere;
}
.activity-feedback > span {
    display: block;
    color: hsl(var(--muted-foreground));
    font-size: 10px;
    font-weight: 600;
}
.activity-feedback > p {
    max-width: 68ch;
    margin-top: 5px;
    color: hsl(var(--foreground) / 0.85);
    font-size: 13px;
    line-height: 1.65;
    white-space: pre-line;
}
.activity-feedback > .feedback-empty {
    color: hsl(var(--muted-foreground));
    font-size: 12px;
}
.grade-overview {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0;
    margin-top: 26px;
    padding: 0 0 24px;
}
.grade-overview > div {
    padding: 0 20px;
    border-right: 1px solid hsl(var(--border));
}
.grade-overview > div:first-child {
    padding-left: 0;
}
.grade-overview > div:last-child {
    border-right: 0;
    padding-right: 0;
}
.grade-overview dt {
    display: flex;
    align-items: center;
    gap: 7px;
    color: hsl(var(--muted-foreground));
    font-size: 11px;
    font-weight: 500;
    line-height: 1.4;
}
.grade-overview dd {
    margin-top: 10px;
    color: hsl(var(--foreground));
    font-size: 26px;
    font-weight: 600;
    line-height: 1.1;
    letter-spacing: -0.04em;
    font-variant-numeric: tabular-nums;
}
.grade-detail {
    display: block;
    min-height: 17px;
    margin-top: 6px;
    color: hsl(var(--muted-foreground));
    font-size: 11px;
}
.attendance-section {
    border-top: 1px solid hsl(var(--border));
}
.attendance-toggle {
    display: flex;
    align-items: center;
    gap: 9px;
    width: 100%;
    padding: 18px 0 0;
    text-align: left;
    font-size: 12px;
    font-weight: 500;
}
.attendance-toggle > svg {
    color: hsl(var(--muted-foreground));
    flex-shrink: 0;
}
.attendance-preview {
    margin-left: auto;
    color: hsl(var(--muted-foreground));
    font-size: 11px;
    font-weight: 400;
}
.attendance-chevron {
    transition: transform 0.18s ease;
}
.attendance-chevron.expanded {
    transform: rotate(180deg);
}
.attendance-details {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-top: 16px;
    padding: 16px;
    background: hsl(var(--background));
}
.attendance-details dt {
    color: hsl(var(--muted-foreground));
    font-size: 11px;
}
.attendance-details dd {
    margin-top: 5px;
    font-size: 20px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}
.log-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 36px 16px;
    border-top: 2px solid hsl(var(--foreground) / 0.85);
    border-bottom: 1px solid hsl(var(--border));
    color: hsl(var(--muted-foreground));
    text-align: center;
}
.log-empty h3 {
    color: hsl(var(--foreground));
    font-size: 15px;
    font-weight: 600;
}
.log-empty p {
    max-width: 300px;
    font-size: 13px;
}
.log-empty .search-action {
    margin-left: 0;
}
@media (max-width: 540px) {
    .grade-overlay {
        padding: 10px;
    }
    .grade-dialog {
        max-height: 95dvh;
        border-radius: 12px;
    }
    .grade-header {
        padding: 22px 18px;
        gap: 12px;
    }
    .grade-header h2 {
        font-size: 22px;
    }
    .eyebrow {
        font-size: 9px;
        margin-bottom: 8px;
    }
    .course-label {
        font-size: 11px;
        margin-top: 8px;
    }
    .header-actions {
        gap: 4px;
    }
    .icon-action {
        width: 30px;
        height: 32px;
        border-radius: 6px;
    }
    .grade-tabs {
        margin: 0 18px;
        gap: 18px;
    }
    .grade-tabs button {
        font-size: 11px;
        padding: 13px 0 11px;
    }
    .grade-tabs button > span {
        font-size: 10px;
    }
    .log-toolbar {
        padding: 14px 18px;
        gap: 8px;
    }
    .filter-label select {
        max-width: 150px;
        font-size: 11px;
    }
    .search-action {
        padding: 8px 10px;
        font-size: 11px;
    }
    .result-count {
        font-size: 11px;
    }
    .log-search {
        margin: 0 18px 14px;
    }
    .grade-content {
        padding: 0 18px 20px;
    }
    .category-toggle {
        grid-template-columns: 19px minmax(0, 1fr) auto 14px;
        gap: 10px;
        padding: 18px 4px;
    }
    .category-heading {
        font-size: 16px;
    }
    .category-heading small {
        font-size: 10px;
    }
    .category-total {
        font-size: 25px;
    }
    .category-total small {
        font-size: 13px;
    }
    .category-index {
        font-size: 10px;
    }
    .category-details {
        padding: 0 10px;
    }
    .activity-row {
        column-gap: 12px;
        padding: 18px 0;
    }
    .activity-main h3 {
        font-size: 14px;
    }
    .activity-date {
        font-size: 10px;
    }
    .activity-type {
        font-size: 10px;
    }
    .activity-result {
        padding-top: 17px;
    }
    .score-value {
        font-size: 19px;
    }
    .score-max {
        font-size: 12px;
    }
    .result-label {
        font-size: 10px;
    }
    .activity-feedback > p {
        font-size: 12px;
    }
    .grade-overview {
        margin-top: 22px;
        padding-bottom: 22px;
    }
    .grade-overview > div {
        padding: 0 12px;
    }
    .grade-overview dt {
        font-size: 10px;
    }
    .grade-overview dt > svg {
        display: none;
    }
    .grade-overview dd {
        font-size: 24px;
    }
    .grade-detail {
        font-size: 10px;
    }
    .attendance-toggle {
        gap: 7px;
        font-size: 12px;
    }
    .attendance-preview {
        font-size: 10px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .attendance-chevron,
    .category-chevron,
    .category-toggle,
    .icon-action,
    .grade-tabs button {
        transition: none;
    }
}
@media print {
    :global(.dark .grade-dialog .activity-type) {
        color: hsl(var(--type-hue, 215) 42% 36%);
    }
    .header-actions,
    .grade-tabs,
    .log-toolbar,
    .log-search,
    .log-empty .search-action,
    .category-chevron,
    .attendance-chevron {
        display: none;
    }
    .grade-overlay {
        position: static;
        display: block;
        padding: 0;
        background: none;
        backdrop-filter: none;
    }
    .grade-dialog {
        display: block;
        max-height: none;
        max-width: none;
        border: none;
        border-radius: 0;
        background: white;
        color: black;
        box-shadow: none;
    }
    .grade-header {
        border-top: 3px solid black;
        background: white;
        color: black;
    }
    .grade-header h2,
    .grade-header .eyebrow,
    .course-label {
        color: black;
    }
    .grade-content {
        overflow: visible;
        background: white;
    }
    .category-details {
        display: block !important;
        background: white;
    }
    .category-toggle {
        break-after: avoid;
    }
    .category-group[data-expanded='true'] > h3 {
        background: white;
    }
    .attendance-details {
        display: grid !important;
    }
}
</style>
