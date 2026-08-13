<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import SectionForm from './SectionForm.vue';
</script>

<template>
    <Head title="New section" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: 'New section', href: '/sections/create' },
        ]"
    >
        <main class="min-h-full bg-[#f3efe5] text-[#17231f] dark:bg-[#0d1512] dark:text-[#f8f3e8]">
            <div class="mx-auto w-full max-w-4xl px-5 py-10 md:py-14">
                <p class="font-mono text-xs font-bold uppercase tracking-[.25em] text-[#a9472d] dark:text-[#f08a68]">Classroom setup</p>
                <h1 class="mt-2 font-serif text-4xl font-black tracking-tight text-[#17231f] dark:text-[#fffaf0]">Open a new class ledger.</h1>
                <p class="mb-8 mt-3 max-w-2xl leading-7 text-[#56665f] dark:text-[#a9b7b1]">
                    Name the class and establish its weekly rhythm. You will draw the room and place chairs next.
                </p>
                <SectionForm />
            </div>
        </main>
    </AppLayout>
</template>
