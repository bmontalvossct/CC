<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary via-primary/90 to-blue-700 text-white shadow-sm shadow-primary/25">
        <AppLogoIcon class-name="size-5.5" />
    </div>
    <div class="ml-3 grid min-w-0 flex-1 text-left">
        <span class="truncate text-[15px] font-bold leading-tight tracking-tight text-foreground">ClassCheck</span>
        <span class="truncate text-[11px] font-medium text-muted-foreground">Classroom workspace</span>
    </div>
</template>
