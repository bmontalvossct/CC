<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';

const message = ref('');
const removeStartListener = router.on('start', ({ detail: { visit } }) => {
    if (visit.async || visit.prefetch || !visit.showProgress) return;
    message.value = visit.method === 'get' ? 'Loading page…' : 'Saving changes…';
});
const removeFinishListener = router.on('finish', ({ detail: { visit } }) => {
    if (!visit.async && !visit.prefetch) message.value = '';
});
const removeExceptionListener = router.on('exception', () => {
    message.value = '';
});

onUnmounted(() => {
    removeStartListener();
    removeFinishListener();
    removeExceptionListener();
});
</script>

<template>
    <div
        v-if="message"
        role="status"
        aria-live="polite"
        aria-atomic="true"
        class="pointer-events-none fixed left-1/2 top-4 z-[100] flex -translate-x-1/2 items-center gap-2 rounded-full border border-border bg-background px-4 py-2 text-sm font-semibold text-foreground shadow-lg"
    >
        <LoaderCircle class="size-4 animate-spin text-primary motion-reduce:animate-none" aria-hidden="true" />
        {{ message }}
    </div>
</template>
