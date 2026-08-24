<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, FolderKanban, LoaderCircle, Plus, Trash2, Users } from 'lucide-vue-next';
import { ref } from 'vue';

type Project = {
    id: number;
    type: 'project' | 'reporting' | 'group_activity';
    format?: 'group' | 'individual';
    title: string;
    description: string | null;
    conducted_on: string | null;
    max_points: string | number | null;
    groups_count: number;
    members_count: number;
};

const props = defineProps<{
    section: { id: number; name: string; subject_code?: string; subject_title: string };
    projects: Project[];
}>();

const creating = ref(false);
const deleteProjectTarget = ref<Project | null>(null);
const isDeleting = ref(false);

const confirmDeleteProject = () => {
    if (!deleteProjectTarget.value) return;
    isDeleting.value = true;
    router.delete(`/sections/${props.section.id}/projects/${deleteProjectTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            deleteProjectTarget.value = null;
        },
    });
};

const form = useForm({
    type: 'group_activity' as 'group_activity' | 'reporting' | 'project',
    format: 'group' as 'group' | 'individual',
    title: '',
    description: '',
    conducted_on: new Date().toISOString().slice(0, 10),
    max_points: '' as number | '',
    group_count: 4,
    randomize: true,
});

const formatDate = (value: string | null) => {
    if (!value) return 'No date';
    return new Intl.DateTimeFormat('en-PH', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' }).format(new Date(value));
};

const submit = () => {
    form.post(`/sections/${props.section.id}/projects`, {
        onSuccess: () => {
            creating.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Head :title="`Projects & Reporting · ${section.name} - ClassCheck`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Assessments', href: `/sections/${section.id}/assessments` },
            { title: 'Projects & Reporting', href: `/sections/${section.id}/projects` },
        ]"
    >
        <main class="page-enter mx-auto flex w-full max-w-[1360px] flex-1 flex-col gap-6 px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <!-- Header Section -->
            <header
                class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 shadow-sm sm:p-8"
            >
                <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="badge-primary font-mono font-bold">{{ section.subject_code || 'Activities' }}</span>
                            <span class="badge-muted">{{ section.name }}</span>
                        </div>
                        <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Group Projects & Reporting</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Organize student groups, randomize members with fair uneven distribution, and assign presentation topics.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="`/sections/${section.id}/assessments`"
                            prefetch="hover"
                            class="shadow-xs inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-semibold text-foreground transition-colors hover:bg-secondary"
                        >
                            <ArrowLeft class="size-4 text-muted-foreground" />
                            <span>Assessments</span>
                        </Link>
                        <button class="ink-button !h-10 !rounded-xl" @click="creating = !creating">
                            <Plus class="size-4" />
                            <span>New Project / Report</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Creation Form Panel -->
            <section v-if="creating" class="paper-card p-6 duration-200 animate-in fade-in zoom-in-95 md:p-8">
                <div class="mb-5 flex items-center justify-between border-b border-border/80 pb-4">
                    <div class="flex items-center gap-2.5">
                        <FolderKanban class="size-5 text-primary" />
                        <h2 class="text-xl font-bold">Create Group Project / Reporting Activity</h2>
                    </div>
                    <button class="text-xs font-semibold text-muted-foreground hover:text-foreground" @click="creating = false">Cancel</button>
                </div>

                <form class="grid gap-5 lg:grid-cols-12" @submit.prevent="submit">
                    <!-- Informational Banner for Group Activities -->
                    <div
                        v-if="form.type === 'group_activity'"
                        class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3.5 text-xs text-emerald-800 dark:text-emerald-300 lg:col-span-12"
                    >
                        <p class="font-bold">Group Activity (Recorded in Activities):</p>
                        <p class="mt-0.5 text-[11px] leading-relaxed">
                            Organize students into collaborative groups. All recorded scores will be calculated directly under the <strong>Activities</strong> category in the Gradebook.
                        </p>
                    </div>

                    <label :class="form.type === 'reporting' ? 'lg:col-span-3' : 'lg:col-span-4'">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Activity Type</span>
                        <select
                            v-model="form.type"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <option value="group_activity">Group Activity (Recorded in Activities)</option>
                            <option value="reporting">Reporting (Presentations & Topics)</option>
                            <option value="project">Project (Unified Scope & Deliverables)</option>
                        </select>
                    </label>

                    <label v-if="form.type === 'reporting'" class="lg:col-span-3">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Reporting Format</span>
                        <select
                            v-model="form.format"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <option value="group">Group (Students in groups)</option>
                            <option value="individual">Individual (1 topic per student)</option>
                        </select>
                    </label>

                    <label :class="form.type === 'reporting' ? 'lg:col-span-6' : 'lg:col-span-8'">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Title</span>
                        <input
                            v-model="form.title"
                            required
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                            :placeholder="
                                form.type === 'group_activity'
                                    ? 'e.g. Laboratory Activity 1 - Data Structures'
                                    : form.type === 'reporting'
                                      ? (form.format === 'individual' ? 'e.g. Individual Research Presentations' : 'e.g. Chapter 4 Group Case Study Presentations')
                                      : 'e.g. Capstone Project Final Deliverable'
                            "
                        />
                        <small v-if="form.errors.title" class="mt-1 block text-xs text-rose-600">{{ form.errors.title }}</small>
                    </label>

                    <label class="lg:col-span-12">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            {{
                                form.type === 'group_activity'
                                    ? 'Group Activity Guidelines & Objectives (Applies to all groups)'
                                    : form.type === 'project'
                                      ? 'Project Description & Objectives (Applies to all groups)'
                                      : (form.format === 'individual' ? 'Individual Presentation Guidelines / Instructions' : 'Group Reporting Guidelines / Instructions')
                            }}
                        </span>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            :placeholder="
                                form.type === 'group_activity'
                                    ? 'Instructions, objectives, or rubrics for the student activity groups...'
                                    : form.format === 'individual'
                                      ? 'Instructions or rubrics for individual presenters...'
                                      : 'Instructions or rubrics for presentation groups...'
                            "
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <label :class="form.format === 'individual' ? 'lg:col-span-6' : 'lg:col-span-3'">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Date Conducted / Due</span>
                        <input
                            v-model="form.conducted_on"
                            type="date"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <label :class="form.format === 'individual' ? 'lg:col-span-6' : 'lg:col-span-3'">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground"
                            >Max Points <em class="font-normal normal-case text-muted-foreground">(optional)</em></span
                        >
                        <input
                            v-model="form.max_points"
                            type="number"
                            step="0.01"
                            placeholder="e.g. 50"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <template v-if="form.format !== 'individual'">
                        <label class="lg:col-span-3">
                            <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Number of Initial Groups</span>
                            <input
                                v-model.number="form.group_count"
                                type="number"
                                min="1"
                                max="50"
                                class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                            />
                        </label>

                        <div class="flex items-center pt-6 lg:col-span-3">
                            <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-foreground">
                                <input v-model="form.randomize" type="checkbox" class="size-4 rounded border-border text-primary focus:ring-primary" />
                                <span>Auto-assign active students</span>
                            </label>
                        </div>
                    </template>

                    <div class="flex items-center justify-end gap-3 pt-2 lg:col-span-12">
                        <button type="button" class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground" @click="creating = false">
                            Cancel
                        </button>
                        <button :disabled="form.processing" class="ink-button !rounded-xl text-xs font-bold">
                            {{ form.processing ? 'Creating…' : 'Create Activity' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- Projects Grid List -->
            <section class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="item in projects"
                    :key="item.id"
                    :href="`/sections/${section.id}/projects/${item.id}`"
                    class="paper-card group relative flex flex-col justify-between overflow-hidden p-6 transition-all hover:border-primary/50 hover:shadow-md"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span
                                class="rounded-full px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-white"
                                :class="
                                    item.type === 'group_activity'
                                        ? 'bg-emerald-800'
                                        : item.type === 'project'
                                          ? 'bg-emerald-800'
                                          : item.format === 'individual'
                                            ? 'bg-indigo-800'
                                            : 'bg-amber-800'
                                "
                            >
                                {{
                                    item.type === 'group_activity'
                                        ? 'Group Activity'
                                        : item.type === 'project'
                                          ? 'Project'
                                          : item.format === 'individual'
                                            ? 'Individual Reporting'
                                            : 'Group Reporting'
                                }}
                            </span>
                            <div class="flex items-center gap-2">
                                <span v-if="item.max_points" class="font-mono text-xs font-medium text-foreground"> {{ item.max_points }} pts </span>
                                <button
                                    type="button"
                                    class="grid size-7 place-items-center rounded-lg text-muted-foreground/60 transition-colors hover:bg-rose-500/10 hover:text-rose-600 dark:hover:text-rose-400"
                                    :title="`Delete ${item.type} misentry`"
                                    @click.stop.prevent="deleteProjectTarget = item"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <h3 class="mt-4 text-xl font-bold tracking-tight text-foreground transition-colors group-hover:text-primary">
                            {{ item.title }}
                        </h3>

                        <p v-if="item.description" class="mt-2 line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                            {{ item.description }}
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <CalendarDays class="size-3.5" /> {{ formatDate(item.conducted_on) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-between border-t border-border/80 pt-4 text-xs font-medium text-muted-foreground">
                        <div class="flex items-center gap-1.5">
                            <FolderKanban class="size-3.5 text-primary" />
                            <span>{{ item.groups_count }} groups</span>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <Users class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                            <span>{{ item.members_count }} assigned members</span>
                        </div>
                    </div>
                </Link>

                <div
                    v-if="!projects.length"
                    class="col-span-full rounded-2xl border border-dashed border-border/80 bg-card p-14 text-center shadow-sm"
                >
                    <FolderKanban class="mx-auto size-8 text-muted-foreground" />
                    <h3 class="mt-4 text-xl font-bold">No projects or reporting activities yet</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Click "New Project / Report" above to set up your first group activity.</p>
                </div>
            </section>
        </main>

        <!-- Delete Project Confirmation Modal -->
        <div
            v-if="deleteProjectTarget"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
            @click.self="deleteProjectTarget = null"
        >
            <div
                class="paper-card relative w-full max-w-lg border-border/90 p-6 shadow-2xl duration-200 animate-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                :aria-label="`Delete ${deleteProjectTarget.title}`"
            >
                <div class="flex items-start gap-4">
                    <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-rose-500/15 text-rose-700 dark:text-rose-400">
                        <Trash2 class="size-6" />
                    </div>
                    <div class="flex-1">
                        <span class="eyebrow text-rose-700 dark:text-rose-400">Permanent Deletion</span>
                        <h3 class="mt-1 text-xl font-bold text-foreground">Delete {{ deleteProjectTarget.title }}?</h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ deleteProjectTarget.type === 'project' ? 'PROJECT' : 'REPORTING' }} · {{ deleteProjectTarget.groups_count }} groups
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-rose-500/20 bg-rose-500/5 p-4 text-xs leading-relaxed text-foreground">
                    <p class="font-bold text-rose-700 dark:text-rose-400">
                        Warning: This will permanently delete this {{ deleteProjectTarget.type }} activity:
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-4 text-muted-foreground">
                        <li>The project configuration and all {{ deleteProjectTarget.groups_count }} group assignments</li>
                        <li>Assigned group topics, notes, and all recorded member grades</li>
                    </ul>
                    <p class="mt-2 font-bold text-rose-700 dark:text-rose-400">This action cannot be undone.</p>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-border/80 pt-4">
                    <button
                        type="button"
                        class="shadow-xs inline-flex h-10 items-center justify-center rounded-xl border border-border bg-card px-5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                        :disabled="isDeleting"
                        @click="deleteProjectTarget = null"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-5 text-xs font-bold text-white shadow-sm transition-all hover:bg-rose-700 disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="confirmDeleteProject"
                    >
                        <LoaderCircle v-if="isDeleting" class="size-4 animate-spin" />
                        <Trash2 v-else class="size-4" />
                        <span>{{ isDeleting ? 'Deleting…' : `Yes, Delete ${deleteProjectTarget.type}` }}</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
