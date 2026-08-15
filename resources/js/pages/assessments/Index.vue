<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Award,
    BarChart3,
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    Download,
    FilePlus2,
    Plus,
    Paperclip,
    Trophy,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Assessment = {
    id: number;
    type: 'activity' | 'quiz' | 'exam';
    title: string;
    conducted_on: string;
    max_points: string;
    graded_count: number;
    points_awarded: string | null;
    attachment_path?: string;
    attachment_name?: string;
};
type Session = { id: number; session_date: string; starts_at: string };

const props = defineProps<{
    section: { id: number; name: string; subject_code?: string; subject_title: string };
    assessments: Assessment[];
    filter: string;
    attendanceSessions: Session[];
}>();

const creating = ref(false);

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('en-PH', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' }).format(new Date(value));

const form = useForm({
    type: 'activity',
    title: '',
    description: '',
    conducted_on: new Date().toISOString().slice(0, 10),
    max_points: 10,
    attendance_session_id: '' as number | '',
    attachment: null as File | null,
});

const tabs = ['all', 'activity', 'quiz', 'exam'] as const;
const filtered = computed(() => props.assessments);

const submit = () =>
    form.post(`/sections/${props.section.id}/assessments`, {
        forceFormData: true,
        onSuccess: () => {
            creating.value = false;
            form.reset();
        },
    });
</script>

<template>
    <Head :title="`Assessments · ${section.name} - ClassCheck`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Assessments', href: `/sections/${section.id}/assessments` },
        ]"
    >
        <main class="page-enter mx-auto flex w-full max-w-[1360px] flex-1 flex-col gap-6 px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <!-- Header Section -->
            <header class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 sm:p-8 shadow-sm">
                <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="badge-primary font-mono font-bold">{{ section.subject_code || 'Assessment Ledger' }}</span>
                            <span class="badge-muted">{{ section.name }}</span>
                        </div>
                        <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Assessments & Scores</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Create quizzes, activities, and exams. Enter scores in classroom chair sequence.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="`/sections/${section.id}/reports/gradebook`"
                            prefetch="hover"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-semibold text-foreground shadow-xs hover:bg-secondary hover:text-primary transition-colors"
                        >
                            <BarChart3 class="size-4 text-primary" />
                            <span>Gradebook</span>
                        </Link>
                        <a
                            :href="`/sections/${section.id}/exports/gradebook`"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-semibold text-foreground shadow-xs hover:bg-secondary transition-colors"
                        >
                            <Download class="size-4 text-muted-foreground" />
                            <span>Export CSV</span>
                        </a>
                        <button
                            class="ink-button !h-10 !rounded-xl"
                            @click="creating = !creating"
                        >
                            <Plus class="size-4" />
                            <span>New assessment</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Creation Form Panel -->
            <section v-if="creating" class="paper-card p-6 md:p-8 animate-in fade-in zoom-in-95 duration-200">
                <div class="mb-5 flex items-center justify-between border-b border-border/80 pb-4">
                    <div class="flex items-center gap-2.5">
                        <FilePlus2 class="size-5 text-primary" />
                        <h2 class="text-xl font-bold">Add to class ledger</h2>
                    </div>
                    <button class="text-xs font-semibold text-muted-foreground hover:text-foreground" @click="creating = false">
                        Cancel
                    </button>
                </div>

                <form class="grid gap-5 lg:grid-cols-12" @submit.prevent="submit">
                    <label class="lg:col-span-3">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Type</span>
                        <select v-model="form.type" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary">
                            <option value="activity">Activity</option>
                            <option value="quiz">Quiz</option>
                            <option value="exam">Exam</option>
                        </select>
                    </label>

                    <label class="lg:col-span-6">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Title</span>
                        <input
                            v-model="form.title"
                            required
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                            placeholder="e.g. Chapter 4 Problem Set"
                        />
                        <small v-if="form.errors.title" class="text-rose-600 text-xs mt-1 block">{{ form.errors.title }}</small>
                    </label>

                    <label class="lg:col-span-3">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Max points</span>
                        <input
                            v-model="form.max_points"
                            required
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <label class="lg:col-span-3">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Date conducted</span>
                        <input
                            v-model="form.conducted_on"
                            required
                            type="date"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <label class="lg:col-span-4">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Link session</span>
                        <select v-model="form.attendance_session_id" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary">
                            <option value="">Auto-match by date</option>
                            <option v-for="session in attendanceSessions" :key="session.id" :value="session.id">
                                {{ session.session_date }} · {{ session.starts_at }}
                            </option>
                        </select>
                    </label>

                    <label class="lg:col-span-5">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Attachment <em class="font-normal normal-case text-muted-foreground">(optional)</em></span>
                        <input
                            type="file"
                            class="block w-full text-xs text-muted-foreground file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border file:border-border file:text-xs file:font-semibold file:bg-secondary file:text-foreground hover:file:bg-secondary/80"
                            @change="form.attachment = ($event.target as HTMLInputElement).files?.[0] || null"
                        />
                    </label>

                    <label class="lg:col-span-9">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Notes <em class="font-normal normal-case text-muted-foreground">(optional)</em></span>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Instructions or rubric notes..."
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <div class="flex items-end justify-end gap-3 lg:col-span-3">
                        <button type="button" class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground" @click="creating = false">
                            Cancel
                        </button>
                        <button
                            :disabled="form.processing"
                            class="ink-button !rounded-xl text-xs font-semibold"
                        >
                            {{ form.processing ? 'Creating…' : 'Create & Score' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- Filter Tabs -->
            <div class="flex items-center gap-2 border-b border-border/80 pb-3">
                <Link
                    v-for="tab in tabs"
                    :key="tab"
                    :href="`/sections/${section.id}/assessments${tab === 'all' ? '' : `?type=${tab}`}`"
                    prefetch="hover"
                    class="rounded-xl px-4 py-2 text-xs font-bold capitalize transition-all"
                    :class="
                        filter === tab
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                    "
                >
                    {{ tab }}
                </Link>
            </div>

            <!-- Assessments Grid -->
            <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="item in filtered"
                    :key="item.id"
                    :href="`/sections/${section.id}/assessments/${item.id}`"
                    prefetch="hover"
                    class="paper-card group flex flex-col justify-between hover:border-primary/50 hover:shadow-lg transition-all"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span
                                class="rounded-md px-2.5 py-0.5 font-mono text-[10px] font-medium uppercase tracking-wider"
                                :class="
                                    item.type === 'exam'
                                        ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20'
                                        : item.type === 'quiz'
                                          ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
                                          : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                "
                            >
                                {{ item.type }}
                            </span>
                            <span class="font-mono text-xs font-medium text-foreground">{{ item.max_points }} pts</span>
                        </div>

                        <h3 class="mt-4 text-xl font-medium tracking-tight group-hover:text-primary transition-colors">
                            {{ item.title }}
                        </h3>

                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 items-center">
                            <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <CalendarDays class="size-3.5" /> {{ formatDate(item.conducted_on) }}
                            </p>
                            <p v-if="item.attachment_path" class="flex items-center gap-1.5 text-xs text-primary font-medium">
                                <Paperclip class="size-3.5" /> Reference attached
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-border/80 pt-4">
                        <div class="flex items-center justify-between text-xs mb-2">
                            <span class="text-muted-foreground font-normal">Scoring progress</span>
                            <span class="font-mono font-medium text-primary">{{ item.graded_count }} recorded</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-secondary">
                            <div
                                class="h-full bg-primary rounded-full transition-all duration-300"
                                :style="{ width: `${Math.min(100, item.graded_count > 0 ? 100 : 0)}%` }"
                            />
                        </div>
                    </div>
                </Link>

                <div v-if="!filtered.length" class="col-span-full rounded-2xl border border-dashed border-border/80 bg-card p-14 text-center shadow-sm">
                    <ClipboardCheck class="mx-auto size-8 text-muted-foreground" />
                    <h3 class="mt-4 text-xl font-bold">No assessments recorded</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Click "New assessment" above to log your first quiz, exam, or activity.</p>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
