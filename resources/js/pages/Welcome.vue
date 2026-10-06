<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import OctoMascot from '@/components/OctoMascot.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowRight, Check, CheckCircle2, ClipboardCheck, Grid3X3, QrCode, RotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage<SharedData>();
const hasWorkspace = computed(() => Boolean(page.props.auth?.user || page.props.is_offline));
const workspaceHref = computed(() => (hasWorkspace.value ? '/dashboard' : '/register'));
const seats = ref([
    { name: 'Mia S.', initials: 'MS', present: true },
    { name: 'John R.', initials: 'JR', present: true },
    { name: 'Lea M.', initials: 'LM', present: false },
    { name: 'Noah C.', initials: 'NC', present: true },
    { name: 'Aya P.', initials: 'AP', present: true },
    { name: 'Luis D.', initials: 'LD', present: true },
    { name: 'Sam T.', initials: 'ST', present: true },
    { name: 'Bea G.', initials: 'BG', present: true },
]);
const presentCount = computed(() => seats.value.filter((seat) => seat.present).length);
const attendanceRate = computed(() => Math.round((presentCount.value / seats.value.length) * 100));
const resetPreview = () =>
    seats.value.forEach((seat, index) => {
        seat.present = index !== 2;
    });
const features = [
    {
        number: '01',
        icon: Grid3X3,
        title: 'Make room for everyone.',
        description: 'Arrange desks, rows, and walkways to match the room you teach in. Every student has a place.',
    },
    {
        number: '02',
        icon: QrCode,
        title: 'Let your class settle in.',
        description: 'Share a private QR code. Students scan and claim their chairs, so you can get straight to teaching.',
    },
    {
        number: '03',
        icon: ClipboardCheck,
        title: 'Keep the day moving.',
        description: 'Tap an empty seat, record a score, and see the bigger picture. Your classroom records, together.',
    },
];
</script>

<template>
    <Head title="ClassCheck ? A little more classroom clarity" />
    <main class="min-h-screen overflow-x-clip bg-background text-foreground">
        <nav class="sticky top-0 z-30 border-b border-border/70 bg-background/90 backdrop-blur-xl" aria-label="Main navigation">
            <div class="mx-auto flex h-[72px] max-w-7xl items-center justify-between gap-3 px-5 lg:px-10">
                <Link href="/" class="flex items-center gap-2.5" aria-label="ClassCheck home">
                    <AppLogoIcon class-name="size-10" />
                    <span class="text-xl tracking-tight">ClassCheck<span class="text-primary">.</span></span>
                </Link>
                <div class="hidden items-center gap-8 text-sm text-muted-foreground md:flex">
                    <a href="#product" class="transition-colors hover:text-primary">Your workspace</a>
                    <a href="#workflow" class="transition-colors hover:text-primary">How it works</a>
                    <a href="#features" class="transition-colors hover:text-primary">The little details</a>
                </div>
                <div class="flex items-center gap-2 sm:gap-4">
                    <Link v-if="!hasWorkspace" href="/login" prefetch="hover" class="px-2 py-2 text-sm transition-colors hover:text-primary"
                        >Log in</Link
                    >
                    <Link :href="workspaceHref" prefetch="hover" class="ink-button !px-4 !text-xs sm:!text-sm">
                        {{ hasWorkspace ? 'Open workspace' : 'Get started' }}
                        <ArrowRight class="hidden size-4 sm:block" />
                    </Link>
                </div>
            </div>
        </nav>

        <section
            id="product"
            class="relative mx-auto grid max-w-7xl items-center gap-12 px-5 pb-16 pt-12 sm:pt-20 lg:grid-cols-[0.95fr_1.05fr] lg:gap-14 lg:px-10 lg:pb-24 lg:pt-24"
        >
            <div class="stagger-enter relative z-10">
                <div class="inline-flex items-center gap-2.5 rounded-full border border-primary/15 bg-primary/5 px-3 py-1.5 text-xs text-primary">
                    <span class="size-1.5 rounded-full bg-primary" />
                    Made for the everyday magic of teaching
                </div>
                <h1 class="mt-6 max-w-xl font-display text-[3.25rem] leading-[1.05] tracking-[-0.045em] sm:text-6xl lg:text-[4.5rem]">
                    A place for<br />every student.<br />
                    <em class="font-normal text-primary">Space for you.</em>
                </h1>
                <p class="mt-6 max-w-md text-base leading-relaxed text-muted-foreground sm:text-lg">
                    A calmer way to manage your classroom. Bring seating, attendance, and assessments together ? and give your attention back to
                    teaching.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <Link :href="workspaceHref" prefetch="hover" class="ink-button !h-12 !px-6">
                        {{ hasWorkspace ? 'Back to your classroom' : 'Start your first class' }}
                        <ArrowRight class="size-4" />
                    </Link>
                    <a href="#workflow" class="secondary-button !h-12 !px-5">Take a look <ArrowDown class="size-4" /></a>
                </div>
                <p class="mt-5 flex items-center gap-2 text-xs text-muted-foreground">
                    <CheckCircle2 class="size-4 text-primary" /> Thoughtfully built around your teaching day.
                </p>
            </div>

            <div v-reveal="120" class="relative mx-auto w-full max-w-xl lg:max-w-none">
                <div class="surface-grid pointer-events-none absolute -inset-6 rounded-[3rem] opacity-70" aria-hidden="true" />
                <div class="classroom-preview relative rounded-[1.5rem] border border-border bg-card p-2 shadow-xl shadow-primary/5 sm:p-3">
                    <div class="flex items-center justify-between gap-3 rounded-t-xl bg-secondary/70 px-4 py-4 sm:px-5">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-xl bg-primary text-white"><Grid3X3 class="size-5" /></span>
                            <div>
                                <p class="text-sm font-bold">Your classroom, at a glance</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">Narra ? Room 204</p>
                            </div>
                        </div>
                        <span class="rounded-full border border-border bg-card px-2.5 py-1 text-[10px] uppercase tracking-wider text-muted-foreground"
                            >Preview</span
                        >
                    </div>

                    <div class="p-4 sm:p-5">
                        <div class="mb-5 flex items-center justify-between">
                            <p class="text-xs text-muted-foreground">
                                <span class="text-foreground">{{ seats.length }} students</span> ? Seating view
                            </p>
                            <button
                                type="button"
                                class="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-secondary hover:text-primary"
                                @click="resetPreview"
                            >
                                <RotateCcw class="size-3" /> Reset
                            </button>
                        </div>
                        <div
                            class="mx-auto mb-5 w-2/3 rounded-b-lg border-x border-b border-primary/15 bg-primary/5 py-1.5 text-center text-[9px] uppercase tracking-[0.25em] text-primary"
                        >
                            Teaching space
                        </div>
                        <div class="stagger-enter grid grid-cols-4 gap-2 sm:gap-3">
                            <button
                                v-for="seat in seats"
                                :key="seat.name"
                                type="button"
                                class="preview-seat group relative flex min-w-0 flex-col items-center rounded-xl border px-1.5 py-3 sm:py-4"
                                :class="
                                    seat.present
                                        ? 'border-primary/15 bg-primary/5 text-primary hover:border-primary/40'
                                        : 'border-rose-300/60 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-950/30 dark:text-rose-300'
                                "
                                :aria-pressed="seat.present"
                                :aria-label="'Mark ' + seat.name + ' as ' + (seat.present ? 'absent' : 'present')"
                                @click="seat.present = !seat.present"
                            >
                                <span
                                    class="relative grid size-9 place-items-center rounded-full bg-card text-[10px] shadow-sm sm:size-11 sm:text-xs"
                                >
                                    {{ seat.initials }}
                                    <span
                                        v-if="seat.present"
                                        class="absolute -bottom-0.5 -right-0.5 grid size-3.5 place-items-center rounded-full bg-primary text-white"
                                        ><Check class="size-2.5"
                                    /></span>
                                </span>
                                <span class="mt-2 text-[11px] sm:text-xs">{{ seat.name }}</span>
                                <span class="mt-1 text-[9px] opacity-75">{{ seat.present ? 'Present' : 'Absent' }}</span>
                            </button>
                        </div>
                        <div
                            class="mt-5 flex items-center gap-4 rounded-xl border border-border/70 bg-background px-4 py-3"
                            role="status"
                            aria-live="polite"
                        >
                            <div class="min-w-[3.25rem] text-2xl tabular-nums tracking-tight text-primary">
                                {{ attendanceRate }}<span class="text-sm">%</span>
                            </div>
                            <div class="flex-1">
                                <div class="mb-1.5 flex justify-between text-[10px] text-muted-foreground">
                                    <span>{{ presentCount }} present</span><span>{{ seats.length - presentCount }} absent</span>
                                </div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-primary/10">
                                    <div class="attendance-bar h-full rounded-full bg-primary" :style="{ width: attendanceRate + '%' }" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="relative -mt-1 ml-auto mr-2 flex w-fit items-center gap-2 rounded-b-2xl border border-t-0 border-border bg-card px-4 py-2.5 text-xs shadow-sm sm:mr-5"
                >
                    <OctoMascot size="sm" :interactive="true" />
                    <span class="text-muted-foreground"><span class="text-foreground">Go on, try it.</span> Tap a seat to take roll call.</span>
                </div>
            </div>
        </section>

        <section id="workflow" class="border-y border-border/80 bg-card/70">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:py-20 lg:px-10">
                <div v-reveal class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <p class="eyebrow">A little less admin</p>
                        <h2 class="mt-3 max-w-xl font-display text-3xl leading-tight tracking-tight sm:text-4xl">
                            From the first bell<br />to the last name on your list.
                        </h2>
                    </div>
                    <p class="max-w-xs text-sm leading-relaxed text-muted-foreground">
                        One familiar workspace, from setting up the room to seeing how everyone is doing.
                    </p>
                </div>
                <div id="features" class="mt-10 grid gap-5 md:grid-cols-3">
                    <article
                        v-for="(feature, index) in features"
                        :key="feature.number"
                        v-reveal="index * 80"
                        class="feature-card paper-card p-6 sm:p-8"
                    >
                        <div class="flex items-center justify-between">
                            <span class="feature-icon bg-primary/7 grid size-11 place-items-center rounded-xl text-primary"
                                ><component :is="feature.icon" class="size-5"
                            /></span>
                            <span class="font-display text-3xl text-muted-foreground/40">{{ feature.number }}</span>
                        </div>
                        <h3 class="mt-8 text-lg font-bold">{{ feature.title }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-muted-foreground">{{ feature.description }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-5 py-16 lg:px-10">
            <div v-reveal class="relative overflow-hidden rounded-3xl bg-[#163d30] p-8 text-white sm:p-12">
                <div class="pointer-events-none absolute -right-16 -top-24 size-80 rounded-full border border-white/10" aria-hidden="true" />
                <div class="relative flex flex-col items-start justify-between gap-8 md:flex-row md:items-center">
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-[#c9dfb7]">Your next class starts here</p>
                        <h2 class="mt-4 max-w-lg font-display text-3xl leading-tight sm:text-4xl">
                            Ready for a little<br /><em>more classroom clarity?</em>
                        </h2>
                    </div>
                    <div class="shrink-0">
                        <Link
                            :href="workspaceHref"
                            prefetch="hover"
                            class="inline-flex h-12 items-center gap-3 rounded-xl bg-[#d9edc5] px-6 text-sm text-[#163d30] shadow-sm transition-colors hover:bg-white"
                        >
                            {{ hasWorkspace ? 'Open your workspace' : 'Make yourself at home' }} <ArrowRight class="size-4" />
                        </Link>
                        <p class="mt-3 text-xs text-white/60">
                            {{ hasWorkspace ? 'Your classroom is right where you left it.' : 'Create your teacher account to get started.' }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
        <footer
            class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-6 text-xs text-muted-foreground lg:px-10"
        >
            <span class="text-sm text-foreground">ClassCheck.</span><span>A little more order. A lot more room to teach.</span>
        </footer>
    </main>
</template>

<style scoped>
.preview-seat {
    transition:
        background-color 200ms,
        border-color 200ms,
        color 200ms,
        transform 250ms var(--ease-out),
        box-shadow 250ms;
}
.attendance-bar {
    transition: width 400ms var(--ease-out);
}
.feature-icon {
    transition:
        transform 300ms var(--ease-out),
        background-color 200ms;
}
@media (hover: hover) and (prefers-reduced-motion: no-preference) {
    .preview-seat:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 12px -8px hsl(var(--primary) / 0.35);
    }
    .preview-seat:active {
        transform: scale(0.96);
    }
    .feature-card:hover .feature-icon {
        transform: rotate(-5deg) scale(1.08);
    }
    .feature-card:hover {
        border-color: hsl(var(--primary) / 0.3);
    }
}
</style>
