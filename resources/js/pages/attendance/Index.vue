<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarCheck2,
    CalendarDays,
    CheckCircle2,
    Clock3,
    History,
    Plus,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

type Summary = { sessions: number; present: number; absent: number; rate: number | null; attended_hours: number };
type Section = {
    id: number;
    subject_code: string;
    subject_title: string;
    name: string;
    term: { name: string; school_year: string };
    default_schedule: { starts_at: string; ends_at: string } | null;
};
const props = defineProps<{
    section: Section;
    referenceDate: string;
    periodSummaries: Record<'week' | 'month' | 'term', Summary>;
    studentSummaries: Array<{ id: number; student_number: string; name: string; week: Summary; month: Summary; term: Summary; overall: Summary }>;
    sessions: Array<{
        id: number;
        session_date: string;
        starts_at: string;
        ends_at: string;
        duration_minutes: number;
        records_count: number;
        present_count: number;
    }>;
}>();

const form = useForm({
    session_date: new Date().toLocaleDateString('en-CA'),
    starts_at: props.section.default_schedule?.starts_at?.slice(0, 5) ?? '08:00',
    ends_at: props.section.default_schedule?.ends_at?.slice(0, 5) ?? '09:00',
    notes: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sections', href: '/sections' },
    { title: props.section.subject_code, href: `/sections/${props.section.id}` },
    { title: 'Attendance', href: `/sections/${props.section.id}/attendance` },
];

const summaryCards = computed(() => [
    { label: 'This week', value: props.periodSummaries.week, icon: CalendarDays, color: 'text-blue-600 dark:text-blue-400 bg-blue-500/10' },
    { label: 'This month', value: props.periodSummaries.month, icon: Clock3, color: 'text-purple-600 dark:text-purple-400 bg-purple-500/10' },
    { label: props.section.term.name, value: props.periodSummaries.term, icon: CheckCircle2, color: 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10' },
]);

const readableDate = (date: string) =>
    new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(new Date(`${date}T00:00:00`));

function changeReferenceDate(event: Event) {
    router.get(
        `/sections/${props.section.id}/attendance`,
        { reference_date: (event.target as HTMLInputElement).value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <Head :title="`${section.subject_code} attendance - ClassCheck`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="page-enter mx-auto flex w-full max-w-[1360px] flex-1 flex-col gap-6 px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <!-- Header -->
            <header class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 sm:p-8 shadow-sm">
                <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="badge-primary font-mono font-bold">{{ section.subject_code }}</span>
                            <span class="badge-muted">{{ section.term.name }} {{ section.term.school_year }}</span>
                        </div>
                        <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ section.name }} · Attendance</h1>
                        <p class="mt-1 text-sm text-muted-foreground">{{ section.subject_title }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                            <span>Reference date</span>
                            <Input class="h-9 w-auto rounded-xl text-xs" type="date" :model-value="referenceDate" @change="changeReferenceDate" />
                        </label>
                    </div>
                </div>
            </header>

            <!-- Summary KPI Cards -->
            <section class="grid gap-4 sm:grid-cols-3" aria-label="Attendance period summaries">
                <article
                    v-for="card in summaryCards"
                    :key="card.label"
                    class="paper-card p-6 flex flex-col justify-between hover:shadow-md hover:border-primary/40 transition-all"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">{{ card.label }}</span>
                        <span :class="['grid size-9 place-items-center rounded-xl', card.color]">
                            <component :is="card.icon" class="size-4.5" />
                        </span>
                    </div>
                    <div class="mt-6 flex items-baseline justify-between">
                        <p class="text-3xl font-extrabold tracking-tight">
                            {{ card.value.rate === null ? '—' : `${card.value.rate}%` }}
                        </p>
                        <span class="text-xs text-muted-foreground font-medium">{{ card.value.attended_hours }} hrs recorded</span>
                    </div>
                    <p class="mt-2 text-xs font-medium text-muted-foreground">
                        <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ card.value.present }}</span> present ·
                        <span class="text-rose-600 dark:text-rose-400 font-bold">{{ card.value.absent }}</span> absent
                    </p>
                </article>
            </section>

            <!-- Start Session & Recent Sessions Grid -->
            <section class="grid items-start gap-6 lg:grid-cols-[22rem_1fr]">
                <!-- Start Attendance Session Form -->
                <form
                    class="paper-card p-6 shadow-sm"
                    @submit.prevent="form.post(`/sections/${section.id}/attendance`)"
                >
                    <div class="flex items-center gap-2.5">
                        <span class="grid size-9 place-items-center rounded-xl bg-primary/10 text-primary">
                            <Plus class="size-5" />
                        </span>
                        <div>
                            <h2 class="text-base font-bold">Start roll call</h2>
                            <p class="text-[11px] text-muted-foreground">All students initialize as present.</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <div class="grid gap-1.5">
                            <Label for="session-date" class="text-xs font-semibold">Meeting date</Label>
                            <Input id="session-date" v-model="form.session_date" type="date" required class="rounded-xl h-10 text-sm" />
                            <InputError class="text-xs mt-1" :message="form.errors.session_date" />
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div class="grid gap-1.5">
                                <Label for="starts" class="text-xs font-semibold">Start time</Label>
                                <Input id="starts" v-model="form.starts_at" type="time" required class="rounded-xl h-10 text-sm" />
                                <InputError class="text-xs mt-1" :message="form.errors.starts_at" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="ends" class="text-xs font-semibold">End time</Label>
                                <Input id="ends" v-model="form.ends_at" type="time" required class="rounded-xl h-10 text-sm" />
                                <InputError class="text-xs mt-1" :message="form.errors.ends_at" />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="notes" class="text-xs font-semibold">Session notes <span class="text-muted-foreground font-normal">(optional)</span></Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="2"
                                maxlength="2000"
                                placeholder="e.g. Lab activity / quiz day"
                                class="rounded-xl border border-input bg-background px-3 py-2 text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                            />
                            <InputError class="text-xs mt-1" :message="form.errors.notes" />
                        </div>

                        <Button type="submit" class="ink-button !h-10 !w-full mt-2" :disabled="form.processing">
                            <CalendarCheck2 class="size-4" />
                            <span>{{ form.processing ? 'Starting session...' : 'Launch live check' }}</span>
                        </Button>
                    </div>
                </form>

                <!-- Recent Sessions Timeline -->
                <div class="paper-card p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-border/80 pb-4">
                        <div class="flex items-center gap-2">
                            <History class="size-4 text-primary" />
                            <h2 class="text-base font-bold">Recent roll-call sessions</h2>
                        </div>
                        <span class="badge-muted">{{ sessions.length }} sessions</span>
                    </div>

                    <div v-if="sessions.length" class="divide-y divide-border/60 max-h-80 overflow-y-auto">
                        <Link
                            v-for="session in sessions"
                            :key="session.id"
                            :href="`/attendance/${session.id}`"
                            prefetch="hover"
                            class="flex items-center justify-between gap-4 py-3.5 px-2 hover:bg-secondary/60 rounded-xl transition-colors group"
                        >
                            <div>
                                <p class="text-sm font-bold group-hover:text-primary transition-colors">
                                    {{ readableDate(session.session_date) }}
                                </p>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    {{ session.starts_at }} – {{ session.ends_at }} · {{ session.duration_minutes }} mins
                                </p>
                            </div>
                            <div class="flex items-center gap-3 text-right">
                                <div>
                                    <span class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ session.present_count }}/{{ session.records_count }}
                                    </span>
                                    <span class="block text-[10px] text-muted-foreground uppercase font-semibold">present</span>
                                </div>
                                <ArrowRight class="size-4 text-muted-foreground group-hover:text-primary group-hover:translate-x-0.5 transition-all" />
                            </div>
                        </Link>
                    </div>
                    <div v-else class="py-12 text-center text-xs text-muted-foreground">
                        No attendance sessions logged yet. Start the first session on the left.
                    </div>
                </div>
            </section>

            <!-- Student Summary Table -->
            <section class="paper-card p-6 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between border-b border-border/80 pb-4">
                    <div>
                        <h2 class="text-lg font-bold">Cumulative student attendance</h2>
                        <p class="text-xs text-muted-foreground">Detailed summary across terms, months, and total hours.</p>
                    </div>
                    <span class="badge-primary font-bold">{{ studentSummaries.length }} students</span>
                </div>

                <div class="overflow-x-auto mt-4">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="border-b border-border/80 bg-secondary/40 text-left text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                <th class="px-4 py-3 rounded-l-lg">Student</th>
                                <th class="px-4 py-3">Week</th>
                                <th class="px-4 py-3">Month</th>
                                <th class="px-4 py-3">Term</th>
                                <th class="px-4 py-3 rounded-r-lg">Total hours</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr v-for="student in studentSummaries" :key="student.id" class="hover:bg-secondary/30 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="font-bold text-foreground">{{ student.name }}</p>
                                    <p class="font-mono text-xs text-muted-foreground">{{ student.student_number }}</p>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs font-semibold">
                                    {{ student.week.rate === null ? '—' : `${student.week.rate}%` }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs font-semibold">
                                    {{ student.month.rate === null ? '—' : `${student.month.rate}%` }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs font-semibold">
                                    {{ student.term.rate === null ? '—' : `${student.term.rate}%` }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs font-bold text-primary">
                                    {{ student.overall.attended_hours }} hrs
                                </td>
                            </tr>
                            <tr v-if="!studentSummaries.length">
                                <td colspan="5" class="py-10 text-center text-xs text-muted-foreground">
                                    No students enrolled in this section.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
