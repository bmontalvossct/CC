<script setup lang="ts">
import AnimatedImage from '@/components/AnimatedImage.vue';
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Check,
    Database,
    Download,
    FileJson,
    FileSpreadsheet,
    FileText,
    FileType2,
    FolderArchive,
    FolderOpen,
    Image as ImageIcon,
    LoaderCircle,
    Presentation,
    RefreshCw,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    show: boolean;
    title?: string;
    fileName?: string;
    fileUrl: string;
    downloadUrl?: string;
    reuploadUrl?: string;
    deleteUrl?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'reuploaded'): void;
    (e: 'deleted'): void;
}>();

const isOpeningFolder = ref(false);
const folderOpened = ref(false);
const isReuploading = ref(false);
const isDeleting = ref(false);
const showDeleteConfirm = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const actionError = ref('');

const openFolderLocation = async () => {
    isOpeningFolder.value = true;
    try {
        const response = await fetch('/system/open-file-location', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                file_url: props.fileUrl,
                file_name: props.fileName,
            }),
        });
        const data = await response.json();
        folderOpened.value = true;
        setTimeout(() => {
            folderOpened.value = false;
        }, 3000);
    } catch {
        // Fallback: If not reachable, open download
        if (effectiveDownloadUrl.value) {
            window.location.href = effectiveDownloadUrl.value;
        }
    } finally {
        isOpeningFolder.value = false;
    }
};

const triggerReupload = () => {
    actionError.value = '';
    fileInputRef.value?.click();
};

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file || !props.reuploadUrl) return;

    if (file.size > 50 * 1024 * 1024) {
        actionError.value = 'File is larger than 50MB. Please select a smaller file.';
        target.value = '';
        return;
    }

    isReuploading.value = true;
    actionError.value = '';

    const formData = new FormData();
    formData.append('attachment', file);

    router.post(props.reuploadUrl, formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            isReuploading.value = false;
            target.value = '';
            emit('reuploaded');
        },
        onError: (errors) => {
            isReuploading.value = false;
            target.value = '';
            actionError.value = errors.attachment || 'Failed to replace attachment. Please check file type.';
        },
    });
};

const deleteAttachment = () => {
    if (!props.deleteUrl) return;

    isDeleting.value = true;
    actionError.value = '';

    router.delete(props.deleteUrl, {
        preserveScroll: true,
        onSuccess: () => {
            isDeleting.value = false;
            showDeleteConfirm.value = false;
            emit('deleted');
            emit('close');
        },
        onError: () => {
            isDeleting.value = false;
            actionError.value = 'Unable to delete attachment. Please try again.';
        },
    });
};

const effectiveDownloadUrl = computed(() => {
    if (props.downloadUrl) return props.downloadUrl;
    if (!props.fileUrl) return '';
    return props.fileUrl.includes('?') ? `${props.fileUrl}&download=1` : `${props.fileUrl}?download=1`;
});

const extension = computed(() => {
    if (!props.fileName) return '';
    const parts = props.fileName.split('.');
    return parts.length > 1 ? parts.pop()!.toLowerCase() : '';
});

const fileCategory = computed<'pdf' | 'image' | 'text' | 'word' | 'excel' | 'powerpoint' | 'archive' | 'database' | 'other'>(() => {
    const ext = extension.value;
    if (ext === 'pdf') return 'pdf';
    if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'heic'].includes(ext)) return 'image';
    if (['txt', 'csv', 'md', 'json', 'log', 'sql', 'xml', 'yaml', 'yml'].includes(ext)) return 'text';
    if (['doc', 'docx', 'rtf', 'odt', 'pages'].includes(ext)) return 'word';
    if (['xls', 'xlsx', 'ods', 'numbers'].includes(ext)) return 'excel';
    if (['ppt', 'pptx', 'odp', 'key'].includes(ext)) return 'powerpoint';
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) return 'archive';
    if (['sqlite', 'db', 'sqlite3', 'bak'].includes(ext)) return 'database';
    return 'other';
});

const categoryLabel = computed(() => {
    const ext = extension.value;
    switch (fileCategory.value) {
        case 'pdf':
            return 'PDF Document';
        case 'image':
            return 'Image File';
        case 'text':
            return ext === 'json' ? 'JSON Data File' : ext === 'sql' ? 'SQL Script File' : 'Text / Data Document';
        case 'word':
            return 'Word Document';
        case 'excel':
            return 'Spreadsheet';
        case 'powerpoint':
            return 'Presentation Slide';
        case 'archive':
            return 'Compressed Archive';
        case 'database':
            return 'Database File (.sqlite / .db)';
        default:
            return `${extension.value.toUpperCase() || 'Attached'} File`;
    }
});

// Keyboard close handler
const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && props.show) {
        emit('close');
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <div
        v-if="show"
        v-modal-focus
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-3 backdrop-blur-md duration-200 animate-in fade-in sm:p-6 md:p-8"
    >
        <div
            class="relative flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-border/90 bg-card text-card-foreground shadow-2xl duration-200 animate-in zoom-in-95"
            role="dialog"
            aria-modal="true"
        >
            <!-- Header Toolbar -->
            <header class="flex items-center justify-between border-b border-border/80 bg-muted/40 px-5 py-3.5 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <ImageIcon v-if="fileCategory === 'image'" class="size-4" />
                        <FileJson v-else-if="extension === 'json'" class="size-4 text-emerald-600 dark:text-emerald-400" />
                        <Database v-else-if="fileCategory === 'database'" class="size-4 text-blue-600 dark:text-blue-400" />
                        <FileText v-else-if="fileCategory === 'pdf' || fileCategory === 'text' || fileCategory === 'word'" class="size-4" />
                        <FileSpreadsheet v-else-if="fileCategory === 'excel'" class="size-4" />
                        <Presentation v-else-if="fileCategory === 'powerpoint'" class="size-4" />
                        <FolderArchive v-else-if="fileCategory === 'archive'" class="size-4" />
                        <FileType2 v-else class="size-4" />
                    </div>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="truncate text-sm font-bold text-foreground sm:text-base">
                                {{ fileName || title || 'Attached Reference' }}
                            </h3>
                            <span
                                class="hidden rounded-md bg-secondary px-2 py-0.5 font-mono text-[10px] font-semibold text-muted-foreground sm:inline-block"
                            >
                                {{ categoryLabel }}
                            </span>
                        </div>
                        <p v-if="title && fileName && title !== fileName" class="truncate text-xs text-muted-foreground">
                            {{ title }}
                        </p>
                    </div>
                </div>

                <!-- Action Controls -->
                <div class="flex shrink-0 items-center gap-2">
                    <!-- Hidden Reupload Input -->
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z,.rtf,.odt,.ods,.odp,.svg,.gif,.bmp,.heic,.pages,.numbers,.key,.json,.sql,.db,.sqlite,.sqlite3"
                        class="hidden"
                        @change="handleFileChange"
                    />

                    <!-- Replace / Reupload Button -->
                    <button
                        v-if="reuploadUrl"
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-primary/40 bg-primary/10 px-3.5 text-xs font-semibold text-primary transition-all hover:bg-primary hover:text-white"
                        :title="isReuploading ? 'Uploading replacement file...' : 'Replace or reupload attached file'"
                        :disabled="isReuploading"
                        @click="triggerReupload"
                    >
                        <LoaderCircle v-if="isReuploading" class="size-3.5 animate-spin" />
                        <RefreshCw v-else class="size-3.5" />
                        <span class="hidden sm:inline">{{ isReuploading ? 'Uploading...' : 'Replace File' }}</span>
                    </button>

                    <!-- Delete Attachment Button -->
                    <button
                        v-if="deleteUrl"
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-3.5 text-xs font-semibold text-rose-600 transition-all hover:bg-rose-600 hover:text-white dark:text-rose-400 dark:hover:text-white"
                        title="Remove or delete this attached file"
                        :disabled="isDeleting"
                        @click="showDeleteConfirm = true"
                    >
                        <Trash2 class="size-3.5" />
                        <span class="hidden sm:inline">Delete File</span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-border bg-card px-3.5 text-xs font-semibold text-foreground transition-all hover:bg-secondary"
                        :title="folderOpened ? 'Folder Opened in Explorer' : 'Open in Windows File Explorer'"
                        :disabled="isOpeningFolder"
                        @click="openFolderLocation"
                    >
                        <Check v-if="folderOpened" class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                        <FolderOpen v-else class="size-3.5 text-amber-600 dark:text-amber-400" />
                        <span class="hidden sm:inline">{{
                            folderOpened ? 'Opened in Explorer' : isOpeningFolder ? 'Opening...' : 'Open Folder'
                        }}</span>
                    </button>

                    <a
                        :href="effectiveDownloadUrl"
                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl bg-primary px-3.5 text-xs font-bold text-primary-foreground shadow-sm transition-all hover:bg-primary/90 hover:shadow"
                        title="Download file to your device"
                    >
                        <Download class="size-3.5" />
                        <span class="hidden sm:inline">Download</span>
                    </a>

                    <button
                        type="button"
                        class="inline-flex size-9 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground transition-colors hover:bg-rose-500/10 hover:text-rose-600"
                        title="Close preview (Esc)"
                        @click="emit('close')"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </header>

            <!-- Error Banner -->
            <div
                v-if="actionError"
                class="flex items-center justify-between border-b border-rose-500/30 bg-rose-500/10 px-5 py-2.5 text-xs font-semibold text-rose-700 dark:text-rose-300"
            >
                <div class="flex items-center gap-2">
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{{ actionError }}</span>
                </div>
                <button type="button" class="text-rose-600 hover:text-rose-800" @click="actionError = ''">
                    <X class="size-3.5" />
                </button>
            </div>

            <!-- Preview Body -->
            <div class="relative flex-1 overflow-auto bg-muted/20 p-3 sm:p-5">
                <!-- Delete Confirmation Dialog Overlay -->
                <div
                    v-if="showDeleteConfirm"
                    class="backdrop-blur-xs absolute inset-0 z-20 flex items-center justify-center bg-zinc-950/80 p-4 animate-in fade-in"
                >
                    <div class="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-2xl animate-in zoom-in-95">
                        <div class="flex items-center gap-3">
                            <div class="grid size-11 place-items-center rounded-xl bg-rose-500/15 text-rose-600 dark:text-rose-400">
                                <Trash2 class="size-5" />
                            </div>
                            <div>
                                <h4 class="text-base font-bold text-foreground">Remove Attached File?</h4>
                                <p class="text-xs text-muted-foreground">This file will be permanently deleted from the section folder.</p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl border border-border/70 bg-secondary/30 p-3 text-xs">
                            <p class="truncate font-mono font-semibold text-foreground">{{ fileName }}</p>
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                Note: All existing student scores, task details, and records will remain completely intact.
                            </p>
                        </div>

                        <div class="mt-5 flex items-center justify-end gap-2.5">
                            <button
                                type="button"
                                class="rounded-xl border border-border bg-card px-4 py-2 text-xs font-semibold text-foreground hover:bg-secondary"
                                :disabled="isDeleting"
                                @click="showDeleteConfirm = false"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700 disabled:opacity-50"
                                :disabled="isDeleting"
                                @click="deleteAttachment"
                            >
                                <LoaderCircle v-if="isDeleting" class="size-3.5 animate-spin" />
                                <span>{{ isDeleting ? 'Deleting...' : 'Yes, Delete Attachment' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- PDF Viewer -->
                <div v-if="fileCategory === 'pdf'" class="h-[68vh] w-full sm:h-[72vh]">
                    <iframe :src="fileUrl" class="size-full rounded-xl border border-border/80 bg-white shadow-inner" title="PDF Viewer" />
                </div>

                <!-- Image Viewer -->
                <div
                    v-else-if="fileCategory === 'image'"
                    class="flex max-h-[72vh] min-h-[50vh] items-center justify-center overflow-auto rounded-xl border border-border/60 bg-black/5 p-4 dark:bg-black/30"
                >
                    <AnimatedImage
                        :src="fileUrl"
                        :alt="fileName || 'Attached Image'"
                        image-class="object-contain"
                        class="h-[60vh] w-full rounded-lg"
                    />
                </div>

                <!-- Text / CSV Viewer -->
                <div v-else-if="fileCategory === 'text'" class="h-[65vh] w-full">
                    <iframe
                        :src="fileUrl"
                        class="size-full rounded-xl border border-border/80 bg-card p-2 font-mono text-xs shadow-inner"
                        title="Text Preview"
                    />
                </div>

                <!-- Non-inline Office / Archive / Other File Card -->
                <div
                    v-else
                    class="flex min-h-[48vh] flex-col items-center justify-center rounded-xl border border-dashed border-border/80 bg-card/60 p-8 text-center"
                >
                    <div class="flex size-20 items-center justify-center rounded-2xl bg-primary/10 text-primary shadow-inner">
                        <FileText v-if="fileCategory === 'word'" class="size-10" />
                        <FileSpreadsheet v-else-if="fileCategory === 'excel'" class="size-10 text-emerald-600 dark:text-emerald-400" />
                        <Presentation v-else-if="fileCategory === 'powerpoint'" class="size-10 text-amber-600 dark:text-amber-400" />
                        <FolderArchive v-else-if="fileCategory === 'archive'" class="size-10 text-purple-600 dark:text-purple-400" />
                        <Database v-else-if="fileCategory === 'database'" class="size-10 text-blue-600 dark:text-blue-400" />
                        <FileType2 v-else class="size-10 text-primary" />
                    </div>

                    <h4 class="mt-4 text-lg font-bold text-foreground">
                        {{ fileName }}
                    </h4>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ categoryLabel }} &middot; Direct preview is not supported inside the browser for this file type.
                    </p>

                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <a :href="effectiveDownloadUrl" class="ink-button !h-10 !rounded-xl !px-6 text-xs font-bold">
                            <Download class="size-4" />
                            <span>Download {{ fileName }}</span>
                        </a>

                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-5 text-xs font-semibold text-foreground transition-colors hover:bg-secondary"
                            :disabled="isOpeningFolder"
                            @click="openFolderLocation"
                        >
                            <Check v-if="folderOpened" class="size-4 text-emerald-600 dark:text-emerald-400" />
                            <FolderOpen v-else class="size-4 text-amber-600 dark:text-amber-400" />
                            <span>{{
                                folderOpened ? 'Folder Opened in Explorer' : isOpeningFolder ? 'Opening Explorer...' : 'Open File Location'
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="flex items-center justify-between border-t border-border/80 bg-muted/40 px-5 py-2.5 text-xs text-muted-foreground sm:px-6">
                <span class="truncate font-mono text-[11px]">
                    {{ fileName }}
                </span>
                <div class="flex items-center gap-2 font-medium">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1 text-xs font-semibold text-muted-foreground hover:bg-muted hover:text-foreground"
                        @click="emit('close')"
                    >
                        Close
                    </button>
                </div>
            </footer>
        </div>
    </div>
</template>
