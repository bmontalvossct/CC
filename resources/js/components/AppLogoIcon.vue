<script setup lang="ts">
import { useDocumentVisibility, usePreferredReducedMotion } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

defineOptions({ inheritAttrs: false });
defineProps<{ className?: HTMLAttributes['class'] }>();

const isBlinking = ref(false);
const closedReady = ref(false);
const mounted = ref(false);
const motion = usePreferredReducedMotion();
const visibility = useDocumentVisibility();
const canAnimate = computed(() => mounted.value && motion.value !== 'reduce' && visibility.value === 'visible');
let blinkTimer: ReturnType<typeof setTimeout> | undefined;
let intervalTimer: ReturnType<typeof setInterval> | undefined;

function triggerBlink() {
    if (!canAnimate.value || !closedReady.value) return;
    isBlinking.value = true;
    clearTimeout(blinkTimer);
    blinkTimer = setTimeout(() => {
        isBlinking.value = false;
    }, 160);
}
function clearTimers() {
    clearTimeout(blinkTimer);
    clearInterval(intervalTimer);
    isBlinking.value = false;
}
watch(canAnimate, (active) => {
    clearTimers();
    if (active) intervalTimer = setInterval(triggerBlink, 7200);
});
onMounted(() => {
    mounted.value = true;
});
onUnmounted(clearTimers);
</script>

<template>
    <span
        :class="['logo-frame relative inline-grid aspect-square shrink-0 select-none', className]"
        v-bind="$attrs"
        role="img"
        aria-label="ClassCheck Logo"
        @mouseenter="triggerBlink"
    >
        <img
            src="/images/logo.png"
            alt=""
            aria-hidden="true"
            width="1024"
            height="1024"
            class="logo-state col-start-1 row-start-1 size-full object-contain"
            :class="{ 'opacity-0': isBlinking }"
            draggable="false"
        />
        <img
            src="/images/logo-closed.png"
            alt=""
            aria-hidden="true"
            width="1024"
            height="1024"
            class="logo-state col-start-1 row-start-1 size-full object-contain"
            :class="isBlinking ? 'opacity-100' : 'opacity-0'"
            decoding="async"
            draggable="false"
            @load="closedReady = true"
        />
    </span>
</template>

<style scoped>
.logo-state {
    transition: opacity 60ms ease;
}
.logo-frame {
    transition: transform 260ms var(--ease-out);
}
@media (hover: hover) and (prefers-reduced-motion: no-preference) {
    .logo-frame:hover {
        transform: rotate(-4deg) scale(1.06);
    }
    .logo-frame:active {
        transform: scale(0.96);
    }
}
</style>
