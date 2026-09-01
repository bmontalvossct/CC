<script setup lang="ts">
import GettingStartedChecklist, { type OnboardingData } from '@/components/onboarding/GettingStartedChecklist.vue';
import OnboardingModal from '@/components/onboarding/OnboardingModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, CalendarCheck2, CheckCircle2, ClipboardList, GraduationCap, HelpCircle, LayoutGrid, Plus, Sparkles, Users } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

interface SectionCard {
    id: number;
    name: string;
    subject: string;
    term: string;
    students: number;
    seats: number;
    attendance_rate: number | null;
}
interface Stats {
    sections: number;
    students: number;
    meetings: number;
    attendance_rate: number | null;
}

const props = withDefaults(
    defineProps<{
        stats?: Stats;
        sections?: SectionCard[];
        teacherName?: string;
        currentTerm?: {
            id?: number;
            name?: string;
            school_year?: string;
            starts_on?: string;
            ends_on?: string;
        } | null;
        onboarding?: OnboardingData;
    }>(),
    {
        stats: () => ({ sections: 0, students: 0, meetings: 0, attendance_rate: null }),
        sections: () => [],
        teacherName: 'Teacher',
        currentTerm: null,
        onboarding: () => ({
            has_academic_term: false,
            has_section: false,
            has_seating_layout: false,
            has_students: false,
            has_attendance: false,
            first_section_id: null,
        }),
    },
);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Overview', href: '/dashboard' }];

const showOnboardingTour = ref(false);
const activeTeacherName = ref(props.teacherName || 'Teacher');
const activeTerm = ref(props.currentTerm);

watch(
    () => props.teacherName,
    (val) => {
        if (val) activeTeacherName.value = val;
    },
);

watch(
    () => props.currentTerm,
    (val) => {
        activeTerm.value = val;
    },
);

const handleOnboardingUpdated = (payload: { teacherName: string; term: any }) => {
    activeTeacherName.value = payload.teacherName;
    activeTerm.value = payload.term;
    router.reload({ only: ['teacherName', 'currentTerm', 'onboarding'] });
};

onMounted(() => {
    // Auto-open tour on first visit for brand new teachers
    const tourSeen = localStorage.getItem('classcheck_onboarding_tour_seen');
    if (!tourSeen && !props.onboarding.has_section) {
        showOnboardingTour.value = true;
        localStorage.setItem('classcheck_onboarding_tour_seen', 'true');
    }
});
</script>

<template>
    <Head title="Overview - ClassCheck" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="page-enter mx-auto w-full max-w-[1360px] px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <!-- Header Section -->
            <section
                class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 shadow-sm sm:p-8 md:p-10"
            >
                <div class="pointer-events-none absolute right-0 top-0 -mr-8 -mt-8 size-64 rounded-full bg-primary/5 blur-3xl" />
                <div class="relative flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary"
                        >
                            <Sparkles class="size-3.5" />
                            <span>Teacher workspace</span>
                        </div>
                        <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl md:text-5xl">Good day, {{ teacherName.split(' ')[0] }}</h1>
                        <p class="mt-2 max-w-2xl text-sm text-muted-foreground sm:text-base">
                            Here is your classroom overview. Manage seats, run roll-call, and log assessments in real time.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            class="secondary-button !h-9 !px-3 text-xs"
                            @click="showOnboardingTour = true"
                        >
                            <HelpCircle class="size-3.5 text-muted-foreground" />
                            <span>Guided Tour</span>
                        </button>
                        <Link href="/sections/create" prefetch="hover" class="ink-button">
                            <Plus class="size-4" />
                            <span>New section</span>
                        </Link>
                        <Link href="/sections" prefetch="hover" class="secondary-button">
                            <span>View all sections</span>
                        </Link>
                    </div>
                </div>
            </section>

            <!-- Getting Started Onboarding Checklist -->
            <GettingStartedChecklist
                :onboarding="onboarding"
                @open-tour="showOnboardingTour = true"
            />

            <!-- KPI Summary Cards -->
            <section class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Key Performance Indicators">
                <article class="paper-card group relative overflow-hidden p-6 hover:border-primary/40 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Active sections</span>
                        <span
                            class="grid size-10 place-items-center rounded-xl bg-blue-500/10 text-blue-600 transition-transform group-hover:scale-110 dark:text-blue-400"
                        >
                            <GraduationCap class="size-5" />
                        </span>
                    </div>
                    <div class="mt-6 flex items-baseline justify-between">
                        <p class="text-3xl font-extrabold tracking-tight">{{ stats.sections }}</p>
                        <span class="text-xs font-medium text-muted-foreground">courses</span>
                    </div>
                </article>

                <article class="paper-card group relative overflow-hidden p-6 hover:border-primary/40 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Enrolled students</span>
                        <span
                            class="grid size-10 place-items-center rounded-xl bg-emerald-500/10 text-emerald-600 transition-transform group-hover:scale-110 dark:text-emerald-400"
                        >
                            <Users class="size-5" />
                        </span>
                    </div>
                    <div class="mt-6 flex items-baseline justify-between">
                        <p class="text-3xl font-extrabold tracking-tight">{{ stats.students }}</p>
                        <span class="text-xs font-medium text-muted-foreground">seated</span>
                    </div>
                </article>

                <article class="paper-card group relative overflow-hidden p-6 hover:border-primary/40 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Meetings logged</span>
                        <span
                            class="grid size-10 place-items-center rounded-xl bg-purple-500/10 text-purple-600 transition-transform group-hover:scale-110 dark:text-purple-400"
                        >
                            <CalendarCheck2 class="size-5" />
                        </span>
                    </div>
                    <div class="mt-6 flex items-baseline justify-between">
                        <p class="text-3xl font-extrabold tracking-tight">{{ stats.meetings }}</p>
                        <span class="text-xs font-medium text-muted-foreground">sessions</span>
                    </div>
                </article>

                <article class="paper-card group relative overflow-hidden p-6 hover:border-primary/40 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Attendance rate</span>
                        <span
                            class="grid size-10 place-items-center rounded-xl bg-amber-500/10 text-amber-600 transition-transform group-hover:scale-110 dark:text-amber-400"
                        >
                            <CheckCircle2 class="size-5" />
                        </span>
                    </div>
                    <div class="mt-6 flex items-baseline justify-between">
                        <p class="text-3xl font-extrabold tracking-tight">
                            {{ stats.attendance_rate === null ? '—' : stats.attendance_rate + '%' }}
                        </p>
                        <span class="text-xs font-medium text-muted-foreground">overall</span>
                    </div>
                </article>
            </section>

            <!-- Sections List Section -->
            <section class="mt-12">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <span class="eyebrow">Classrooms</span>
                        <h2 class="mt-1 text-2xl font-bold tracking-tight">Your active sections</h2>
                    </div>
                    <Link
                        href="/sections"
                        prefetch="hover"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
                    >
                        <span>View all</span>
                        <ArrowRight class="size-4" />
                    </Link>
                </div>

                <div v-if="sections.length" class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="section in sections"
                        :key="section.id"
                        class="paper-card group flex flex-col justify-between hover:border-primary/50 hover:shadow-lg"
                    >
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-xl font-bold tracking-tight transition-colors group-hover:text-primary">
                                        {{ section.name }}
                                    </h3>
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-md bg-primary/10 px-2 py-0.5 font-mono text-xs font-bold text-primary"
                                        >
                                            {{ section.subject }}
                                        </span>
                                    </div>
                                    <p class="mt-1 line-clamp-1 text-xs text-muted-foreground">{{ section.term }}</p>
                                </div>
                                <Link
                                    :href="`/sections/${section.id}`"
                                    prefetch="hover"
                                    class="grid size-9 shrink-0 place-items-center rounded-full border border-border bg-secondary/50 text-muted-foreground transition-colors group-hover:border-primary group-hover:bg-primary group-hover:text-white"
                                    aria-label="Open section details"
                                >
                                    <ArrowRight class="size-4" />
                                </Link>
                            </div>

                            <!-- Metrics Strip -->
                            <div class="mt-6 grid grid-cols-3 gap-2 rounded-lg bg-secondary/60 p-3 text-center text-xs">
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Students</span>
                                    <strong class="mt-1 block text-sm font-bold">{{ section.students }}</strong>
                                </div>
                                <div class="border-x border-border/80">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Seats</span>
                                    <strong class="mt-1 block text-sm font-bold">{{ section.seats }}</strong>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Attendance</span>
                                    <strong
                                        class="mt-1 block text-sm font-bold"
                                        :class="
                                            section.attendance_rate !== null && section.attendance_rate >= 80
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : ''
                                        "
                                    >
                                        {{ section.attendance_rate === null ? '—' : section.attendance_rate + '%' }}
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <!-- Action shortcuts strip -->
                        <div class="mt-6 flex items-center gap-2 border-t border-border/80 pt-4">
                            <Link
                                :href="`/sections/${section.id}/attendance`"
                                prefetch="hover"
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-border bg-card px-2.5 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-secondary hover:text-primary"
                            >
                                <CalendarCheck2 class="size-3.5" />
                                <span>Attendance</span>
                            </Link>
                            <Link
                                :href="`/sections/${section.id}/assessments`"
                                prefetch="hover"
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-border bg-card px-2.5 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-secondary hover:text-primary"
                            >
                                <ClipboardList class="size-3.5" />
                                <span>Scores</span>
                            </Link>
                            <Link
                                :href="`/sections/${section.id}`"
                                prefetch="hover"
                                class="inline-flex items-center justify-center rounded-lg border border-border bg-card p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary"
                                title="Classroom floor"
                            >
                                <LayoutGrid class="size-4" />
                            </Link>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-6 rounded-2xl border border-dashed border-border/80 bg-card p-12 text-center shadow-sm">
                    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <GraduationCap class="size-7" />
                    </span>
                    <h3 class="mt-5 text-2xl font-bold">Create your first classroom</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                        {{
                            $page.props.is_offline
                                ? 'Add a section, arrange its seating layout, and manage your students and classroom chairs.'
                                : 'Add a section, arrange its seating layout, and let students self-claim chairs with a single QR code.'
                        }}
                    </p>
                    <Link href="/sections/create" prefetch="hover" class="ink-button mt-6">
                        <Plus class="size-4" />
                        <span>Create a section</span>
                    </Link>
                </div>
            </section>
        </main>

        <!-- Onboarding Walkthrough Tour Modal -->
        <OnboardingModal
            :open="showOnboardingTour"
            :teacher-name="activeTeacherName"
            :current-term="activeTerm"
            :first-section-id="onboarding.first_section_id"
            @updated="handleOnboardingUpdated"
            @close="showOnboardingTour = false"
        />
    </AppLayout>
</template>
