<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/vue3';
import {
    Camera,
    CheckCircle2,
    FileArchive,
    FolderArchive,
    Info,
    Upload,
    UploadCloud,
    X,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    sectionId: number;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const form = useForm({
    photos_zip: null as File | null,
});

const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const handleFileSelect = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.photos_zip = target.files[0];
    }
};

const handleDrop = (e: DragEvent) => {
    isDragging.value = false;
    if (e.dataTransfer?.files && e.dataTransfer.files[0]) {
        const file = e.dataTransfer.files[0];
        if (file.name.endsWith('.zip') || file.type === 'application/zip' || file.type === 'application/x-zip-compressed') {
            form.photos_zip = file;
        }
    }
};

const submitPhotos = () => {
    if (!form.photos_zip || form.processing) return;

    form.post(`/sections/${props.sectionId}/photos-import`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            form.reset();
            form.clearErrors();
        }
    },
);
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 sm:p-6 backdrop-blur-xs duration-200 animate-in fade-in print:hidden"
    >
        <div
            class="paper-card relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden border border-border/80 bg-card p-6 shadow-2xl duration-200 animate-in zoom-in-95"
            role="dialog"
            aria-modal="true"
            aria-label="Bulk Student Photos ZIP Importer"
        >
            <!-- Header Bar -->
            <div class="flex items-center justify-between border-b border-border/70 pb-4">
                <div class="flex items-center gap-2.5">
                    <span class="grid size-9 place-items-center rounded-xl bg-primary/10 text-primary">
                        <Camera class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold tracking-tight text-foreground">Bulk Student Photos ZIP</h2>
                        <p class="text-xs text-muted-foreground">
                            Upload a ZIP archive to match photos across the class roster
                        </p>
                    </div>
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8 rounded-lg text-muted-foreground hover:text-foreground"
                    title="Close"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </Button>
            </div>

            <!-- Upload Area & Instructions -->
            <form class="mt-4 space-y-4" @submit.prevent="submitPhotos">
                <!-- Dropzone -->
                <div
                    class="relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed p-6 text-center transition-all cursor-pointer"
                    :class="[
                        isDragging ? 'border-primary bg-primary/10' : form.photos_zip ? 'border-emerald-500/50 bg-emerald-500/5' : 'border-border hover:border-primary/50 hover:bg-secondary/20',
                    ]"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="handleDrop"
                    @click="fileInput?.click()"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".zip,application/zip,application/x-zip-compressed"
                        class="hidden"
                        @change="handleFileSelect"
                    />

                    <div v-if="form.photos_zip" class="flex flex-col items-center gap-2">
                        <span class="grid size-12 place-items-center rounded-2xl bg-emerald-500/20 text-emerald-500">
                            <FileArchive class="size-6" />
                        </span>
                        <div>
                            <div class="font-bold text-sm text-foreground">{{ form.photos_zip.name }}</div>
                            <div class="text-xs text-muted-foreground">{{ (form.photos_zip.size / (1024 * 1024)).toFixed(2) }} MB · Ready to upload</div>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center gap-2">
                        <span class="grid size-12 place-items-center rounded-2xl bg-secondary text-muted-foreground">
                            <UploadCloud class="size-6" />
                        </span>
                        <div>
                            <div class="font-semibold text-sm text-foreground">Click or drop photos.zip here</div>
                            <div class="text-xs text-muted-foreground">Supports .zip archives up to 50MB</div>
                        </div>
                    </div>
                </div>

                <InputError :message="form.errors.photos_zip" />

                <!-- Guidelines Notice Card -->
                <div class="rounded-xl border border-border/70 bg-secondary/30 p-3.5 text-xs text-muted-foreground space-y-2">
                    <div class="flex items-center gap-1.5 font-semibold text-foreground">
                        <Info class="size-3.5 text-primary" />
                        <span>How filenames are matched:</span>
                    </div>
                    <ul class="list-disc pl-4 space-y-1 text-[11px] leading-relaxed">
                        <li>
                            <strong class="text-foreground">By Student ID (Recommended):</strong> E.g. <code class="rounded bg-secondary px-1 py-0.5 font-mono">2023-0101.jpg</code> or <code class="rounded bg-secondary px-1 py-0.5 font-mono">20230101.png</code>.
                        </li>
                        <li>
                            <strong class="text-foreground">By Student Name:</strong> E.g. <code class="rounded bg-secondary px-1 py-0.5 font-mono">dela_cruz_juan.jpg</code> or <code class="rounded bg-secondary px-1 py-0.5 font-mono">cruz.jpg</code>.
                        </li>
                        <li>
                            <strong class="text-foreground">Supported Formats:</strong> JPG, JPEG, PNG, and WEBP.
                        </li>
                    </ul>
                </div>

                <!-- Footer Buttons -->
                <div class="flex items-center justify-end gap-2 border-t border-border/70 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-9 rounded-xl px-4 text-xs font-medium"
                        @click="emit('close')"
                    >
                        Cancel
                    </Button>

                    <Button
                        type="submit"
                        class="ink-button !h-9 !px-4 text-xs font-semibold"
                        :disabled="!form.photos_zip || form.processing"
                    >
                        <LoadingSpinner v-if="form.processing" size="sm" />
                        <Upload v-else class="mr-1.5 size-3.5" />
                        <span>{{ form.processing ? 'Extracting & Matching Photos...' : 'Upload & Match Photos' }}</span>
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
