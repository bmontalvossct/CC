<script setup lang="ts">
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    BarChart3,
    CalendarDays,
    ClipboardCheck,
    Download,
    Eye,
    FolderKanban,
    LoaderCircle,
    Paperclip,
    Plus,
    Trash2,
    Users,
    X,
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

type Project = {
    id: number;
    type: 'project' | 'reporting';
    title: string;
    description: string | null;
    conducted_on: string | null;
    max_points: string | number | null;
    groups_count: number;
    members_count: number;
    attachment_path?: string;
    attachment_name?: string;
};

type Session = { id: number; session_date: string; starts_at: string };

const props = withDefaults(
    defineProps<{
        section: { id: number; name: string; subject_code?: string; subject_title: string };
        assessments: Assessment[];
        projects?: Project[];
        activeStudentsCount?: number;
        filter: string;
        attendanceSessions: Session[];
    }>(),
    {
        projects: () => [],
        activeStudentsCount: 0,
    },
);

const creating = ref(false);
const creationMode = ref<'assessment' | 'project'>('assessment');
const fileInputRef = ref<HTMLInputElement | null>(null);
const projectFileInputRef = ref<HTMLInputElement | null>(null);

const deleteAssessmentTarget = ref<Assessment | null>(null);
const deleteProjectTarget = ref<Project | null>(null);
const isDeleting = ref(false);

const confirmDeleteAssessment = () => {
    if (!deleteAssessmentTarget.value) return;
    isDeleting.value = true;
    router.delete(`/sections/${props.section.id}/assessments/${deleteAssessmentTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            deleteAssessmentTarget.value = null;
        },
    });
};

const confirmDeleteProject = () => {
    if (!deleteProjectTarget.value) return;
    isDeleting.value = true;
    router.delete(`/sections/${props.section.id}/projects/${deleteProjectTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            deleteProjectTarget.value = null;
        },
    });
};

const previewTarget = ref<{
    show: boolean;
    title: string;
    fileName: string;
    fileUrl: string;
    downloadUrl: string;
}>({
    show: false,
    title: '',
    fileName: '',
    fileUrl: '',
    downloadUrl: '',
});

const openAssessmentPreview = (assessment: Assessment, e?: Event) => {
    if (e) {
        e.stopPropagation();
        e.preventDefault();
    }
    previewTarget.value = {
        show: true,
        title: assessment.title,
        fileName: assessment.attachment_name || 'Attached Reference',
        fileUrl: `/sections/${props.section.id}/assessments/${assessment.id}/attachment`,
        downloadUrl: `/sections/${props.section.id}/assessments/${assessment.id}/attachment?download=1`,
    };
};

const openProjectPreview = (project: Project, e?: Event) => {
    if (e) {
        e.stopPropagation();
        e.preventDefault();
    }
    previewTarget.value = {
        show: true,
        title: project.title,
        fileName: project.attachment_name || 'Project Guidelines',
        fileUrl: `/sections/${props.section.id}/projects/${project.id}/attachment`,
        downloadUrl: `/sections/${props.section.id}/projects/${project.id}/attachment?download=1`,
    };
};

const removeAttachment = () => {
    form.attachment = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
};

const removeProjectAttachment = () => {
    projectForm.attachment = null;
    if (projectFileInputRef.value) projectFileInputRef.value.value = '';
};

const formatDate = (value: string | null) => {
    if (!value) return 'No date';
    return new Intl.DateTimeFormat('en-PH', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' }).format(new Date(value));
};

// Form for standard assessments (Activity, Quiz, Exam)
const form = useForm({
    type: 'activity',
    title: '',
    description: '',
    conducted_on: new Date().toISOString().slice(0, 10),
    max_points: 10,
    attendance_session_id: '' as number | '',
    attachment: null as File | null,
});

// Form for project / reporting
const projectForm = useForm({
    type: 'reporting',
    title: '',
    description: '',
    conducted_on: new Date().toISOString().slice(0, 10),
    max_points: '' as number | '',
    group_count: 4,
    randomize: true,
    attachment: null as File | null,
});

const tabs = ['all', 'activity', 'quiz', 'exam', 'project'] as const;

const filteredAssessments = computed(() => {
    if (props.filter === 'project') return [];
    return props.assessments;
});

const filteredProjects = computed(() => {
    if (props.filter === 'quiz' || props.filter === 'exam') return [];
    return props.projects;
});

const submitAssessment = () =>
    form.post(`/sections/${props.section.id}/assessments`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            creating.value = false;
            form.reset();
            if (fileInputRef.value) fileInputRef.value.value = '';
        },
    });

const submitProject = () =>
    projectForm.post(`/sections/${props.section.id}/projects`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            creating.value = false;
            projectForm.reset();
            if (projectFileInputRef.value) projectFileInputRef.value.value = '';
        },
    });
</script>

<template>
    <Head :title="`Assessments & Projects · ${section.name} - ClassCheck`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Assessments', href: `/sections/${section.id}/assessments` },
        ]"
    >
        <main class="page-enter mx-auto flex w-full max-w-[1360px] flex-1 flex-col gap-6 px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <!-- Header Section -->
            <header
                class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 shadow-sm sm:p-8"
            >
                <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="badge-primary font-mono font-bold">{{ section.subject_code || 'Assessment Ledger' }}</span>
                            <span class="badge-muted">{{ section.name }}</span>
                        </div>
                        <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Activities, Projects & Scores</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Create quizzes, group projects, reporting presentations, and exams.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="`/sections/${section.id}/reports/gradebook`"
                            prefetch="hover"
                            class="group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-4 text-sm font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                        >
                            <BarChart3 class="size-4 text-primary transition-colors group-hover:text-white" />
                            <span>Gradebook</span>
                        </Link>
                        <a
                            :href="`/sections/${section.id}/exports/gradebook`"
                            class="group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-4 text-sm font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                        >
                            <Download class="size-4 text-primary transition-colors group-hover:text-white" />
                            <span>Export CSV</span>
                        </a>
                        <button
                            class="group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-4 text-sm font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            @click="
                                creating = true;
                                creationMode = 'project';
                            "
                        >
                            <FolderKanban class="size-4 text-primary transition-colors group-hover:text-white" />
                            <span>New Project / Report</span>
                        </button>
                        <button
                            class="ink-button !h-10 !rounded-xl"
                            @click="
                                creating = true;
                                creationMode = 'assessment';
                            "
                        >
                            <Plus class="size-4" />
                            <span>New assessment</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Creation Form Panel -->
            <section v-if="creating" class="paper-card p-6 duration-200 animate-in fade-in zoom-in-95 md:p-8">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-4 border-b border-border/80 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex rounded-xl bg-secondary p-1">
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs font-bold transition-all"
                                :class="
                                    creationMode === 'assessment'
                                        ? 'shadow-xs bg-card text-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="creationMode = 'assessment'"
                            >
                                Standard (Quiz / Activity / Exam)
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs font-bold transition-all"
                                :class="
                                    creationMode === 'project' ? 'shadow-xs bg-card text-foreground' : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="creationMode = 'project'"
                            >
                                Group Project & Reporting
                            </button>
                        </div>
                    </div>
                    <button class="text-xs font-semibold text-muted-foreground hover:text-foreground" @click="creating = false">Cancel</button>
                </div>

                <!-- STANDARD ASSESSMENT FORM -->
                <form v-if="creationMode === 'assessment'" class="grid gap-5 lg:grid-cols-12" @submit.prevent="submitAssessment">
                    <div v-if="form.hasErrors" class="rounded-xl border border-rose-200 bg-rose-50/80 p-3.5 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300 lg:col-span-12">
                        <div class="flex items-center gap-2 font-bold">
                            <AlertCircle class="size-4 shrink-0 text-rose-600 dark:text-rose-400" />
                            <span>Unable to create activity. Please check the fields below:</span>
                        </div>
                        <ul class="mt-1.5 list-inside list-disc space-y-0.5 pl-1 text-[11px]">
                            <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                        </ul>
                    </div>

                    <label class="lg:col-span-3">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Type</span>
                        <select
                            v-model="form.type"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <option value="activity">Activity</option>
                            <option value="quiz">Quiz</option>
                            <option value="exam">Exam</option>
                        </select>
                        <small v-if="form.errors.type" class="mt-1 block text-xs text-rose-600">{{ form.errors.type }}</small>
                    </label>

                    <label class="lg:col-span-6">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Title</span>
                        <input
                            v-model="form.title"
                            required
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                            placeholder="e.g. Chapter 4 Problem Set"
                        />
                        <small v-if="form.errors.title" class="mt-1 block text-xs text-rose-600">{{ form.errors.title }}</small>
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
                        <small v-if="form.errors.max_points" class="mt-1 block text-xs text-rose-600">{{ form.errors.max_points }}</small>
                    </label>

                    <label class="lg:col-span-3">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Date conducted</span>
                        <input
                            v-model="form.conducted_on"
                            required
                            type="date"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                        <small v-if="form.errors.conducted_on" class="mt-1 block text-xs text-rose-600">{{ form.errors.conducted_on }}</small>
                    </label>

                    <label class="lg:col-span-4">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Link session</span>
                        <select
                            v-model="form.attendance_session_id"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <option value="">Auto-match by date</option>
                            <option v-for="session in attendanceSessions" :key="session.id" :value="session.id">
                                {{ session.session_date }} · {{ session.starts_at }}
                            </option>
                        </select>
                        <small v-if="form.errors.attendance_session_id" class="mt-1 block text-xs text-rose-600">{{ form.errors.attendance_session_id }}</small>
                    </label>

                    <label class="lg:col-span-5">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground"
                            >Attachment <em class="font-normal normal-case text-muted-foreground">(optional, max 50MB)</em></span
                        >
                        <div class="flex items-center gap-2">
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z,.rtf,.odt,.ods,.odp,.svg"
                                class="block w-full text-xs text-muted-foreground file:mr-2 file:rounded-lg file:border file:border-border file:bg-secondary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-foreground hover:file:bg-secondary/80"
                                @change="form.attachment = ($event.target as HTMLInputElement).files?.[0] || null"
                            />
                            <button
                                v-if="form.attachment"
                                type="button"
                                title="Remove file"
                                class="shrink-0 rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                @click="removeAttachment"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <span v-if="form.attachment" class="mt-1 block font-mono text-[10px] text-primary">
                            Attached: {{ form.attachment.name }} ({{ (form.attachment.size / 1024 / 1024).toFixed(2) }} MB)
                        </span>
                        <small v-if="form.errors.attachment" class="mt-1 block text-xs font-semibold text-rose-600">{{ form.errors.attachment }}</small>
                    </label>

                    <label class="lg:col-span-9">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground"
                            >Notes <em class="font-normal normal-case text-muted-foreground">(optional)</em></span
                        >
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Instructions or rubric notes..."
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-primary"
                        />
                        <small v-if="form.errors.description" class="mt-1 block text-xs text-rose-600">{{ form.errors.description }}</small>
                    </label>

                    <div class="flex items-end justify-end gap-3 lg:col-span-3">
                        <button
                            type="button"
                            class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground"
                            @click="creating = false"
                        >
                            Cancel
                        </button>
                        <button :disabled="form.processing" class="ink-button !rounded-xl text-xs font-semibold">
                            {{ form.processing ? 'Creating…' : 'Create & Score' }}
                        </button>
                    </div>
                </form>

                <!-- GROUP PROJECT & REPORTING FORM -->
                <form v-else class="grid gap-5 lg:grid-cols-12" @submit.prevent="submitProject">
                    <div v-if="projectForm.hasErrors" class="rounded-xl border border-rose-200 bg-rose-50/80 p-3.5 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300 lg:col-span-12">
                        <div class="flex items-center gap-2 font-bold">
                            <AlertCircle class="size-4 shrink-0 text-rose-600 dark:text-rose-400" />
                            <span>Unable to create group project. Please check the fields below:</span>
                        </div>
                        <ul class="mt-1.5 list-inside list-disc space-y-0.5 pl-1 text-[11px]">
                            <li v-for="(err, key) in projectForm.errors" :key="key">{{ err }}</li>
                        </ul>
                    </div>

                    <label class="lg:col-span-4">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Activity Mode</span>
                        <select
                            v-model="projectForm.type"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <option value="reporting">Group Reporting (Manual topic per group)</option>
                            <option value="project">Group Project (Unified topic & scope for all groups)</option>
                        </select>
                        <small v-if="projectForm.errors.type" class="mt-1 block text-xs text-rose-600">{{ projectForm.errors.type }}</small>
                    </label>

                    <label class="lg:col-span-8">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Title</span>
                        <input
                            v-model="projectForm.title"
                            required
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                            :placeholder="
                                projectForm.type === 'reporting'
                                    ? 'e.g. Chapter 5 Group Presentation Topics'
                                    : 'e.g. Midterm System Architecture Project'
                            "
                        />
                        <small v-if="projectForm.errors.title" class="mt-1 block text-xs text-rose-600">{{ projectForm.errors.title }}</small>
                    </label>

                    <label class="lg:col-span-12">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            {{
                                projectForm.type === 'project'
                                    ? 'Project Description & Objectives (Applies to all groups)'
                                    : 'Reporting Guidelines / Instructions'
                            }}
                        </span>
                        <textarea
                            v-model="projectForm.description"
                            rows="2"
                            placeholder="Instructions, guidelines, or scope details..."
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-primary"
                        />
                        <small v-if="projectForm.errors.description" class="mt-1 block text-xs text-rose-600">{{ projectForm.errors.description }}</small>
                    </label>

                    <label class="lg:col-span-3">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Conducted / Due Date</span>
                        <input
                            v-model="projectForm.conducted_on"
                            type="date"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                        <small v-if="projectForm.errors.conducted_on" class="mt-1 block text-xs text-rose-600">{{ projectForm.errors.conducted_on }}</small>
                    </label>

                    <label class="lg:col-span-3">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Initial Groups Count</span>
                        <input
                            v-model.number="projectForm.group_count"
                            type="number"
                            min="1"
                            max="50"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                        <small v-if="projectForm.errors.group_count" class="mt-1 block text-xs text-rose-600">{{ projectForm.errors.group_count }}</small>
                    </label>

                    <label class="lg:col-span-3">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground"
                            >Max Points <em class="font-normal normal-case text-muted-foreground">(optional)</em></span
                        >
                        <input
                            v-model="projectForm.max_points"
                            type="number"
                            step="0.01"
                            placeholder="e.g. 50"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                        <small v-if="projectForm.errors.max_points" class="mt-1 block text-xs text-rose-600">{{ projectForm.errors.max_points }}</small>
                    </label>

                    <label class="lg:col-span-5">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground"
                            >Attachment / Guidelines <em class="font-normal normal-case text-muted-foreground">(optional, max 50MB)</em></span
                        >
                        <div class="flex items-center gap-2">
                            <input
                                ref="projectFileInputRef"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z,.rtf,.odt,.ods,.odp,.svg"
                                class="block w-full text-xs text-muted-foreground file:mr-2 file:rounded-lg file:border file:border-border file:bg-secondary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-foreground hover:file:bg-secondary/80"
                                @change="projectForm.attachment = ($event.target as HTMLInputElement).files?.[0] || null"
                            />
                            <button
                                v-if="projectForm.attachment"
                                type="button"
                                title="Remove file"
                                class="shrink-0 rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                @click="removeProjectAttachment"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <span v-if="projectForm.attachment" class="mt-1 block font-mono text-[10px] text-primary">
                            Attached: {{ projectForm.attachment.name }} ({{ (projectForm.attachment.size / 1024 / 1024).toFixed(2) }} MB)
                        </span>
                        <small v-if="projectForm.errors.attachment" class="mt-1 block text-xs font-semibold text-rose-600">{{ projectForm.errors.attachment }}</small>
                    </label>

                    <div class="flex items-center pt-6 lg:col-span-4">
                        <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-foreground">
                            <input
                                v-model="projectForm.randomize"
                                type="checkbox"
                                class="size-4 rounded border-border text-primary focus:ring-primary"
                            />
                            <span>Randomize members now</span>
                        </label>
                    </div>

                    <div class="flex items-end justify-end gap-3 border-t border-border/80 pt-4 lg:col-span-12">
                        <button
                            type="button"
                            class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground"
                            @click="creating = false"
                        >
                            Cancel
                        </button>
                        <button :disabled="projectForm.processing" class="ink-button !rounded-xl text-xs font-semibold">
                            {{ projectForm.processing ? 'Creating…' : 'Create & Open Project' }}
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
                            ? 'shadow-xs bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                    "
                >
                    {{ tab === 'project' ? 'Projects & Reports' : tab }}
                </Link>
            </div>

            <!-- PROJECTS SECTION (when in all, activity, or project tab) -->
            <div v-if="filteredProjects.length > 0 && (filter === 'all' || filter === 'activity' || filter === 'project')" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <FolderKanban class="size-4 text-primary" />
                        <h2 class="text-lg font-bold text-foreground">Group Projects & Reporting</h2>
                    </div>
                    <Link :href="`/sections/${section.id}/projects`" class="text-xs font-semibold text-primary hover:underline">
                        View all ({{ projects.length }})
                    </Link>
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="item in filteredProjects"
                        :key="item.id"
                        :href="`/sections/${section.id}/projects/${item.id}`"
                        prefetch="hover"
                        class="paper-card group flex flex-col justify-between border-l-4 transition-all hover:border-primary/50 hover:shadow-lg"
                        :class="item.type === 'project' ? 'border-l-primary' : 'border-l-amber-500'"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <span
                                    class="rounded-md px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider"
                                    :class="
                                        item.type === 'project'
                                            ? 'bg-emerald-800 text-white'
                                            : 'bg-amber-800 text-white'
                                    "
                                >
                                    {{ item.type === 'project' ? 'Project' : 'Reporting' }}
                                </span>
                                <div class="flex items-center gap-2">
                                    <span v-if="item.max_points" class="font-mono text-xs font-medium text-foreground"> {{ item.max_points }} pts </span>
                                    <button
                                        type="button"
                                        class="grid size-7 place-items-center rounded-lg text-muted-foreground/60 transition-colors hover:bg-rose-500/10 hover:text-rose-600 dark:hover:text-rose-400"
                                        title="Delete project/report misentry"
                                        @click.stop.prevent="deleteProjectTarget = item"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                            </div>

                            <h3 class="mt-3 text-lg font-bold tracking-tight text-foreground transition-colors group-hover:text-primary">
                                {{ item.title }}
                            </h3>

                            <p v-if="item.description" class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                                {{ item.description }}
                            </p>

                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1">
                                <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <CalendarDays class="size-3.5" /> {{ formatDate(item.conducted_on) }}
                                </p>
                                <button
                                    v-if="item.attachment_path"
                                    type="button"
                                    class="flex items-center gap-1.5 rounded-lg bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary transition-colors hover:bg-primary hover:text-white"
                                    title="Preview attached file with download option"
                                    @click.stop.prevent="openProjectPreview(item, $event)"
                                >
                                    <Paperclip class="size-3" />
                                    <span>Preview attached</span>
                                </button>
                            </div>
                        </div>

                        <div class="mt-5 flex items-center justify-between border-t border-border/80 pt-3 text-xs font-medium text-muted-foreground">
                            <span class="flex items-center gap-1"> <FolderKanban class="size-3 text-primary" /> {{ item.groups_count }} groups </span>
                            <span class="flex items-center gap-1">
                                <Users class="size-3 text-emerald-600 dark:text-emerald-400" /> {{ item.members_count }} members
                            </span>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Standard Assessments Grid -->
            <section v-if="filter !== 'project'" class="space-y-4">
                <div v-if="filteredAssessments.length > 0 && filter === 'all' && filteredProjects.length > 0" class="flex items-center gap-2 pt-2">
                    <h2 class="text-lg font-bold text-foreground">Individual Quizzes, Activities & Exams</h2>
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="item in filteredAssessments"
                        :key="item.id"
                        :href="`/sections/${section.id}/assessments/${item.id}`"
                        prefetch="hover"
                        class="paper-card group flex flex-col justify-between transition-all hover:border-primary/50 hover:shadow-lg"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <span
                                    class="rounded-md px-2.5 py-0.5 font-mono text-[10px] font-medium uppercase tracking-wider"
                                    :class="
                                        item.type === 'exam'
                                            ? 'bg-purple-800 text-white'
                                            : item.type === 'quiz'
                                              ? 'bg-blue-800 text-white'
                                              : 'bg-emerald-800 text-white'
                                    "
                                >
                                    {{ item.type }}
                                </span>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-medium text-foreground">{{ item.max_points }} pts</span>
                                    <button
                                        type="button"
                                        class="grid size-7 place-items-center rounded-lg text-muted-foreground/60 transition-colors hover:bg-rose-500/10 hover:text-rose-600 dark:hover:text-rose-400"
                                        :title="`Delete ${item.type} misentry`"
                                        @click.stop.prevent="deleteAssessmentTarget = item"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                            </div>

                            <h3 class="mt-4 text-xl font-medium tracking-tight transition-colors group-hover:text-primary">
                                {{ item.title }}
                            </h3>

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                                <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <CalendarDays class="size-3.5" /> {{ formatDate(item.conducted_on) }}
                                </p>
                                <button
                                    v-if="item.attachment_path"
                                    type="button"
                                    class="flex items-center gap-1.5 rounded-lg bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary transition-colors hover:bg-primary hover:text-white"
                                    title="Preview attached file with download option"
                                    @click.stop.prevent="openAssessmentPreview(item, $event)"
                                >
                                    <Paperclip class="size-3" />
                                    <span>Preview attached</span>
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-border/80 pt-4">
                            <div class="mb-2 flex items-center justify-between text-xs">
                                <span class="font-normal text-muted-foreground">Scoring progress</span>
                                <span class="font-mono font-medium text-primary">{{ item.graded_count }} recorded</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-secondary">
                                <div
                                    class="h-full rounded-full bg-primary transition-all duration-300"
                                    :style="{ width: `${Math.min(100, item.graded_count > 0 ? 100 : 0)}%` }"
                                />
                            </div>
                        </div>
                    </Link>

                    <div
                        v-if="!filteredAssessments.length && !filteredProjects.length"
                        class="col-span-full rounded-2xl border border-dashed border-border/80 bg-card p-14 text-center shadow-sm"
                    >
                        <ClipboardCheck class="mx-auto size-8 text-muted-foreground" />
                        <h3 class="mt-4 text-xl font-bold">No activities or assessments recorded</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Click "New assessment" or "New Project / Report" above to get started.</p>
                    </div>
                </div>
            </section>
        </main>

        <!-- Delete Assessment Confirmation Modal -->
        <div
            v-if="deleteAssessmentTarget"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
            @click.self="deleteAssessmentTarget = null"
        >
            <div
                class="paper-card relative w-full max-w-lg border-border/90 p-6 shadow-2xl duration-200 animate-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                :aria-label="`Delete ${deleteAssessmentTarget.title}`"
            >
                <div class="flex items-start gap-4">
                    <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-rose-500/15 text-rose-700 dark:text-rose-400">
                        <Trash2 class="size-6" />
                    </div>
                    <div class="flex-1">
                        <span class="eyebrow text-rose-700 dark:text-rose-400">Permanent Deletion</span>
                        <h3 class="mt-1 text-xl font-bold text-foreground">Delete {{ deleteAssessmentTarget.title }}?</h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ deleteAssessmentTarget.type.toUpperCase() }} · {{ deleteAssessmentTarget.max_points }} max points · {{ formatDate(deleteAssessmentTarget.conducted_on) }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-rose-500/20 bg-rose-500/5 p-4 text-xs leading-relaxed text-foreground">
                    <p class="font-bold text-rose-700 dark:text-rose-400">
                        Warning: This will permanently delete this {{ deleteAssessmentTarget.type }} entry:
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-4 text-muted-foreground">
                        <li>The assessment record and grade configuration</li>
                        <li>
                            All <strong class="text-foreground">{{ deleteAssessmentTarget.graded_count }} recorded scores</strong> for this item
                        </li>
                        <li v-if="deleteAssessmentTarget.attachment_name">
                            Attached file: {{ deleteAssessmentTarget.attachment_name }}
                        </li>
                    </ul>
                    <p class="mt-2 font-bold text-rose-700 dark:text-rose-400">
                        This action cannot be undone.
                    </p>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-border/80 pt-4">
                    <button
                        type="button"
                        class="shadow-xs inline-flex h-10 items-center justify-center rounded-xl border border-border bg-card px-5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                        :disabled="isDeleting"
                        @click="deleteAssessmentTarget = null"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-5 text-xs font-bold text-white shadow-sm transition-all hover:bg-rose-700 disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="confirmDeleteAssessment"
                    >
                        <LoaderCircle v-if="isDeleting" class="size-4 animate-spin" />
                        <Trash2 v-else class="size-4" />
                        <span>{{ isDeleting ? 'Deleting…' : `Yes, Delete ${deleteAssessmentTarget.type}` }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Project Confirmation Modal -->
        <div
            v-if="deleteProjectTarget"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
            @click.self="deleteProjectTarget = null"
        >
            <div
                class="paper-card relative w-full max-w-lg border-border/90 p-6 shadow-2xl duration-200 animate-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                :aria-label="`Delete ${deleteProjectTarget.title}`"
            >
                <div class="flex items-start gap-4">
                    <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-rose-500/15 text-rose-700 dark:text-rose-400">
                        <Trash2 class="size-6" />
                    </div>
                    <div class="flex-1">
                        <span class="eyebrow text-rose-700 dark:text-rose-400">Permanent Deletion</span>
                        <h3 class="mt-1 text-xl font-bold text-foreground">Delete {{ deleteProjectTarget.title }}?</h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ deleteProjectTarget.type === 'project' ? 'PROJECT' : 'REPORTING' }} · {{ deleteProjectTarget.groups_count }} groups
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-rose-500/20 bg-rose-500/5 p-4 text-xs leading-relaxed text-foreground">
                    <p class="font-bold text-rose-700 dark:text-rose-400">
                        Warning: This will permanently delete this {{ deleteProjectTarget.type }} activity:
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-4 text-muted-foreground">
                        <li>The project configuration and group assignments</li>
                        <li>All assigned group topics, notes, and individual grades</li>
                        <li v-if="deleteProjectTarget.attachment_name">
                            Attached guidelines file: {{ deleteProjectTarget.attachment_name }}
                        </li>
                    </ul>
                    <p class="mt-2 font-bold text-rose-700 dark:text-rose-400">
                        This action cannot be undone.
                    </p>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-border/80 pt-4">
                    <button
                        type="button"
                        class="shadow-xs inline-flex h-10 items-center justify-center rounded-xl border border-border bg-card px-5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                        :disabled="isDeleting"
                        @click="deleteProjectTarget = null"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-5 text-xs font-bold text-white shadow-sm transition-all hover:bg-rose-700 disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="confirmDeleteProject"
                    >
                        <LoaderCircle v-if="isDeleting" class="size-4 animate-spin" />
                        <Trash2 v-else class="size-4" />
                        <span>{{ isDeleting ? 'Deleting…' : 'Yes, Delete Project' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Attachment Preview Modal -->
        <FilePreviewModal
            :show="previewTarget.show"
            :title="previewTarget.title"
            :file-name="previewTarget.fileName"
            :file-url="previewTarget.fileUrl"
            :download-url="previewTarget.downloadUrl"
            @close="previewTarget.show = false"
        />
    </AppLayout>
</template>
