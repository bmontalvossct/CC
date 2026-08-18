<script setup lang="ts">
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Download,
    Keyboard,
    LoaderCircle,
    Paperclip,
    RotateCcw,
    Save,
    Settings,
    Sparkles,
    Trash2,
    TriangleAlert,
    UserX,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';

type Student = {
    id: number;
    student_number: string;
    full_name: string;
    seat_label: string | null;
    is_absent: boolean;
    score: string | null;
    absence_override: boolean;
};
type Assessment = {
    id: number;
    type: string;
    title: string;
    description?: string;
    conducted_on: string;
    max_points: string;
    attachment_path?: string;
    attachment_name?: string;
    attendance_session_id?: number;
    attendance_session?: { session_date: string; starts_at: string; ends_at: string };
};
type Session = { id: number; session_date: string; starts_at: string };

const props = defineProps<{
    section: { id: number; name: string; subject_code?: string; subject_title: string };
    assessment: Assessment;
    students: Student[];
    summary: { graded: number; missing: number; absent: number; average: number | null };
    attendanceSessions: Session[];
}>();

const showPreview = ref(false);
const editing = ref(false);
const showDeleteModal = ref(false);
const isDeleting = ref(false);

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(`/sections/${props.section.id}/assessments/${props.assessment.id}`, {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};

const editForm = useForm({
    type: props.assessment.type,
    title: props.assessment.title,
    description: props.assessment.description ?? '',
    conducted_on: props.assessment.conducted_on.slice(0, 10),
    max_points: Number(props.assessment.max_points),
    attendance_session_id: props.assessment.attendance_session_id ?? '',
    attachment: null as File | null,
    _method: 'PUT',
});

const submitEdit = () => {
    editForm.post(`/sections/${props.section.id}/assessments/${props.assessment.id}`, {
        forceFormData: true,
        onSuccess: () => {
            editing.value = false;
            editForm.reset();
        },
    });
};

const includeAbsent = ref(false);
const formatDate = (value: string) =>
    new Intl.DateTimeFormat('en-PH', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' }).format(new Date(value));

const toCleanString = (val: unknown): string => {
    if (val === null || val === undefined) return '';
    return String(val).trim();
};

const normalize = (val: unknown): string => {
    const str = toCleanString(val);
    if (str === '') return '';
    const n = Number(str);
    if (!Number.isFinite(n)) return str;
    return String(Number(n.toFixed(2)));
};

const initialMap = Object.fromEntries(props.students.map((student) => [student.id, normalize(student.score)]));
const scores = reactive<Record<number, string | number>>({ ...initialMap });
const lastSavedScores = reactive<Record<number, string | number>>({ ...initialMap });

const inputs = new Map<number, HTMLInputElement>();

const isSaving = ref(false);
const saveSuccessMessage = ref('');
const saveErrorMessage = ref('');

const getScoreError = (studentId: number): string => {
    const raw = toCleanString(scores[studentId]);
    if (raw === '') return '';
    const num = Number(raw);
    if (!Number.isFinite(num)) return 'Invalid number';
    if (num < 0) return 'Cannot be below 0';
    if (num > Number(props.assessment.max_points)) return `Cannot exceed total score of ${props.assessment.max_points} pts`;
    return '';
};

const hasInvalidScore = (studentId: number): boolean => {
    return getScoreError(studentId) !== '';
};

const isUnsaved = (studentId: number): boolean => {
    return normalize(scores[studentId]) !== normalize(lastSavedScores[studentId]);
};

const hasUnsavedChanges = computed(() => {
    return props.students.some((s) => isUnsaved(s.id));
});

const unsavedCount = computed(() => {
    return props.students.filter((s) => isUnsaved(s.id)).length;
});

const eligible = computed(() => props.students.filter((student) => !student.is_absent || includeAbsent.value));

const liveSummary = computed(() => {
    const validScores: number[] = [];
    props.students.forEach((student) => {
        if (student.is_absent && !includeAbsent.value) return;
        const val = toCleanString(scores[student.id]);
        if (val !== '') {
            const num = Number(val);
            if (Number.isFinite(num) && num >= 0 && num <= Number(props.assessment.max_points)) {
                validScores.push(num);
            }
        }
    });

    const graded = validScores.length;
    const missing = Math.max(0, eligible.value.length - graded);
    const absent = props.students.filter((s) => s.is_absent).length;
    const average = graded > 0 ? (validScores.reduce((a, b) => a + b, 0) / graded).toFixed(2) : null;

    return { graded, missing, absent, average };
});

const move = (student: Student, direction: number) => {
    const list = eligible.value;
    const index = list.findIndex((item) => item.id === student.id);
    if (index === -1) return;
    const nextIndex = index + direction;
    if (nextIndex >= 0 && nextIndex < list.length) {
        const target = list[nextIndex];
        const el = inputs.get(target.id);
        if (el) {
            el.focus();
            el.select();
        }
    }
};

const handleKey = (event: KeyboardEvent, student: Student) => {
    if (event.key === 'Enter' || event.key === 'Tab') {
        event.preventDefault();
        move(student, event.shiftKey ? -1 : 1);
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(student, 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(student, -1);
    }
};

const saveAll = () => {
    if (isSaving.value) return;

    saveErrorMessage.value = '';
    saveSuccessMessage.value = '';

    const maxPoints = Number(props.assessment.max_points);
    let firstInvalidStudentId: number | null = null;
    const payloadScores: Record<number, number | null> = {};

    for (const student of props.students) {
        const err = getScoreError(student.id);
        if (err) {
            if (firstInvalidStudentId === null) {
                firstInvalidStudentId = student.id;
            }
        }
        const raw = toCleanString(scores[student.id]);
        payloadScores[student.id] = raw === '' ? null : Number(raw);
    }

    if (firstInvalidStudentId !== null) {
        saveErrorMessage.value = `Cannot save: Some scores are below 0 or exceed the total score of ${maxPoints} pts. Please fix highlighted fields.`;
        inputs.get(firstInvalidStudentId)?.focus();
        inputs.get(firstInvalidStudentId)?.select();
        return;
    }

    isSaving.value = true;

    router.post(
        `/sections/${props.section.id}/assessments/${props.assessment.id}/scores/batch`,
        {
            scores: payloadScores,
            include_absent: includeAbsent.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isSaving.value = false;
                props.students.forEach((student) => {
                    lastSavedScores[student.id] = normalize(scores[student.id]);
                });
                saveSuccessMessage.value = 'All scores have been saved successfully!';
                setTimeout(() => {
                    saveSuccessMessage.value = '';
                }, 4000);
            },
            onError: (errs) => {
                isSaving.value = false;
                const firstErr = Object.values(errs)[0] || 'Could not save scores.';
                saveErrorMessage.value = String(firstErr);
            },
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
};

const resetScores = () => {
    props.students.forEach((student) => {
        scores[student.id] = lastSavedScores[student.id] ?? '';
    });
    saveErrorMessage.value = '';
};

const handleGlobalKeydown = (e: KeyboardEvent) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        void saveAll();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
    <Head :title="`${assessment.title} · Scores - ClassCheck`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Assessments', href: `/sections/${section.id}/assessments` },
            { title: assessment.title, href: '#' },
        ]"
    >
        <main class="page-enter mx-auto flex w-full max-w-[1360px] flex-1 flex-col gap-6 px-5 pb-24 pt-8 md:px-10 md:pt-10">
            <Link
                :href="`/sections/${section.id}/assessments`"
                prefetch="hover"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground transition-colors hover:text-primary"
            >
                <ArrowLeft class="size-3.5" /> Back to assessments
            </Link>

            <!-- Top Header & Primary Actions -->
            <header class="paper-card p-6 shadow-sm">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge-primary font-mono font-bold uppercase">{{ assessment.type }}</span>
                            <span class="badge-muted">{{ formatDate(assessment.conducted_on) }}</span>
                            <span class="badge-muted font-mono font-medium">{{ assessment.max_points }} max points</span>
                        </div>
                        <h1 class="mt-3 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">{{ assessment.title }}</h1>
                        <p v-if="assessment.description" class="mt-2 max-w-2xl text-sm text-muted-foreground">
                            {{ assessment.description }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        <button
                            type="button"
                            :disabled="isSaving"
                            class="ink-button !h-10 !rounded-xl !px-5 text-xs font-bold shadow-md transition-all hover:scale-[1.02]"
                            :class="hasUnsavedChanges ? 'ring-2 ring-primary ring-offset-2' : ''"
                            @click="saveAll"
                        >
                            <LoaderCircle v-if="isSaving" class="size-4 animate-spin" />
                            <Save v-else class="size-4" />
                            <span>{{
                                isSaving ? 'Saving all scores…' : hasUnsavedChanges ? `Save All Scores (${unsavedCount})` : 'Save All Scores'
                            }}</span>
                        </button>

                        <button
                            v-if="assessment.attachment_path"
                            type="button"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary/40 bg-primary/10 px-4 text-xs font-semibold text-primary transition-colors hover:bg-primary hover:text-white"
                            title="Preview attached file with download option"
                            @click="showPreview = true"
                        >
                            <Paperclip class="size-3.5" />
                            <span>Preview: {{ assessment.attachment_name || 'Attachment' }}</span>
                        </button>
                        <a
                            :href="`/sections/${section.id}/assessments/${assessment.id}/export`"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                        >
                            <Download class="size-3.5 text-muted-foreground" />
                            <span>Export scores</span>
                        </a>
                        <button
                            type="button"
                            @click="editing = true"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                        >
                            <Settings class="size-3.5 text-primary" />
                            <span>Edit assessment</span>
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 text-xs font-bold text-rose-600 transition-all hover:bg-rose-600 hover:text-white dark:text-rose-400 dark:hover:text-white"
                            title="Delete this activity misentry"
                            @click="showDeleteModal = true"
                        >
                            <Trash2 class="size-3.5" />
                            <span class="capitalize">Delete {{ assessment.type }}</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Alerts Banner -->
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="transform -translate-y-2 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform -translate-y-2 opacity-0"
            >
                <div
                    v-if="saveSuccessMessage"
                    class="flex items-center justify-between rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-700 dark:text-emerald-400"
                >
                    <div class="flex items-center gap-2">
                        <CheckCircle2 class="size-4.5 text-emerald-600 dark:text-emerald-400" />
                        <span>{{ saveSuccessMessage }}</span>
                    </div>
                    <button type="button" class="text-emerald-700 hover:text-emerald-900 dark:text-emerald-400" @click="saveSuccessMessage = ''">
                        <X class="size-4" />
                    </button>
                </div>
            </transition>

            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="transform -translate-y-2 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform -translate-y-2 opacity-0"
            >
                <div
                    v-if="saveErrorMessage"
                    class="flex items-center justify-between rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-700 dark:text-rose-400"
                >
                    <div class="flex items-center gap-2">
                        <TriangleAlert class="size-4.5 text-rose-600 dark:text-rose-400" />
                        <span>{{ saveErrorMessage }}</span>
                    </div>
                    <button type="button" class="text-rose-700 hover:text-rose-900 dark:text-rose-400" @click="saveErrorMessage = ''">
                        <X class="size-4" />
                    </button>
                </div>
            </transition>

            <!-- KPI Metric Chips -->
            <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <article class="paper-card p-5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Scores recorded</span>
                    <p class="mt-3 text-2xl font-bold tracking-tight">
                        {{ liveSummary.graded }}<span class="text-xs font-normal text-muted-foreground">/{{ students.length }}</span>
                    </p>
                </article>

                <article class="paper-card p-5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Class average</span>
                    <p class="mt-3 text-2xl font-bold tracking-tight text-primary">
                        {{ liveSummary.average !== null ? `${liveSummary.average} pts` : '—' }}
                    </p>
                </article>

                <article class="paper-card p-5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Ungraded (Present)</span>
                    <p class="mt-3 text-2xl font-bold tracking-tight">
                        {{ liveSummary.missing }}
                    </p>
                </article>

                <article class="paper-card p-5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Absent from session</span>
                    <p class="mt-3 text-2xl font-bold tracking-tight text-rose-600 dark:text-rose-400">
                        {{ liveSummary.absent }}
                    </p>
                </article>
            </section>

            <!-- Score Entry Table -->
            <section class="paper-card overflow-hidden p-6 shadow-sm">
                <div class="flex flex-col gap-4 border-b border-border/80 pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-foreground">Score Entry Table</h2>
                        <p class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Keyboard class="size-3.5 text-primary" />
                            <span
                                >Type a score and press <strong>Tab</strong> or <strong>Enter</strong> to quickly advance down the list. Click
                                <strong>Save All Scores</strong> when finished (or press Ctrl+S).</span
                            >
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <label
                            class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-border/60 bg-secondary/50 px-3.5 py-2 text-xs font-medium transition-colors hover:bg-secondary"
                        >
                            <input v-model="includeAbsent" type="checkbox" class="size-4 rounded border-input text-primary focus:ring-primary" />
                            <span>Allow scoring absent students</span>
                        </label>

                        <button type="button" :disabled="isSaving" class="ink-button !h-9 !rounded-xl !px-4 text-xs font-bold" @click="saveAll">
                            <LoaderCircle v-if="isSaving" class="size-3.5 animate-spin" />
                            <Save v-else class="size-3.5" />
                            <span>Save All Scores</span>
                        </button>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[700px] border-collapse text-sm">
                        <thead>
                            <tr
                                class="border-b border-border/80 bg-secondary/40 text-left text-[11px] font-bold uppercase tracking-wider text-muted-foreground"
                            >
                                <th class="w-24 rounded-l-lg px-4 py-3">Chair</th>
                                <th class="px-4 py-3">Student Name & ID</th>
                                <th class="w-64 px-4 py-3">Score / {{ assessment.max_points }} pts</th>
                                <th class="w-40 rounded-r-lg px-4 py-3">Status / Grade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="student in students"
                                :key="student.id"
                                class="transition-colors hover:bg-secondary/30"
                                :class="[
                                    student.is_absent && !includeAbsent ? 'bg-muted/20 opacity-60' : '',
                                    hasInvalidScore(student.id) ? 'bg-rose-500/5' : isUnsaved(student.id) ? 'bg-primary/5' : '',
                                ]"
                            >
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-lg border border-border/80 bg-secondary px-2.5 py-1 font-mono text-xs font-bold text-foreground"
                                    >
                                        {{ student.seat_label || '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <div
                                            class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 font-mono text-[10px] font-bold uppercase text-primary"
                                        >
                                            {{ student.full_name?.split(' ')?.[0]?.[0] || 'S' }}
                                        </div>
                                        <div>
                                            <span class="block font-semibold text-foreground">{{ student.full_name }}</span>
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono text-xs text-muted-foreground">{{ student.student_number }}</span>
                                                <span
                                                    v-if="student.is_absent"
                                                    class="inline-flex items-center gap-1 rounded-full border border-rose-500/20 bg-rose-500/10 px-2 py-0.5 text-[10px] font-medium text-rose-600 dark:text-rose-400"
                                                >
                                                    <UserX class="size-3" /> Absent
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="relative max-w-[220px]">
                                        <input
                                            :ref="
                                                (el) => {
                                                    if (el) inputs.set(student.id, el as HTMLInputElement);
                                                }
                                            "
                                            v-model="scores[student.id]"
                                            :disabled="student.is_absent && !includeAbsent"
                                            type="number"
                                            min="0"
                                            :max="assessment.max_points"
                                            step="0.01"
                                            placeholder="—"
                                            inputmode="decimal"
                                            class="w-full rounded-xl border px-3 py-1.5 font-mono text-base font-bold tabular-nums transition-all focus:outline-none disabled:cursor-not-allowed disabled:bg-muted/40"
                                            :class="[
                                                hasInvalidScore(student.id)
                                                    ? '!border-rose-500 !bg-rose-500/10 !text-rose-600 !ring-2 !ring-rose-500 dark:!text-rose-400'
                                                    : isUnsaved(student.id)
                                                      ? 'border-primary bg-primary/5 text-foreground ring-1 ring-primary/40 focus:ring-2 focus:ring-primary'
                                                      : 'border-input bg-background text-foreground focus:border-primary focus:ring-2 focus:ring-primary/20',
                                            ]"
                                            :aria-label="`Score for ${student.full_name}`"
                                            @focus="($event.target as HTMLInputElement)?.select()"
                                            @keydown="handleKey($event, student)"
                                        />
                                    </div>
                                    <p
                                        v-if="hasInvalidScore(student.id)"
                                        class="mt-1 flex items-center gap-1 text-[11px] font-bold text-rose-600 dark:text-rose-400"
                                    >
                                        <TriangleAlert class="size-3.5 shrink-0" />
                                        <span>{{ getScoreError(student.id) }}</span>
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-xs font-medium">
                                    <div
                                        v-if="hasInvalidScore(student.id)"
                                        class="inline-flex items-center gap-1 rounded-lg border border-rose-500/30 bg-rose-500/10 px-2 py-0.5 font-mono text-xs font-bold text-rose-600 dark:text-rose-400"
                                    >
                                        <TriangleAlert class="size-3" />
                                        <span>Error</span>
                                    </div>
                                    <div v-else-if="toCleanString(scores[student.id]) !== ''" class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-lg px-2 py-0.5 font-mono text-xs font-bold"
                                            :class="
                                                Number(scores[student.id]) >= Number(assessment.max_points) * 0.75
                                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                    : Number(scores[student.id]) >= Number(assessment.max_points) * 0.5
                                                      ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                      : 'bg-rose-500/10 text-rose-600 dark:text-rose-400'
                                            "
                                        >
                                            {{ Math.round((Number(scores[student.id]) / Number(assessment.max_points)) * 100) }}%
                                        </span>
                                        <span
                                            v-if="isUnsaved(student.id)"
                                            class="font-mono text-[10px] font-bold text-amber-600 dark:text-amber-400"
                                            title="Unsaved changes"
                                        >
                                            ● Unsaved
                                        </span>
                                        <span v-else class="font-mono text-[10px] font-medium text-muted-foreground"> Saved </span>
                                    </div>
                                    <div v-else-if="student.is_absent" class="italic text-muted-foreground">Absent (Skipped)</div>
                                    <div v-else class="text-muted-foreground">Not recorded</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Save Action Bar -->
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-border/80 pt-4">
                    <div class="text-xs text-muted-foreground">
                        <span v-if="hasUnsavedChanges" class="font-semibold text-amber-600 dark:text-amber-400">
                            {{ unsavedCount }} student score{{ unsavedCount !== 1 ? 's' : '' }} modified
                        </span>
                        <span v-else class="text-muted-foreground"> All scores are up to date with the database. </span>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            v-if="hasUnsavedChanges"
                            type="button"
                            class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-border bg-card px-3 text-xs font-semibold text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            @click="resetScores"
                        >
                            <RotateCcw class="size-3.5" />
                            <span>Discard changes</span>
                        </button>

                        <button type="button" :disabled="isSaving" class="ink-button !h-9 !rounded-xl !px-5 text-xs font-bold" @click="saveAll">
                            <LoaderCircle v-if="isSaving" class="size-3.5 animate-spin" />
                            <Save v-else class="size-3.5" />
                            <span>{{ isSaving ? 'Saving…' : 'Save All Scores' }}</span>
                        </button>
                    </div>
                </div>
            </section>
        </main>

        <!-- Floating Sticky Bottom Bar when unsaved changes exist -->
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="transform translate-y-8 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform translate-y-8 opacity-0"
        >
            <div
                v-if="hasUnsavedChanges"
                class="fixed bottom-6 right-6 z-40 flex items-center gap-3 rounded-2xl border border-border/90 bg-card/95 p-3 shadow-2xl backdrop-blur-md"
            >
                <div class="hidden pl-2 text-xs sm:block">
                    <p class="font-bold text-foreground">{{ unsavedCount }} unsaved score{{ unsavedCount !== 1 ? 's' : '' }}</p>
                    <p class="text-[10px] text-muted-foreground">Press Ctrl+S to save anytime</p>
                </div>

                <button type="button" :disabled="isSaving" class="ink-button !h-10 !rounded-xl !px-5 text-xs font-bold shadow-lg" @click="saveAll">
                    <LoaderCircle v-if="isSaving" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    <span>{{ isSaving ? 'Saving…' : 'Save All Scores' }}</span>
                </button>
            </div>
        </transition>

        <!-- Edit Assessment Modal -->
        <div
            v-if="editing"
            class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
            @click.self="editing = false"
        >
            <div
                class="paper-card relative w-full max-w-lg overflow-hidden border-border/90 p-8 shadow-2xl duration-200 animate-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                aria-label="Edit assessment"
            >
                <button
                    type="button"
                    class="absolute right-4 top-4 grid size-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                    @click="editing = false"
                >
                    <X class="size-4.5" />
                </button>

                <div
                    class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 font-mono text-[11px] font-medium uppercase tracking-wider text-primary"
                >
                    <Sparkles class="size-3.5" /> Edit Assessment
                </div>

                <h3 class="mt-3 text-2xl font-bold tracking-tight text-foreground">Assessment details</h3>
                <p class="mt-1 text-xs text-muted-foreground">Modify settings or upload a reference document below.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitEdit">
                    <label class="sm:col-span-2">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Title</span>
                        <input
                            v-model="editForm.title"
                            required
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                        <small v-if="editForm.errors.title" class="mt-1 block text-xs text-rose-600">{{ editForm.errors.title }}</small>
                    </label>

                    <label class="sm:col-span-1">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Type</span>
                        <select
                            v-model="editForm.type"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <option value="activity">Activity</option>
                            <option value="quiz">Quiz</option>
                            <option value="exam">Exam</option>
                        </select>
                    </label>

                    <label class="sm:col-span-1">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Max points</span>
                        <input
                            v-model="editForm.max_points"
                            required
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                        <small v-if="editForm.errors.max_points" class="mt-1 block text-xs text-rose-600">{{ editForm.errors.max_points }}</small>
                    </label>

                    <label class="sm:col-span-1">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Date conducted</span>
                        <input
                            v-model="editForm.conducted_on"
                            required
                            type="date"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <label class="sm:col-span-1">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Link session</span>
                        <select
                            v-model="editForm.attendance_session_id"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <option value="">Auto-match by date</option>
                            <option v-for="session in attendanceSessions" :key="session.id" :value="session.id">
                                {{ session.session_date }} · {{ session.starts_at }}
                            </option>
                        </select>
                        <small v-if="editForm.errors.attendance_session_id" class="mt-1 block text-xs text-rose-600">{{
                            editForm.errors.attendance_session_id
                        }}</small>
                    </label>

                    <label class="sm:col-span-2">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground"
                            >Reference File / Questions <em class="font-normal normal-case text-muted-foreground">(optional, max 50MB)</em></span
                        >
                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z,.rtf,.odt,.ods,.odp,.svg"
                            class="block w-full text-xs text-muted-foreground file:mr-2 file:rounded-lg file:border file:border-border file:bg-secondary file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-foreground hover:file:bg-secondary/80"
                            @change="editForm.attachment = ($event.target as HTMLInputElement).files?.[0] || null"
                        />
                        <span v-if="assessment.attachment_name" class="mt-1.5 block text-[10px] font-normal text-muted-foreground">
                            Current: {{ assessment.attachment_name }}
                        </span>
                        <small v-if="editForm.errors.attachment" class="mt-1 block text-xs text-rose-600">{{ editForm.errors.attachment }}</small>
                    </label>

                    <label class="sm:col-span-2">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground"
                            >Notes <em class="font-normal normal-case text-muted-foreground">(optional)</em></span
                        >
                        <textarea
                            v-model="editForm.description"
                            rows="2"
                            placeholder="Instructions or rubric notes..."
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border/80 pt-4 sm:col-span-2">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-600 hover:text-white dark:text-rose-400 dark:hover:text-white"
                            @click="
                                editing = false;
                                showDeleteModal = true;
                            "
                        >
                            <Trash2 class="size-3.5" />
                            <span class="capitalize">Delete {{ assessment.type }}</span>
                        </button>

                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="shadow-xs inline-flex h-10 items-center justify-center rounded-xl border border-border bg-card px-5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                                @click="editing = false"
                            >
                                Cancel
                            </button>
                            <button type="submit" :disabled="editForm.processing" class="ink-button !h-10 !rounded-xl !px-5 text-xs font-medium">
                                {{ editForm.processing ? 'Saving…' : 'Save Changes' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div
            v-if="showDeleteModal"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
            @click.self="showDeleteModal = false"
        >
            <div
                class="paper-card relative w-full max-w-lg border-border/90 p-6 shadow-2xl duration-200 animate-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                :aria-label="`Delete ${assessment.title}`"
            >
                <div class="flex items-start gap-4">
                    <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-rose-500/15 text-rose-700 dark:text-rose-400">
                        <Trash2 class="size-6" />
                    </div>
                    <div class="flex-1">
                        <span class="eyebrow text-rose-700 dark:text-rose-400">Permanent Deletion</span>
                        <h3 class="mt-1 text-xl font-bold text-foreground">Delete {{ assessment.title }}?</h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ assessment.type.toUpperCase() }} · {{ assessment.max_points }} max points · {{ formatDate(assessment.conducted_on) }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-rose-500/20 bg-rose-500/5 p-4 text-xs leading-relaxed text-foreground">
                    <p class="font-bold text-rose-700 dark:text-rose-400">
                        Warning: This action will permanently remove this assessment entry and its associated data:
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-4 text-muted-foreground">
                        <li>The assessment ledger record and task description</li>
                        <li>
                            All <strong class="text-foreground">{{ summary.graded }} recorded student scores</strong> for this assessment
                        </li>
                        <li v-if="assessment.attachment_name">Attached reference file: {{ assessment.attachment_name }}</li>
                    </ul>
                    <p class="mt-2 font-bold text-rose-700 dark:text-rose-400">This action cannot be undone.</p>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-border/80 pt-4">
                    <button
                        type="button"
                        class="shadow-xs inline-flex h-10 items-center justify-center rounded-xl border border-border bg-card px-5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                        :disabled="isDeleting"
                        @click="showDeleteModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-5 text-xs font-bold text-white shadow-sm transition-all hover:bg-rose-700 disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="confirmDelete"
                    >
                        <LoaderCircle v-if="isDeleting" class="size-4 animate-spin" />
                        <Trash2 v-else class="size-4" />
                        <span>{{ isDeleting ? 'Deleting assessment…' : 'Yes, Delete Assessment' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Attachment Preview Modal -->
        <FilePreviewModal
            v-if="assessment.attachment_path"
            :show="showPreview"
            :title="assessment.title"
            :file-name="assessment.attachment_name"
            :file-url="`/sections/${section.id}/assessments/${assessment.id}/attachment`"
            :download-url="`/sections/${section.id}/assessments/${assessment.id}/attachment?download=1`"
            @close="showPreview = false"
        />
    </AppLayout>
</template>
