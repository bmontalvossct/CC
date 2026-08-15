<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Download,
    FileText,
    Keyboard,
    LoaderCircle,
    TriangleAlert,
    UserX,
    Settings,
    X,
    Sparkles,
    Paperclip,
} from 'lucide-vue-next';
import { computed, nextTick, reactive, ref } from 'vue';

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

const editing = ref(false);
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

const scores = reactive<Record<number, string>>(Object.fromEntries(props.students.map((student) => [student.id, student.score ?? ''])));
const status = reactive<Record<number, 'idle' | 'saving' | 'saved' | 'error'>>(
    Object.fromEntries(props.students.map((student) => [student.id, 'idle'])),
);
const errors = reactive<Record<number, string>>({});
const inputs = new Map<number, HTMLInputElement>();

const gradedCount = computed(() => Object.values(scores).filter((value) => value !== '').length);
const eligible = computed(() => props.students.filter((student) => !student.is_absent || includeAbsent.value));

const csrf = () =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] || '',
    );

const save = async (student: Student) => {
    if (student.is_absent && !includeAbsent.value) return;
    const raw = scores[student.id].trim();
    const numeric = raw === '' ? null : Number(raw);
    if (numeric !== null && (!Number.isFinite(numeric) || numeric < 0 || numeric > Number(props.assessment.max_points))) {
        status[student.id] = 'error';
        errors[student.id] = `Must be 0–${props.assessment.max_points}.`;
        return;
    }
    status[student.id] = 'saving';
    errors[student.id] = '';
    try {
        const response = await fetch(`/sections/${props.section.id}/assessments/${props.assessment.id}/scores/${student.id}`, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrf() },
            body: JSON.stringify({ score: numeric, include_absent: includeAbsent.value }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || payload.errors?.score?.[0] || 'Could not save score.');
        status[student.id] = 'saved';
        window.setTimeout(() => {
            if (status[student.id] === 'saved') status[student.id] = 'idle';
        }, 1800);
    } catch (error) {
        status[student.id] = 'error';
        errors[student.id] = error instanceof Error ? error.message : 'Could not save score.';
    }
};

const move = async (student: Student, direction: number) => {
    await save(student);
    const index = eligible.value.findIndex((item) => item.id === student.id);
    const target = eligible.value[index + direction];
    if (target)
        await nextTick(() => {
            inputs.get(target.id)?.focus();
            inputs.get(target.id)?.select();
        });
};

const handleKey = (event: KeyboardEvent, student: Student) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        void move(student, event.shiftKey ? -1 : 1);
    }
    if (event.key === 'Tab') {
        event.preventDefault();
        void move(student, event.shiftKey ? -1 : 1);
    }
};
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
        <main class="page-enter mx-auto flex w-full max-w-[1360px] flex-1 flex-col gap-6 px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <Link
                :href="`/sections/${section.id}/assessments`"
                prefetch="hover"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground hover:text-primary transition-colors"
            >
                <ArrowLeft class="size-3.5" /> Back to assessments
            </Link>

            <!-- Assessment Header Banner -->
            <header class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 sm:p-8 shadow-sm">
                <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="badge-primary font-mono font-medium uppercase">{{ assessment.type }}</span>
                            <span class="badge-muted">{{ formatDate(assessment.conducted_on) }}</span>
                            <span class="badge-muted font-medium">{{ assessment.max_points }} max points</span>
                        </div>
                        <h1 class="mt-3 text-3xl font-medium tracking-tight sm:text-4xl">{{ assessment.title }}</h1>
                        <p v-if="assessment.description" class="mt-2 text-sm text-muted-foreground max-w-2xl">
                            {{ assessment.description }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a
                            v-if="assessment.attachment_path"
                            :href="`/sections/${section.id}/assessments/${assessment.id}/attachment`"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-xs font-medium text-foreground shadow-xs hover:bg-secondary transition-colors"
                        >
                            <FileText class="size-3.5 text-primary" />
                            <span>{{ assessment.attachment_name || 'Attachment' }}</span>
                        </a>
                        <a
                            :href="`/sections/${section.id}/assessments/${assessment.id}/export`"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-xs font-medium text-foreground shadow-xs hover:bg-secondary transition-colors"
                        >
                            <Download class="size-3.5 text-muted-foreground" />
                            <span>Export scores</span>
                        </a>
                        <button
                            type="button"
                            @click="editing = true"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-xs font-medium text-foreground shadow-xs hover:bg-secondary transition-colors"
                        >
                            <Settings class="size-3.5 text-primary" />
                            <span>Edit assessment</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- KPI Metric Chips -->
            <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <article class="paper-card p-5">
                    <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Scores recorded</span>
                    <p class="mt-3 text-2xl font-medium tracking-tight">
                        {{ gradedCount }}<span class="text-xs font-normal text-muted-foreground">/{{ students.length }}</span>
                    </p>
                </article>

                <article class="paper-card p-5">
                    <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Class average</span>
                    <p class="mt-3 text-2xl font-medium tracking-tight text-primary">
                        {{ summary.average !== null ? summary.average : '—' }}
                    </p>
                </article>

                <article class="paper-card p-5">
                    <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Ungraded (Present)</span>
                    <p class="mt-3 text-2xl font-medium tracking-tight">
                        {{ summary.missing }}
                    </p>
                </article>

                <article class="paper-card p-5">
                    <span class="text-[10px] font-medium uppercase tracking-wider text-rose-600 dark:text-rose-400">Absent from session</span>
                    <p class="mt-3 text-2xl font-medium tracking-tight text-rose-600 dark:text-rose-400">
                        {{ summary.absent }}
                    </p>
                </article>
            </section>

            <!-- Score Entry Table -->
            <section class="paper-card p-6 shadow-sm overflow-hidden">
                <div class="flex flex-col gap-4 border-b border-border/80 pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-medium">Chair-sequence score entry</h2>
                        <p class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Keyboard class="size-3.5 text-primary" />
                            <span>Press Tab or Enter to auto-save and advance to the next chair.</span>
                        </p>
                    </div>

                    <label class="flex cursor-pointer items-center gap-3 rounded-xl bg-secondary/50 border border-border/60 px-4 py-2.5 text-xs font-medium transition-colors hover:bg-secondary">
                        <input
                            v-model="includeAbsent"
                            type="checkbox"
                            class="size-4 rounded border-input text-primary focus:ring-primary"
                        />
                        <span>Allow scoring absent students (Override)</span>
                    </label>
                </div>

                <div class="overflow-x-auto mt-4">
                    <table class="w-full min-w-[700px] border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-border/80 bg-secondary/40 text-left text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                <th class="w-24 px-4 py-3 rounded-l-lg">Chair</th>
                                <th class="px-4 py-3">Student</th>
                                <th class="w-56 px-4 py-3">Score / {{ assessment.max_points }}</th>
                                <th class="w-36 px-4 py-3 rounded-r-lg">Sync Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="student in students"
                                :key="student.id"
                                class="transition-colors hover:bg-secondary/30"
                                :class="student.is_absent && !includeAbsent ? 'opacity-60 bg-muted/20' : ''"
                            >
                                <td class="px-4 py-3">
                                    <span class="rounded-lg bg-secondary px-2.5 py-1 font-mono text-xs font-medium text-foreground border border-border/80">
                                        {{ student.seat_label || '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="block text-foreground font-medium">{{ student.full_name }}</span>
                                    <span class="font-mono text-xs text-muted-foreground">{{ student.student_number }}</span>
                                    <span
                                        v-if="student.is_absent"
                                        class="ml-2 inline-flex items-center gap-1 font-medium text-[10px] text-rose-600 dark:text-rose-400 bg-rose-500/10 px-2 py-0.5 rounded-full border border-rose-500/20"
                                    >
                                        <UserX class="size-3" /> Absent
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <input
                                        :ref="(el) => { if (el) inputs.set(student.id, el as HTMLInputElement); }"
                                        v-model="scores[student.id]"
                                        :disabled="student.is_absent && !includeAbsent"
                                        type="number"
                                        min="0"
                                        :max="assessment.max_points"
                                        step="0.01"
                                        inputmode="decimal"
                                        class="w-full rounded-xl border border-input bg-background px-3 py-1.5 font-mono text-base font-medium tabular-nums focus:border-primary focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:bg-muted/40"
                                        :aria-label="`Score for ${student.full_name}`"
                                        @blur="save(student)"
                                        @keydown="handleKey($event, student)"
                                    />
                                    <p v-if="errors[student.id]" class="mt-1 text-xs text-rose-600 font-medium">{{ errors[student.id] }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <span v-if="status[student.id] === 'saving'" class="inline-flex items-center gap-1.5 font-normal text-muted-foreground">
                                        <LoaderCircle class="size-3.5 animate-spin text-primary" /> Saving
                                    </span>
                                    <span v-else-if="status[student.id] === 'saved'" class="inline-flex items-center gap-1.5 font-medium text-emerald-600 dark:text-emerald-400">
                                        <Check class="size-3.5" /> Saved
                                    </span>
                                    <span v-else-if="status[student.id] === 'error'" class="inline-flex items-center gap-1.5 font-medium text-rose-600">
                                        <TriangleAlert class="size-3.5" /> Retry
                                    </span>
                                    <span v-else-if="student.is_absent" class="text-muted-foreground italic">Skipped (absent)</span>
                                    <span v-else class="text-muted-foreground">Ready</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <!-- Edit Assessment Modal -->
        <div
            v-if="editing"
            class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 backdrop-blur-md animate-in fade-in duration-200"
            @click.self="editing = false"
        >
            <div
                class="paper-card relative w-full max-w-lg overflow-hidden p-8 shadow-2xl animate-in zoom-in-95 duration-200 border-border/90"
                role="dialog"
                aria-modal="true"
                aria-label="Edit assessment"
            >
                <button
                    type="button"
                    class="absolute right-4 top-4 grid size-8 place-items-center rounded-full text-muted-foreground hover:bg-secondary hover:text-foreground transition-colors"
                    @click="editing = false"
                >
                    <X class="size-4.5" />
                </button>

                <div class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 font-mono text-[11px] font-medium uppercase tracking-wider text-primary border border-primary/20">
                    <Sparkles class="size-3.5" /> Edit Assessment
                </div>

                <h3 class="mt-3 text-2xl font-medium tracking-tight">Assessment details</h3>
                <p class="mt-1 text-xs text-muted-foreground">Modify settings or upload a reference document below.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitEdit">
                    <label class="sm:col-span-2">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Title</span>
                        <input
                            v-model="editForm.title"
                            required
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                        <small v-if="editForm.errors.title" class="text-rose-600 text-xs mt-1 block">{{ editForm.errors.title }}</small>
                    </label>

                    <label class="sm:col-span-1">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Type</span>
                        <select v-model="editForm.type" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary">
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
                        <small v-if="editForm.errors.max_points" class="text-rose-600 text-xs mt-1 block">{{ editForm.errors.max_points }}</small>
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
                        <select v-model="editForm.attendance_session_id" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary">
                            <option value="">Auto-match by date</option>
                            <option v-for="session in attendanceSessions" :key="session.id" :value="session.id">
                                {{ session.session_date }} · {{ session.starts_at }}
                            </option>
                        </select>
                        <small v-if="editForm.errors.attendance_session_id" class="text-rose-600 text-xs mt-1 block">{{ editForm.errors.attendance_session_id }}</small>
                    </label>

                    <label class="sm:col-span-2">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Reference File / Questions <em class="font-normal normal-case text-muted-foreground">(optional)</em></span>
                        <input
                            type="file"
                            class="block w-full text-xs text-muted-foreground file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border file:border-border file:text-xs file:font-medium file:bg-secondary file:text-foreground hover:file:bg-secondary/80"
                            @change="editForm.attachment = ($event.target as HTMLInputElement).files?.[0] || null"
                        />
                        <span v-if="assessment.attachment_name" class="mt-1.5 block text-[10px] text-muted-foreground font-normal">
                            Current: {{ assessment.attachment_name }}
                        </span>
                        <small v-if="editForm.errors.attachment" class="text-rose-600 text-xs mt-1 block">{{ editForm.errors.attachment }}</small>
                    </label>

                    <label class="sm:col-span-2">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Notes <em class="font-normal normal-case text-muted-foreground">(optional)</em></span>
                        <textarea
                            v-model="editForm.description"
                            rows="2"
                            placeholder="Instructions or rubric notes..."
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <div class="sm:col-span-2 mt-4 flex justify-end gap-3 border-t border-border/80 pt-4">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-xl border border-border bg-card px-5 text-xs font-medium text-foreground shadow-xs hover:bg-secondary transition-colors"
                            @click="editing = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="ink-button !h-10 !rounded-xl !px-5 text-xs font-medium"
                        >
                            {{ editForm.processing ? 'Saving…' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
