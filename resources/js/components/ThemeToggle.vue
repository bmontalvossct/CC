<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';

const { appearance, updateAppearance } = useAppearance();

const isDark = computed(() => {
    if (appearance.value === 'dark') return true;
    if (appearance.value === 'light') return false;
    return typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches;
});

const toggle = () => {
    updateAppearance(isDark.value ? 'light' : 'dark');
};
</script>

<template>
    <button
        type="button"
        class="shadow-xs grid size-9 place-items-center rounded-xl border border-border/80 bg-card text-muted-foreground transition-all hover:border-primary/40 hover:bg-secondary hover:text-foreground focus-visible:ring-2 focus-visible:ring-primary"
        :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        :aria-label="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        @click="toggle"
    >
        <Sun v-if="isDark" class="size-4 rotate-0 text-amber-400 transition-transform duration-200 hover:rotate-45" />
        <Moon v-else class="size-4 text-slate-700 transition-transform duration-200 hover:-rotate-12 dark:text-slate-300" />
    </button>
</template>
