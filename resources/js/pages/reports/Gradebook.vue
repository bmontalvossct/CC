<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, Printer, TableProperties } from 'lucide-vue-next';
import { onMounted } from 'vue';

type Assessment = { id: number; type: 'activity' | 'quiz' | 'exam'; title: string; conducted_on: string; max_points: string };
type Category = { earned: number; possible: number; percentage: number | null; missing: number };
type Row = {
    id: number;
    student_number: string;
    full_name: string;
    scores: Record<number, string | null>;
    categories: Record<'activity' | 'quiz' | 'exam', Category>;
};

const props = defineProps<{
    section: { id: number; name: string; subject_code?: string; subject_title: string };
    assessments: Assessment[];
    rows: Row[];
    categorySummary: Record<string, { count: number; possible: number }>;
    printMode: boolean;
}>();

const types = ['activity', 'quiz', 'exam'] as const;

onMounted(() => {
    if (props.printMode) window.setTimeout(() => window.print(), 250);
});
</script>

<template>
    <Head :title="`Gradebook · ${section.name} - ClassCheck`" />
    <component
        :is="printMode ? 'div' : AppLayout"
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Gradebook', href: '#' },
        ]"
    >
        <main class="min-h-screen bg-background p-5 text-foreground md:p-8 print:bg-white print:p-0 print:text-black">
            <div class="mx-auto max-w-[1500px]">
                <!-- Top Toolbar (Hidden on Print) -->
                <div v-if="!printMode" class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
                    <Link
                        :href="`/sections/${section.id}/assessments`"
                        prefetch="hover"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground hover:text-primary transition-colors"
                    >
                        <ArrowLeft class="size-3.5" /> Back to assessments
                    </Link>

                    <div class="flex items-center gap-2">
                        <a
                            :href="`/sections/${section.id}/exports/gradebook`"
                            class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-border bg-card px-3.5 text-xs font-semibold text-foreground shadow-xs hover:bg-secondary transition-colors"
                        >
                            <Download class="size-3.5 text-muted-foreground" />
                            <span>Export CSV</span>
                        </a>
                        <a
                            :href="`/sections/${section.id}/reports/gradebook/print`"
                            target="_blank"
                            class="ink-button !h-9 !rounded-xl !px-3.5 text-xs"
                        >
                            <Printer class="size-3.5" />
                            <span>Print view</span>
                        </a>
                    </div>
                </div>

                <!-- Header Block -->
                <header
                    class="rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 sm:p-8 shadow-sm print:rounded-none print:border-b-2 print:border-black print:bg-white print:p-0 print:text-black"
                >
                    <div class="flex items-center gap-2">
                        <span class="badge-primary font-mono font-bold">{{ section.subject_code }}</span>
                        <span class="badge-muted">{{ section.name }}</span>
                    </div>
                    <h1 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl print:text-xl">{{ section.subject_title }}</h1>
                    <p class="mt-1 text-xs text-muted-foreground print:text-black">
                        Raw score gradebook. Unweighted points calculated from all active ledger entries.
                    </p>
                </header>

                <!-- Category Summary Cards -->
                <section class="my-6 grid gap-4 sm:grid-cols-3 print:grid-cols-3">
                    <div
                        v-for="type in types"
                        :key="type"
                        class="paper-card p-5 print:rounded-none print:border print:border-black print:bg-white"
                    >
                        <span
                            class="font-mono text-[10px] font-extrabold uppercase tracking-wider"
                            :class="
                                type === 'exam'
                                    ? 'text-purple-600 dark:text-purple-400'
                                    : type === 'quiz'
                                      ? 'text-blue-600 dark:text-blue-400'
                                      : 'text-emerald-600 dark:text-emerald-400'
                            "
                        >
                            {{ type }} total
                        </span>
                        <p class="mt-2 text-2xl font-extrabold tracking-tight">{{ categorySummary[type].count }} items</p>
                        <p class="text-xs text-muted-foreground font-medium mt-0.5">{{ categorySummary[type].possible }} possible pts</p>
                    </div>
                </section>

                <!-- Gradebook Matrix Table -->
                <div
                    class="paper-card overflow-x-auto shadow-sm print:overflow-visible print:rounded-none print:border print:border-black print:bg-white p-0"
                >
                    <table class="w-full min-w-max border-collapse text-xs print:text-[8px]">
                        <thead>
                            <tr class="border-b border-border/80 bg-secondary/50">
                                <th
                                    class="sticky left-0 z-20 min-w-56 bg-secondary/95 backdrop-blur-xs px-4 py-3.5 text-left font-bold uppercase tracking-wider text-muted-foreground print:static print:bg-white"
                                >
                                    Student
                                </th>
                                <th
                                    v-for="item in assessments"
                                    :key="item.id"
                                    class="min-w-28 border-l border-border/60 px-3 py-3 text-center"
                                >
                                    <span class="block font-mono text-[9px] font-extrabold uppercase tracking-wider text-primary">{{ item.type }}</span>
                                    <strong class="mt-0.5 block truncate text-foreground font-bold max-w-32 mx-auto">{{ item.title }}</strong>
                                    <span class="font-mono text-[10px] text-muted-foreground">/ {{ item.max_points }}</span>
                                </th>
                                <th
                                    v-for="type in types"
                                    :key="`total-${type}`"
                                    class="min-w-28 border-l-2 border-border bg-secondary/80 px-3 text-center capitalize font-bold text-foreground"
                                >
                                    {{ type }} Total
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="row in rows"
                                :key="row.id"
                                class="hover:bg-secondary/30 transition-colors break-inside-avoid"
                            >
                                <td class="sticky left-0 z-10 bg-card/95 backdrop-blur-xs px-4 py-3 border-r border-border/50 print:static print:bg-white">
                                    <strong class="block font-bold text-foreground">{{ row.full_name }}</strong>
                                    <span class="font-mono text-[10px] text-muted-foreground">{{ row.student_number }}</span>
                                </td>
                                <td
                                    v-for="item in assessments"
                                    :key="item.id"
                                    class="border-l border-border/60 px-3 py-3 text-center font-mono text-xs"
                                    :class="row.scores[item.id] === null ? 'text-muted-foreground/60' : 'font-bold text-foreground'"
                                >
                                    {{ row.scores[item.id] ?? '—' }}
                                </td>
                                <td
                                    v-for="type in types"
                                    :key="type"
                                    class="border-l-2 border-border bg-secondary/20 px-3 py-3 text-center font-mono"
                                >
                                    <strong class="block text-xs font-bold">{{ row.categories[type].earned }} / {{ row.categories[type].possible }}</strong>
                                    <span class="block text-[10px] text-muted-foreground">
                                        {{ row.categories[type].percentage === null ? '—' : `${row.categories[type].percentage}%` }}
                                        <span v-if="row.categories[type].missing > 0" class="text-rose-600">({{ row.categories[type].missing }} miss)</span>
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td :colspan="1 + assessments.length + 3" class="py-12 text-center text-xs text-muted-foreground">
                                    No students are enrolled in this section.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="mt-4 text-[11px] text-muted-foreground print:text-[8px]">
                    Note: Blank scores are counted as missing, not zero. Category percentages reflect earned versus total possible published points.
                </p>
            </div>
        </main>
    </component>
</template>

<style scoped>
@media print {
    @page {
        size: landscape;
        margin: 8mm;
    }
}
</style>
