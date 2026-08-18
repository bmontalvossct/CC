<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex items-center gap-2.5">
        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl border border-border/80 bg-card p-0.5 shadow-sm">
            <AppLogoIcon class-name="size-7" />
        </div>
        <div class="grid min-w-0 flex-1 text-left">
            <span class="truncate text-[15px] font-medium leading-tight tracking-tight text-foreground">ClassCheck</span>
            <span class="truncate text-[11px] font-medium text-muted-foreground">Classroom workspace</span>
        </div>
    </div>
</template>
