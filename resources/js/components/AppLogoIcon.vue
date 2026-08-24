<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });
defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <img src="/images/logo.png" alt="ClassCheck Logo" :class="['aspect-square shrink-0 object-contain', className]" v-bind="$attrs" />
</template>
