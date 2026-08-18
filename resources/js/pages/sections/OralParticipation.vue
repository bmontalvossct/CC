<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { AlertCircle, Armchair, ArrowLeft, Calendar, Edit2, History, Mic, Plus, Save, Settings, Trash2, Trophy, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface RecitationLog {
    id: number;
    conducted_on: string;
    conducted_on_formatted: string;
    accuracy: number | null;
    delivery: number | null;
    score: number;
    comments: string | null;
    created_at?: string;
}

interface StudentRow {
    id: number;
    student_number: string;
    full_name: string;
    first_name?: string;
    seat_label: string | null;
    times_called: number;
    avg_accuracy: number | null;
    avg_delivery: number | null;
    avg_score: number | null;
    bonus_points: number;
    bonus_cap: number;
    computed_grade: number | null;
    called_today: boolean;
    recitations: RecitationLog[];
    today_recitation: {
        id: number;
        accuracy: number | null;
        delivery: number | null;
        score: number | string;
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
    bonusCap?: number;
    todayDate: string;
    todayFormatted: string;
}>();

const page = usePage<any>();
const activeTab = ref<'floor' | 'rubrics'>('floor');
const scoringStudent = ref<StudentRow | null>(null);

// Bonus Cap configuration state
const showBonusCapModal = ref(false);
const bonusCapForm = useForm({
    recitation: props.bonusCap ?? 5,
});

watch(
    () => props.bonusCap,
    (val) => {
        bonusCapForm.recitation = val ?? 5;
    },
);

const saveBonusCap = () => {
    bonusCapForm.put(`/sections/${props.section.id}/grading-weights`, {
        preserveScroll: true,
        onSuccess: () => {
            showBonusCapModal.value = false;
        },
    });
};

// Student Logs Modal state
const selectedStudentForLogs = ref<StudentRow | null>(null);
const editingLog = ref<RecitationLog | null>(null);
const deletingLog = ref<RecitationLog | null>(null);

const currentLogsStudent = computed(() => {
    if (!selectedStudentForLogs.value) return null;
    return props.students.find((s) => s.id === selectedStudentForLogs.value?.id) || selectedStudentForLogs.value;
});

const initials = (name?: string) => {
    if (!name) return '';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();
};

const scoreForm = useForm({
    score: 8,
    accuracy: 4,
    delivery: 4,
    comments: '',
    conducted_on: props.todayDate,
});

const editLogForm = useForm({
    score: 8,
    accuracy: 4,
    delivery: 4,
    comments: '',
    conducted_on: '',
});

const openScoring = (student: StudentRow) => {
    scoringStudent.value = student;
    scoreForm.clearErrors();
    scoreForm.conducted_on = props.todayDate;
    if (student.today_recitation) {
        scoreForm.accuracy = student.today_recitation.accuracy !== null ? Number(student.today_recitation.accuracy) : 4;
        scoreForm.delivery = student.today_recitation.delivery !== null ? Number(student.today_recitation.delivery) : 4;
        scoreForm.score = Number(student.today_recitation.score) || 8;
        scoreForm.comments = student.today_recitation.comments || '';
    } else {
        scoreForm.accuracy = 4;
        scoreForm.delivery = 4;
        scoreForm.score = 8;
        scoreForm.comments = '';
    }
};

const closeScoring = () => {
    scoringStudent.value = null;
    scoreForm.reset();
    scoreForm.clearErrors();
};

const submitScore = () => {
    if (!scoringStudent.value) return;
    scoreForm.post(`/sections/${props.section.id}/recitation/score/${scoringStudent.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeScoring(),
    });
};

const setPreset = (score: number) => {
    scoreForm.score = score;
    const half = Math.round(score / 2);
    scoreForm.accuracy = Math.min(5, Math.max(0, half));
    scoreForm.delivery = Math.min(5, Math.max(0, score - scoreForm.accuracy));
};

const setEditPreset = (score: number) => {
    editLogForm.score = score;
    const half = Math.round(score / 2);
    editLogForm.accuracy = Math.min(5, Math.max(0, half));
    editLogForm.delivery = Math.min(5, Math.max(0, score - editLogForm.accuracy));
};

const setAccuracy = (val: number) => {
    scoreForm.accuracy = val;
    scoreForm.score = Math.min(10, Math.max(0, Number(scoreForm.accuracy) + Number(scoreForm.delivery)));
};

const setDelivery = (val: number) => {
    scoreForm.delivery = val;
    scoreForm.score = Math.min(10, Math.max(0, Number(scoreForm.accuracy) + Number(scoreForm.delivery)));
};

const setEditAccuracy = (val: number) => {
    editLogForm.accuracy = val;
    editLogForm.score = Math.min(10, Math.max(0, Number(editLogForm.accuracy) + Number(editLogForm.delivery)));
};

const setEditDelivery = (val: number) => {
    editLogForm.delivery = val;
    editLogForm.score = Math.min(10, Math.max(0, Number(editLogForm.accuracy) + Number(editLogForm.delivery)));
};

// Logs Modal Actions
const openStudentLogs = (student: StudentRow) => {
    selectedStudentForLogs.value = student;
    editingLog.value = null;
    deletingLog.value = null;
};

const closeStudentLogs = () => {
    selectedStudentForLogs.value = null;
    editingLog.value = null;
    deletingLog.value = null;
};

const startEditLog = (log: RecitationLog) => {
    editingLog.value = log;
    editLogForm.clearErrors();
    editLogForm.score = Number(log.score);
    editLogForm.accuracy = log.accuracy !== null ? Number(log.accuracy) : Math.min(5, Math.ceil(Number(log.score) / 2));
    editLogForm.delivery = log.delivery !== null ? Number(log.delivery) : Math.min(5, Math.floor(Number(log.score) / 2));
    editLogForm.comments = log.comments || '';
    editLogForm.conducted_on = log.conducted_on;
};

const cancelEditLog = () => {
    editingLog.value = null;
    editLogForm.reset();
    editLogForm.clearErrors();
};

const submitEditLog = () => {
    if (!editingLog.value || !selectedStudentForLogs.value) return;
    editLogForm.put(`/sections/${props.section.id}/recitations/${editingLog.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingLog.value = null;
        },
    });
};

const deleteLogForm = useForm({});
const confirmDeleteLog = (log: RecitationLog) => {
    deletingLog.value = log;
};

const submitDeleteLog = () => {
    if (!deletingLog.value) return;
    deleteLogForm.delete(`/sections/${props.section.id}/recitations/${deletingLog.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deletingLog.value = null;
        },
    });
};

// Find student info for a seat
const studentMap = computed(() => {
    const map = new Map<number, StudentRow>();
    props.students.forEach((s) => map.set(Number(s.id), s));
    return map;
});

// Unseated students in the section
const unseatedStudents = computed(() => {
    const seatedIds = new Set<number>();
    props.section.layoutBlocks.forEach((block) => {
        block.seats.forEach((seat) => {
            if (seat.student_id) seatedIds.add(Number(seat.student_id));
        });
    });
    return props.students.filter((s) => !seatedIds.has(Number(s.id)));
});

// Classroom layout helpers
const getBlockRows = (block: any) => {
    const internalRows = block.internal_rows || Math.max(1, ...(block.seats?.map((s: any) => s.row_number) ?? [1]));
    return Array.from({ length: internalRows }, (_, index) =>
        (block.seats || []).filter((seat: any) => seat.row_number === index + 1).sort((a: any, b: any) => a.column_number - b.column_number),
    );
};

const getBlockCols = (block: any) => {
    return block.internal_columns || Math.max(1, ...(block.seats?.map((s: any) => s.column_number) ?? [1]));
};

const getBlockDensity = (block: any): 'spacious' | 'compact' | 'condensed' | 'micro' => {
    const cols = getBlockCols(block);
    if (cols <= 5) return 'spacious';
    if (cols <= 8) return 'compact';
    if (cols <= 12) return 'condensed';
    return 'micro';
};

const hasColumnAisle = (block: any, position: number) => (block.aisle_after_columns ?? []).includes(position);
const hasRowAisle = (block: any, position: number) => (block.aisle_after_rows ?? []).includes(position);

// Grade color helper
const gradeColor = (pct: number | null) => {
    if (pct === null) return 'text-muted-foreground';
    if (pct >= 90) return 'text-emerald-600 dark:text-emerald-400';
    if (pct >= 80) return 'text-primary';
    if (pct >= 75) return 'text-amber-600 dark:text-amber-400';
    return 'text-rose-600 dark:text-rose-400';
};

const ratingLabel = (val: number) => {
    if (val === 5) return '5 · Excellent';
    if (val === 4) return '4 · Very Good';
    if (val === 3) return '3 · Good / Fair';
    if (val === 2) return '2 · Needs Work';
    if (val === 1) return '1 · Poor';
    return '0 · No Points';
};
</script>

<template>
    <Head :title="`Oral Participation · ${section.name} - ClassCheck`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Oral Participation', href: '#' },
        ]"
    >
        <main class="min-h-screen bg-background p-5 text-foreground md:p-8">
            <div class="mx-auto max-w-[1400px]">
                <!-- Flash Message -->
                <div
                    v-if="page.props.flash?.success"
                    class="shadow-xs mb-6 rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-medium text-primary"
                >
                    {{ page.props.flash.success }}
                </div>

                <!-- Header Block -->
                <header class="rounded-3xl border border-border/80 bg-card p-6 shadow-sm sm:p-8">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge-primary font-mono font-medium">{{ section.subject_code }}</span>
                                <span class="badge-muted">{{ section.name }}</span>
                                <span class="badge-amber font-mono font-medium text-amber-700 dark:text-amber-400">
                                    Recitation Bonus: +{{ bonusCap || 5 }} pts
                                </span>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-500/20 dark:text-amber-400"
                                    title="Configure recitation bonus points"
                                    @click="showBonusCapModal = true"
                                >
                                    <Settings class="size-3" />
                                    <span>Bonus Points</span>
                                </button>
                            </div>
                            <h1 class="mt-2 text-2xl font-bold tracking-tight text-foreground sm:text-3xl">Oral Participation & Recitations</h1>
                            <p class="mt-1 text-xs text-muted-foreground sm:text-sm">
                                Track student answers, view recitation logs per student, and adjust scores from 0 to 10.
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <Link
                                :href="`/sections/${section.id}`"
                                class="shadow-xs inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-medium text-foreground transition-colors hover:bg-secondary"
                            >
                                <ArrowLeft class="size-4 text-muted-foreground" />
                                <span>Back to section</span>
                            </Link>
                        </div>
                    </div>
                </header>

                <!-- Tab Switcher -->
                <div class="mt-6 flex items-center gap-1 rounded-xl border border-border/60 bg-secondary/50 p-1">
                    <button
                        type="button"
                        class="flex-1 rounded-lg px-4 py-2.5 text-xs font-medium transition-colors"
                        :class="
                            activeTab === 'floor'
                                ? 'border border-border/80 bg-card text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="activeTab === 'floor'"
                    >
                        <Mic class="mr-1.5 inline size-3.5" /> Floor View — Click to Score & Logs
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-lg px-4 py-2.5 text-xs font-medium transition-colors"
                        :class="
                            activeTab === 'rubrics'
                                ? 'border border-border/80 bg-card text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="activeTab === 'rubrics'"
                    >
                        <Trophy class="mr-1.5 inline size-3.5" /> Rubrics & Student Recitation Logs
                    </button>
                </div>

                <!-- Floor View Tab -->
                <section v-if="activeTab === 'floor'" class="paper-card mt-6 rounded-3xl p-6 md:p-8">
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="eyebrow">Seating layout</span>
                            <h2 class="mt-1 text-xl font-medium tracking-tight">Click any student's chair to grade or view recitation logs</h2>
                        </div>
                        <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                            <span class="inline-flex size-3 rounded-full bg-emerald-400"></span>
                            <span>Scored today</span>
                            <span class="ml-3 inline-flex size-3 rounded-full bg-[#164e3f]"></span>
                            <span>Not yet scored</span>
                        </div>
                    </div>

                    <!-- Teaching Wall Bar -->
                    <div
                        class="shadow-xs mb-6 flex items-center justify-center rounded-2xl bg-[#164e3f] px-6 py-3.5 text-center text-xs font-bold uppercase tracking-[0.25em] text-white dark:bg-[#134e48] md:text-sm"
                    >
                        Teaching Wall / Front Board
                    </div>

                    <!-- Classroom Grid -->
                    <div
                        v-if="section.layoutBlocks && section.layoutBlocks.length"
                        class="w-full max-w-full overflow-x-auto overscroll-x-contain pb-4 [-webkit-overflow-scrolling:touch] [scrollbar-gutter:stable]"
                        role="region"
                        aria-label="Interactive classroom oral participation seating chart"
                        tabindex="0"
                    >
                        <div
                            class="grid w-full min-w-0 gap-6"
                            :style="{
                                gridTemplateColumns: `repeat(${Math.max(1, ...section.layoutBlocks.map((block: any) => block.block_column || 1))}, minmax(0, 1fr))`,
                            }"
                        >
                            <article
                                v-for="block in section.layoutBlocks"
                                :key="block.id"
                                class="shadow-xs rounded-2xl border border-border/80 bg-card transition-all"
                                :class="getBlockDensity(block) === 'spacious' ? 'p-5' : getBlockDensity(block) === 'compact' ? 'p-3.5' : 'p-2 sm:p-3'"
                                :style="{ gridColumn: block.block_column || 1, gridRow: block.block_row || 1 }"
                            >
                                <p
                                    v-if="block.label && block.label !== 'Classroom'"
                                    class="mb-3 text-xs font-bold uppercase tracking-[0.14em] text-muted-foreground"
                                >
                                    {{ block.label }}
                                </p>

                                <template v-for="(seats, rowIndex) in getBlockRows(block)" :key="rowIndex">
                                    <div
                                        class="flex items-stretch first:mt-0 last:mb-0"
                                        :class="
                                            getBlockDensity(block) === 'spacious'
                                                ? 'my-3 gap-3'
                                                : getBlockDensity(block) === 'compact'
                                                  ? 'my-2 gap-2'
                                                  : getBlockDensity(block) === 'condensed'
                                                    ? 'my-1.5 gap-1.5'
                                                    : 'my-1 gap-1'
                                        "
                                    >
                                        <template v-for="seat in seats" :key="seat.id">
                                            <div
                                                class="relative flex min-w-0 flex-1 flex-col items-stretch"
                                                :class="
                                                    getBlockDensity(block) === 'spacious'
                                                        ? 'min-w-[5.5rem] sm:min-w-[6.5rem]'
                                                        : getBlockDensity(block) === 'compact'
                                                          ? 'min-w-[3.75rem] sm:min-w-[4.5rem]'
                                                          : getBlockDensity(block) === 'condensed'
                                                            ? 'min-w-[2.75rem] sm:min-w-[3.25rem]'
                                                            : 'min-w-[2rem] sm:min-w-[2.5rem]'
                                                "
                                            >
                                                <!-- Student Seated -->
                                                <div
                                                    v-if="!seat.is_disabled && seat.student_id && studentMap.get(Number(seat.student_id))"
                                                    class="group relative flex w-full flex-1 flex-col items-center justify-center border text-center transition-all duration-150"
                                                    :class="[
                                                        getBlockDensity(block) === 'spacious'
                                                            ? 'min-h-[6.5rem] rounded-2xl p-2.5 sm:min-h-[7.25rem] sm:p-3'
                                                            : getBlockDensity(block) === 'compact'
                                                              ? 'min-h-[4.75rem] rounded-xl p-1.5 sm:min-h-[5.5rem] sm:p-2'
                                                              : getBlockDensity(block) === 'condensed'
                                                                ? 'min-h-[3.75rem] rounded-lg p-1 sm:min-h-[4.25rem]'
                                                                : 'min-h-[3rem] rounded-md p-0.5 sm:min-h-[3.5rem]',
                                                        studentMap.get(Number(seat.student_id))?.called_today
                                                            ? 'border-emerald-400/80 bg-[#164e3f] text-white shadow-md ring-2 ring-emerald-400 ring-offset-2 hover:brightness-105 dark:bg-[#134e48]'
                                                            : 'shadow-xs border-[#1b5d4e]/80 bg-[#164e3f] text-white hover:-translate-y-0.5 hover:shadow-md hover:brightness-105 dark:bg-[#134e48]',
                                                    ]"
                                                >
                                                    <!-- Quick Actions on Hover / Top Bar -->
                                                    <div class="absolute right-1 top-1 z-20 flex items-center gap-1">
                                                        <button
                                                            type="button"
                                                            title="View recitation logs"
                                                            class="grid place-items-center rounded-md bg-black/30 text-white/80 transition-colors hover:bg-black/60 hover:text-white"
                                                            :class="getBlockDensity(block) === 'spacious' ? 'size-5' : 'size-4'"
                                                            @click.stop="openStudentLogs(studentMap.get(Number(seat.student_id))!)"
                                                        >
                                                            <History :class="getBlockDensity(block) === 'spacious' ? 'size-3' : 'size-2.5'" />
                                                        </button>
                                                    </div>

                                                    <!-- Main Card Clickable to Score -->
                                                    <button
                                                        type="button"
                                                        class="flex size-full cursor-pointer flex-col items-center justify-center text-center focus:outline-none"
                                                        @click="openScoring(studentMap.get(Number(seat.student_id))!)"
                                                    >
                                                        <!-- Scaled Photo / Avatar -->
                                                        <div
                                                            class="shadow-xs flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-white/20 ring-1 ring-white/25 sm:ring-2"
                                                            :class="
                                                                getBlockDensity(block) === 'spacious'
                                                                    ? 'size-10 sm:size-11'
                                                                    : getBlockDensity(block) === 'compact'
                                                                      ? 'size-7 sm:size-8'
                                                                      : getBlockDensity(block) === 'condensed'
                                                                        ? 'size-5 sm:size-6'
                                                                        : 'size-4 sm:size-5'
                                                            "
                                                        >
                                                            <img
                                                                v-if="studentMap.get(Number(seat.student_id))?.photo_url"
                                                                :src="studentMap.get(Number(seat.student_id))!.photo_url!"
                                                                :alt="studentMap.get(Number(seat.student_id))!.full_name"
                                                                class="size-full object-cover"
                                                            />
                                                            <span
                                                                v-else
                                                                class="uppercase tracking-wider text-white"
                                                                :class="
                                                                    getBlockDensity(block) === 'spacious'
                                                                        ? 'text-xs font-black sm:text-sm'
                                                                        : getBlockDensity(block) === 'compact'
                                                                          ? 'text-[10px] font-bold sm:text-xs'
                                                                          : getBlockDensity(block) === 'condensed'
                                                                            ? 'text-[8px] font-bold sm:text-[9px]'
                                                                            : 'text-[7px] font-bold'
                                                                "
                                                            >
                                                                {{ initials(studentMap.get(Number(seat.student_id))?.full_name) }}
                                                            </span>
                                                        </div>

                                                        <!-- Complete Name -->
                                                        <span
                                                            class="block w-full truncate text-center font-bold uppercase leading-tight tracking-tight text-white"
                                                            :class="
                                                                getBlockDensity(block) === 'spacious'
                                                                    ? 'mt-2 max-w-[7.5rem] text-[11px] sm:text-xs'
                                                                    : getBlockDensity(block) === 'compact'
                                                                      ? 'mt-1 max-w-[5rem] text-[9.5px] sm:text-[10.5px]'
                                                                      : getBlockDensity(block) === 'condensed'
                                                                        ? 'mt-0.5 max-w-[3.75rem] text-[8px] sm:text-[8.5px]'
                                                                        : 'mt-0.25 max-w-[2.75rem] text-[7px]'
                                                            "
                                                            :title="studentMap.get(Number(seat.student_id))?.full_name || '—'"
                                                        >
                                                            {{ studentMap.get(Number(seat.student_id))?.full_name || '—' }}
                                                        </span>

                                                        <!-- Seat Label & Recitation Status -->
                                                        <div
                                                            class="flex items-center justify-center gap-1 font-mono font-medium uppercase leading-none tracking-wider text-white/70"
                                                            :class="
                                                                getBlockDensity(block) === 'spacious'
                                                                    ? 'mt-0.5 text-[9px] sm:text-[10px]'
                                                                    : getBlockDensity(block) === 'compact'
                                                                      ? 'mt-0.5 text-[8px] sm:text-[8.5px]'
                                                                      : getBlockDensity(block) === 'condensed'
                                                                        ? 'mt-0 text-[7px] sm:text-[7.5px]'
                                                                        : 'mt-0 text-[6.5px]'
                                                            "
                                                        >
                                                            <span>{{ seat.label }}</span>
                                                            <span
                                                                v-if="studentMap.get(Number(seat.student_id))?.today_recitation"
                                                                class="font-bold text-emerald-300"
                                                            >
                                                                · {{ studentMap.get(Number(seat.student_id))!.today_recitation!.score }}/10
                                                            </span>
                                                            <span
                                                                v-else-if="studentMap.get(Number(seat.student_id))?.called_today"
                                                                class="font-bold text-emerald-300"
                                                            >
                                                                · Scored
                                                            </span>
                                                        </div>
                                                    </button>
                                                </div>

                                                <!-- Available / Vacant Chair -->
                                                <div
                                                    v-else-if="!seat.is_disabled"
                                                    class="flex w-full flex-1 flex-col items-center justify-center border-2 border-slate-200/90 bg-card text-center text-muted-foreground dark:border-border/80"
                                                    :class="
                                                        getBlockDensity(block) === 'spacious'
                                                            ? 'min-h-[6.5rem] rounded-2xl p-2.5 sm:min-h-[7.25rem] sm:p-3'
                                                            : getBlockDensity(block) === 'compact'
                                                              ? 'min-h-[4.75rem] rounded-xl p-1.5 sm:min-h-[5.5rem] sm:p-2'
                                                              : getBlockDensity(block) === 'condensed'
                                                                ? 'min-h-[3.75rem] rounded-lg p-1 sm:min-h-[4.25rem]'
                                                                : 'min-h-[3rem] rounded-md p-0.5 sm:min-h-[3.5rem]'
                                                    "
                                                >
                                                    <Armchair
                                                        class="text-slate-400 dark:text-muted-foreground/60"
                                                        :class="
                                                            getBlockDensity(block) === 'spacious'
                                                                ? 'size-6'
                                                                : getBlockDensity(block) === 'compact'
                                                                  ? 'size-4.5 sm:size-5'
                                                                  : getBlockDensity(block) === 'condensed'
                                                                    ? 'size-3.5 sm:size-4'
                                                                    : 'size-3'
                                                        "
                                                    />
                                                    <span
                                                        class="block font-mono font-semibold uppercase tracking-wider text-slate-500 dark:text-muted-foreground"
                                                        :class="
                                                            getBlockDensity(block) === 'spacious'
                                                                ? 'mt-2 text-[10px] sm:text-[11px]'
                                                                : getBlockDensity(block) === 'compact'
                                                                  ? 'mt-1 text-[9px] sm:text-[9.5px]'
                                                                  : getBlockDensity(block) === 'condensed'
                                                                    ? 'mt-0.5 text-[7.5px] sm:text-[8px]'
                                                                    : 'mt-0.25 text-[6.5px]'
                                                        "
                                                    >
                                                        {{ seat.label }}
                                                    </span>
                                                </div>

                                                <!-- Disabled / Unavailable Slot -->
                                                <div
                                                    v-else
                                                    class="w-full flex-1 rounded-lg border-2 border-transparent bg-[repeating-linear-gradient(135deg,hsl(var(--border)),hsl(var(--border))_4px,transparent_4px,transparent_8px)] opacity-30"
                                                    :class="
                                                        getBlockDensity(block) === 'spacious'
                                                            ? 'min-h-[6.5rem] sm:min-h-[7.25rem]'
                                                            : getBlockDensity(block) === 'compact'
                                                              ? 'min-h-[4.75rem] sm:min-h-[5.5rem]'
                                                              : getBlockDensity(block) === 'condensed'
                                                                ? 'min-h-[3.75rem] sm:min-h-[4.25rem]'
                                                                : 'min-h-[3rem] sm:min-h-[3.5rem]'
                                                    "
                                                />
                                            </div>

                                            <!-- Column Aisle Spacer -->
                                            <div
                                                v-if="hasColumnAisle(block, seat.column_number)"
                                                class="shrink-0 rounded-lg border-2 border-dashed border-primary/30 bg-primary/5"
                                                :class="
                                                    getBlockDensity(block) === 'spacious'
                                                        ? 'mx-2 w-7'
                                                        : getBlockDensity(block) === 'compact'
                                                          ? 'mx-1.5 w-5'
                                                          : 'mx-1 w-4'
                                                "
                                            />
                                        </template>
                                    </div>

                                    <!-- Row Aisle Spacer -->
                                    <div
                                        v-if="hasRowAisle(block, rowIndex + 1)"
                                        class="flex w-full items-center justify-center rounded-lg border-2 border-dashed border-primary/30 bg-primary/5 text-center font-semibold uppercase tracking-wider text-primary/70"
                                        :class="
                                            getBlockDensity(block) === 'spacious'
                                                ? 'my-2 h-7 text-[10px]'
                                                : getBlockDensity(block) === 'compact'
                                                  ? 'my-1.5 h-5.5 text-[9px]'
                                                  : 'my-1 h-4.5 text-[8px]'
                                        "
                                    >
                                        Aisle
                                    </div>
                                </template>
                            </article>
                        </div>
                    </div>

                    <div v-else class="rounded-2xl border border-dashed border-border bg-secondary/30 p-12 text-center">
                        <p class="text-sm text-muted-foreground">No classroom layout configured. Set up a floor plan first.</p>
                    </div>

                    <!-- Unseated Students Section -->
                    <div v-if="unseatedStudents.length" class="mt-8 border-t border-border/80 pt-6">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-foreground">Unseated Students ({{ unseatedStudents.length }})</h3>
                                <p class="text-xs text-muted-foreground">Students enrolled without an assigned chair — click to grade or view logs</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                            <div
                                v-for="student in unseatedStudents"
                                :key="student.id"
                                class="group relative flex flex-col items-center justify-center rounded-2xl border p-3 text-center transition-all hover:scale-[1.02]"
                                :class="[
                                    student.called_today
                                        ? 'border-emerald-400/80 bg-[#164e3f] text-white shadow-md'
                                        : 'border-border bg-card text-foreground hover:bg-secondary',
                                ]"
                            >
                                <button
                                    type="button"
                                    title="View recitation logs"
                                    class="absolute right-2 top-2 grid size-5 place-items-center rounded-md bg-black/20 text-muted-foreground transition-colors hover:bg-black/50 hover:text-white"
                                    @click.stop="openStudentLogs(student)"
                                >
                                    <History class="size-3" />
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full cursor-pointer flex-col items-center justify-center focus:outline-none"
                                    @click="openScoring(student)"
                                >
                                    <div
                                        class="flex size-9 items-center justify-center rounded-full bg-primary/20 font-bold uppercase text-primary dark:text-primary-foreground"
                                    >
                                        {{ initials(student.full_name) }}
                                    </div>
                                    <span class="mt-2 block w-full truncate text-xs font-bold">{{ student.full_name }}</span>
                                    <span class="font-mono text-[10px] text-muted-foreground">{{ student.student_number }}</span>
                                    <span v-if="student.today_recitation" class="mt-1 font-mono text-[10px] font-bold text-emerald-400">
                                        {{ student.today_recitation.score }}/10
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Rubrics & Grades Tab -->
                <section v-if="activeTab === 'rubrics'" class="paper-card mt-6 overflow-hidden p-6 shadow-sm md:p-8">
                    <div class="mb-6">
                        <span class="eyebrow">Bonus Computation & Logs</span>
                        <h2 class="mt-1 text-xl font-medium tracking-tight">Oral recitation rubrics, scores (0–10) & student logs</h2>
                        <p class="mt-2 max-w-2xl text-xs text-muted-foreground">
                            Each recitation can be scored from <strong class="font-medium text-foreground">0 to 10</strong> points. Recitations earn
                            <strong class="font-medium text-amber-600 dark:text-amber-400"
                                >additional bonus points (+{{ bonusCap || 5 }} pts)</strong
                            >
                            added directly to the student's Activities score. Click <strong class="font-medium text-foreground">Logs</strong> on any
                            student to review and adjust their full recitation history.
                        </p>
                    </div>

                    <!-- Bonus Points Configuration Card -->
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-amber-500/30 bg-amber-500/5 p-4">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                                Oral Recitation Bonus Points
                            </span>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Additional bonus points awarded to students' Activities scores based on their recitation average.
                            </p>
                        </div>
                        <form class="flex items-center gap-2" @submit.prevent="saveBonusCap">
                            <span class="text-xs font-medium text-muted-foreground">Bonus Points:</span>
                            <div class="flex items-center gap-1">
                                <span class="text-xs font-bold text-amber-600">+</span>
                                <input
                                    v-model.number="bonusCapForm.recitation"
                                    type="number"
                                    min="0"
                                    class="w-20 rounded-lg border border-amber-500/40 bg-background px-3 py-1.5 text-center text-sm font-bold text-amber-600 focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-amber-400"
                                />
                                <span class="text-xs font-bold text-amber-600 dark:text-amber-400">pts</span>
                            </div>
                            <button
                                type="submit"
                                :disabled="bonusCapForm.processing"
                                class="ink-button !h-9 !rounded-xl !px-3.5 text-xs"
                            >
                                <Save class="size-3.5" />
                                <span>{{ bonusCapForm.processing ? 'Saving…' : 'Save Points' }}</span>
                            </button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[850px] border-collapse text-sm">
                            <thead>
                                <tr
                                    class="border-b border-border/80 bg-secondary/40 text-left text-[11px] font-medium uppercase tracking-wider text-muted-foreground"
                                >
                                    <th class="rounded-l-lg px-4 py-3">Chair</th>
                                    <th class="px-4 py-3">Student</th>
                                    <th class="px-4 py-3 text-center">Sessions Logged</th>
                                    <th class="px-4 py-3 text-center">Avg Accuracy</th>
                                    <th class="px-4 py-3 text-center">Avg Delivery</th>
                                    <th class="px-4 py-3 text-center">Avg Score / 10</th>
                                    <th class="px-4 py-3 text-center">Bonus Pts (to Activities)</th>
                                    <th class="rounded-r-lg px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr
                                    v-for="student in students"
                                    :key="student.id"
                                    class="cursor-pointer transition-colors hover:bg-secondary/30"
                                    @click="openStudentLogs(student)"
                                >
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-lg border border-border/80 bg-secondary px-2.5 py-1 font-mono text-xs font-medium text-foreground"
                                        >
                                            {{ student.seat_label || '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="block font-medium text-foreground">{{ student.full_name }}</span>
                                        <span class="font-mono text-xs text-muted-foreground">{{ student.student_number }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-secondary/70 px-2.5 py-1 text-xs font-semibold text-foreground transition-all hover:scale-105 hover:bg-secondary"
                                            @click.stop="openStudentLogs(student)"
                                        >
                                            <History class="size-3 text-primary" />
                                            <span>{{ student.times_called }} logs</span>
                                        </button>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-center text-xs font-medium"
                                        :class="gradeColor(student.avg_accuracy ? student.avg_accuracy * 20 : null)"
                                    >
                                        {{ student.avg_accuracy !== null ? student.avg_accuracy.toFixed(1) : '—' }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-center text-xs font-medium"
                                        :class="gradeColor(student.avg_delivery ? student.avg_delivery * 20 : null)"
                                    >
                                        {{ student.avg_delivery !== null ? student.avg_delivery.toFixed(1) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-mono text-xs font-medium">
                                        {{ student.avg_score !== null ? student.avg_score.toFixed(1) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            v-if="student.avg_score !== null"
                                            class="inline-flex items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                        >
                                            +{{ student.bonus_points }} pts to Activities
                                        </span>
                                        <span v-else class="text-xs text-muted-foreground">— (0 rec)</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2" @click.stop>
                                            <button
                                                type="button"
                                                class="inline-flex h-8 items-center gap-1 rounded-lg border border-border bg-card px-2.5 text-xs font-medium text-foreground transition-all hover:bg-secondary"
                                                @click="openStudentLogs(student)"
                                            >
                                                <History class="size-3 text-muted-foreground" />
                                                <span>Logs</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex h-8 items-center gap-1 rounded-lg px-3 text-xs font-bold transition-all hover:scale-105"
                                                :class="[
                                                    student.called_today
                                                        ? 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20 dark:text-emerald-400'
                                                        : 'bg-primary/10 text-primary hover:bg-primary/20',
                                                ]"
                                                @click="openScoring(student)"
                                            >
                                                <Mic class="size-3" />
                                                <span>{{ student.called_today ? 'Update Score' : 'Score Oral' }}</span>
                                            </button>
                                        </div>
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

        <!-- Scoring Modal (0 to 10 Points Adjustment) -->
        <div
            v-if="scoringStudent"
            class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
            @click.self="closeScoring"
        >
            <div
                class="paper-card relative max-h-[90vh] w-full max-w-lg overflow-hidden overflow-y-auto border-border/90 p-8 shadow-2xl duration-200 animate-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                aria-label="Score oral participation"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Recitation Grading</span>
                    <button
                        type="button"
                        class="grid size-8 place-items-center rounded-xl text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        @click="closeScoring"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="mt-3 flex items-center justify-between">
                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 font-mono text-[11px] font-medium uppercase tracking-wider text-primary"
                    >
                        <Mic class="size-3.5" /> Oral Recitation
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                        @click="openStudentLogs(scoringStudent)"
                    >
                        <History class="size-3" />
                        <span>View past logs ({{ scoringStudent.recitations?.length || 0 }})</span>
                    </button>
                </div>

                <h3 class="mt-3 text-2xl font-bold tracking-tight text-foreground">{{ scoringStudent.full_name }}</h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    Chair {{ scoringStudent.seat_label || 'Unassigned' }} · {{ scoringStudent.student_number }}
                </p>

                <!-- Date Picker for Recitation -->
                <div class="mt-4 flex items-center gap-2">
                    <Calendar class="size-4 text-muted-foreground" />
                    <label class="text-xs font-medium text-muted-foreground">Session Date:</label>
                    <input
                        v-model="scoreForm.conducted_on"
                        type="date"
                        class="rounded-lg border border-border bg-secondary/50 px-2.5 py-1 font-mono text-xs font-medium text-foreground focus-visible:ring-2 focus-visible:ring-primary"
                    />
                </div>

                <!-- 0-10 Direct Score Adjustment & Presets -->
                <div class="mt-5 rounded-2xl border border-border/80 bg-secondary/30 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-foreground">Score Adjustment (0 to 10)</span>
                        <div class="flex items-baseline gap-1 font-mono">
                            <span class="text-2xl font-black text-primary">{{ scoreForm.score }}</span>
                            <span class="text-xs text-muted-foreground">/ 10</span>
                        </div>
                    </div>

                    <!-- Slider 0-10 -->
                    <div class="mt-3">
                        <input
                            v-model.number="scoreForm.score"
                            type="range"
                            min="0"
                            max="10"
                            step="0.5"
                            class="h-2 w-full cursor-pointer appearance-none rounded-lg bg-border accent-primary"
                        />
                        <div class="mt-1 flex justify-between font-mono text-[10px] text-muted-foreground">
                            <span>0 pts</span>
                            <span>2.5</span>
                            <span>5 pts</span>
                            <span>7.5</span>
                            <span>10 pts</span>
                        </div>
                    </div>

                    <!-- Quick Preset Buttons 0 to 10 -->
                    <div class="mt-3 flex flex-wrap gap-1">
                        <button
                            v-for="s in [10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 0]"
                            :key="`preset-${s}`"
                            type="button"
                            class="min-w-[32px] rounded-lg border px-2 py-1 text-center font-mono text-xs font-bold transition-all"
                            :class="
                                Number(scoreForm.score) === s
                                    ? 'shadow-xs border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-card text-foreground hover:bg-secondary'
                            "
                            @click="setPreset(s)"
                        >
                            {{ s }}
                        </button>
                    </div>
                </div>

                <form class="mt-5 space-y-5" @submit.prevent="submitScore">
                    <!-- Accuracy (0-5) -->
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Accuracy (0–5): <span class="font-bold text-foreground">{{ ratingLabel(scoreForm.accuracy) }}</span>
                            </label>
                            <span class="font-mono text-xs font-bold text-foreground">{{ scoreForm.accuracy }}/5</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button
                                v-for="n in [0, 1, 2, 3, 4, 5]"
                                :key="`acc-${n}`"
                                type="button"
                                class="grid size-9 place-items-center rounded-xl border font-mono text-xs font-bold transition-all duration-150"
                                :class="
                                    n === scoreForm.accuracy
                                        ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                        : 'border-border bg-card text-muted-foreground hover:border-primary/40'
                                "
                                @click="setAccuracy(n)"
                            >
                                <span>{{ n }}</span>
                            </button>
                        </div>
                        <small v-if="scoreForm.errors.accuracy" class="mt-1 block text-xs text-rose-600">{{ scoreForm.errors.accuracy }}</small>
                    </div>

                    <!-- Delivery (0-5) -->
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Delivery (0–5): <span class="font-bold text-foreground">{{ ratingLabel(scoreForm.delivery) }}</span>
                            </label>
                            <span class="font-mono text-xs font-bold text-foreground">{{ scoreForm.delivery }}/5</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button
                                v-for="n in [0, 1, 2, 3, 4, 5]"
                                :key="`del-${n}`"
                                type="button"
                                class="grid size-9 place-items-center rounded-xl border font-mono text-xs font-bold transition-all duration-150"
                                :class="
                                    n === scoreForm.delivery
                                        ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                        : 'border-border bg-card text-muted-foreground hover:border-primary/40'
                                "
                                @click="setDelivery(n)"
                            >
                                <span>{{ n }}</span>
                            </button>
                        </div>
                        <small v-if="scoreForm.errors.delivery" class="mt-1 block text-xs text-rose-600">{{ scoreForm.errors.delivery }}</small>
                    </div>

                    <!-- Total Preview -->
                    <div class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 text-center">
                        <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground"
                            >Recitation Score & Bonus (Added to Activities)</span
                        >
                        <div class="mt-1 flex items-baseline justify-center gap-2">
                            <p class="text-3xl font-bold tracking-tight text-primary">
                                {{ scoreForm.score }}<span class="text-sm font-normal text-muted-foreground">/10</span>
                            </p>
                            <span class="rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                +{{ ((Number(scoreForm.score) / 10) * (bonusCap || 5)).toFixed(1) }} bonus pts to Activities
                            </span>
                        </div>
                    </div>

                    <!-- Comments -->
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Comments / Recitation Note <em class="font-normal normal-case text-muted-foreground">(optional)</em>
                        </label>
                        <textarea
                            v-model="scoreForm.comments"
                            rows="2"
                            placeholder="Notes on question answered, topic, or performance..."
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 border-t border-border/80 pt-4">
                        <button
                            type="button"
                            class="shadow-xs inline-flex h-10 items-center justify-center rounded-xl border border-border bg-card px-5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="closeScoring"
                        >
                            Cancel
                        </button>
                        <button type="submit" :disabled="scoreForm.processing" class="ink-button !h-10 !rounded-xl !px-5 text-xs font-bold">
                            {{ scoreForm.processing ? 'Saving…' : scoringStudent.called_today ? 'Update Score' : 'Save Score' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Student Recitation Logs History Modal -->
        <div
            v-if="currentLogsStudent"
            class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
            @click.self="closeStudentLogs"
        >
            <div
                class="paper-card relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden border-border/90 p-6 shadow-2xl duration-200 animate-in zoom-in-95 md:p-8"
                role="dialog"
                aria-modal="true"
                aria-label="Oral participation logs per student"
            >
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-border/80 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="eyebrow">Recitation History</span>
                            <span class="badge-primary font-mono text-[10px]">Chair {{ currentLogsStudent.seat_label || 'Unassigned' }}</span>
                        </div>
                        <h3 class="text-xl font-bold tracking-tight text-foreground">{{ currentLogsStudent.full_name }}</h3>
                        <p class="font-mono text-xs text-muted-foreground">{{ currentLogsStudent.student_number }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="ink-button !h-9 !rounded-xl !px-3 text-xs font-semibold"
                            @click="
                                openScoring(currentLogsStudent);
                                closeStudentLogs();
                            "
                        >
                            <Plus class="size-3.5" />
                            <span>Log Today's Oral</span>
                        </button>
                        <button
                            type="button"
                            class="grid size-8 place-items-center rounded-xl text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            @click="closeStudentLogs"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>

                <!-- Stats Summary Row -->
                <div class="my-4 grid grid-cols-3 gap-3">
                    <div class="rounded-2xl border border-border/70 bg-secondary/30 p-3 text-center">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Total Sessions</span>
                        <p class="mt-0.5 font-mono text-lg font-bold text-foreground">{{ currentLogsStudent.recitations?.length || 0 }}</p>
                    </div>
                    <div class="rounded-2xl border border-border/70 bg-secondary/30 p-3 text-center">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Average Score</span>
                        <p class="mt-0.5 font-mono text-lg font-bold text-primary">
                            {{ currentLogsStudent.avg_score !== null ? `${currentLogsStudent.avg_score} / 10` : '—' }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-3 text-center">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Bonus to Activities</span>
                        <p class="mt-0.5 font-mono text-lg font-bold text-emerald-600 dark:text-emerald-400">
                            +{{ currentLogsStudent.bonus_points }} pts
                        </p>
                    </div>
                </div>

                <!-- Log List (Scrollable) -->
                <div class="flex-1 space-y-3 overflow-y-auto pr-1">
                    <div
                        v-if="!currentLogsStudent.recitations || !currentLogsStudent.recitations.length"
                        class="py-12 text-center text-xs text-muted-foreground"
                    >
                        <History class="mx-auto mb-2 size-8 text-muted-foreground opacity-40" />
                        No recitation entries recorded yet for {{ currentLogsStudent.full_name }}.
                    </div>

                    <!-- Edit Log Inline Form -->
                    <div v-if="editingLog" class="rounded-2xl border-2 border-primary/40 bg-primary/5 p-4 shadow-sm duration-150 animate-in fade-in">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-primary">
                                Adjust Log Entry · {{ editingLog.conducted_on_formatted }}
                            </span>
                            <button type="button" class="text-xs text-muted-foreground hover:text-foreground" @click="cancelEditLog">Cancel</button>
                        </div>

                        <!-- Date & Score Adjuster -->
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-muted-foreground">Date</label>
                                    <input
                                        v-model="editLogForm.conducted_on"
                                        type="date"
                                        class="mt-1 rounded-lg border border-border bg-card px-2.5 py-1 font-mono text-xs"
                                    />
                                </div>
                                <div class="min-w-[200px] flex-1">
                                    <div class="flex items-center justify-between">
                                        <label class="text-[10px] font-bold uppercase text-muted-foreground">Score (0–10)</label>
                                        <span class="font-mono text-sm font-black text-primary">{{ editLogForm.score }}/10</span>
                                    </div>
                                    <input
                                        v-model.number="editLogForm.score"
                                        type="range"
                                        min="0"
                                        max="10"
                                        step="0.5"
                                        class="mt-1 h-2 w-full cursor-pointer appearance-none rounded-lg bg-border accent-primary"
                                    />
                                </div>
                            </div>

                            <!-- Presets -->
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="s in [10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 0]"
                                    :key="`edit-preset-${s}`"
                                    type="button"
                                    class="min-w-[28px] rounded-lg border px-1.5 py-0.5 text-center font-mono text-[11px] font-bold"
                                    :class="
                                        Number(editLogForm.score) === s
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-card hover:bg-secondary'
                                    "
                                    @click="setEditPreset(s)"
                                >
                                    {{ s }}
                                </button>
                            </div>

                            <!-- Accuracy & Delivery Sub-ratings -->
                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-muted-foreground">Accuracy (0–5)</label>
                                    <div class="mt-1 flex items-center gap-1">
                                        <button
                                            v-for="n in [0, 1, 2, 3, 4, 5]"
                                            :key="`edit-acc-${n}`"
                                            type="button"
                                            class="size-7 rounded-md border font-mono text-[10px] font-bold"
                                            :class="
                                                n === editLogForm.accuracy
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border bg-card'
                                            "
                                            @click="setEditAccuracy(n)"
                                        >
                                            {{ n }}
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-muted-foreground">Delivery (0–5)</label>
                                    <div class="mt-1 flex items-center gap-1">
                                        <button
                                            v-for="n in [0, 1, 2, 3, 4, 5]"
                                            :key="`edit-del-${n}`"
                                            type="button"
                                            class="size-7 rounded-md border font-mono text-[10px] font-bold"
                                            :class="
                                                n === editLogForm.delivery
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border bg-card'
                                            "
                                            @click="setEditDelivery(n)"
                                        >
                                            {{ n }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Comments -->
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-muted-foreground">Comments / Notes</label>
                                <textarea
                                    v-model="editLogForm.comments"
                                    rows="2"
                                    placeholder="Notes..."
                                    class="mt-1 w-full rounded-xl border border-input bg-card px-3 py-1.5 text-xs"
                                />
                            </div>

                            <div class="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    class="rounded-xl border border-border bg-card px-3 py-1.5 text-xs font-medium hover:bg-secondary"
                                    @click="cancelEditLog"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    :disabled="editLogForm.processing"
                                    class="ink-button !h-8 !rounded-xl !px-4 text-xs font-bold"
                                    @click="submitEditLog"
                                >
                                    <Save class="size-3" />
                                    {{ editLogForm.processing ? 'Saving…' : 'Save Adjustments' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- List of Logs Cards -->
                    <div
                        v-for="log in currentLogsStudent.recitations"
                        :key="log.id"
                        class="rounded-2xl border border-border/80 bg-card p-4 transition-all hover:border-border"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="grid size-9 place-items-center rounded-xl bg-primary/10 font-bold text-primary">
                                    <Mic class="size-4" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-foreground">{{ log.conducted_on_formatted }}</span>
                                        <span class="font-mono text-[10px] text-muted-foreground">{{ log.conducted_on }}</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-mono text-xs font-bold"
                                            :class="
                                                Number(log.score) >= 8
                                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                    : Number(log.score) >= 6
                                                      ? 'bg-primary/10 text-primary'
                                                      : Number(log.score) >= 4
                                                        ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                        : 'bg-rose-500/10 text-rose-600 dark:text-rose-400'
                                            "
                                        >
                                            {{ log.score }} / 10 pts
                                        </span>
                                        <span v-if="log.accuracy !== null && log.delivery !== null" class="text-[11px] text-muted-foreground">
                                            (Accuracy: {{ log.accuracy }}/5 · Delivery: {{ log.delivery }}/5)
                                        </span>
                                    </div>
                                    <p v-if="log.comments" class="mt-2 rounded-lg bg-secondary/30 p-2 text-xs italic text-muted-foreground">
                                        "{{ log.comments }}"
                                    </p>
                                </div>
                            </div>

                            <!-- Actions per log entry -->
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    title="Adjust score from 0-10"
                                    class="inline-flex items-center gap-1 rounded-lg border border-border bg-secondary/50 px-2.5 py-1 text-xs font-semibold text-foreground transition-colors hover:bg-secondary hover:text-primary"
                                    @click="startEditLog(log)"
                                >
                                    <Edit2 class="size-3" />
                                    <span>Adjust</span>
                                </button>
                                <button
                                    type="button"
                                    title="Delete this recitation entry"
                                    class="grid size-7 place-items-center rounded-lg text-muted-foreground transition-colors hover:bg-rose-500/10 hover:text-rose-600"
                                    @click="confirmDeleteLog(log)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Log Confirmation Modal -->
        <div
            v-if="deletingLog"
            class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
            @click.self="deletingLog = null"
        >
            <div
                class="paper-card relative w-full max-w-sm overflow-hidden border-border/90 p-6 shadow-2xl duration-200 animate-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                aria-label="Confirm deletion"
            >
                <div class="flex items-center gap-3">
                    <div class="grid size-10 place-items-center rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-400">
                        <AlertCircle class="size-5" />
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-foreground">Delete Recitation Log?</h4>
                        <p class="text-xs text-muted-foreground">Date: {{ deletingLog.conducted_on_formatted }} ({{ deletingLog.score }}/10)</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-muted-foreground">
                    Are you sure you want to delete this recitation entry? The student's average recitation score and bonus points will be
                    automatically recalculated.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-border bg-card px-3 py-1.5 text-xs font-medium hover:bg-secondary"
                        @click="deletingLog = null"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="deleteLogForm.processing"
                        class="rounded-xl bg-rose-600 px-3.5 py-1.5 text-xs font-bold text-white transition-colors hover:bg-rose-700"
                        @click="submitDeleteLog"
                    >
                        {{ deleteLogForm.processing ? 'Deleting…' : 'Delete Entry' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Bonus Cap Editor Modal -->
        <div
            v-if="showBonusCapModal"
            class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
            @click.self="showBonusCapModal = false"
        >
            <div
                class="paper-card relative w-full max-w-md overflow-hidden border-border/90 p-6 shadow-2xl duration-200 animate-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                aria-label="Oral Recitation Bonus Cap Configuration"
            >
                <div class="flex items-start justify-between border-b border-border/80 pb-4">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Oral Recitation</span>
                        <h3 class="mt-0.5 text-lg font-bold text-foreground">Configure Bonus Points</h3>
                    </div>
                    <button
                        type="button"
                        class="grid size-7 place-items-center rounded-lg text-muted-foreground hover:bg-secondary hover:text-foreground"
                        @click="showBonusCapModal = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">
                    Oral recitation scores earn bonus points directly added to student Activities coursework scores without increasing
                    the maximum points denominator.
                </p>

                <form class="mt-4 space-y-4" @submit.prevent="saveBonusCap">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-foreground">
                            Bonus Points
                        </label>
                        <div class="mt-1.5 flex items-center gap-2">
                            <span class="font-mono text-base font-bold text-amber-600 dark:text-amber-400">+</span>
                            <input
                                v-model.number="bonusCapForm.recitation"
                                type="number"
                                min="0"
                                class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-center font-mono text-base font-bold text-foreground focus-visible:ring-2 focus-visible:ring-primary"
                                placeholder="5"
                            />
                            <span class="font-mono text-sm font-bold text-muted-foreground">pts</span>
                        </div>
                        <p v-if="bonusCapForm.errors.recitation" class="mt-1 text-xs text-rose-600">
                            {{ bonusCapForm.errors.recitation }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-border/80 pt-4">
                        <button
                            type="button"
                            class="rounded-xl border border-border bg-card px-4 py-2 text-xs font-medium hover:bg-secondary"
                            @click="showBonusCapModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="bonusCapForm.processing"
                            class="ink-button !h-9 !rounded-xl !px-4 text-xs font-bold"
                        >
                            <Save class="size-3.5" />
                            <span>{{ bonusCapForm.processing ? 'Saving…' : 'Save Bonus Cap' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
