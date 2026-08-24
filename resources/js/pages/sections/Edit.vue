<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import SectionForm from './SectionForm.vue';

defineProps<{
    section: any;
    currentTerm?: {
        id?: number;
        name: string;
        school_year: string;
        starts_on?: string;
        ends_on?: string;
        default_starts_at?: string;
        default_ends_at?: string;
    };
}>();
</script>

<template>
    <Head :title="`Edit ${section.name}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Edit', href: `/sections/${section.id}/edit` },
        ]"
    >
        <main class="page-enter min-h-full bg-background text-foreground">
            <div class="mx-auto w-full max-w-4xl px-5 pb-16 pt-9 md:px-10 md:pt-12">
                <header class="border-b border-border pb-10">
                    <p class="text-[15px] font-medium text-primary">Section details</p>
                    <h1 class="mt-2 font-display text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">Edit {{ section.name }}.</h1>
                    <p class="mt-3 max-w-2xl text-[17px] leading-7 text-muted-foreground">
                        Keep the class identity, academic term, and weekly schedule up to date.
                    </p>
                </header>
                <div class="mt-8">
                    <SectionForm :section="section" :current-term="currentTerm" />
                </div>
            </div>
        </main>
    </AppLayout>
</template>
