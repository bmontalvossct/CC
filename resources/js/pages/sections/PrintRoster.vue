<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { onMounted } from 'vue';

const props = defineProps<{
    section: {
        id: number;
        name: string;
        subject_code?: string;
        subject_title: string;
        room?: string | null;
        academic_term: { name: string; school_year: string };
        students: Array<{
            id: number;
            student_number: string;
            first_name: string;
            last_name: string;
            full_name?: string;
            photo_url?: string | null;
            seat?: { label: string } | null;
        }>;
    };
}>();

const initials = (first: string, last: string) => `${first?.[0] || ''}${last?.[0] || ''}`.toUpperCase();

const print = () => window.print();

onMounted(() => {
    window.setTimeout(() => window.print(), 350);
});
</script>

<template>
    <Head :title="`Roster Sheet · ${section.name} - ClassCheck`" />
    <main class="min-h-screen bg-background p-6 text-foreground print:bg-white print:p-0 print:text-black">
        <div class="mx-auto max-w-5xl">
            <!-- Screen Toolbar -->
            <div class="mb-6 flex items-center justify-between print:hidden">
                <Link
                    :href="`/sections/${section.id}`"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground transition-colors hover:text-primary"
                >
                    <ArrowLeft class="size-3.5" /> Back to section
                </Link>
                <button type="button" class="ink-button !h-9 !rounded-xl !px-4 text-xs font-semibold" @click="print">
                    <Printer class="size-3.5" /> Print directory
                </button>
            </div>

            <!-- Print Header -->
            <header class="border-b-2 border-zinc-900 pb-5 text-left">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-mono text-xs font-bold uppercase tracking-widest text-primary print:text-black">
                            {{ section.subject_code }} · {{ section.academic_term.name }} (SY {{ section.academic_term.school_year }})
                        </p>
                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl print:text-2xl">
                            {{ section.name }} — Class Roster & Seating Directory
                        </h1>
                        <p class="mt-0.5 text-xs text-muted-foreground print:text-black">
                            {{ section.subject_title }} <span v-if="section.room">· Room {{ section.room }}</span>
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="rounded-lg bg-secondary px-3 py-1 font-mono text-xs font-bold print:border print:border-black">
                            {{ section.students.length }} Students
                        </span>
                    </div>
                </div>
            </header>

            <!-- Photo & Seat Directory Cards Grid -->
            <section class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 print:grid-cols-4">
                <article
                    v-for="student in section.students"
                    :key="student.id"
                    class="shadow-xs flex break-inside-avoid items-center gap-3 rounded-xl border border-border bg-card p-3 print:rounded-none print:border print:border-zinc-300 print:shadow-none"
                >
                    <img
                        v-if="student.photo_url"
                        :src="student.photo_url"
                        alt=""
                        class="size-12 shrink-0 rounded-xl border border-border object-cover print:size-10"
                    />
                    <div
                        v-else
                        class="grid size-12 shrink-0 place-items-center rounded-xl border border-border bg-secondary font-mono text-xs font-bold text-muted-foreground print:size-10"
                    >
                        {{ initials(student.first_name, student.last_name) }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <strong class="block truncate text-xs font-bold leading-tight text-foreground print:text-black">
                            {{ student.last_name }}, {{ student.first_name }}
                        </strong>
                        <span class="block font-mono text-[10px] text-muted-foreground print:text-zinc-600">
                            {{ student.student_number }}
                        </span>
                        <span
                            class="mt-1 inline-block rounded bg-primary/10 px-1.5 py-0.5 font-mono text-[9px] font-bold text-primary print:border print:border-black print:bg-transparent print:text-black"
                        >
                            {{ student.seat?.label || 'Unseated' }}
                        </span>
                    </div>
                </article>
            </section>
        </div>
    </main>
</template>

<style scoped>
@media print {
    @page {
        size: portrait;
        margin: 10mm;
    }
}
</style>
