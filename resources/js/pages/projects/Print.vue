<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

type Student = {
    id: number;
    student_number: string;
    first_name: string;
    last_name: string;
    middle_name?: string;
    seat_label?: string;
};

type Group = {
    id: number;
    group_number: number;
    name: string;
    topic: string | null;
    description?: string | null;
    students: Student[];
};

type Project = {
    id: number;
    type: 'project' | 'reporting' | 'group_activity';
    format?: 'group' | 'individual';
    title: string;
    description: string | null;
    conducted_on: string | null;
    groups: Group[];
};

defineProps<{
    section: { id: number; name: string; subject_code?: string; subject_title: string };
    project: Project;
}>();

const formatDate = (val: string | null) => {
    if (!val) return '';
    return new Intl.DateTimeFormat('en-PH', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' }).format(new Date(val));
};

onMounted(() => {
    setTimeout(() => {
        window.print();
    }, 500);
});
</script>

<template>
    <Head :title="`Print: ${project.title} · ${section.name}`" />

    <div class="min-h-screen bg-white p-8 font-sans text-slate-900 print:p-0">
        <!-- Header -->
        <header class="mb-6 border-b-2 border-slate-900 pb-4">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-slate-900 px-2 py-0.5 font-mono text-xs font-bold uppercase text-white">
                            {{ section.subject_code || 'Activity' }}
                        </span>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-600">{{ section.name }}</span>
                    </div>
                    <h1 class="mt-2 text-2xl font-black text-slate-900">{{ project.title }}</h1>
                    <p class="mt-0.5 text-xs text-slate-600">{{ section.subject_title }}</p>
                </div>

                <div class="text-right">
                    <span class="inline-block rounded border border-slate-900 px-2.5 py-1 text-xs font-black uppercase">
                        {{
                            project.type === 'group_activity'
                                ? 'Group Activity'
                                : project.type === 'project'
                                  ? 'Project Groups'
                                  : project.format === 'individual'
                                    ? 'Individual Reporting'
                                    : 'Reporting Groups'
                        }}
                    </span>
                    <p v-if="project.conducted_on" class="mt-1 font-mono text-xs text-slate-600">Date: {{ formatDate(project.conducted_on) }}</p>
                </div>
            </div>

            <!-- Scope / Description if Project -->
            <div v-if="project.description" class="mt-4 rounded border border-slate-200 bg-slate-50 p-3 text-xs">
                <span class="block text-[10px] font-bold uppercase text-slate-500">
                    {{
                        project.type === 'group_activity'
                            ? 'Group Activity Guidelines & Objectives:'
                            : project.type === 'project'
                              ? 'Project Description & Objectives:'
                              : project.format === 'individual'
                                ? 'Presentation Guidelines:'
                                : 'General Instructions:'
                    }}
                </span>
                <p class="mt-1 leading-relaxed text-slate-700">{{ project.description }}</p>
            </div>
        </header>

        <!-- Individual Reporting Presentation Ledger -->
        <div v-if="project.format === 'individual'" class="overflow-hidden rounded-lg border border-slate-300">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100 font-bold uppercase text-slate-600">
                    <tr class="border-b border-slate-300 text-[11px]">
                        <th class="w-10 px-3 py-2 text-center">#</th>
                        <th class="px-3 py-2">Presenter Name</th>
                        <th class="w-28 px-3 py-2">Student ID</th>
                        <th class="w-16 px-3 py-2 text-center">Seat</th>
                        <th class="px-3 py-2">Assigned Presentation Topic & Description</th>
                        <th class="w-24 px-3 py-2 text-center">Score</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="(group, idx) in project.groups" :key="group.id" class="break-inside-avoid">
                        <td class="px-3 py-2 text-center font-mono text-slate-400">{{ idx + 1 }}</td>
                        <td class="px-3 py-2 font-bold text-slate-900">
                            {{
                                group.students[0]
                                    ? `${group.students[0].last_name}, ${group.students[0].first_name} ${group.students[0].middle_name || ''}`
                                    : group.name
                            }}
                        </td>
                        <td class="px-3 py-2 font-mono text-slate-600">
                            {{ group.students[0]?.student_number || '—' }}
                        </td>
                        <td class="px-3 py-2 text-center font-mono text-slate-500">
                            {{ group.students[0]?.seat_label || '—' }}
                        </td>
                        <td class="px-3 py-2 text-slate-800">
                            <div class="font-bold text-slate-900">{{ group.topic || '—' }}</div>
                            <div v-if="group.description" class="mt-0.5 text-[10px] text-slate-500">{{ group.description }}</div>
                        </td>
                        <td class="px-3 py-2 text-center font-mono font-bold text-slate-900">
                            {{ group.score ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Groups Grid (For Group Project & Group Reporting & Group Activity) -->
        <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 print:grid-cols-2">
            <div v-for="group in project.groups" :key="group.id" class="break-inside-avoid rounded-lg border border-slate-300 bg-white p-4">
                <div class="mb-3 flex items-center justify-between border-b border-slate-200 pb-2">
                    <h2 class="text-sm font-black text-slate-900">{{ group.name }}</h2>
                    <span class="font-mono text-xs font-bold text-slate-500"> {{ group.students.length }} members </span>
                </div>

                <!-- Group Topic -->
                <div class="mb-3 rounded bg-slate-100 p-2.5 text-xs">
                    <span class="block text-[10px] font-bold uppercase text-slate-500">
                        {{
                            project.type === 'group_activity'
                                ? 'Activity Topic / Focus:'
                                : project.type === 'reporting'
                                  ? 'Assigned Presentation Topic:'
                                  : 'Project Focus:'
                        }}
                    </span>
                    <p class="mt-0.5 font-bold text-slate-900">
                        {{
                            group.topic ||
                            (project.type === 'project' || project.type === 'group_activity' ? project.title : 'Topic not yet specified')
                        }}
                    </p>
                    <p v-if="group.description" class="mt-1 border-t border-slate-200/80 pt-1 text-[11px] text-slate-600">
                        {{ group.description }}
                    </p>
                </div>

                <!-- Members Roster Table -->
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 text-[10px] uppercase text-slate-500">
                            <th class="w-6 py-1">#</th>
                            <th class="py-1">Student Name</th>
                            <th class="w-24 py-1 text-right">Student ID</th>
                            <th class="w-16 py-1 text-right">Chair</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(student, idx) in group.students" :key="student.id" class="border-b border-slate-100 last:border-0">
                            <td class="py-1.5 font-mono text-slate-400">{{ idx + 1 }}.</td>
                            <td class="py-1.5 font-bold text-slate-800">
                                {{ student.last_name }}, {{ student.first_name }} {{ student.middle_name || '' }}
                            </td>
                            <td class="py-1.5 text-right font-mono text-slate-600">{{ student.student_number }}</td>
                            <td class="py-1.5 text-right font-mono text-slate-500">{{ student.seat_label || '—' }}</td>
                        </tr>
                        <tr v-if="!group.students.length">
                            <td colspan="4" class="py-3 text-center italic text-slate-400">No students assigned</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-10 border-t border-slate-200 pt-4 text-center text-[10px] text-slate-400">
            Generated with ClassCheck · {{ section.subject_code }} {{ section.name }}
        </footer>
    </div>
</template>

<style scoped>
@media print {
    @page {
        margin: 1cm;
    }
}
</style>
