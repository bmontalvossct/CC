<script setup lang="ts">
import OctoSpinner from '@/components/OctoSpinner.vue';

withDefaults(
    defineProps<{
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl';
        variant?: 'primary' | 'white' | 'muted' | 'emerald' | 'rose' | 'amber' | 'default';
        label?: string;
        overlay?: boolean;
        showLabel?: boolean;
    }>(),
    {
        size: 'md',
        variant: 'default',
        label: 'Loading...',
        overlay: false,
        showLabel: false,
    },
);
</script>

<template>
    <OctoSpinner
        :size="size"
        :label="label"
        :overlay="overlay"
        :show-label="showLabel"
    />
</template>
