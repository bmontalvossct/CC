<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl';
        variant?: 'default' | 'primary' | 'white' | 'muted';
        label?: string;
        overlay?: boolean;
        showLabel?: boolean;
        speed?: 'slow' | 'normal' | 'fast';
    }>(),
    {
        size: 'md',
        variant: 'default',
        label: 'Loading...',
        overlay: false,
        showLabel: false,
        speed: 'normal',
    },
);

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'xs':
            return 'size-3.5';
        case 'sm':
            return 'size-4';
        case 'md':
            return 'size-5';
        case 'lg':
            return 'size-8';
        case 'xl':
            return 'size-12';
        case '2xl':
            return 'size-16';
        default:
            return 'size-5';
    }
});

const speedClasses = computed(() => {
    switch (props.speed) {
        case 'slow':
            return 'duration-1000';
        case 'fast':
            return 'duration-500';
        case 'normal':
        default:
            return 'duration-700';
    }
});
</script>

<template>
    <!-- Full-screen / container Overlay Mode -->
    <div
        v-if="overlay"
        class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-background/80"
        role="status"
        aria-live="polite"
    >
        <div class="flex flex-col items-center gap-3.5 rounded-2xl border border-border/80 bg-card p-6 text-center shadow-xl">
            <div class="relative flex items-center justify-center">
                <img
                    src="/images/octo-spinner.png"
                    alt="Loading..."
                    :class="[
                        'aspect-square object-contain animate-spin select-none transition-all dark:invert',
                        sizeClasses,
                        speedClasses,
                    ]"
                />
            </div>
            <span v-if="label" class="text-sm font-semibold tracking-tight text-foreground">{{ label }}</span>
        </div>
    </div>

    <!-- Inline Spinner Mode -->
    <div v-else class="inline-flex items-center gap-2 select-none" role="status" aria-live="polite">
        <img
            src="/images/octo-spinner.png"
            alt="Loading..."
            :class="[
                'aspect-square shrink-0 object-contain animate-spin dark:invert',
                sizeClasses,
                speedClasses,
            ]"
        />
        <span v-if="showLabel && label" class="text-xs font-medium text-muted-foreground">{{ label }}</span>
        <span v-else-if="label" class="sr-only">{{ label }}</span>
    </div>
</template>
