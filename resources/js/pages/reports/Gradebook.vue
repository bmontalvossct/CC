<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, Printer } from 'lucide-vue-next';
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
    <Head :title="`Gradebook · ${section.name}`" />
    <component
        :is="printMode ? 'div' : AppLayout"
        :breadcrumbs="[
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Gradebook', href: '#' },
        ]"
    >
        <main class="min-h-screen bg-[#f3efe4] p-5 text-[#20251f] dark:bg-[#111712] dark:text-[#f2efe5] print:bg-white print:p-0 print:text-black">
            <div class="mx-auto max-w-[1500px]">
                <div v-if="!printMode" class="mb-5 flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Link :href="`/sections/${section.id}/assessments`" class="inline-flex items-center gap-2 text-sm font-semibold"
                        ><ArrowLeft class="size-4" /> Assessments</Link
                    >
                    <div class="flex gap-2">
                        <a
                            :href="`/sections/${section.id}/exports/gradebook`"
                            class="inline-flex items-center gap-2 rounded-xl border border-[#283b2e]/20 bg-[#fffdf6] px-4 py-2.5 text-sm font-semibold"
                            ><Download class="size-4" /> Export CSV</a
                        ><a
                            :href="`/sections/${section.id}/reports/gradebook/print`"
                            target="_blank"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#173c2a] px-4 py-2.5 text-sm font-semibold text-white"
                            ><Printer class="size-4" /> Print</a
                        >
                    </div>
                </div>
                <header
                    class="rounded-[1.5rem] bg-[#173c2a] p-7 text-white print:rounded-none print:border-b-2 print:border-black print:bg-white print:px-0 print:text-black"
                >
                    <p class="font-mono text-xs uppercase tracking-[.24em] text-[#f4c95d] print:text-black">ClassCheck · raw score register</p>
                    <h1 class="mt-2 font-serif text-4xl">{{ section.subject_code }} {{ section.subject_title }}</h1>
                    <p class="mt-2 text-[#cbd8ce] print:text-black">{{ section.name }} · Scores are unweighted and shown exactly as recorded.</p>
                </header>

                <section class="my-5 grid gap-3 sm:grid-cols-3 print:grid-cols-3">
                    <div
                        v-for="type in types"
                        :key="type"
                        class="rounded-2xl border border-[#283b2e]/15 bg-[#fffdf6] p-4 dark:bg-[#1b241d] print:rounded-none print:border-black print:bg-white"
                    >
                        <p class="font-mono text-[10px] font-bold uppercase tracking-[.18em]">{{ type }}</p>
                        <p class="mt-1 font-serif text-2xl">{{ categorySummary[type].count }} items</p>
                        <p class="text-xs text-[#687067]">{{ categorySummary[type].possible }} possible points</p>
                    </div>
                </section>

                <div
                    class="overflow-x-auto rounded-[1.5rem] border border-[#283b2e]/20 bg-[#fffdf6] dark:bg-[#1b241d] print:overflow-visible print:rounded-none print:border-black print:bg-white"
                >
                    <table class="w-full min-w-max border-collapse text-xs print:text-[8px]">
                        <thead>
                            <tr class="border-b border-[#283b2e]/20">
                                <th
                                    class="sticky left-0 z-20 min-w-52 bg-[#fffdf6] px-4 py-4 text-left dark:bg-[#1b241d] print:static print:bg-white"
                                >
                                    Student
                                </th>
                                <th v-for="item in assessments" :key="item.id" class="min-w-28 border-l border-[#283b2e]/10 px-3 py-3 text-center">
                                    <span class="block font-mono text-[9px] uppercase tracking-wider text-[#b7552d]">{{ item.type }}</span
                                    ><strong class="mt-1 block">{{ item.title }}</strong
                                    ><small class="font-normal text-[#687067]">/ {{ item.max_points }}</small>
                                </th>
                                <th
                                    v-for="type in types"
                                    :key="`total-${type}`"
                                    class="min-w-28 border-l-2 border-[#173c2a]/30 bg-[#edf0e8] px-3 text-center capitalize dark:bg-white/5"
                                >
                                    {{ type }} total
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.id" class="break-inside-avoid border-b border-[#283b2e]/10 last:border-0">
                                <td class="sticky left-0 z-10 bg-[#fffdf6] px-4 py-3 dark:bg-[#1b241d] print:static print:bg-white">
                                    <strong class="block">{{ row.full_name }}</strong
                                    ><small class="font-mono text-[#687067]">{{ row.student_number }}</small>
                                </td>
                                <td
                                    v-for="item in assessments"
                                    :key="item.id"
                                    class="border-l border-[#283b2e]/10 px-3 py-3 text-center font-mono text-sm print:text-[8px]"
                                    :class="row.scores[item.id] === null ? 'text-[#a7aaa5]' : 'font-bold'"
                                >
                                    {{ row.scores[item.id] ?? '—' }}
                                </td>
                                <td
                                    v-for="type in types"
                                    :key="type"
                                    class="border-l-2 border-[#173c2a]/20 bg-[#edf0e8]/70 px-3 py-3 text-center dark:bg-white/5"
                                >
                                    <strong class="block">{{ row.categories[type].earned }} / {{ row.categories[type].possible }}</strong
                                    ><small
                                        >{{ row.categories[type].percentage === null ? '—' : `${row.categories[type].percentage}%` }} ·
                                        {{ row.categories[type].missing }} missing</small
                                    >
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td :colspan="1 + assessments.length + 3" class="p-12 text-center text-[#687067]">
                                    No students are enrolled in this section.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-xs text-[#687067] print:text-[8px]">
                    Blank scores are missing, not zero. Category percentages use all published possible points and are not an official weighted final
                    grade.
                </p>
            </div>
        </main>
    </component>
</template>

<style scoped>
@media print {
    @page {
        size: landscape;
        margin: 10mm;
    }
}
</style>
