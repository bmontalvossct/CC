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
    students: Student[];
};

type Project = {
    id: number;
    type: 'project' | 'reporting';
    title: string;
    description: string | null;
    conducted_on: string | null;
    groups: Group[];
};

const props = defineProps<{
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
                        {{ project.type === 'project' ? 'Project Groups' : 'Reporting Groups' }}
                    </span>
                    <p v-if="project.conducted_on" class="mt-1 font-mono text-xs text-slate-600">Date: {{ formatDate(project.conducted_on) }}</p>
                </div>
            </div>

            <!-- Scope / Description if Project -->
            <div v-if="project.description" class="mt-4 rounded border border-slate-200 bg-slate-50 p-3 text-xs">
                <span class="block text-[10px] font-bold uppercase text-slate-500">
                    {{ project.type === 'project' ? 'Project Description & Objectives:' : 'General Instructions:' }}
                </span>
                <p class="mt-1 leading-relaxed text-slate-700">{{ project.description }}</p>
            </div>
        </header>

        <!-- Groups Grid -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 print:grid-cols-2">
            <div v-for="group in project.groups" :key="group.id" class="break-inside-avoid rounded-lg border border-slate-300 bg-white p-4">
                <div class="mb-3 flex items-center justify-between border-b border-slate-200 pb-2">
                    <h2 class="text-sm font-black text-slate-900">{{ group.name }}</h2>
                    <span class="font-mono text-xs font-bold text-slate-500"> {{ group.students.length }} members </span>
                </div>

                <!-- Group Topic -->
                <div class="mb-3 rounded bg-slate-100 p-2.5 text-xs">
                    <span class="block text-[10px] font-bold uppercase text-slate-500">
                        {{ project.type === 'reporting' ? 'Assigned Presentation Topic:' : 'Project Focus:' }}
                    </span>
                    <p class="mt-0.5 font-medium text-slate-900">
                        {{ group.topic || (project.type === 'project' ? project.title : 'Topic not yet specified') }}
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
