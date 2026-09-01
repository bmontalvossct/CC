<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Armchair,
    ArrowRight,
    Calendar,
    CalendarCheck2,
    Check,
    ChevronDown,
    ChevronUp,
    GraduationCap,
    HelpCircle,
    LayoutGrid,
    QrCode,
    Sparkles,
    Users,
} from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';
import { computed, onMounted, ref } from 'vue';

export type OnboardingData = {
    has_academic_term: boolean;
    has_section: boolean;
    has_seating_layout: boolean;
    has_students: boolean;
    has_attendance: boolean;
    first_section_id?: number | null;
};

const props = withDefaults(
    defineProps<{
        onboarding: OnboardingData;
    }>(),
    {
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

const emit = defineEmits<{
    (e: 'openTour'): void;
}>();

const isCollapsed = ref(false);

onMounted(() => {
    const saved = localStorage.getItem('classcheck_onboarding_collapsed');
    if (saved !== null) {
        isCollapsed.value = saved === 'true';
    }
});

const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value;
    localStorage.setItem('classcheck_onboarding_collapsed', String(isCollapsed.value));
};

const page = usePage<SharedData>();
const isOffline = computed(() => Boolean(page.props.is_offline));

const steps = computed(() => {
    const ob = props.onboarding;
    const secId = ob.first_section_id;

    return [
        {
            id: 'term',
            title: 'Configure Academic Term',
            description: 'Set up your current academic semester and school year.',
            completed: ob.has_academic_term,
            href: '/settings/academic-term',
            actionText: 'Set up term',
            icon: Calendar,
        },
        {
            id: 'section',
            title: 'Create Your First Section',
            description: 'Define course subject code, room number, and class schedule.',
            completed: ob.has_section,
            href: '/sections/create',
            actionText: 'Create section',
            icon: GraduationCap,
        },
        {
            id: 'layout',
            title: 'Design Seating Floor Plan',
            description: 'Arrange layout blocks, rows, aisles, and student chairs.',
            completed: ob.has_seating_layout,
            href: secId ? `/sections/${secId}` : '/sections/create',
            actionText: secId ? 'Configure layout' : 'Create section first',
            disabled: !ob.has_section,
            icon: LayoutGrid,
        },
        {
            id: 'students',
            title: isOffline.value ? 'Enroll Students & Manage Roster' : 'Enroll Students & Share QR',
            description: isOffline.value
                ? 'Import a CSV class roster or enroll students manually.'
                : 'Import a CSV class roster or display the QR code for student self-claim.',
            completed: ob.has_students,
            href: secId ? `/sections/${secId}` : '/sections/create',
            actionText: secId ? 'Manage roster' : 'Create section first',
            disabled: !ob.has_section,
            icon: Users,
        },
        {
            id: 'attendance',
            title: 'Run Your First Roll Call',
            description: 'Take attendance with 1-tap seating checks or bulk actions.',
            completed: ob.has_attendance,
            href: secId ? `/sections/${secId}/attendance` : '/sections/create',
            actionText: secId ? 'Start attendance' : 'Create section first',
            disabled: !ob.has_section,
            icon: CalendarCheck2,
        },
    ];
});

const completedCount = computed(() => steps.value.filter((s) => s.completed).length);
const totalSteps = computed(() => steps.value.length);
const progressPct = computed(() => Math.round((completedCount.value / totalSteps.value) * 100));
const allCompleted = computed(() => completedCount.value === totalSteps.value);
</script>

<template>
    <section class="mt-8 rounded-2xl border border-border/80 bg-card p-6 shadow-sm sm:p-7 transition-all duration-200">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 rounded-full border border-border bg-secondary/50 px-2.5 py-0.5 text-xs font-medium text-foreground">
                        <Sparkles class="size-3 text-muted-foreground" />
                        <span>Setup Guide</span>
                    </span>
                    <span class="font-mono text-xs font-medium text-muted-foreground">
                        {{ completedCount }} of {{ totalSteps }} completed ({{ progressPct }}%)
                    </span>
                </div>
                <h2 class="text-xl font-bold tracking-tight text-foreground">Getting Started with ClassCheck</h2>
                <p class="text-xs text-muted-foreground">
                    Follow these essential steps to set up your classrooms, seat your students, and log attendance.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-secondary/50 px-3 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                    @click="emit('openTour')"
                >
                    <HelpCircle class="size-3.5 text-muted-foreground" />
                    <span>Guided Tour</span>
                </button>

                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-lg border border-border text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                    :title="isCollapsed ? 'Expand checklist' : 'Collapse checklist'"
                    @click="toggleCollapse"
                >
                    <ChevronDown v-if="isCollapsed" class="size-4" />
                    <ChevronUp v-else class="size-4" />
                </button>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-secondary">
            <div
                class="h-full bg-foreground transition-all duration-500 ease-out"
                :style="{ width: `${progressPct}%` }"
            />
        </div>

        <!-- Collapsible Steps Grid -->
        <div v-if="!isCollapsed" class="mt-6 space-y-3">
            <div
                v-for="(step, index) in steps"
                :key="step.id"
                class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border p-4 transition-colors"
                :class="
                    step.completed
                        ? 'border-border/60 bg-secondary/20'
                        : 'border-border/80 bg-background hover:bg-secondary/20'
                "
            >
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-8 shrink-0 place-items-center rounded-lg border text-xs font-semibold"
                        :class="
                            step.completed
                                ? 'border-border/80 bg-secondary text-foreground'
                                : 'border-border bg-card text-muted-foreground'
                        "
                    >
                        <Check v-if="step.completed" class="size-4 text-foreground" />
                        <span v-else>{{ index + 1 }}</span>
                    </span>

                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2">
                            <h3
                                class="text-sm font-semibold"
                                :class="step.completed ? 'text-foreground line-through opacity-80' : 'text-foreground'"
                            >
                                {{ step.title }}
                            </h3>
                            <span v-if="step.completed" class="rounded bg-secondary px-1.5 py-0.2 font-mono text-[10px] text-muted-foreground font-medium">
                                Done
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ step.description }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center sm:self-center self-end shrink-0 pl-11 sm:pl-0">
                    <span
                        v-if="step.completed"
                        class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground"
                    >
                        <span>Completed</span>
                    </span>

                    <Link
                        v-else-if="!step.disabled"
                        :href="step.href"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-card px-3 text-xs font-semibold text-foreground transition-colors hover:bg-secondary"
                    >
                        <span>{{ step.actionText }}</span>
                        <ArrowRight class="size-3 text-muted-foreground" />
                    </Link>

                    <span
                        v-else
                        class="text-xs text-muted-foreground/60 italic"
                    >
                        {{ step.actionText }}
                    </span>
                </div>
            </div>

            <!-- All completed callout -->
            <div
                v-if="allCompleted"
                class="flex items-center justify-between rounded-xl border border-border/80 bg-secondary/30 p-4 text-xs"
            >
                <div class="flex items-center gap-2 text-foreground font-medium">
                    <Sparkles class="size-4 text-muted-foreground" />
                    <span>All setup milestones completed! You have everything set up to manage classes seamlessly.</span>
                </div>
                <button
                    type="button"
                    class="text-xs text-muted-foreground hover:text-foreground hover:underline"
                    @click="toggleCollapse"
                >
                    Minimize
                </button>
            </div>
        </div>
    </section>
</template>
