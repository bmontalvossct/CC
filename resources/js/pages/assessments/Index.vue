<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BarChart3, CalendarDays, ClipboardCheck, Download, FilePlus2, Plus, Trophy } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Assessment = {
    id: number;
    type: 'activity' | 'quiz' | 'exam';
    title: string;
    conducted_on: string;
    max_points: string;
    graded_count: number;
    points_awarded: string | null;
};
type Session = { id: number; session_date: string; starts_at: string };
const props = defineProps<{
    section: { id: number; name: string; subject_code?: string; subject_title: string };
    assessments: Assessment[];
    filter: string;
    attendanceSessions: Session[];
}>();
const creating = ref(false);
const formatDate = (value: string) =>
    new Intl.DateTimeFormat('en-PH', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' }).format(new Date(value));
const form = useForm({
    type: 'activity',
    title: '',
    description: '',
    conducted_on: new Date().toISOString().slice(0, 10),
    max_points: 10,
    attendance_session_id: '' as number | '',
    attachment: null as File | null,
});
const tabs = ['all', 'activity', 'quiz', 'exam'] as const;
const filtered = computed(() => props.assessments);
const submit = () =>
    form.post(`/sections/${props.section.id}/assessments`, {
        forceFormData: true,
        onSuccess: () => {
            creating.value = false;
            form.reset();
        },
    });
</script>

<template>
    <Head :title="`Assessments · ${section.name}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Assessments', href: '#' },
        ]"
    >
        <main class="min-h-full bg-[#f5f5f7] text-[#1d1d1f] dark:bg-[#1d1d1f] dark:text-[#f5f5f7]">
            <div class="mx-auto max-w-7xl p-5 sm:p-8">
                <header
                    class="relative overflow-hidden rounded-[2rem] border border-[#e5e7eb]/20 bg-[#1d1d1f] px-6 py-8 text-[#ffffff] shadow-[0_22px_60px_-34px_rgba(15,35,23,.8)] sm:px-10"
                >
                    <div
                        class="absolute inset-0 opacity-15"
                        style="
                            background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px);
                            background-size: 28px 28px;
                        "
                    />
                    <div class="relative flex flex-col justify-between gap-8 lg:flex-row lg:items-end">
                        <div>
                            <p class="mb-3 font-mono text-xs uppercase tracking-[.28em] text-[#2997ff]">
                                {{ section.subject_code || 'Class ledger' }} · {{ section.name }}
                            </p>
                            <h1 class="max-w-3xl font-serif text-4xl leading-tight sm:text-5xl">
                                Assessments, arranged for the pace of a classroom.
                            </h1>
                            <p class="mt-4 max-w-xl text-sm leading-6 text-[#f5f5f7]">
                                Create activities, quizzes, and exams. Then record scores in the same physical order as the chairs.
                            </p>
                        </div>
                        <button
                            class="inline-flex items-center justify-center gap-2 rounded-full bg-[#2997ff] px-5 py-3 font-semibold text-[#1d1d1f] transition hover:-translate-y-0.5 hover:bg-[#eaf4ff]"
                            @click="creating = !creating"
                        >
                            <Plus class="size-4" /> New assessment
                        </button>
                    </div>
                </header>

                <section v-if="creating" class="mt-6 rounded-[1.5rem] border border-[#e5e7eb]/20 bg-[#ffffff] p-6 shadow-sm dark:bg-[#1d1d1f]">
                    <div class="mb-5 flex items-center gap-3">
                        <FilePlus2 class="size-5 text-[#0071e3]" />
                        <h2 class="font-serif text-2xl">Add to the class ledger</h2>
                    </div>
                    <form class="grid gap-5 lg:grid-cols-12" @submit.prevent="submit">
                        <label class="lg:col-span-3"
                            ><span class="mb-2 block text-xs font-bold uppercase tracking-wider">Type</span
                            ><select v-model="form.type" class="w-full rounded-xl border-[#86868b] bg-transparent">
                                <option value="activity">Activity</option>
                                <option value="quiz">Quiz</option>
                                <option value="exam">Exam</option>
                            </select></label
                        >
                        <label class="lg:col-span-6"
                            ><span class="mb-2 block text-xs font-bold uppercase tracking-wider">Title</span
                            ><input
                                v-model="form.title"
                                required
                                class="w-full rounded-xl border-[#86868b] bg-transparent"
                                placeholder="e.g. Chapter 4 problem set"
                            /><small class="text-red-700">{{ form.errors.title }}</small></label
                        >
                        <label class="lg:col-span-3"
                            ><span class="mb-2 block text-xs font-bold uppercase tracking-wider">Maximum points</span
                            ><input
                                v-model="form.max_points"
                                required
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="w-full rounded-xl border-[#86868b] bg-transparent"
                        /></label>
                        <label class="lg:col-span-3"
                            ><span class="mb-2 block text-xs font-bold uppercase tracking-wider">Date conducted</span
                            ><input v-model="form.conducted_on" required type="date" class="w-full rounded-xl border-[#86868b] bg-transparent"
                        /></label>
                        <label class="lg:col-span-4"
                            ><span class="mb-2 block text-xs font-bold uppercase tracking-wider">Attendance session</span
                            ><select v-model="form.attendance_session_id" class="w-full rounded-xl border-[#86868b] bg-transparent">
                                <option value="">Match by date automatically</option>
                                <option v-for="session in attendanceSessions" :key="session.id" :value="session.id">
                                    {{ session.session_date }} · {{ session.starts_at }}
                                </option>
                            </select></label
                        >
                        <label class="lg:col-span-5"
                            ><span class="mb-2 block text-xs font-bold uppercase tracking-wider"
                                >Attachment <em class="font-normal normal-case">optional</em></span
                            ><input
                                type="file"
                                class="block w-full rounded-xl border border-[#86868b] p-2 text-sm"
                                @change="form.attachment = ($event.target as HTMLInputElement).files?.[0] || null"
                        /></label>
                        <label class="lg:col-span-9"
                            ><span class="mb-2 block text-xs font-bold uppercase tracking-wider"
                                >Notes <em class="font-normal normal-case">optional</em></span
                            ><textarea v-model="form.description" rows="2" class="w-full rounded-xl border-[#86868b] bg-transparent" />
                        </label>
                        <div class="flex items-end justify-end gap-3 lg:col-span-3">
                            <button type="button" class="px-4 py-2 text-sm" @click="creating = false">Cancel</button
                            ><button
                                :disabled="form.processing"
                                class="rounded-xl bg-[#0071e3] px-5 py-2.5 font-semibold text-white disabled:opacity-50"
                            >
                                {{ form.processing ? 'Creating…' : 'Create & score' }}
                            </button>
                        </div>
                    </form>
                </section>

                <div class="mt-8 flex flex-wrap items-center justify-between gap-4 border-b border-[#e5e7eb]/20 pb-4">
                    <nav class="flex gap-2" aria-label="Assessment type">
                        <Link
                            v-for="tab in tabs"
                            :key="tab"
                            :href="`/sections/${section.id}/assessments${tab === 'all' ? '' : `?type=${tab}`}`"
                            class="rounded-full px-4 py-2 text-sm font-semibold capitalize"
                            :class="filter === tab ? 'bg-[#1d1d1f] text-white' : 'hover:bg-[#e5e7eb] dark:hover:bg-white/10'"
                            >{{ tab }}</Link
                        >
                    </nav>
                    <div class="flex gap-2">
                        <a
                            :href="`/sections/${section.id}/exports/gradebook`"
                            class="inline-flex items-center gap-2 rounded-lg border border-[#e5e7eb]/25 px-3 py-2 text-sm"
                            ><Download class="size-4" /> CSV</a
                        ><Link
                            :href="`/sections/${section.id}/reports/gradebook`"
                            class="inline-flex items-center gap-2 rounded-lg border border-[#e5e7eb]/25 px-3 py-2 text-sm"
                            ><BarChart3 class="size-4" /> Gradebook</Link
                        >
                    </div>
                </div>

                <section class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="item in filtered"
                        :key="item.id"
                        :href="`/sections/${section.id}/assessments/${item.id}`"
                        class="group relative overflow-hidden rounded-[1.35rem] border border-[#e5e7eb]/15 bg-[#ffffff] p-5 transition hover:-translate-y-1 hover:border-[#0071e3]/50 hover:shadow-xl dark:bg-[#1d1d1f]"
                    >
                        <span
                            class="absolute right-4 top-4 rounded-full px-3 py-1 font-mono text-[10px] font-bold uppercase tracking-widest"
                            :class="
                                item.type === 'exam'
                                    ? 'bg-[#0071e3] text-white'
                                    : item.type === 'quiz'
                                      ? 'bg-[#2997ff] text-[#1d1d1f]'
                                      : 'bg-[#f5f5f7] text-[#1d1d1f]'
                            "
                            >{{ item.type }}</span
                        >
                        <div class="mb-8 flex size-11 items-center justify-center rounded-2xl bg-[#1d1d1f] text-[#2997ff]">
                            <ClipboardCheck v-if="item.type !== 'exam'" class="size-5" /><Trophy v-else class="size-5" />
                        </div>
                        <h3 class="pr-16 font-serif text-2xl leading-tight group-hover:text-[#0071e3]">{{ item.title }}</h3>
                        <div class="mt-5 flex items-center justify-between text-sm text-[#86868b] dark:text-[#86868b]">
                            <span class="inline-flex items-center gap-1.5"><CalendarDays class="size-4" />{{ formatDate(item.conducted_on) }}</span
                            ><strong>{{ item.max_points }} pts</strong>
                        </div>
                        <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-[#e5e7eb] dark:bg-white/10">
                            <div
                                class="h-full bg-[#0071e3]"
                                :style="{ width: `${Math.min(100, (item.graded_count / Math.max(item.graded_count, 1)) * 100)}%` }"
                            />
                        </div>
                        <p class="mt-2 text-xs text-[#86868b]">{{ item.graded_count }} scores recorded</p>
                    </Link>
                    <div v-if="!filtered.length" class="col-span-full rounded-[1.5rem] border border-dashed border-[#e5e7eb]/30 p-14 text-center">
                        <p class="font-serif text-2xl">The ledger is ready.</p>
                        <p class="mt-2 text-sm text-[#86868b]">Create the first assessment for this section.</p>
                    </div>
                </section>
            </div>
        </main>
    </AppLayout>
</template>
