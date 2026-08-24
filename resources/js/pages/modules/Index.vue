<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Copy,
    Download,
    Edit3,
    ExternalLink,
    FileUp,
    Layers,
    Link2,
    LoaderCircle,
    Plus,
    Presentation,
    Search,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type CourseModule = {
    id: number;
    section_id: number;
    module_number: string;
    title: string;
    description: string | null;
    link_url: string | null;
    has_file: boolean;
    file_name: string | null;
    file_size: number | null;
    formatted_file_size: string | null;
    file_mime: string | null;
    sort_order: number;
    created_at?: string;
    updated_at?: string;
};

type Section = {
    id: number;
    subject_code: string;
    subject_title: string;
    name: string;
    term?: { name: string; school_year: string } | null;
};

const props = defineProps<{
    section: Section;
    modules: CourseModule[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sections', href: '/sections' },
    { title: props.section.subject_code || props.section.name, href: `/sections/${props.section.id}` },
    { title: 'Modules & Presentations', href: `/sections/${props.section.id}/modules` },
];

// Search & Filter
const searchQuery = ref('');
const filterType = ref<'all' | 'files' | 'links'>('all');

const filteredModules = computed(() => {
    let list = props.modules;

    if (filterType.value === 'files') {
        list = list.filter((m) => m.has_file);
    } else if (filterType.value === 'links') {
        list = list.filter((m) => m.link_url);
    }

    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return list;

    return list.filter(
        (m) =>
            m.module_number.toLowerCase().includes(q) ||
            m.title.toLowerCase().includes(q) ||
            (m.description && m.description.toLowerCase().includes(q)) ||
            (m.file_name && m.file_name.toLowerCase().includes(q)),
    );
});

const totalModulesCount = computed(() => props.modules.length);
const totalFilesCount = computed(() => props.modules.filter((m) => m.has_file).length);
const totalLinksCount = computed(() => props.modules.filter((m) => m.link_url).length);

// Add / Edit Modal State
const showModal = ref(false);
const editingModule = ref<CourseModule | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const isDragOver = ref(false);
const copiedId = ref<number | null>(null);

const form = useForm({
    module_number: '',
    title: '',
    description: '',
    link_url: '',
    file: null as File | null,
    remove_file: false,
});

const openCreateModal = () => {
    editingModule.value = null;
    form.reset();
    form.clearErrors();
    form.module_number = `Module ${props.modules.length + 1}`;
    selectedFile.value = null;
    showModal.value = true;
};

const openEditModal = (module: CourseModule) => {
    editingModule.value = module;
    form.reset();
    form.clearErrors();
    form.module_number = module.module_number;
    form.title = module.title;
    form.description = module.description || '';
    form.link_url = module.link_url || '';
    form.file = null;
    form.remove_file = false;
    selectedFile.value = null;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingModule.value = null;
    form.reset();
    form.clearErrors();
    selectedFile.value = null;
};

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        const file = target.files[0];
        if (file.size > 50 * 1024 * 1024) {
            form.setError('file', 'File size exceeds the 50 MB limit.');
            selectedFile.value = null;
            form.file = null;
            return;
        }
        form.clearErrors('file');
        selectedFile.value = file;
        form.file = file;
        form.remove_file = false;
    }
};

const handleDrop = (event: DragEvent) => {
    isDragOver.value = false;
    if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
        const file = event.dataTransfer.files[0];
        if (file.size > 50 * 1024 * 1024) {
            form.setError('file', 'File size exceeds the 50 MB limit.');
            selectedFile.value = null;
            form.file = null;
            return;
        }
        form.clearErrors('file');
        selectedFile.value = file;
        form.file = file;
        form.remove_file = false;
    }
};

const removeSelectedFile = () => {
    selectedFile.value = null;
    form.file = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const submitForm = () => {
    if (editingModule.value) {
        form.post(`/sections/${props.section.id}/modules/${editingModule.value.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(`/sections/${props.section.id}/modules`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

// Delete Confirmation
const moduleToDelete = ref<CourseModule | null>(null);
const isDeleting = ref(false);

const confirmDelete = (module: CourseModule) => {
    moduleToDelete.value = module;
};

const executeDelete = () => {
    if (!moduleToDelete.value) return;
    isDeleting.value = true;
    router.delete(`/sections/${props.section.id}/modules/${moduleToDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            moduleToDelete.value = null;
        },
    });
};

const copyModuleLink = (module: CourseModule) => {
    const targetUrl = module.link_url || `/sections/${props.section.id}/modules/${module.id}/download`;
    navigator.clipboard.writeText(targetUrl);
    copiedId.value = module.id;
    setTimeout(() => {
        if (copiedId.value === module.id) copiedId.value = null;
    }, 2000);
};

const getFileTypeBadge = (fileName: string | null, mime: string | null) => {
    if (!fileName && !mime) return 'FILE';
    const ext = fileName?.split('.').pop()?.toUpperCase() || '';
    if (['PPT', 'PPTX', 'KEY'].includes(ext) || mime?.includes('presentation') || mime?.includes('powerpoint')) return 'SLIDES';
    if (['PDF'].includes(ext) || mime?.includes('pdf')) return 'PDF';
    if (['DOC', 'DOCX'].includes(ext) || mime?.includes('word')) return 'DOC';
    if (['MP4', 'MOV', 'WEBM'].includes(ext) || mime?.includes('video')) return 'VIDEO';
    if (['ZIP', 'RAR', '7Z'].includes(ext) || mime?.includes('zip') || mime?.includes('compressed')) return 'ARCHIVE';
    return ext || 'FILE';
};
</script>

<template>
    <Head :title="`Modules & Presentations · ${section.subject_code} - ClassCheck`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="page-enter mx-auto flex w-full max-w-[1360px] flex-1 flex-col gap-6 px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <!-- Header Banner -->
            <header
                class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 shadow-sm sm:p-8"
            >
                <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <div class="mb-3 flex items-center gap-2">
                            <Link
                                :href="`/sections/${section.id}`"
                                prefetch="hover"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground transition-colors hover:text-primary"
                            >
                                <ArrowLeft class="size-3.5" /> Back to section classroom
                            </Link>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge-primary font-mono font-bold">{{ section.subject_code }}</span>
                            <span v-if="section.term" class="badge-muted">{{ section.term.name }} {{ section.term.school_year }}</span>
                            <span class="badge-muted">{{ section.name }}</span>
                        </div>
                        <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Course Modules & Presentations</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Upload lecture presentations (up to 50 MB) or provide presentation links for all course modules.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button class="ink-button !h-10 !rounded-xl" @click="openCreateModal">
                            <Plus class="size-4" />
                            <span>Add Module</span>
                        </Button>
                    </div>
                </div>

                <!-- KPI Metric Strip -->
                <div class="mt-6 grid grid-cols-3 gap-3 border-t border-border/60 pt-5 sm:w-fit">
                    <div class="rounded-xl border border-border/80 bg-secondary/30 px-4 py-2.5">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Modules</span>
                        <p class="mt-0.5 font-mono text-2xl font-bold text-foreground">{{ totalModulesCount }}</p>
                    </div>
                    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2.5">
                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Uploaded Files</span>
                        <p class="mt-0.5 font-mono text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ totalFilesCount }}</p>
                    </div>
                    <div class="rounded-xl border border-blue-500/30 bg-blue-500/10 px-4 py-2.5">
                        <span class="text-xs font-semibold uppercase tracking-wider text-blue-700 dark:text-blue-400">Presentation Links</span>
                        <p class="mt-0.5 font-mono text-2xl font-bold text-blue-700 dark:text-blue-400">{{ totalLinksCount }}</p>
                    </div>
                </div>
            </header>

            <!-- Main Content Area: Module List -->
            <section class="paper-card overflow-hidden p-6 shadow-sm">
                <!-- Controls & Filter Toolbar -->
                <div class="flex flex-col justify-between gap-4 border-b border-border/80 pb-5 md:flex-row md:items-center">
                    <div>
                        <h2 class="text-xl font-bold text-foreground">Module Directory</h2>
                        <p class="text-xs text-muted-foreground">
                            Listing of all course modules, lecture titles, slide decks, and presentation materials.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Search Box -->
                        <div class="relative min-w-[240px]">
                            <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search by module or title..."
                                class="w-full rounded-xl border border-input bg-background py-1.5 pl-9 pr-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                            />
                        </div>

                        <!-- Filter Options -->
                        <div class="flex items-center gap-1 rounded-xl border border-border/80 bg-secondary/30 p-1">
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1 text-xs font-medium transition-colors"
                                :class="filterType === 'all' ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-secondary'"
                                @click="filterType = 'all'"
                            >
                                All ({{ modules.length }})
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1 text-xs font-medium transition-colors"
                                :class="filterType === 'files' ? 'bg-emerald-700 text-white' : 'text-muted-foreground hover:bg-secondary'"
                                @click="filterType = 'files'"
                            >
                                Files ({{ totalFilesCount }})
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1 text-xs font-medium transition-colors"
                                :class="filterType === 'links' ? 'bg-blue-700 text-white' : 'text-muted-foreground hover:bg-secondary'"
                                @click="filterType = 'links'"
                            >
                                Links ({{ totalLinksCount }})
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Structured Modules List (Module -> Title -> Link / Presentation) -->
                <div v-if="filteredModules.length > 0" class="mt-5 space-y-3">
                    <article
                        v-for="item in filteredModules"
                        :key="item.id"
                        class="shadow-xs group relative flex flex-col justify-between gap-4 rounded-2xl border border-border/80 bg-card p-5 transition-all hover:border-primary/50 hover:shadow-md md:flex-row md:items-center"
                    >
                        <!-- Left Info: Module Identifier & Title -->
                        <div class="flex min-w-0 items-start gap-4">
                            <!-- Module Badge Column -->
                            <div class="flex shrink-0 flex-col items-center justify-center">
                                <span
                                    class="inline-flex items-center justify-center rounded-xl bg-primary/10 px-3.5 py-2 font-mono text-sm font-bold text-primary ring-1 ring-primary/20"
                                >
                                    {{ item.module_number }}
                                </span>
                            </div>

                            <!-- Title & Notes Column -->
                            <div class="min-w-0">
                                <h3 class="text-lg font-bold text-foreground transition-colors group-hover:text-primary">
                                    {{ item.title }}
                                </h3>
                                <p v-if="item.description" class="mt-1 line-clamp-2 text-sm leading-relaxed text-muted-foreground">
                                    {{ item.description }}
                                </p>

                                <!-- Attachment & Link Quick Meta Badges -->
                                <div class="mt-2.5 flex flex-wrap items-center gap-2 text-xs">
                                    <!-- File badge -->
                                    <span
                                        v-if="item.has_file"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 font-medium text-emerald-800 dark:text-emerald-300"
                                    >
                                        <Presentation class="size-3.5" />
                                        <span class="font-bold">{{ getFileTypeBadge(item.file_name, item.file_mime) }}</span>
                                        <span class="text-emerald-700/80 dark:text-emerald-400/80">· {{ item.formatted_file_size }}</span>
                                    </span>

                                    <!-- Link badge -->
                                    <span
                                        v-if="item.link_url"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-blue-500/30 bg-blue-500/10 px-2.5 py-1 font-medium text-blue-800 dark:text-blue-300"
                                    >
                                        <Link2 class="size-3.5" />
                                        <span>Presentation Link Attached</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Actions: Link / Download Presentation & Admin Actions -->
                        <div class="flex shrink-0 flex-wrap items-center gap-2 border-t border-border/50 pt-3 md:border-t-0 md:pt-0">
                            <!-- 1. External Presentation Link Action -->
                            <a
                                v-if="item.link_url"
                                :href="item.link_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="shadow-xs inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white transition-colors hover:bg-blue-700"
                                title="Open online presentation in new tab"
                            >
                                <ExternalLink class="size-3.5" />
                                <span>Open Presentation</span>
                            </a>

                            <!-- 2. Uploaded File Download / View Action -->
                            <a
                                v-if="item.has_file"
                                :href="`/sections/${section.id}/modules/${item.id}/download?download=1`"
                                class="shadow-xs inline-flex items-center gap-1.5 rounded-xl bg-emerald-700 px-3.5 py-2 text-xs font-semibold text-white transition-colors hover:bg-emerald-800"
                                :title="`Download ${item.file_name} (${item.formatted_file_size})`"
                            >
                                <Download class="size-3.5" />
                                <span>Download ({{ item.formatted_file_size }})</span>
                            </a>

                            <!-- 3. Copy Link Action -->
                            <button
                                v-if="item.link_url || item.has_file"
                                type="button"
                                class="inline-flex size-9 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                :title="copiedId === item.id ? 'Copied to clipboard' : 'Copy link'"
                                @click="copyModuleLink(item)"
                            >
                                <Check v-if="copiedId === item.id" class="size-4 text-emerald-600" />
                                <Copy v-else class="size-4" />
                            </button>

                            <!-- 4. Edit Button -->
                            <button
                                type="button"
                                class="inline-flex size-9 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                title="Edit module details"
                                @click="openEditModal(item)"
                            >
                                <Edit3 class="size-4" />
                            </button>

                            <!-- 5. Delete Button -->
                            <button
                                type="button"
                                class="inline-flex size-9 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-700 transition-colors hover:bg-rose-100 hover:text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-400 dark:hover:bg-rose-900/60"
                                title="Delete module"
                                @click="confirmDelete(item)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </article>
                </div>

                <!-- Empty State -->
                <div v-else class="rounded-2xl border border-dashed border-border bg-secondary/20 py-16 text-center">
                    <div class="mx-auto grid size-14 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <Layers class="size-7" />
                    </div>
                    <h3 class="mt-4 text-xl font-bold text-foreground">
                        {{ searchQuery ? 'No matching modules found' : 'No course modules uploaded yet' }}
                    </h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-muted-foreground">
                        {{
                            searchQuery
                                ? 'Try searching with different keywords or clearing your active filters.'
                                : 'Upload slide decks (up to 50 MB) or provide external presentation links (Google Slides, Canva) to list all your modules.'
                        }}
                    </p>
                    <Button class="ink-button mt-5" @click="openCreateModal">
                        <Plus class="size-4" />
                        <span>Add First Module</span>
                    </Button>
                </div>
            </section>
        </main>

        <!-- Add / Edit Module Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-zinc-950/70 p-4 backdrop-blur-md"
            @click.self="closeModal"
        >
            <div class="paper-card my-8 w-full max-w-lg p-7 shadow-2xl duration-200 animate-in fade-in zoom-in-95">
                <div class="flex items-center justify-between border-b border-border/60 pb-3">
                    <div>
                        <span class="eyebrow">{{ editingModule ? 'Edit Content' : 'Course Material' }}</span>
                        <h2 class="text-xl font-bold text-foreground">{{ editingModule ? 'Edit Course Module' : 'Add Course Module' }}</h2>
                    </div>
                    <button
                        type="button"
                        class="grid size-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        @click="closeModal"
                    >
                        <X class="size-4.5" />
                    </button>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="submitForm">
                    <!-- Module Number & Title Row -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-1">
                            <Label for="module_number" class="text-xs font-semibold">Module # / Code</Label>
                            <Input
                                id="module_number"
                                v-model="form.module_number"
                                required
                                placeholder="e.g. Module 1"
                                class="mt-1 h-10 rounded-xl text-sm"
                            />
                            <InputError class="mt-1 text-xs" :message="form.errors.module_number" />
                        </div>
                        <div class="sm:col-span-2">
                            <Label for="title" class="text-xs font-semibold">Module Title</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                required
                                placeholder="e.g. Introduction to Cloud Computing"
                                class="mt-1 h-10 rounded-xl text-sm"
                            />
                            <InputError class="mt-1 text-xs" :message="form.errors.title" />
                        </div>
                    </div>

                    <!-- Presentation Link URL -->
                    <div>
                        <Label for="link_url" class="text-xs font-semibold">
                            Presentation Link <span class="font-normal text-muted-foreground">(Google Slides, Canva, Loom, etc.)</span>
                        </Label>
                        <div class="relative mt-1">
                            <Link2 class="absolute left-3 top-3 size-4 text-muted-foreground" />
                            <Input
                                id="link_url"
                                v-model="form.link_url"
                                type="url"
                                placeholder="https://docs.google.com/presentation/d/..."
                                class="h-10 rounded-xl pl-9 text-sm"
                            />
                        </div>
                        <InputError class="mt-1 text-xs" :message="form.errors.link_url" />
                    </div>

                    <!-- File Upload Zone (Max 50 MB) -->
                    <div>
                        <div class="flex items-center justify-between">
                            <Label class="text-xs font-semibold">
                                Presentation File Upload <span class="font-normal text-muted-foreground">(Max 50 MB)</span>
                            </Label>
                            <span class="font-mono text-[11px] font-semibold text-primary">Up to 50 MB</span>
                        </div>

                        <!-- If current module already has a file -->
                        <div
                            v-if="editingModule?.has_file && !form.remove_file && !selectedFile"
                            class="mt-2 flex items-center justify-between rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs"
                        >
                            <div class="flex items-center gap-2">
                                <Presentation class="size-4 text-emerald-700 dark:text-emerald-400" />
                                <div>
                                    <p class="font-semibold text-emerald-950 dark:text-emerald-100">{{ editingModule.file_name }}</p>
                                    <p class="text-[11px] text-emerald-800/80 dark:text-emerald-300/80">
                                        Current file · {{ editingModule.formatted_file_size }}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg px-2 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-500/10 dark:text-rose-400"
                                @click="form.remove_file = true"
                            >
                                Replace / Remove
                            </button>
                        </div>

                        <!-- Dropzone input -->
                        <div
                            v-if="!editingModule?.has_file || form.remove_file || selectedFile"
                            class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed p-5 text-center transition-colors"
                            :class="
                                isDragOver
                                    ? 'border-primary bg-primary/10'
                                    : 'border-border/80 bg-secondary/20 hover:border-primary/50 hover:bg-secondary/40'
                            "
                            @click="fileInputRef?.click()"
                            @dragover.prevent="isDragOver = true"
                            @dragleave.prevent="isDragOver = false"
                            @drop.prevent="handleDrop"
                        >
                            <input
                                ref="fileInputRef"
                                type="file"
                                class="hidden"
                                accept=".pdf,.ppt,.pptx,.key,.odp,.zip,.rar,.mp4,.doc,.docx"
                                @change="handleFileSelect"
                            />

                            <div v-if="selectedFile" class="flex items-center gap-2.5">
                                <Presentation class="size-5 text-primary" />
                                <div class="text-left">
                                    <p class="font-bold text-foreground">{{ selectedFile.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ (selectedFile.size / (1024 * 1024)).toFixed(2) }} MB</p>
                                </div>
                                <button
                                    type="button"
                                    class="ml-2 rounded-full p-1 text-muted-foreground hover:bg-secondary hover:text-foreground"
                                    @click.stop="removeSelectedFile"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>

                            <div v-else class="space-y-1">
                                <FileUp class="mx-auto size-7 text-muted-foreground" />
                                <p class="text-xs font-semibold text-foreground">Click to browse or drag & drop presentation file</p>
                                <p class="text-[11px] text-muted-foreground">PDF, PPT, PPTX, KEY, ZIP, MP4 (Max 50 MB)</p>
                            </div>
                        </div>
                        <InputError class="mt-1 text-xs" :message="form.errors.file" />
                    </div>

                    <!-- Description / Summary Notes -->
                    <div>
                        <Label for="description" class="text-xs font-semibold">
                            Module Summary / Topics Covered <span class="font-normal text-muted-foreground">(optional)</span>
                        </Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            maxlength="5000"
                            placeholder="Brief description of lecture objectives, key takeaways, and references..."
                            class="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        />
                        <InputError class="mt-1 text-xs" :message="form.errors.description" />
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-6 flex items-center justify-end gap-3 border-t border-border/60 pt-4">
                        <button
                            type="button"
                            class="rounded-xl border border-border bg-background px-4 py-2 text-sm font-semibold text-foreground transition-colors hover:bg-secondary"
                            :disabled="form.processing"
                            @click="closeModal"
                        >
                            Cancel
                        </button>
                        <Button type="submit" class="ink-button !h-10 px-5 text-sm font-semibold" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                            <span>{{ form.processing ? 'Saving module...' : editingModule ? 'Update Module' : 'Save Module' }}</span>
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div
            v-if="moduleToDelete"
            class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            role="dialog"
            aria-modal="true"
            @click.self="moduleToDelete = null"
        >
            <div class="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-2xl animate-in fade-in zoom-in-95">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-rose-100 dark:bg-rose-950/60">
                        <Trash2 class="size-5 text-rose-600 dark:text-rose-400" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-foreground">Delete Module</h3>
                        <p class="text-xs text-muted-foreground">{{ moduleToDelete.module_number }}: {{ moduleToDelete.title }}</p>
                    </div>
                </div>

                <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                    Are you sure you want to delete this module? Any uploaded presentation files and links associated with it will be permanently
                    removed.
                </p>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-border bg-background px-4 py-2 text-sm font-semibold text-foreground transition-colors hover:bg-secondary"
                        :disabled="isDeleting"
                        @click="moduleToDelete = null"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rose-700 disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="executeDelete"
                    >
                        <LoaderCircle v-if="isDeleting" class="size-4 animate-spin" />
                        <span>{{ isDeleting ? 'Deleting...' : 'Yes, Delete Module' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
