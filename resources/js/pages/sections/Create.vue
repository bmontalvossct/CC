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
        <main class="mx-auto w-full max-w-4xl p-5 py-10">
            <p class="font-mono text-xs uppercase tracking-[.25em] text-[#86868b]">Classroom setup</p>
            <h1 class="mt-2 font-serif text-4xl font-black tracking-tight text-[#1d1d1f]">Open a new class ledger.</h1>
            <p class="mb-8 mt-3 max-w-2xl text-[#6e6e73]">
                Name the class and establish its weekly rhythm. You will draw the room and place chairs next.
            </p>
            <SectionForm />
        </main>
    </AppLayout>
</template>
