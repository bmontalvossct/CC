<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex size-8 items-center justify-center rounded-md bg-[#0071e3] text-white">
        <AppLogoIcon class-name="size-6" />
    </div>
    <div class="ml-2.5 grid min-w-0 flex-1 text-left">
        <span class="truncate text-[15px] font-semibold leading-none tracking-[-0.01em] text-[#1d1d1f] dark:text-white">ClassCheck</span>
        <span class="mt-1 truncate text-[11px] text-[#86868b]">Classroom workspace</span>
    </div>
</template>
