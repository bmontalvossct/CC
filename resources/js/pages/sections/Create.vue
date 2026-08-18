<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import SectionForm from './SectionForm.vue';

defineProps<{
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
    <Head title="New section" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: 'New section', href: '/sections/create' },
        ]"
    >
        <main class="page-enter min-h-full bg-background text-foreground">
            <div class="mx-auto w-full max-w-4xl px-5 pb-16 pt-9 md:px-10 md:pt-12">
                <header class="border-b border-border pb-10">
                    <p class="text-[15px] font-medium text-primary">Classroom setup</p>
                    <h1 class="mt-2 font-display text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">Create a new section.</h1>
                    <p class="mt-3 max-w-2xl text-[17px] leading-7 text-muted-foreground">
                        Add the class details and weekly schedule. You can arrange the room and add students next.
                    </p>
                </header>
                <div class="mt-8">
                    <SectionForm :current-term="currentTerm" />
                </div>
            </div>
        </main>
    </AppLayout>
</template>
