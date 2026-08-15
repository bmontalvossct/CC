<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Download, Printer, Save, Settings, Trophy } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type Assessment = { id: number; type: 'activity' | 'quiz' | 'exam'; title: string; conducted_on: string; max_points: string };
type Category = { earned: number; possible: number; percentage: number | null; missing: number };
type Recitation = { count: number; avg_score: number | null; percentage: number | null };
type Row = {
    id: number;
    student_number: string;
    full_name: string;
    scores: Record<number, string | null>;
    categories: Record<'activity' | 'quiz' | 'exam', Category>;
    recitation: Recitation;
};

const props = defineProps<{
    section: { id: number; name: string; subject_code?: string; subject_title: string };
    assessments: Assessment[];
    rows: Row[];
    categorySummary: Record<string, { count: number; possible: number }>;
    gradingWeights: Record<string, number>;
    printMode: boolean;
}>();

const page = usePage<any>();
const types = ['activity', 'quiz', 'exam'] as const;
const showWeightsEditor = ref(false);

const weightsForm = useForm({
    activity: props.gradingWeights.activity ?? 25,
    quiz: props.gradingWeights.quiz ?? 25,
    exam: props.gradingWeights.exam ?? 30,
    recitation: props.gradingWeights.recitation ?? 20,
});

const weightsTotal = computed(() => weightsForm.activity + weightsForm.quiz + weightsForm.exam + weightsForm.recitation);
const weightsValid = computed(() => weightsTotal.value === 100);

const saveWeights = () => {
    if (!weightsValid.value) return;
    weightsForm.put(`/sections/${props.section.id}/grading-weights`, {
        preserveScroll: true,
        onSuccess: () => { showWeightsEditor.value = false; },
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

// Compute weighted overall percentage per student
const computeOverall = (row: Row): number | null => {
    const w = props.gradingWeights;
    let totalWeight = 0;
    let weighted = 0;

    // Activities
    if (row.categories.activity.percentage !== null && w.activity > 0) {
        weighted += row.categories.activity.percentage * (w.activity / 100);
        totalWeight += w.activity;
    }
    // Quizzes
    if (row.categories.quiz.percentage !== null && w.quiz > 0) {
        weighted += row.categories.quiz.percentage * (w.quiz / 100);
        totalWeight += w.quiz;
    }
    // Exams
    if (row.categories.exam.percentage !== null && w.exam > 0) {
        weighted += row.categories.exam.percentage * (w.exam / 100);
        totalWeight += w.exam;
    }
    // Recitation / Oral Participation
    if (row.recitation.percentage !== null && w.recitation > 0) {
        weighted += row.recitation.percentage * (w.recitation / 100);
        totalWeight += w.recitation;
    }

    if (totalWeight === 0) return null;
    return round(weighted, 2);
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
            <div class="mx-auto max-w-[1500px]">
                <!-- Flash Message -->
                <div
                    v-if="page.props.flash?.success"
                    class="mb-6 rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-medium text-primary shadow-xs print:hidden"
                >
                    {{ page.props.flash.success }}
                </div>

                <!-- Top Toolbar (Hidden on Print) -->
                <div v-if="!printMode" class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
                    <Link
                        :href="`/sections/${section.id}/assessments`"
                        prefetch="hover"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-primary transition-colors"
                    >
                        <ArrowLeft class="size-3.5" /> Back to assessments
                    </Link>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-border bg-card px-3.5 text-xs font-medium text-foreground shadow-xs hover:bg-secondary transition-colors"
                            @click="showWeightsEditor = !showWeightsEditor"
                        >
                            <Settings class="size-3.5 text-primary" />
                            <span>Rubrics Weights</span>
                        </button>
                        <a
                            :href="`/sections/${section.id}/exports/gradebook`"
                            class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-border bg-card px-3.5 text-xs font-medium text-foreground shadow-xs hover:bg-secondary transition-colors"
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
                    class="mb-6 paper-card p-6 shadow-sm print:hidden animate-in slide-in-from-top-2 duration-200"
                >
                    <div class="flex items-center gap-2 mb-4">
                        <Trophy class="size-4 text-primary" />
                        <h3 class="text-base font-medium">Grading rubrics — Set percentage weights</h3>
                    </div>
                    <p class="text-xs text-muted-foreground mb-5">
                        Define how much each component contributes to the final grade. Weights must total exactly 100%.
                    </p>

                    <form class="grid grid-cols-2 sm:grid-cols-4 gap-4" @submit.prevent="saveWeights">
                        <label>
                            <span class="mb-1.5 block text-[10px] font-medium uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Activities</span>
                            <div class="flex items-center gap-1">
                                <input
                                    v-model.number="weightsForm.activity"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium text-center focus-visible:ring-2 focus-visible:ring-primary"
                                />
                                <span class="text-xs text-muted-foreground">%</span>
                            </div>
                        </label>
                        <label>
                            <span class="mb-1.5 block text-[10px] font-medium uppercase tracking-wider text-blue-600 dark:text-blue-400">Quizzes</span>
                            <div class="flex items-center gap-1">
                                <input
                                    v-model.number="weightsForm.quiz"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium text-center focus-visible:ring-2 focus-visible:ring-primary"
                                />
                                <span class="text-xs text-muted-foreground">%</span>
                            </div>
                        </label>
                        <label>
                            <span class="mb-1.5 block text-[10px] font-medium uppercase tracking-wider text-purple-600 dark:text-purple-400">Exams</span>
                            <div class="flex items-center gap-1">
                                <input
                                    v-model.number="weightsForm.exam"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium text-center focus-visible:ring-2 focus-visible:ring-primary"
                                />
                                <span class="text-xs text-muted-foreground">%</span>
                            </div>
                        </label>
                        <label>
                            <span class="mb-1.5 block text-[10px] font-medium uppercase tracking-wider text-orange-600 dark:text-orange-400">Oral Participation</span>
                            <div class="flex items-center gap-1">
                                <input
                                    v-model.number="weightsForm.recitation"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium text-center focus-visible:ring-2 focus-visible:ring-primary"
                                />
                                <span class="text-xs text-muted-foreground">%</span>
                            </div>
                        </label>
                    </form>

                    <div class="mt-4 flex items-center justify-between border-t border-border/80 pt-4">
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-medium text-muted-foreground">Total:</span>
                            <span
                                class="text-sm font-medium"
                                :class="weightsValid ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                            >
                                {{ weightsTotal }}%
                            </span>
                            <span v-if="!weightsValid" class="text-[10px] text-rose-600 dark:text-rose-400">Must equal 100%</span>
                        </div>
                        <button
                            type="button"
                            :disabled="!weightsValid || weightsForm.processing"
                            class="ink-button !h-9 !rounded-xl !px-4 text-xs font-medium"
                            @click="saveWeights"
                        >
                            <Save class="size-3.5" />
                            {{ weightsForm.processing ? 'Saving…' : 'Save Weights' }}
                        </button>
                    </div>
                </section>

                <!-- Header Block -->
                <header
                    class="rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 sm:p-8 shadow-sm print:rounded-none print:border-b-2 print:border-black print:bg-white print:p-0 print:text-black"
                >
                    <div class="flex items-center gap-2">
                        <span class="badge-primary font-mono font-medium">{{ section.subject_code }}</span>
                        <span class="badge-muted">{{ section.name }}</span>
                    </div>
                    <h1 class="mt-2 text-2xl font-medium tracking-tight sm:text-3xl print:text-xl">{{ section.subject_title }}</h1>
                    <p class="mt-1 text-xs text-muted-foreground print:text-black">
                        Weighted gradebook with college grading scale (1.0–5.0).
                        Components: Activities {{ gradingWeights.activity }}%, Quizzes {{ gradingWeights.quiz }}%, Exams {{ gradingWeights.exam }}%, Oral Participation {{ gradingWeights.recitation }}%.
                    </p>
                </header>

                <!-- Category Summary Cards -->
                <section class="my-6 grid gap-4 sm:grid-cols-4 print:grid-cols-4">
                    <div
                        v-for="type in types"
                        :key="type"
                        class="paper-card p-5 print:rounded-none print:border print:border-black print:bg-white"
                    >
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
                        <p class="mt-2 text-2xl font-medium tracking-tight">{{ categorySummary[type].count }} items</p>
                        <p class="text-xs text-muted-foreground font-normal mt-0.5">{{ categorySummary[type].possible }} possible pts</p>
                    </div>
                    <div class="paper-card p-5 print:rounded-none print:border print:border-black print:bg-white">
                        <span class="font-mono text-[10px] font-medium uppercase tracking-wider text-orange-600 dark:text-orange-400">
                            Oral Participation · {{ gradingWeights.recitation }}%
                        </span>
                        <p class="mt-2 text-2xl font-medium tracking-tight">Per session</p>
                        <p class="text-xs text-muted-foreground font-normal mt-0.5">Max 10 pts (Accuracy + Delivery)</p>
                    </div>
                </section>

                <!-- Final Grades Table -->
                <div
                    class="paper-card overflow-x-auto shadow-sm print:overflow-visible print:rounded-none print:border print:border-black print:bg-white p-0"
                >
                    <table class="w-full min-w-max border-collapse text-xs print:text-[8px]">
                        <thead>
                            <tr class="border-b border-border/80 bg-secondary/50">
                                <th
                                    class="sticky left-0 z-20 min-w-56 bg-secondary/95 backdrop-blur-xs px-4 py-3.5 text-left font-medium uppercase tracking-wider text-muted-foreground print:static print:bg-white"
                                >
                                    Student
                                </th>
                                <th
                                    v-for="item in assessments"
                                    :key="item.id"
                                    class="min-w-24 border-l border-border/60 px-3 py-3 text-center"
                                >
                                    <span class="block font-mono text-[9px] font-medium uppercase tracking-wider text-primary">{{ item.type }}</span>
                                    <span class="mt-0.5 block truncate text-foreground font-medium max-w-28 mx-auto">{{ item.title }}</span>
                                    <span class="font-mono text-[10px] text-muted-foreground">/ {{ item.max_points }}</span>
                                </th>
                                <th
                                    v-for="type in types"
                                    :key="`total-${type}`"
                                    class="min-w-24 border-l-2 border-border bg-secondary/80 px-3 text-center capitalize font-medium text-foreground"
                                >
                                    {{ type }} %
                                </th>
                                <th class="min-w-24 border-l-2 border-border bg-secondary/80 px-3 text-center font-medium text-orange-600 dark:text-orange-400">
                                    Oral %
                                </th>
                                <th class="min-w-28 border-l-2 border-primary/30 bg-primary/10 px-3 text-center font-medium text-foreground">
                                    Weighted %
                                </th>
                                <th class="min-w-24 border-l-2 border-primary/30 bg-primary/10 px-3 text-center font-medium text-foreground">
                                    Grade
                                </th>
                                <th class="min-w-20 border-l-2 border-primary/30 bg-primary/10 px-3 text-center font-medium text-foreground">
                                    Remarks
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="row in rows"
                                :key="row.id"
                                class="hover:bg-secondary/30 transition-colors break-inside-avoid"
                            >
                                <td class="sticky left-0 z-10 bg-card/95 backdrop-blur-xs px-4 py-3 border-r border-border/50 print:static print:bg-white">
                                    <span class="block font-medium text-foreground">{{ row.full_name }}</span>
                                    <span class="font-mono text-[10px] text-muted-foreground">{{ row.student_number }}</span>
                                </td>
                                <td
                                    v-for="item in assessments"
                                    :key="item.id"
                                    class="border-l border-border/60 px-3 py-3 text-center font-mono text-xs"
                                    :class="row.scores[item.id] === null ? 'text-muted-foreground/60' : 'font-medium text-foreground'"
                                >
                                    {{ row.scores[item.id] ?? '—' }}
                                </td>
                                <td
                                    v-for="type in types"
                                    :key="type"
                                    class="border-l-2 border-border bg-secondary/20 px-3 py-3 text-center font-mono"
                                >
                                    <span class="block text-xs font-medium">
                                        {{ row.categories[type].percentage !== null ? `${row.categories[type].percentage}%` : '—' }}
                                    </span>
                                </td>
                                <td class="border-l-2 border-border bg-secondary/20 px-3 py-3 text-center font-mono text-xs font-medium">
                                    {{ row.recitation.percentage !== null ? `${row.recitation.percentage}%` : '—' }}
                                </td>
                                <td class="border-l-2 border-primary/30 bg-primary/5 px-3 py-3 text-center font-mono text-sm font-medium text-foreground">
                                    {{ computeOverall(row) !== null ? `${computeOverall(row)}%` : '—' }}
                                </td>
                                <td class="border-l-2 border-primary/30 bg-primary/5 px-3 py-3 text-center">
                                    <span
                                        class="inline-flex items-center justify-center rounded-full px-2.5 py-1 text-xs font-medium border min-w-[52px]"
                                        :class="[
                                            gradeDisplay(percentToGrade(computeOverall(row))) === 'INC'
                                                ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20'
                                                : gradeClass(percentToGrade(computeOverall(row))) === 'text-emerald-600 dark:text-emerald-400'
                                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'
                                                    : gradeClass(percentToGrade(computeOverall(row))) === 'text-amber-600 dark:text-amber-400'
                                                        ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20'
                                                        : 'bg-primary/10 text-primary border-primary/20'
                                        ]"
                                    >
                                        {{ gradeDisplay(percentToGrade(computeOverall(row))) }}
                                    </span>
                                </td>
                                <td class="border-l-2 border-primary/30 bg-primary/5 px-3 py-3 text-center text-[10px] font-medium">
                                    <template v-if="computeOverall(row) !== null">
                                        <span v-if="gradeDisplay(percentToGrade(computeOverall(row))) === 'INC'" class="text-rose-600 dark:text-rose-400">Failing</span>
                                        <span v-else class="text-emerald-600 dark:text-emerald-400">Passed</span>
                                    </template>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td :colspan="4 + assessments.length + types.length" class="py-12 text-center text-xs text-muted-foreground">
                                    No students are enrolled in this section.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Grading Scale Legend -->
                <div class="mt-6 paper-card p-5 print:rounded-none print:border print:border-black print:bg-white">
                    <h3 class="text-xs font-medium uppercase tracking-wider text-muted-foreground mb-3">College Grading Scale</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-5 lg:grid-cols-10 gap-2 text-center text-[10px]">
                        <div v-for="entry in [
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
                        ]" :key="entry.grade"
                            class="rounded-lg border border-border/60 bg-secondary/30 px-2 py-2"
                            :class="entry.grade === 'INC' ? 'border-rose-500/30 bg-rose-500/5' : ''"
                        >
                            <span class="block font-medium text-foreground" :class="entry.grade === 'INC' ? 'text-rose-600 dark:text-rose-400' : ''">{{ entry.grade }}</span>
                            <span class="text-muted-foreground">{{ entry.range }}</span>
                        </div>
                    </div>
                </div>

                <p class="mt-4 text-[11px] text-muted-foreground print:text-[8px]">
                    Note: Blank scores are counted as 0 for category percentages. INC (Incomplete) is assigned when the computed grade exceeds 3.0. Grading scale follows the Philippine college standard.
                </p>
            </div>
        </main>
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
