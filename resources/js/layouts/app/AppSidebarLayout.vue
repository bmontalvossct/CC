<script setup lang="ts">
import AiAssistantDrawer from '@/components/ai/AiAssistantDrawer.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ClassroomTimer from '@/components/tools/ClassroomTimer.vue';
import { useClassroomTimer } from '@/composables/useClassroomTimer';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { isTimerOpen } = useClassroomTimer();
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <ClassroomTimer v-model="isTimerOpen" />
        <AiAssistantDrawer />
    </AppShell>
</template>
