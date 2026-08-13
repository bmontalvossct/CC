<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarCheck2, ClipboardCheck, GraduationCap, Plus, Users } from 'lucide-vue-next';

interface SectionCard {
    id: number;
    name: string;
    subject: string;
    term: string;
    students: number;
    seats: number;
    attendance_rate: number | null;
}
interface Stats {
    sections: number;
    students: number;
    meetings: number;
    attendance_rate: number | null;
}

withDefaults(defineProps<{ stats?: Stats; sections?: SectionCard[]; teacherName?: string }>(), {
    stats: () => ({ sections: 0, students: 0, meetings: 0, attendance_rate: null }),
    sections: () => [],
    teacherName: 'Teacher',
});

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Overview', href: '/dashboard' }];
</script>

<template>
    <Head title="Overview" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="page-enter mx-auto w-full max-w-[1320px] px-5 pb-16 pt-9 md:px-10 md:pt-12">
            <section class="flex flex-col justify-between gap-7 border-b border-border pb-10 md:flex-row md:items-end">
                <div>
                    <p class="text-[15px] font-medium text-[#0071e3]">Overview</p>
                    <h1 class="mt-2 text-4xl font-semibold leading-tight tracking-[-0.03em] sm:text-5xl">
                        Good day, {{ teacherName.split(' ')[0] }}.
                    </h1>
                    <p class="mt-3 max-w-2xl text-[17px] leading-7 text-muted-foreground">
                        Everything you need for today’s classes, together in one place.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link href="/sections/create" class="ink-button"><Plus class="size-4" /> New section</Link>
                    <Link href="/sections" class="secondary-button">View all sections</Link>
                </div>
            </section>

            <section class="mt-8 grid gap-px overflow-hidden rounded-lg border border-border bg-border sm:grid-cols-2 xl:grid-cols-4">
                <article class="bg-card p-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">Active sections</p>
                        <GraduationCap class="size-5 text-[#0071e3]" />
                    </div>
                    <p class="mt-8 text-4xl font-semibold tracking-tight">{{ stats.sections }}</p>
                </article>
                <article class="bg-card p-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">Students seated</p>
                        <Users class="size-5 text-[#0071e3]" />
                    </div>
                    <p class="mt-8 text-4xl font-semibold tracking-tight">{{ stats.students }}</p>
                </article>
                <article class="bg-card p-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">Meetings logged</p>
                        <CalendarCheck2 class="size-5 text-[#0071e3]" />
                    </div>
                    <p class="mt-8 text-4xl font-semibold tracking-tight">{{ stats.meetings }}</p>
                </article>
                <article class="bg-card p-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">Attendance rate</p>
                        <ClipboardCheck class="size-5 text-[#0071e3]" />
                    </div>
                    <p class="mt-8 text-4xl font-semibold tracking-tight">{{ stats.attendance_rate === null ? '—' : stats.attendance_rate + '%' }}</p>
                </article>
            </section>

            <section class="mt-12">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-[#0071e3]">Classrooms</p>
                        <h2 class="mt-1 text-3xl font-semibold tracking-[-0.02em]">Your active sections</h2>
                    </div>
                    <Link href="/sections" class="hidden items-center gap-1.5 text-sm font-medium text-[#0066cc] sm:inline-flex"
                        >View all <ArrowRight class="size-4"
                    /></Link>
                </div>

                <div v-if="sections.length" class="mt-6 grid overflow-hidden rounded-lg border border-border md:grid-cols-2">
                    <Link
                        v-for="(section, index) in sections"
                        :key="section.id"
                        :href="`/sections/${section.id}`"
                        class="group bg-card p-6 transition-colors hover:bg-[#f5f5f7] dark:hover:bg-secondary"
                        :class="{ 'border-t border-border md:border-t-0': index > 0, 'md:border-l': index % 2 === 1, 'md:!border-t': index > 1 }"
                    >
                        <div class="flex items-start justify-between gap-5">
                            <div>
                                <p class="text-xs font-medium text-[#0071e3]">{{ section.subject }}</p>
                                <h3 class="mt-2 text-2xl font-semibold">{{ section.name }}</h3>
                                <p class="mt-1 text-sm text-muted-foreground">{{ section.term }}</p>
                            </div>
                            <span
                                class="flex size-9 items-center justify-center rounded-full border border-border text-[#0066cc] transition-colors group-hover:border-[#0071e3] group-hover:bg-[#0071e3] group-hover:text-white"
                                ><ArrowRight class="size-4"
                            /></span>
                        </div>
                        <dl class="mt-8 grid grid-cols-3 gap-4 border-t border-border pt-5">
                            <div>
                                <dt class="text-xs text-muted-foreground">Students</dt>
                                <dd class="mt-1 text-sm font-semibold">{{ section.students }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Seats</dt>
                                <dd class="mt-1 text-sm font-semibold">{{ section.seats }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Attendance</dt>
                                <dd class="mt-1 text-sm font-semibold">
                                    {{ section.attendance_rate === null ? '—' : section.attendance_rate + '%' }}
                                </dd>
                            </div>
                        </dl>
                    </Link>
                </div>

                <div v-else class="mt-6 rounded-lg bg-[#f5f5f7] px-6 py-16 text-center dark:bg-secondary/50">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-white text-[#0071e3] dark:bg-card"
                        ><GraduationCap class="size-6"
                    /></span>
                    <h3 class="mt-6 text-2xl font-semibold">Create your first classroom.</h3>
                    <p class="mx-auto mt-2 max-w-md text-[15px] leading-6 text-muted-foreground">
                        Add a section, arrange its seating plan, and invite students with one QR code.
                    </p>
                    <Link href="/sections/create" class="ink-button mt-7"><Plus class="size-4" /> Create a section</Link>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
