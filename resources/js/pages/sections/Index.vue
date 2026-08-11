<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, ArrowUpRight, BookOpen, LayoutGrid, Plus, Users } from 'lucide-vue-next';

defineProps<{ sections: any[] }>();
const toggleArchive = (id: number) => router.patch(`/sections/${id}/archive`);
</script>

<template>
    <Head title="Sections" />
    <AppLayout :breadcrumbs="[{ title: 'Sections', href: '/sections' }]">
        <main class="min-h-full bg-[radial-gradient(circle_at_20%_0%,rgba(217,119,6,.09),transparent_26%)] p-5 py-10 md:p-10">
            <header class="mx-auto flex max-w-6xl flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p class="font-mono text-xs font-bold uppercase tracking-[.25em] text-amber-700">Teacher's register</p>
                    <h1 class="mt-2 font-serif text-5xl font-black tracking-tight text-stone-950">Your classrooms.</h1>
                    <p class="mt-3 text-stone-600">Every chair, name, and class record starts here.</p>
                </div>
                <Button as-child class="bg-stone-950 text-white hover:bg-amber-800"
                    ><Link href="/sections/create"><Plus class="mr-2 size-4" /> New section</Link></Button
                >
            </header>

            <section v-if="sections.length" class="mx-auto mt-10 grid max-w-6xl gap-5 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="section in sections"
                    :key="section.id"
                    class="group relative overflow-hidden rounded-2xl border border-stone-200 bg-[#fffdf7] p-6 shadow-[0_25px_70px_-55px_rgba(28,25,23,.9)] transition hover:-translate-y-1 hover:border-amber-400"
                    :class="{ 'opacity-65': section.archived_at }"
                >
                    <div class="absolute right-0 top-0 h-20 w-20 bg-[linear-gradient(135deg,transparent_50%,rgba(217,119,6,.12)_50%)]" />
                    <div class="flex items-center justify-between">
                        <span class="rounded-full bg-amber-100 px-3 py-1 font-mono text-xs font-bold uppercase tracking-wider text-amber-900">{{
                            section.subject_code
                        }}</span
                        ><span v-if="section.archived_at" class="font-mono text-[10px] uppercase tracking-wider text-stone-500">Archived</span>
                    </div>
                    <h2 class="mt-5 font-serif text-2xl font-bold text-stone-950">{{ section.name }}</h2>
                    <p class="mt-1 min-h-12 text-sm text-stone-600">{{ section.subject_title }}</p>
                    <div class="mt-5 grid grid-cols-2 gap-3 border-y border-dashed border-stone-300 py-4 text-sm">
                        <span class="flex items-center gap-2"><Users class="size-4 text-amber-700" /> {{ section.students_count }} students</span
                        ><span class="flex items-center gap-2"
                            ><LayoutGrid class="size-4 text-amber-700" /> {{ section.layout_blocks_count }} blocks</span
                        >
                    </div>
                    <p class="mt-4 text-xs text-stone-500">{{ section.academic_term.name }} - SY {{ section.academic_term.school_year }}</p>
                    <div class="mt-5 flex items-center justify-between">
                        <button
                            type="button"
                            class="rounded-md p-2 text-stone-400 hover:bg-stone-100 hover:text-stone-800"
                            @click="toggleArchive(section.id)"
                        >
                            <Archive class="size-4" /><span class="sr-only">Toggle archive</span></button
                        ><Link :href="`/sections/${section.id}`" class="inline-flex items-center gap-1 font-semibold text-amber-800"
                            >Open room <ArrowUpRight class="size-4 transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5"
                        /></Link>
                    </div>
                </article>
            </section>

            <section v-else class="mx-auto mt-12 max-w-3xl rounded-3xl border border-dashed border-stone-300 bg-[#fffdf7] p-12 text-center">
                <BookOpen class="mx-auto size-12 text-amber-700" />
                <h2 class="mt-5 font-serif text-3xl font-bold">The register is ready.</h2>
                <p class="mx-auto mt-2 max-w-md text-stone-600">
                    Create your first section, draw its seating blocks, then invite students with one QR code.
                </p>
                <Button as-child class="mt-6 bg-amber-700 text-white"><Link href="/sections/create">Create the first section</Link></Button>
            </section>
        </main>
    </AppLayout>
</template>
