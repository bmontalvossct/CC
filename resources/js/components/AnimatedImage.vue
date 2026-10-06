<script setup lang="ts">
import { ImageOff } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        src: string;
        alt: string;
        imageClass?: string;
    }>(),
    { imageClass: 'object-cover' },
);

const displayedSrc = ref('');
const isLoading = ref(true);
const hasError = ref(false);
const imageVersion = ref(0);
let request = 0;
let stopWatching: (() => void) | undefined;

onMounted(() => {
    stopWatching = watch(
        () => props.src,
        async (src) => {
            const current = ++request;
            isLoading.value = true;
            hasError.value = false;
            const image = new Image();
            image.decoding = 'async';
            image.src = src;
            try {
                if (!src) throw new Error('No image source');
                await image.decode();
                if (current !== request) return;
                displayedSrc.value = src;
                imageVersion.value++;
            } catch {
                if (current !== request) return;
                displayedSrc.value = '';
                hasError.value = true;
            } finally {
                if (current === request) isLoading.value = false;
            }
        },
        { immediate: true },
    );
});

onUnmounted(() => {
    request++;
    stopWatching?.();
});
</script>

<template>
    <span class="media-frame relative isolate block overflow-hidden" :aria-busy="isLoading">
        <span v-if="isLoading && !displayedSrc" class="media-placeholder absolute inset-0 bg-muted" aria-hidden="true" />
        <Transition name="image-crossfade" appear>
            <img
                v-if="displayedSrc"
                :key="imageVersion"
                :src="displayedSrc"
                :alt="alt"
                :class="imageClass"
                class="absolute inset-0 size-full"
                decoding="async"
                draggable="false"
            />
        </Transition>
        <span v-if="isLoading && displayedSrc" class="absolute inset-x-0 bottom-0 z-10 bg-card/90 px-3 py-2 text-center text-xs text-muted-foreground" role="status">Loading image?</span>
        <span
            v-if="hasError"
            class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-muted text-muted-foreground"
            role="img"
            :aria-label="`${alt}: image unavailable`"
        >
            <slot name="fallback"><ImageOff class="size-6" aria-hidden="true" /><span class="text-xs">Image unavailable</span></slot>
        </span>
    </span>
</template>
