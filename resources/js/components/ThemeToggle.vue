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
        class="grid size-9 place-items-center rounded-xl border border-border/80 bg-card text-muted-foreground transition-all hover:bg-secondary hover:text-foreground hover:border-primary/40 focus-visible:ring-2 focus-visible:ring-primary shadow-xs"
        :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        :aria-label="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        @click="toggle"
    >
        <Sun v-if="isDark" class="size-4 text-amber-400 transition-transform duration-200 rotate-0 hover:rotate-45" />
        <Moon v-else class="size-4 text-slate-700 dark:text-slate-300 transition-transform duration-200 hover:-rotate-12" />
    </button>
</template>
