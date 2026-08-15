<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background p-5 sm:items-center sm:justify-center sm:p-10">
        <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center sm:flex-none">
            <Link :href="route('home')" class="mb-8 flex items-center gap-3 self-center">
                <span class="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary via-primary/90 to-blue-700 text-white shadow-sm shadow-primary/25">
                    <AppLogoIcon class-name="size-5.5" />
                </span>
                <span class="text-lg font-bold tracking-tight text-foreground">ClassCheck</span>
            </Link>
            <div class="paper-card rounded-2xl p-7 sm:p-9 shadow-lg">
                <div class="mb-7 text-center sm:text-left">
                    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ title }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">{{ description }}</p>
                </div>
                <slot />
            </div>
            <p class="mt-6 text-center text-xs text-muted-foreground font-medium">A focused workspace designed for the speed of teaching.</p>
        </div>
    </div>
</template>
