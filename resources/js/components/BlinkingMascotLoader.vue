<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
        label?: string;
        overlay?: boolean;
        showLabel?: boolean;
        interval?: number;
    }>(),
    {
        size: 'md',
        label: 'Loading...',
        overlay: false,
        showLabel: false,
        interval: 800,
    },
);

const isBlinking = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;
let active = true;

const runBlinkCycle = () => {
    if (!active) return;

    // Open eyes state for 700ms - 900ms
    isBlinking.value = false;
    timer = setTimeout(() => {
        if (!active) return;
        // Blink eyes closed for 180ms
        isBlinking.value = true;
        timer = setTimeout(() => {
            if (!active) return;
            runBlinkCycle();
        }, 180);
    }, props.interval);
};

onMounted(() => {
    active = true;
    runBlinkCycle();
});

onUnmounted(() => {
    active = false;
    if (timer) clearTimeout(timer);
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'xs':
            return 'size-4';
        case 'sm':
            return 'size-5';
        case 'md':
            return 'size-8';
        case 'lg':
            return 'size-12';
        case 'xl':
            return 'size-20';
        default:
            return 'size-8';
    }
});
</script>

<template>
    <!-- Overlay Mode -->
    <div
        v-if="overlay"
        class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-xs"
        role="status"
        aria-live="polite"
    >
        <div class="flex flex-col items-center gap-3 rounded-2xl border border-border/80 bg-card p-7 text-center shadow-2xl">
            <div class="relative flex items-center justify-center">
                <!-- Blinking Mascot Frame -->
                <img
                    v-show="!isBlinking"
                    src="/images/logo-open.png"
                    alt="Loading..."
                    :class="['aspect-square object-contain transition-transform duration-150', sizeClasses]"
                />
                <img
                    v-show="isBlinking"
                    src="/images/logo-closed.png"
                    alt="Loading..."
                    :class="['aspect-square object-contain transition-transform duration-150', sizeClasses]"
                />
            </div>
            <span v-if="label" class="text-sm font-medium text-foreground tracking-tight">{{ label }}</span>
        </div>
    </div>

    <!-- Inline Mode -->
    <div
        v-else
        class="inline-flex items-center gap-2"
        role="status"
        aria-live="polite"
    >
        <div class="relative inline-flex items-center justify-center">
            <img
                v-show="!isBlinking"
                src="/images/logo-open.png"
                alt="Loading..."
                :class="['aspect-square object-contain', sizeClasses]"
            />
            <img
                v-show="isBlinking"
                src="/images/logo-closed.png"
                alt="Loading..."
                :class="['aspect-square object-contain', sizeClasses]"
            />
        </div>
        <span v-if="showLabel && label" class="text-sm font-medium text-muted-foreground">{{ label }}</span>
        <span v-else-if="label" class="sr-only">{{ label }}</span>
    </div>
</template>
