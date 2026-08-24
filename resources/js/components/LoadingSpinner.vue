<script setup lang="ts">
import BlinkingMascotLoader from '@/components/BlinkingMascotLoader.vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
        variant?: 'primary' | 'white' | 'muted' | 'emerald' | 'rose' | 'amber';
        label?: string;
        overlay?: boolean;
        showLabel?: boolean;
        useMascot?: boolean;
    }>(),
    {
        size: 'md',
        variant: 'primary',
        label: 'Loading...',
        overlay: false,
        showLabel: false,
        useMascot: true,
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
            return 'size-7';
        case 'xl':
            return 'size-10';
        default:
            return 'size-5';
    }
});

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'white':
            return 'text-white';
        case 'muted':
            return 'text-muted-foreground';
        case 'emerald':
            return 'text-emerald-600 dark:text-emerald-400';
        case 'rose':
            return 'text-rose-600 dark:text-rose-400';
        case 'amber':
            return 'text-amber-600 dark:text-amber-400';
        case 'primary':
        default:
            return 'text-primary';
    }
});
</script>

<template>
    <BlinkingMascotLoader v-if="useMascot" :size="size" :label="label" :overlay="overlay" :show-label="showLabel" />

    <div
        v-else-if="overlay"
        class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-background/70"
        role="status"
        aria-live="polite"
    >
        <div class="flex flex-col items-center gap-3 rounded-2xl border border-border/80 bg-card p-6 shadow-xl">
            <svg
                :class="[sizeClasses, variantClasses, 'animate-spin']"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
            </svg>
            <span v-if="label" class="text-sm font-medium text-foreground">{{ label }}</span>
        </div>
    </div>

    <div v-else class="inline-flex items-center gap-2" role="status" aria-live="polite">
        <svg
            :class="[sizeClasses, variantClasses, 'animate-spin']"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
        </svg>
        <span v-if="showLabel && label" class="text-sm font-medium text-muted-foreground">{{ label }}</span>
        <span v-else-if="label" class="sr-only">{{ label }}</span>
    </div>
</template>
