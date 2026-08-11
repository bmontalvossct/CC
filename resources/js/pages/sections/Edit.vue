<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import SectionForm from './SectionForm.vue';
defineProps<{ section: any }>();
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
        <main class="mx-auto w-full max-w-4xl p-5 py-10">
            <h1 class="mb-8 font-serif text-4xl font-black">Edit the class ledger.</h1>
            <SectionForm :section="section" />
        </main>
    </AppLayout>
</template>
