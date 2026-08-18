<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background p-5 sm:items-center sm:justify-center sm:p-10">
        <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center sm:flex-none">
            <Link :href="route('home')" class="mb-8 flex items-center gap-2.5 self-center">
                <span class="flex size-10 items-center justify-center rounded-xl border border-border/80 bg-card p-0.5 shadow-sm">
                    <AppLogoIcon class-name="size-8" />
                </span>
                <span class="text-lg font-medium tracking-tight text-foreground">ClassCheck</span>
            </Link>
            <div class="paper-card rounded-2xl p-7 shadow-lg sm:p-9">
                <div class="mb-7 text-center sm:text-left">
                    <h1 class="text-2xl font-medium tracking-tight sm:text-3xl">{{ title }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">{{ description }}</p>
                </div>
                <slot />
            </div>
            <p class="mt-6 text-center text-xs font-medium text-muted-foreground">A focused workspace designed for the speed of teaching.</p>
        </div>
    </div>
</template>
