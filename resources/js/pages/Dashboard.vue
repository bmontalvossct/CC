<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarCheck2, ClipboardCheck, GraduationCap, Plus, ScanLine, Users } from 'lucide-vue-next';

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
        <main class="mx-auto w-full max-w-[1500px] space-y-6 p-4 md:p-7">
            <section
                class="relative overflow-hidden rounded-[1.6rem] bg-[#18352f] px-6 py-8 text-[#fff8e8] shadow-[0_24px_60px_rgba(24,53,47,.2)] md:px-10 md:py-10"
            >
                <div class="absolute -right-12 -top-20 size-64 rounded-full border-[34px] border-[#f4c65d]/10"></div>
                <div class="absolute bottom-0 right-[18%] h-24 w-40 skew-x-[-18deg] bg-[#b85d3d]/15"></div>
                <div class="relative flex flex-col justify-between gap-7 lg:flex-row lg:items-end">
                    <div class="max-w-2xl">
                        <p class="mb-3 text-xs font-bold uppercase tracking-[.23em] text-[#f4c65d]">Monday classroom briefing</p>
                        <h1 class="font-display text-4xl font-bold leading-[1.02] md:text-6xl">Good day, {{ teacherName.split(' ')[0] }}.</h1>
                        <p class="mt-4 max-w-xl text-base leading-relaxed text-[#dbe5df]">
                            Your sections, seats, attendance, and scores are arranged for the next roll call.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <Link
                            href="/sections/create"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#f4c65d] px-4 py-3 font-bold text-[#18352f] transition hover:-translate-y-0.5"
                            ><Plus class="size-4" /> New section</Link
                        >
                        <Link
                            href="/sections"
                            class="inline-flex items-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-3 font-bold backdrop-blur transition hover:bg-white/15"
                            ><ScanLine class="size-4" /> Open classroom</Link
                        >
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="paper-card p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="eyebrow">Active sections</p>
                            <p class="font-display mt-3 text-4xl font-bold">{{ stats.sections }}</p>
                        </div>
                        <span class="rounded-xl bg-[#dcebe5] p-3 text-[#245246]"><GraduationCap class="size-5" /></span>
                    </div>
                </article>
                <article class="paper-card p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="eyebrow">Students seated</p>
                            <p class="font-display mt-3 text-4xl font-bold">{{ stats.students }}</p>
                        </div>
                        <span class="rounded-xl bg-[#f3e5c3] p-3 text-[#8a5a20]"><Users class="size-5" /></span>
                    </div>
                </article>
                <article class="paper-card p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="eyebrow">Meetings logged</p>
                            <p class="font-display mt-3 text-4xl font-bold">{{ stats.meetings }}</p>
                        </div>
                        <span class="rounded-xl bg-[#eadfd7] p-3 text-[#9b4e35]"><CalendarCheck2 class="size-5" /></span>
                    </div>
                </article>
                <article class="paper-card p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="eyebrow">Attendance rate</p>
                            <p class="font-display mt-3 text-4xl font-bold">
                                {{ stats.attendance_rate === null ? '—' : stats.attendance_rate + '%' }}
                            </p>
                        </div>
                        <span class="rounded-xl bg-[#dcebe5] p-3 text-[#245246]"><ClipboardCheck class="size-5" /></span>
                    </div>
                </article>
            </section>

            <section class="paper-card overflow-hidden">
                <div class="flex flex-col gap-3 border-b border-[#ded5c1] px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="eyebrow">Classroom roster</p>
                        <h2 class="font-display mt-1 text-2xl font-bold">Your active sections</h2>
                    </div>
                    <Link href="/sections" class="inline-flex items-center gap-2 text-sm font-bold text-[#245246]"
                        >View all <ArrowRight class="size-4"
                    /></Link>
                </div>
                <div v-if="sections.length" class="grid gap-px bg-[#ded5c1] lg:grid-cols-2">
                    <Link
                        v-for="section in sections"
                        :key="section.id"
                        :href="`/sections/${section.id}`"
                        class="group bg-[#fffdf7] p-6 transition hover:bg-[#fff8e8] dark:bg-[#1a2a25]"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[.16em] text-[#a05a3b]">{{ section.subject }}</p>
                                <h3 class="font-display mt-2 text-2xl font-bold">{{ section.name }}</h3>
                                <p class="mt-1 text-sm text-muted-foreground">{{ section.term }}</p>
                            </div>
                            <ArrowRight class="size-5 transition group-hover:translate-x-1" />
                        </div>
                        <div class="mt-6 grid grid-cols-3 gap-3 text-sm">
                            <div>
                                <span class="block text-xs text-muted-foreground">Students</span><strong>{{ section.students }}</strong>
                            </div>
                            <div>
                                <span class="block text-xs text-muted-foreground">Seats</span><strong>{{ section.seats }}</strong>
                            </div>
                            <div>
                                <span class="block text-xs text-muted-foreground">Attendance</span
                                ><strong>{{ section.attendance_rate === null ? '—' : section.attendance_rate + '%' }}</strong>
                            </div>
                        </div>
                    </Link>
                </div>
                <div v-else class="px-6 py-14 text-center">
                    <div class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-[#e7eee8] text-[#245246]">
                        <GraduationCap class="size-8" />
                    </div>
                    <h3 class="font-display mt-5 text-2xl font-bold">Set up your first classroom</h3>
                    <p class="mx-auto mt-2 max-w-md text-muted-foreground">
                        Create a section, shape its seating blocks, then let students claim their chairs through QR.
                    </p>
                    <Link href="/sections/create" class="ink-button mt-6"><Plus class="size-4" /> Create a section</Link>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
