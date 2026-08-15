<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    ArrowRight,
    BookOpen,
    GraduationCap,
    LayoutGrid,
    Plus,
    School,
    Users,
} from 'lucide-vue-next';

defineProps<{ sections: any[] }>();
const toggleArchive = (id: number) => router.patch(`/sections/${id}/archive`);
</script>

<template>
    <Head title="Sections - ClassCheck" />
    <AppLayout :breadcrumbs="[{ title: 'Sections', href: '/sections' }]">
        <main class="page-enter mx-auto w-full max-w-[1360px] px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <header class="flex flex-col justify-between gap-6 border-b border-border/80 pb-8 sm:flex-row sm:items-end">
                <div>
                    <span class="eyebrow">Classrooms & Courses</span>
                    <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Your sections</h1>
                    <p class="mt-1.5 text-sm sm:text-base text-muted-foreground">
                        Manage classroom layouts, rosters, and academic records across your subjects.
                    </p>
                </div>
                <Button as-child class="ink-button !h-10 !rounded-xl">
                    <Link href="/sections/create" prefetch="hover">
                        <Plus class="size-4" />
                        <span>New section</span>
                    </Link>
                </Button>
            </header>

            <section v-if="sections.length" class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="section in sections"
                    :key="section.id"
                    class="paper-card group flex min-h-[19rem] flex-col justify-between hover:border-primary/50 hover:shadow-lg transition-all"
                    :class="{ 'opacity-75 bg-secondary/30': section.archived_at }"
                >
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="inline-flex items-center rounded-md bg-primary/10 px-2.5 py-0.5 font-mono text-xs font-bold text-primary">
                                {{ section.subject_code }}
                            </span>
                            <span
                                v-if="section.archived_at"
                                class="rounded-full bg-secondary px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground border border-border"
                            >
                                Archived
                            </span>
                        </div>
                        <h2 class="mt-4 text-xl font-bold tracking-tight group-hover:text-primary transition-colors">
                            {{ section.name }}
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground line-clamp-2 leading-relaxed">
                            {{ section.subject_title }}
                        </p>
                    </div>

                    <div>
                        <div class="mt-6 grid grid-cols-2 gap-3 border-t border-border/80 pt-4 text-xs">
                            <span class="flex items-center gap-2 font-medium text-foreground/90">
                                <Users class="size-4 text-primary" /> {{ section.students_count }} students
                            </span>
                            <span class="flex items-center gap-2 font-medium text-foreground/90">
                                <LayoutGrid class="size-4 text-primary" /> {{ section.layout_blocks_count }} blocks
                            </span>
                        </div>
                        <p class="mt-2.5 text-[11px] text-muted-foreground">
                            {{ section.academic_term.name }} · SY {{ section.academic_term.school_year }}
                        </p>

                        <div class="mt-4 flex items-center justify-between border-t border-border/60 pt-3">
                            <button
                                type="button"
                                class="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                :title="section.archived_at ? 'Restore section' : 'Archive section'"
                                @click="toggleArchive(section.id)"
                            >
                                <Archive class="size-4" />
                                <span class="sr-only">Toggle archive</span>
                            </button>
                            <Link
                                :href="`/sections/${section.id}`"
                                prefetch="hover"
                                class="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:underline"
                            >
                                <span>Open classroom</span>
                                <ArrowRight class="size-3.5 transition-transform group-hover:translate-x-0.5" />
                            </Link>
                        </div>
                    </div>
                </article>
            </section>

            <section v-else class="mt-8 rounded-2xl border border-dashed border-border/80 bg-card p-12 text-center shadow-sm">
                <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-primary/10 text-primary">
                    <BookOpen class="size-7" />
                </span>
                <h2 class="mt-5 text-2xl font-bold">Create your first section</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                    Set up your class, customize the room grid, and invite students to sit with a QR code.
                </p>
                <Button as-child class="ink-button mt-6">
                    <Link href="/sections/create" prefetch="hover">
                        <Plus class="size-4" />
                        <span>Create section</span>
                    </Link>
                </Button>
            </section>
        </main>
    </AppLayout>
</template>
