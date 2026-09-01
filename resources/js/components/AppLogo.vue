<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useActiveSection } from '@/composables/useActiveSection';

const { sectionName, courseName } = useActiveSection();
</script>

<template>
    <div class="flex items-center gap-2.5">
        <AppLogoIcon class-name="size-8 shrink-0 rounded-xl" />
        <div class="grid min-w-0 flex-1 text-left">
            <span class="truncate text-[15px] font-semibold leading-tight tracking-tight text-foreground" :title="sectionName">
                {{ sectionName }}
            </span>
            <span class="truncate text-[11px] font-medium text-muted-foreground" :title="courseName">
                {{ courseName }}
            </span>
        </div>
    </div>
</template>
