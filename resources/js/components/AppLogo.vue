<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex size-9 items-center justify-center rounded-xl bg-[#18352f] text-[#f4c65d] shadow-[0_6px_16px_rgba(24,53,47,.22)]">
        <AppLogoIcon class-name="size-7" />
    </div>
    <div class="ml-2 grid min-w-0 flex-1 text-left">
        <span class="font-display truncate text-[1.05rem] font-bold leading-none tracking-tight text-[#18352f] dark:text-[#f8f1df]">ClassCheck</span>
        <span class="mt-1 truncate text-[10px] font-semibold uppercase tracking-[.17em] text-[#a05a3b]">Teacher command desk</span>
    </div>
</template>
