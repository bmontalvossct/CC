<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });
defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <svg :class="className" v-bind="$attrs" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M8 15.5 24 7l16 8.5-16 8.7L8 15.5Z" fill="currentColor" />
        <path d="M13 20v12.5c0 3.2 5.2 6.5 11 6.5s11-3.3 11-6.5V20l-11 6-11-6Z" fill="currentColor" opacity=".72" />
        <path d="M40 17v12" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        <path d="m20 31 3 3 6-7" stroke="white" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</template>
