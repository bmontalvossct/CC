<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <div class="flex min-h-svh flex-col bg-[#f5f5f7] p-5 dark:bg-background sm:items-center sm:justify-center sm:p-10">
        <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center sm:flex-none">
            <Link :href="route('home')" class="mb-10 flex items-center gap-2.5 self-start">
                <span class="flex size-8 items-center justify-center rounded-md bg-[#0071e3] text-white"><AppLogoIcon class-name="size-6" /></span>
                <span class="text-[15px] font-semibold">ClassCheck</span>
            </Link>
            <div class="rounded-lg border border-border bg-card p-6 sm:p-8">
                <div class="mb-8">
                    <h1 class="text-3xl font-semibold tracking-[-0.025em]">{{ title }}</h1>
                    <p class="mt-2 text-[15px] leading-6 text-muted-foreground">{{ description }}</p>
                </div>
                <slot />
            </div>
            <p class="mt-6 text-center text-xs text-muted-foreground">A calmer way to manage every classroom.</p>
        </div>
    </div>
</template>
