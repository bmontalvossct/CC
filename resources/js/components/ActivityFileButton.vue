<script setup lang="ts">
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import { router } from '@inertiajs/vue3';
import { FileText, LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    url: string;
    fileName?: string | null;
    attached: boolean;
    activityTitle: string;
    reloadProp: string;
}>();
const input = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const preview = ref(false);
const error = ref('');

const upload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;
    error.value = '';
    if (file.size > 50 * 1024 * 1024) {
        error.value = 'Choose an instructions file smaller than 50MB.';
        target.value = '';
        return;
    }
    uploading.value = true;
    try {
        const body = new FormData();
        body.append('attachment', file);
        const response = await fetch(props.url, {
            method: 'POST',
            body,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.errors?.attachment?.[0] || data.message || 'Upload failed. Please retry.');
        router.reload({
            only: [props.reloadProp],
            onSuccess: () => {
                preview.value = true;
            },
        });
    } catch (cause) {
        error.value = cause instanceof Error ? cause.message : 'Upload failed. Please retry.';
    } finally {
        uploading.value = false;
        target.value = '';
    }
};
</script>

<template>
    <div class="flex flex-col gap-1">
        <input ref="input" type="file" class="hidden" accept=".pdf,.docx,.txt,.md,.csv" aria-label="Upload activity instructions" @change="upload" />
        <button
            type="button"
            class="inline-flex h-10 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-primary/40 bg-primary/10 px-4 text-sm font-semibold text-primary transition-colors hover:bg-primary hover:text-primary-foreground disabled:opacity-50"
            :disabled="uploading"
            :title="attached ? 'View activity instructions: ' + fileName : 'Upload activity instructions (PDF, DOCX, TXT, Markdown, CSV; up to 50MB)'"
            @click="attached ? (preview = true) : input?.click()"
        >
            <LoaderCircle v-if="uploading" class="size-4 animate-spin" />
            <FileText v-else class="size-4" />
            {{ uploading ? 'Uploading...' : 'Activity File' }}
        </button>
        <p v-if="error" role="alert" class="max-w-72 whitespace-normal text-xs text-rose-600">{{ error }}</p>
        <FilePreviewModal
            v-if="attached && preview"
            :show="preview"
            :title="`Activity instructions: ${activityTitle}`"
            :file-name="fileName || 'Activity instructions'"
            :file-url="url"
            :download-url="`${url}?download=1`"
            :reupload-url="url"
            @close="preview = false"
            @reuploaded="preview = false"
        />
    </div>
</template>
