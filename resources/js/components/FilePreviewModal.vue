<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';
import {
    Download,
    ExternalLink,
    FileSpreadsheet,
    FileText,
    FileType2,
    FolderArchive,
    Image as ImageIcon,
    Presentation,
    X,
} from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    title?: string;
    fileName?: string;
    fileUrl: string;
    downloadUrl?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

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

const fileCategory = computed<'pdf' | 'image' | 'text' | 'word' | 'excel' | 'powerpoint' | 'archive' | 'other'>(() => {
    const ext = extension.value;
    if (ext === 'pdf') return 'pdf';
    if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'heic'].includes(ext)) return 'image';
    if (['txt', 'csv', 'md', 'json', 'log'].includes(ext)) return 'text';
    if (['doc', 'docx', 'rtf', 'odt', 'pages'].includes(ext)) return 'word';
    if (['xls', 'xlsx', 'ods', 'numbers'].includes(ext)) return 'excel';
    if (['ppt', 'pptx', 'odp', 'key'].includes(ext)) return 'powerpoint';
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) return 'archive';
    return 'other';
});

const categoryLabel = computed(() => {
    switch (fileCategory.value) {
        case 'pdf':
            return 'PDF Document';
        case 'image':
            return 'Image File';
        case 'text':
            return 'Text Document';
        case 'word':
            return 'Word Document';
        case 'excel':
            return 'Spreadsheet';
        case 'powerpoint':
            return 'Presentation Slide';
        case 'archive':
            return 'Compressed Archive';
        default:
            return `${extension.value.toUpperCase() || 'Attached'} File`;
    }
});

const isPreviewableInline = computed(() => {
    return ['pdf', 'image', 'text'].includes(fileCategory.value);
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
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-3 backdrop-blur-md duration-200 animate-in fade-in sm:p-6 md:p-8"
        @click.self="emit('close')"
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
                            <span class="hidden rounded-md bg-secondary px-2 py-0.5 font-mono text-[10px] font-semibold text-muted-foreground sm:inline-block">
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
                    <a
                        :href="effectiveDownloadUrl"
                        download
                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl bg-primary px-3.5 text-xs font-bold text-primary-foreground shadow-sm transition-all hover:bg-primary/90 hover:shadow"
                        title="Download file to your device"
                    >
                        <Download class="size-3.5" />
                        <span class="hidden sm:inline">Download</span>
                    </a>

                    <a
                        :href="fileUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex size-9 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        title="Open in new tab"
                    >
                        <ExternalLink class="size-4" />
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

            <!-- Preview Body -->
            <div class="flex-1 overflow-auto bg-muted/20 p-3 sm:p-5">
                <!-- PDF Viewer -->
                <div v-if="fileCategory === 'pdf'" class="h-[68vh] w-full sm:h-[72vh]">
                    <iframe
                        :src="fileUrl"
                        class="size-full rounded-xl border border-border/80 bg-white shadow-inner"
                        title="PDF Viewer"
                    />
                </div>

                <!-- Image Viewer -->
                <div
                    v-else-if="fileCategory === 'image'"
                    class="flex min-h-[50vh] max-h-[72vh] items-center justify-center overflow-auto rounded-xl border border-border/60 bg-black/5 p-4 dark:bg-black/30"
                >
                    <img
                        :src="fileUrl"
                        :alt="fileName || 'Attached Image'"
                        class="max-h-[66vh] max-w-full rounded-lg object-contain shadow-md"
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
                        <FileType2 v-else class="size-10 text-primary" />
                    </div>

                    <h4 class="mt-4 text-lg font-bold text-foreground">
                        {{ fileName }}
                    </h4>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ categoryLabel }} &middot; Direct preview is not supported inside the browser for this file type.
                    </p>

                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <a
                            :href="effectiveDownloadUrl"
                            download
                            class="ink-button !h-10 !rounded-xl !px-6 text-xs font-bold"
                        >
                            <Download class="size-4" />
                            <span>Download {{ fileName }}</span>
                        </a>

                        <a
                            :href="fileUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-xs font-semibold text-foreground transition-colors hover:bg-secondary"
                        >
                            <ExternalLink class="size-3.5" />
                            <span>Open In Browser</span>
                        </a>
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
