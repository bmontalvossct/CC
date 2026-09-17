<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, Download, FileText, Keyboard, LoaderCircle, TriangleAlert, UserX } from 'lucide-vue-next';
import { computed, nextTick, reactive, ref } from 'vue';

type Student = {
    id: number;
    student_number: string;
    full_name: string;
    seat_label: string | null;
    is_absent: boolean;
    score: string | null;
    absence_override: boolean;
};
type Assessment = {
    id: number;
    type: string;
    title: string;
    description?: string;
    conducted_on: string;
    max_points: string;
    attachment_path?: string;
    attachment_name?: string;
    attendance_session?: { session_date: string; starts_at: string; ends_at: string };
};
const props = defineProps<{
    section: { id: number; name: string; subject_code?: string; subject_title: string };
    assessment: Assessment;
    students: Student[];
    summary: { graded: number; missing: number; absent: number; average: number | null };
}>();

const includeAbsent = ref(false);
const formatDate = (value: string) =>
    new Intl.DateTimeFormat('en-PH', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' }).format(new Date(value));
const scores = reactive<Record<number, string>>(Object.fromEntries(props.students.map((student) => [student.id, student.score ?? ''])));
const status = reactive<Record<number, 'idle' | 'saving' | 'saved' | 'error'>>(
    Object.fromEntries(props.students.map((student) => [student.id, 'idle'])),
);
const errors = reactive<Record<number, string>>({});
const inputs = new Map<number, HTMLInputElement>();
const gradedCount = computed(() => Object.values(scores).filter((value) => value !== '').length);
const eligible = computed(() => props.students.filter((student) => !student.is_absent || includeAbsent.value));

const csrf = () =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] || '',
    );
const save = async (student: Student) => {
    if (student.is_absent && !includeAbsent.value) return;
    const raw = scores[student.id].trim();
    const numeric = raw === '' ? null : Number(raw);
    if (numeric !== null && (!Number.isFinite(numeric) || numeric < 0 || numeric > Number(props.assessment.max_points))) {
        status[student.id] = 'error';
        errors[student.id] = `Use 0–${props.assessment.max_points}.`;
        return;
    }
    status[student.id] = 'saving';
    errors[student.id] = '';
    try {
        const response = await fetch(`/sections/${props.section.id}/assessments/${props.assessment.id}/scores/${student.id}`, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrf() },
            body: JSON.stringify({ score: numeric, include_absent: includeAbsent.value }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || payload.errors?.score?.[0] || 'Could not save this score.');
        status[student.id] = 'saved';
        window.setTimeout(() => {
            if (status[student.id] === 'saved') status[student.id] = 'idle';
        }, 1800);
    } catch (error) {
        status[student.id] = 'error';
        errors[student.id] = error instanceof Error ? error.message : 'Could not save this score.';
    }
};
const move = async (student: Student, direction: number) => {
    await save(student);
    const index = eligible.value.findIndex((item) => item.id === student.id);
    const target = eligible.value[index + direction];
    if (target)
        await nextTick(() => {
            inputs.get(target.id)?.focus();
            inputs.get(target.id)?.select();
        });
};
const handleKey = (event: KeyboardEvent, student: Student) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        void move(student, event.shiftKey ? -1 : 1);
    }
    if (event.key === 'Tab') {
        event.preventDefault();
        void move(student, event.shiftKey ? -1 : 1);
    }
};
</script>

<template>
    <Head :title="`${assessment.title} · Scores`" />
    <AppLayout
        :breadcrumbs="[
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Assessments', href: `/sections/${section.id}/assessments` },
            { title: assessment.title, href: '#' },
        ]"
    >
        <main class="min-h-full bg-[#f3efe4] text-[#20251f] dark:bg-[#111712] dark:text-[#f2efe5]">
            <div class="mx-auto max-w-7xl p-5 sm:p-8">
                <Link
                    :href="`/sections/${section.id}/assessments`"
                    class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#526157] hover:text-[#b7552d]"
                    ><ArrowLeft class="size-4" /> Back to assessments</Link
                >
                <header
                    class="grid gap-5 rounded-[2rem] border border-[#283b2e]/20 bg-[#fffdf6] p-6 shadow-sm dark:bg-[#1b241d] lg:grid-cols-[1fr_auto] lg:p-9"
                >
                    <div>
                        <div class="mb-4 flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full bg-[#173c2a] px-3 py-1 font-mono text-[10px] font-bold uppercase tracking-[.2em] text-[#f4c95d]"
                                >{{ assessment.type }}</span
                            ><span class="text-sm text-[#687067]"
                                >{{ formatDate(assessment.conducted_on) }} · {{ assessment.max_points }} points</span
                            >
                        </div>
                        <h1 class="font-serif text-4xl leading-tight sm:text-5xl">{{ assessment.title }}</h1>
                        <p v-if="assessment.description" class="mt-3 max-w-2xl text-sm leading-6 text-[#687067] dark:text-[#bac2ba]">
                            {{ assessment.description }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-start gap-2 lg:justify-end">
                        <a
                            v-if="assessment.attachment_path"
                            :href="`/sections/${section.id}/assessments/${assessment.id}/attachment`"
                            class="inline-flex items-center gap-2 rounded-xl border border-[#283b2e]/20 px-4 py-2.5 text-sm font-semibold"
                            ><FileText class="size-4" /> {{ assessment.attachment_name || 'Attachment' }}</a
                        >
                        <a
                            :href="`/sections/${section.id}/assessments/${assessment.id}/export`"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#173c2a] px-4 py-2.5 text-sm font-semibold text-white"
                            ><Download class="size-4" /> Export</a
                        >
                    </div>
                </header>

                <section class="my-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-2xl bg-[#173c2a] p-4 text-white">
                        <p class="text-xs uppercase tracking-widest text-[#b9cfbf]">Recorded</p>
                        <p class="mt-2 font-serif text-3xl">
                            {{ gradedCount }}<span class="text-base text-[#b9cfbf]"> / {{ students.length }}</span>
                        </p>
                    </div>
                    <div class="rounded-2xl border border-[#283b2e]/15 bg-[#fffdf6] p-4 dark:bg-[#1b241d]">
                        <p class="text-xs uppercase tracking-widest text-[#687067]">Average</p>
                        <p class="mt-2 font-serif text-3xl">{{ summary.average ?? '—' }}</p>
                    </div>
                    <div class="rounded-2xl border border-[#283b2e]/15 bg-[#fffdf6] p-4 dark:bg-[#1b241d]">
                        <p class="text-xs uppercase tracking-widest text-[#687067]">Missing</p>
                        <p class="mt-2 font-serif text-3xl">{{ summary.missing }}</p>
                    </div>
                    <div class="rounded-2xl bg-[#b7552d] p-4 text-white">
                        <p class="text-xs uppercase tracking-widest text-[#fbd8c9]">Absent</p>
                        <p class="mt-2 font-serif text-3xl">{{ summary.absent }}</p>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[1.5rem] border border-[#283b2e]/20 bg-[#fffdf6] dark:bg-[#1b241d]">
                    <div class="flex flex-col gap-4 border-b border-[#283b2e]/15 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="font-serif text-2xl">Chair-order score entry</h2>
                            <p class="mt-1 flex items-center gap-2 text-xs text-[#687067]">
                                <Keyboard class="size-4" /> Tab, Shift+Tab, or Enter saves and moves through the room.
                            </p>
                        </div>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl bg-[#eee8d8] px-4 py-3 text-sm dark:bg-white/10"
                            ><input
                                v-model="includeAbsent"
                                type="checkbox"
                                class="rounded border-[#8b8e86] text-[#b7552d] focus:ring-[#b7552d]"
                            /><span
                                ><strong>Include absent students</strong
                                ><small class="block text-[#687067]">Scores will be marked as an override.</small></span
                            ></label
                        >
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[700px] border-collapse">
                            <thead>
                                <tr class="border-b border-[#283b2e]/15 text-left font-mono text-[10px] uppercase tracking-[.16em] text-[#687067]">
                                    <th class="w-24 px-5 py-4">Chair</th>
                                    <th class="px-3 py-4">Student</th>
                                    <th class="w-52 px-3 py-4">Score / {{ assessment.max_points }}</th>
                                    <th class="w-36 px-5 py-4">Save status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="student in students"
                                    :key="student.id"
                                    class="border-b border-[#283b2e]/10 transition last:border-0"
                                    :class="student.is_absent && !includeAbsent ? 'bg-[#b7552d]/5 opacity-70' : 'hover:bg-[#f4c95d]/10'"
                                >
                                    <td class="px-5 py-3">
                                        <span class="rounded-lg bg-[#173c2a] px-2.5 py-1.5 font-mono text-xs font-bold text-[#f4c95d]">{{
                                            student.seat_label || '—'
                                        }}</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <strong class="block">{{ student.full_name }}</strong
                                        ><span class="text-xs text-[#687067]">{{ student.student_number }}</span
                                        ><span
                                            v-if="student.is_absent"
                                            class="ml-2 inline-flex items-center gap-1 text-xs font-semibold text-[#b7552d]"
                                            ><UserX class="size-3" /> Absent</span
                                        >
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            :ref="
                                                (el) => {
                                                    if (el) inputs.set(student.id, el as HTMLInputElement);
                                                }
                                            "
                                            v-model="scores[student.id]"
                                            :disabled="student.is_absent && !includeAbsent"
                                            type="number"
                                            min="0"
                                            :max="assessment.max_points"
                                            step="0.01"
                                            inputmode="decimal"
                                            class="w-full rounded-xl border-[#aaa99f] bg-white text-lg font-bold tabular-nums focus:border-[#b7552d] focus:ring-[#b7552d] disabled:cursor-not-allowed disabled:bg-[#e7e1d4] dark:bg-[#111712]"
                                            :aria-label="`Score for ${student.full_name}`"
                                            @blur="save(student)"
                                            @keydown="handleKey($event, student)"
                                        />
                                        <p v-if="errors[student.id]" class="mt-1 text-xs text-red-700">{{ errors[student.id] }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-xs">
                                        <span v-if="status[student.id] === 'saving'" class="inline-flex items-center gap-1.5 text-[#687067]"
                                            ><LoaderCircle class="size-3.5 animate-spin" /> Saving</span
                                        ><span
                                            v-else-if="status[student.id] === 'saved'"
                                            class="inline-flex items-center gap-1.5 font-semibold text-emerald-700"
                                            ><Check class="size-3.5" /> Saved</span
                                        ><span
                                            v-else-if="status[student.id] === 'error'"
                                            class="inline-flex items-center gap-1.5 font-semibold text-red-700"
                                            ><TriangleAlert class="size-3.5" /> Retry</span
                                        ><span v-else-if="student.is_absent" class="text-[#b7552d]">Skipped</span
                                        ><span v-else class="text-[#8a8f88]">Ready</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </AppLayout>
</template>
