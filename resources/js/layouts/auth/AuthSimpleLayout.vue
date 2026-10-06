<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import OctoMascot from '@/components/OctoMascot.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2 } from 'lucide-vue-next';

defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <div class="relative flex min-h-svh flex-col bg-background p-5 sm:p-10">
        <Link :href="route('home')" class="relative z-10 flex w-fit items-center gap-2.5">
            <AppLogoIcon class-name="size-10" /><span class="text-xl tracking-tight">ClassCheck<span class="text-primary">.</span></span>
        </Link>
        <div class="mx-auto grid w-full max-w-5xl flex-1 items-center gap-12 py-10 lg:grid-cols-2 lg:gap-20">
            <div class="page-enter relative hidden lg:block">
                <div class="surface-grid absolute -inset-8 rounded-3xl opacity-60" aria-hidden="true" />
                <div class="relative">
                    <span class="eyebrow">Room to do your best teaching</span>
                    <h2 class="mt-5 font-display text-5xl leading-[1.1] tracking-tight">
                        Your classroom.<br />All together.<br /><em class="text-primary">All in hand.</em>
                    </h2>
                    <p class="mt-5 max-w-sm text-base leading-relaxed text-muted-foreground">
                        A familiar place for your seats, students, and the little details that make a teaching day.
                    </p>
                    <div class="mt-9 flex items-center gap-4">
                        <div class="grid size-20 place-items-center rounded-2xl border border-border bg-card shadow-sm">
                            <OctoMascot size="2xl" :interactive="true" />
                        </div>
                        <div>
                            <p class="text-sm">A little help from Octo.</p>
                            <p class="mt-1 text-xs text-muted-foreground">More space to focus on your class.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="stagger-enter mx-auto w-full max-w-md">
                <div class="paper-card rounded-3xl p-7 sm:p-9">
                    <div class="mb-7">
                        <span class="mb-5 inline-flex items-center gap-1.5 text-xs text-primary"
                            ><CheckCircle2 class="size-3.5" /> Your teacher workspace</span
                        >
                        <h1 class="text-2xl tracking-tight sm:text-3xl">{{ title }}</h1>
                        <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ description }}</p>
                    </div>
                    <slot />
                </div>
                <Link
                    :href="route('home')"
                    class="mx-auto mt-6 flex w-fit items-center gap-2 rounded-md text-xs text-muted-foreground transition-colors hover:text-primary"
                    ><ArrowLeft class="size-3.5" /> Back to ClassCheck</Link
                >
            </div>
        </div>
        <p class="text-center text-xs text-muted-foreground">A focused workspace designed for the speed of teaching.</p>
    </div>
</template>
