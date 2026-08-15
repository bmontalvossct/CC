<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    MessageSquare,
    Mic,
    Sparkles,
    Star,
    Trophy,
    User,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface StudentRow {
    id: number;
    student_number: string;
    full_name: string;
    seat_label: string | null;
    times_called: number;
    avg_accuracy: number | null;
    avg_delivery: number | null;
    avg_score: number | null;
    computed_grade: number | null;
    called_today: boolean;
    today_recitation: {
        id: number;
        accuracy: number;
        delivery: number;
        score: string;
        comments: string | null;
    } | null;
}

interface LayoutBlock {
    id: number;
    label: string;
    block_row: number;
    block_column: number;
    seats: Array<{
        id: number;
        label: string;
        row_number: number;
        column_number: number;
        is_disabled: boolean;
        student_id: number | null;
    }>;
}

const props = defineProps<{
    section: {
        id: number;
        name: string;
        subject_code: string;
        subject_title: string;
        layoutBlocks: LayoutBlock[];
    };
    students: StudentRow[];
    todayDate: string;
    todayFormatted: string;
}>();

const page = usePage<any>();
const activeTab = ref<'floor' | 'rubrics'>('floor');
const scoringStudent = ref<StudentRow | null>(null);

const scoreForm = useForm({
    accuracy: 3,
    delivery: 3,
    comments: '',
});

const totalScore = computed(() => scoreForm.accuracy + scoreForm.delivery);

const openScoring = (student: StudentRow) => {
    scoringStudent.value = student;
    if (student.today_recitation) {
        scoreForm.accuracy = student.today_recitation.accuracy;
        scoreForm.delivery = student.today_recitation.delivery;
        scoreForm.comments = student.today_recitation.comments || '';
    } else {
        scoreForm.accuracy = 3;
        scoreForm.delivery = 3;
        scoreForm.comments = '';
    }
};

const closeScoring = () => {
    scoringStudent.value = null;
    scoreForm.reset();
};

const submitScore = () => {
    if (!scoringStudent.value) return;
    scoreForm.post(`/sections/${props.section.id}/recitation/score/${scoringStudent.value.id}`, {
        onSuccess: () => closeScoring(),
        preserveScroll: true,
    });
};

// Find student info for a seat
const studentMap = computed(() => {
    const map = new Map<number, StudentRow>();
    props.students.forEach((s) => map.set(s.id, s));
    return map;
});

// Build grid from layout blocks
const maxBlockRow = computed(() => Math.max(...props.section.layoutBlocks.map((b) => b.block_row), 0));
const maxBlockCol = computed(() => Math.max(...props.section.layoutBlocks.map((b) => b.block_column), 0));

const blockGrid = computed(() => {
    const grid: (LayoutBlock | null)[][] = [];
    for (let r = 0; r <= maxBlockRow.value; r++) {
        grid[r] = [];
        for (let c = 0; c <= maxBlockCol.value; c++) {
            grid[r][c] = props.section.layoutBlocks.find((b) => b.block_row === r && b.block_column === c) || null;
        }
    }
    return grid;
});

// Grade color helper
const gradeColor = (grade: number | null) => {
    if (grade === null) return 'text-muted-foreground';
    if (grade >= 85) return 'text-emerald-600 dark:text-emerald-400';
    if (grade >= 70) return 'text-amber-600 dark:text-amber-400';
    return 'text-rose-600 dark:text-rose-400';
};

// Star rating display for accuracy/delivery
const ratingLabel = (val: number) => {
    const labels = ['—', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
    return labels[val] || '—';
};
</script>

<template>
    <Head :title="`Oral Participation - ${section.name}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Oral Participation', href: `/sections/${section.id}/recitation` },
        ]"
    >
        <main class="page-enter min-h-full bg-background px-5 pb-16 pt-8 text-foreground md:px-10 md:pt-10">
            <div class="mx-auto max-w-[1360px]">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-6 rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-medium text-primary shadow-xs"
                >
                    {{ page.props.flash.success }}
                </div>

                <!-- Header -->
                <header class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 sm:p-8 shadow-sm">
                    <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="badge-primary font-mono font-medium">{{ section.subject_code }}</span>
                                <span class="badge-muted">{{ todayFormatted }}</span>
                            </div>
                            <h1 class="mt-3 text-3xl font-medium tracking-tight sm:text-4xl">Oral Participation</h1>
                            <p class="mt-2 text-sm text-muted-foreground">{{ section.subject_title }} — {{ section.name }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <Link
                                :href="`/sections/${section.id}`"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-medium text-foreground shadow-xs hover:bg-secondary transition-colors"
                            >
                                <ArrowLeft class="size-4 text-muted-foreground" />
                                <span>Back to section</span>
                            </Link>
                        </div>
                    </div>
                </header>

                <!-- Tab Switcher -->
                <div class="mt-6 flex items-center gap-1 rounded-xl bg-secondary/50 border border-border/60 p-1">
                    <button
                        type="button"
                        class="flex-1 rounded-lg px-4 py-2.5 text-xs font-medium transition-colors"
                        :class="activeTab === 'floor' ? 'bg-card text-foreground shadow-sm border border-border/80' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'floor'"
                    >
                        <Mic class="inline size-3.5 mr-1.5" /> Floor View — Click to Score
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-lg px-4 py-2.5 text-xs font-medium transition-colors"
                        :class="activeTab === 'rubrics' ? 'bg-card text-foreground shadow-sm border border-border/80' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'rubrics'"
                    >
                        <Trophy class="inline size-3.5 mr-1.5" /> Rubrics & Grades
                    </button>
                </div>

                <!-- Floor View Tab -->
                <section v-if="activeTab === 'floor'" class="mt-6 paper-card p-6 md:p-8">
                    <div class="mb-6">
                        <span class="eyebrow">Seating layout</span>
                        <h2 class="mt-1 text-xl font-medium tracking-tight">Click a student's chair to grade their oral participation today</h2>
                    </div>

                    <!-- Classroom Grid -->
                    <div v-if="section.layoutBlocks.length" class="overflow-x-auto">
                        <div class="inline-block min-w-full">
                            <div v-for="(row, ri) in blockGrid" :key="ri" class="flex gap-4">
                                <template v-for="(block, ci) in row" :key="`${ri}-${ci}`">
                                    <div v-if="block" class="mb-4">
                                        <div class="grid gap-1.5" :style="{ gridTemplateColumns: `repeat(${Math.max(...block.seats.map(s => s.column_number), 0) + 1}, minmax(60px, 1fr))` }">
                                            <template v-for="seat in block.seats" :key="seat.id">
                                                <button
                                                    v-if="!seat.is_disabled && seat.student_id"
                                                    type="button"
                                                    class="group relative flex flex-col items-center justify-center rounded-xl border p-2.5 text-center transition-all duration-200 cursor-pointer min-h-[72px]"
                                                    :class="[
                                                        studentMap.get(seat.student_id)?.called_today
                                                            ? 'border-emerald-500/40 bg-emerald-500/10 hover:bg-emerald-500/20'
                                                            : 'border-border bg-card hover:border-primary/40 hover:bg-primary/5 hover:shadow-md'
                                                    ]"
                                                    @click="studentMap.get(seat.student_id) && openScoring(studentMap.get(seat.student_id)!)"
                                                >
                                                    <span class="text-[10px] font-mono font-medium text-muted-foreground">{{ seat.label }}</span>
                                                    <span class="mt-0.5 text-[11px] font-medium text-foreground leading-tight line-clamp-2">
                                                        {{ studentMap.get(seat.student_id)?.full_name || '—' }}
                                                    </span>
                                                    <span
                                                        v-if="studentMap.get(seat.student_id)?.called_today"
                                                        class="mt-1 inline-flex items-center gap-0.5 rounded-full bg-emerald-500 px-1.5 py-0.5 text-[9px] font-medium text-white"
                                                    >
                                                        <Check class="size-2.5" /> Scored
                                                    </span>
                                                    <span
                                                        v-if="studentMap.get(seat.student_id)?.today_recitation"
                                                        class="mt-0.5 text-[10px] font-mono font-medium text-primary"
                                                    >
                                                        {{ studentMap.get(seat.student_id)!.today_recitation!.score }}/10
                                                    </span>
                                                </button>
                                                <div
                                                    v-else-if="!seat.is_disabled"
                                                    class="flex items-center justify-center rounded-xl border border-dashed border-border/60 bg-muted/20 min-h-[72px]"
                                                >
                                                    <span class="text-[10px] text-muted-foreground/40">{{ seat.label }}</span>
                                                </div>
                                                <div v-else class="min-h-[72px]" />
                                            </template>
                                        </div>
                                    </div>
                                    <div v-else class="mb-4 w-4" />
                                </template>
                            </div>
                        </div>
                    </div>

                    <div v-else class="rounded-2xl border border-dashed border-border bg-secondary/30 p-12 text-center">
                        <p class="text-sm text-muted-foreground">No classroom layout configured. Set up a floor plan first.</p>
                    </div>
                </section>

                <!-- Rubrics & Grades Tab -->
                <section v-if="activeTab === 'rubrics'" class="mt-6 paper-card p-6 md:p-8 shadow-sm overflow-hidden">
                    <div class="mb-6">
                        <span class="eyebrow">Grade computation</span>
                        <h2 class="mt-1 text-xl font-medium tracking-tight">Oral participation rubrics</h2>
                        <p class="mt-2 text-xs text-muted-foreground max-w-2xl">
                            Each recitation is scored on two criteria: <strong class="font-medium text-foreground">Accuracy</strong> (1–5) and
                            <strong class="font-medium text-foreground">Delivery</strong> (1–5), for a maximum of 10 points per session.
                            The computed grade is the average score converted to a percentage.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px] border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-border/80 bg-secondary/40 text-left text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                    <th class="px-4 py-3 rounded-l-lg">Chair</th>
                                    <th class="px-4 py-3">Student</th>
                                    <th class="px-4 py-3 text-center">Times Called</th>
                                    <th class="px-4 py-3 text-center">Avg Accuracy</th>
                                    <th class="px-4 py-3 text-center">Avg Delivery</th>
                                    <th class="px-4 py-3 text-center">Avg Score / 10</th>
                                    <th class="px-4 py-3 text-center rounded-r-lg">Computed Grade</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr
                                    v-for="student in students"
                                    :key="student.id"
                                    class="transition-colors hover:bg-secondary/30"
                                >
                                    <td class="px-4 py-3">
                                        <span class="rounded-lg bg-secondary px-2.5 py-1 font-mono text-xs font-medium text-foreground border border-border/80">
                                            {{ student.seat_label || '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="block text-foreground font-medium">{{ student.full_name }}</span>
                                        <span class="font-mono text-xs text-muted-foreground">{{ student.student_number }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-foreground">
                                            <MessageSquare class="size-3 text-primary" />
                                            {{ student.times_called }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-xs font-medium" :class="gradeColor(student.avg_accuracy ? student.avg_accuracy * 20 : null)">
                                        {{ student.avg_accuracy !== null ? student.avg_accuracy.toFixed(1) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-xs font-medium" :class="gradeColor(student.avg_delivery ? student.avg_delivery * 20 : null)">
                                        {{ student.avg_delivery !== null ? student.avg_delivery.toFixed(1) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-mono text-xs font-medium">
                                        {{ student.avg_score !== null ? student.avg_score.toFixed(1) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            v-if="student.computed_grade !== null"
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium border"
                                            :class="[
                                                student.computed_grade >= 85 ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' :
                                                student.computed_grade >= 70 ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20' :
                                                'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20'
                                            ]"
                                        >
                                            {{ student.computed_grade.toFixed(1) }}%
                                        </span>
                                        <span v-else class="text-xs text-muted-foreground">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="!students.length" class="py-12 text-center text-sm text-muted-foreground">
                        No students enrolled in this section yet.
                    </div>
                </section>
            </div>
        </main>

        <!-- Scoring Modal -->
        <div
            v-if="scoringStudent"
            class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 backdrop-blur-md animate-in fade-in duration-200"
            @click.self="closeScoring"
        >
            <div
                class="paper-card relative w-full max-w-md overflow-hidden p-8 shadow-2xl animate-in zoom-in-95 duration-200 border-border/90"
                role="dialog"
                aria-modal="true"
                aria-label="Score oral participation"
            >
                <button
                    type="button"
                    class="absolute right-4 top-4 grid size-8 place-items-center rounded-full text-muted-foreground hover:bg-secondary hover:text-foreground transition-colors"
                    @click="closeScoring"
                >
                    <X class="size-4.5" />
                </button>

                <div class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 font-mono text-[11px] font-medium uppercase tracking-wider text-primary border border-primary/20">
                    <Mic class="size-3.5" /> Oral Score
                </div>

                <h3 class="mt-3 text-2xl font-medium tracking-tight">{{ scoringStudent.full_name }}</h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    Chair {{ scoringStudent.seat_label || '—' }} · {{ todayFormatted }}
                </p>

                <form class="mt-6 space-y-5" @submit.prevent="submitScore">
                    <!-- Accuracy -->
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Accuracy (1–5): <span class="text-foreground">{{ ratingLabel(scoreForm.accuracy) }}</span>
                        </label>
                        <div class="flex items-center gap-1.5">
                            <button
                                v-for="n in 5"
                                :key="`acc-${n}`"
                                type="button"
                                class="grid size-10 place-items-center rounded-xl border transition-all duration-150"
                                :class="n <= scoreForm.accuracy
                                    ? 'border-primary bg-primary/10 text-primary shadow-sm'
                                    : 'border-border bg-card text-muted-foreground hover:border-primary/40'"
                                @click="scoreForm.accuracy = n"
                            >
                                <Star class="size-4" :class="n <= scoreForm.accuracy ? 'fill-primary' : ''" />
                            </button>
                        </div>
                        <small v-if="scoreForm.errors.accuracy" class="text-rose-600 text-xs mt-1 block">{{ scoreForm.errors.accuracy }}</small>
                    </div>

                    <!-- Delivery -->
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Delivery (1–5): <span class="text-foreground">{{ ratingLabel(scoreForm.delivery) }}</span>
                        </label>
                        <div class="flex items-center gap-1.5">
                            <button
                                v-for="n in 5"
                                :key="`del-${n}`"
                                type="button"
                                class="grid size-10 place-items-center rounded-xl border transition-all duration-150"
                                :class="n <= scoreForm.delivery
                                    ? 'border-primary bg-primary/10 text-primary shadow-sm'
                                    : 'border-border bg-card text-muted-foreground hover:border-primary/40'"
                                @click="scoreForm.delivery = n"
                            >
                                <Star class="size-4" :class="n <= scoreForm.delivery ? 'fill-primary' : ''" />
                            </button>
                        </div>
                        <small v-if="scoreForm.errors.delivery" class="text-rose-600 text-xs mt-1 block">{{ scoreForm.errors.delivery }}</small>
                    </div>

                    <!-- Total Preview -->
                    <div class="rounded-xl bg-secondary/50 border border-border/60 p-4 text-center">
                        <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Total Score</span>
                        <p class="mt-1 text-3xl font-medium tracking-tight text-primary">
                            {{ totalScore }}<span class="text-sm font-normal text-muted-foreground">/10</span>
                        </p>
                    </div>

                    <!-- Comments -->
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Comments <em class="font-normal normal-case text-muted-foreground">(optional)</em>
                        </label>
                        <textarea
                            v-model="scoreForm.comments"
                            rows="2"
                            placeholder="Notes on student performance..."
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 border-t border-border/80 pt-4">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-xl border border-border bg-card px-5 text-xs font-medium text-foreground shadow-xs hover:bg-secondary transition-colors"
                            @click="closeScoring"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="scoreForm.processing"
                            class="ink-button !h-10 !rounded-xl !px-5 text-xs font-medium"
                        >
                            {{ scoreForm.processing ? 'Saving…' : (scoringStudent.called_today ? 'Update Score' : 'Save Score') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
