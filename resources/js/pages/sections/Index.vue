<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, ArrowRight, BookOpen, LayoutGrid, Plus, Users } from 'lucide-vue-next';

defineProps<{ sections: any[] }>();
const toggleArchive = (id: number) => router.patch(`/sections/${id}/archive`);
</script>

<template>
    <Head title="Sections" />
    <AppLayout :breadcrumbs="[{ title: 'Sections', href: '/sections' }]">
        <main class="page-enter mx-auto w-full max-w-[1320px] px-5 pb-16 pt-9 md:px-10 md:pt-12">
            <header class="flex flex-col justify-between gap-6 border-b border-border pb-10 sm:flex-row sm:items-end">
                <div>
                    <p class="text-[15px] font-medium text-[#0071e3]">Classrooms</p>
                    <h1 class="mt-2 text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">Your sections.</h1>
                    <p class="mt-3 text-[17px] text-muted-foreground">Every class, student, and record starts here.</p>
                </div>
                <Button as-child
                    ><Link href="/sections/create"><Plus class="size-4" /> New section</Link></Button
                >
            </header>

            <section v-if="sections.length" class="mt-8 grid overflow-hidden rounded-lg border border-border md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="(section, index) in sections"
                    :key="section.id"
                    class="group flex min-h-72 flex-col bg-card p-6 transition-colors hover:bg-[#f5f5f7] dark:hover:bg-secondary"
                    :class="[
                        { 'opacity-60': section.archived_at },
                        index > 0 ? 'border-t border-border' : '',
                        index % 2 ? 'md:border-l' : '',
                        index > 1 ? 'md:border-t' : '',
                        index % 3 ? 'xl:border-l' : '',
                        index > 2 ? 'xl:border-t' : '',
                    ]"
                >
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-xs font-medium text-[#0071e3]">{{ section.subject_code }}</span>
                        <span v-if="section.archived_at" class="rounded-full bg-secondary px-3 py-1 text-[11px] text-muted-foreground">Archived</span>
                    </div>
                    <h2 class="mt-5 text-2xl font-semibold">{{ section.name }}</h2>
                    <p class="mt-1 line-clamp-2 text-sm leading-6 text-muted-foreground">{{ section.subject_title }}</p>
                    <div class="mt-7 grid grid-cols-2 gap-4 border-t border-border pt-5 text-sm">
                        <span class="flex items-center gap-2"><Users class="size-4 text-[#0071e3]" /> {{ section.students_count }} students</span>
                        <span class="flex items-center gap-2"
                            ><LayoutGrid class="size-4 text-[#0071e3]" /> {{ section.layout_blocks_count }} blocks</span
                        >
                    </div>
                    <p class="mt-4 text-xs text-muted-foreground">{{ section.academic_term.name }} · SY {{ section.academic_term.school_year }}</p>
                    <div class="mt-auto flex items-center justify-between pt-6">
                        <button
                            type="button"
                            class="rounded-full p-2 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            @click="toggleArchive(section.id)"
                        >
                            <Archive class="size-4" /><span class="sr-only">Toggle archive</span>
                        </button>
                        <Link :href="`/sections/${section.id}`" class="inline-flex items-center gap-1.5 text-sm font-medium text-[#0066cc]"
                            >Open section <ArrowRight class="size-4 transition-transform group-hover:translate-x-0.5"
                        /></Link>
                    </div>
                </article>
            </section>

            <section v-else class="mt-8 rounded-lg bg-[#f5f5f7] p-12 text-center dark:bg-secondary/50">
                <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-white text-[#0071e3] dark:bg-card"
                    ><BookOpen class="size-6"
                /></span>
                <h2 class="mt-6 text-3xl font-semibold">Create your first section.</h2>
                <p class="mx-auto mt-2 max-w-md text-[15px] leading-6 text-muted-foreground">
                    Set up the class, arrange its seats, then invite students with one QR code.
                </p>
                <Button as-child class="mt-7"><Link href="/sections/create">Create a section</Link></Button>
            </section>
        </main>
    </AppLayout>
</template>
