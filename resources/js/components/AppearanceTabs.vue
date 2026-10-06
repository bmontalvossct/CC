<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { tabIndicatorTransition } from '@/lib/motion';
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { motion } from 'motion-v';

interface Props {
    class?: string;
}

const { class: containerClass = '' } = defineProps<Props>();

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div :class="['inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800', containerClass]">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            @click="updateAppearance(value)"
            :class="[
                'relative flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                appearance === value
                    ? 'text-neutral-900 dark:text-neutral-100'
                    : 'text-neutral-500 hover:text-black dark:text-neutral-400 dark:hover:text-white',
            ]"
        >
            <motion.div
                v-if="appearance === value"
                layout-id="appearance-active-tab"
                class="absolute inset-0 rounded-md bg-white shadow-xs dark:bg-neutral-700"
                :transition="tabIndicatorTransition"
            />
            <span class="relative z-10 flex items-center">
                <component :is="Icon" class="-ml-1 h-4 w-4" />
                <span class="ml-1.5 text-sm font-medium">{{ label }}</span>
            </span>
        </button>
    </div>
</template>
