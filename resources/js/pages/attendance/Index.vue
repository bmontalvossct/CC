<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, CheckCircle2, Clock3, Plus } from 'lucide-vue-next';
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
    { label: 'This week', value: props.periodSummaries.week, icon: CalendarDays },
    { label: 'This month', value: props.periodSummaries.month, icon: Clock3 },
    { label: props.section.term.name, value: props.periodSummaries.term, icon: CheckCircle2 },
]);
const readableDate = (date: string) => new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(new Date(`${date}T00:00:00`));
function changeReferenceDate(event: Event) {
    router.get(
        `/sections/${props.section.id}/attendance`,
        { reference_date: (event.target as HTMLInputElement).value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <Head :title="`${section.subject_code} attendance`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
            <header class="flex flex-col justify-between gap-4 border-b border-border pb-6 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-muted-foreground">Attendance register</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight">{{ section.subject_code }} · {{ section.name }}</h1>
                    <p class="mt-1 text-muted-foreground">{{ section.subject_title }} · {{ section.term.name }} {{ section.term.school_year }}</p>
                </div>
                <label class="flex items-center gap-3 text-sm font-medium"
                    >Summary date <Input class="w-auto" type="date" :model-value="referenceDate" @change="changeReferenceDate"
                /></label>
            </header>
            <section class="grid gap-3 md:grid-cols-3" aria-label="Attendance period summaries">
                <article v-for="card in summaryCards" :key="card.label" class="rounded-xl border bg-card p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="font-medium text-muted-foreground">{{ card.label }}</p>
                        <component :is="card.icon" class="size-5 text-primary" />
                    </div>
                    <div class="mt-4 flex items-end justify-between gap-3">
                        <p class="text-3xl font-bold">{{ card.value.rate === null ? '—' : `${card.value.rate}%` }}</p>
                        <p class="text-sm text-muted-foreground">{{ card.value.attended_hours }} total hours</p>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">{{ card.value.present }} present · {{ card.value.absent }} absent</p>
                </article>
            </section>
            <section class="grid items-start gap-6 lg:grid-cols-[22rem_1fr]">
                <form class="rounded-xl border bg-card p-5 shadow-sm" @submit.prevent="form.post(`/sections/${section.id}/attendance`)">
                    <div class="flex items-center gap-2">
                        <span class="rounded-lg bg-primary/10 p-2 text-primary"><Plus class="size-4" /></span>
                        <div>
                            <h2 class="font-semibold">Start attendance</h2>
                            <p class="text-xs text-muted-foreground">Everyone starts as present.</p>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-4">
                        <div class="grid gap-2">
                            <Label for="session-date">Date</Label
                            ><Input id="session-date" v-model="form.session_date" type="date" required /><InputError
                                :message="form.errors.session_date"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-2">
                                <Label for="starts">Starts</Label><Input id="starts" v-model="form.starts_at" type="time" required /><InputError
                                    :message="form.errors.starts_at"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="ends">Ends</Label><Input id="ends" v-model="form.ends_at" type="time" required /><InputError
                                    :message="form.errors.ends_at"
                                />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="notes">Notes (optional)</Label
                            ><textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                maxlength="2000"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            /><InputError :message="form.errors.notes" />
                        </div>
                        <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Starting…' : 'Open classroom check' }}</Button>
                    </div>
                </form>
                <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="border-b px-5 py-4">
                        <h2 class="font-semibold">Recent sessions</h2>
                        <p class="text-xs text-muted-foreground">Multiple meetings can be recorded per day.</p>
                    </div>
                    <div v-if="sessions.length" class="divide-y">
                        <Link
                            v-for="session in sessions"
                            :key="session.id"
                            :href="`/attendance/${session.id}`"
                            class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
                            ><div>
                                <p class="font-medium">{{ readableDate(session.session_date) }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ session.starts_at }}–{{ session.ends_at }} · {{ session.duration_minutes }} minutes
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold">{{ session.present_count }}/{{ session.records_count }}</p>
                                <p class="text-xs text-muted-foreground">present</p>
                            </div></Link
                        >
                    </div>
                    <div v-else class="px-5 py-12 text-center text-sm text-muted-foreground">No attendance sessions yet.</div>
                </div>
            </section>
            <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="border-b px-5 py-4">
                    <h2 class="font-semibold">Student attendance summary</h2>
                    <p class="text-xs text-muted-foreground">Hours use each session’s full duration.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-muted/60 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-5 py-3">Student</th>
                                <th class="px-4 py-3">Week</th>
                                <th class="px-4 py-3">Month</th>
                                <th class="px-4 py-3">Term</th>
                                <th class="px-4 py-3">Total hours</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="student in studentSummaries" :key="student.id">
                                <td class="px-5 py-3">
                                    <p class="font-medium">{{ student.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ student.student_number }}</p>
                                </td>
                                <td class="px-4 py-3">{{ student.week.rate === null ? '—' : `${student.week.rate}%` }}</td>
                                <td class="px-4 py-3">{{ student.month.rate === null ? '—' : `${student.month.rate}%` }}</td>
                                <td class="px-4 py-3">{{ student.term.rate === null ? '—' : `${student.term.rate}%` }}</td>
                                <td class="px-4 py-3 font-semibold">{{ student.overall.attended_hours }}</td>
                            </tr>
                            <tr v-if="!studentSummaries.length">
                                <td colspan="5" class="px-5 py-10 text-center text-muted-foreground">Add students to begin tracking attendance.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
