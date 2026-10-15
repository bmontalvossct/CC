<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import OctoMascot from '@/components/OctoMascot.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowRight,
    BookOpen,
    CalendarDays,
    ChartNoAxesCombined,
    Check,
    CheckCircle2,
    ClipboardCheck,
    Download,
    Grid3X3,
    Monitor,
    RotateCcw,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ installerUrl: string | null }>();
const page = usePage<SharedData>();
const hasWorkspace = computed(() => Boolean(page.props.auth?.user || page.props.is_offline));
const workspaceHref = computed(() => (hasWorkspace.value ? '/dashboard' : '/register'));
const showDownload = computed(() => !page.props.is_offline);
const greeted = ref(false);
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
        icon: CalendarDays,
        title: 'Plan with a clearer head.',
        description:
            'Keep subjects, academic terms, and class schedules together. See what is coming up without piecing it together from separate lists.',
    },
    {
        number: '02',
        icon: Grid3X3,
        title: 'Know the room. Know the class.',
        description: 'Match your seating plan to the real classroom, organize your roster, and take attendance from the same familiar view.',
    },
    {
        number: '03',
        icon: ClipboardCheck,
        title: 'Keep progress in view.',
        description: 'Record quizzes, exams, activities, and recitations. Bring scores into your gradebook so progress is easier to follow.',
    },
    {
        number: '04',
        icon: BookOpen,
        title: 'Give every lesson a home.',
        description:
            'Organize course modules and learning materials alongside your sections. Keep the resources you need close to the class they belong to.',
    },
    {
        number: '05',
        icon: ChartNoAxesCombined,
        title: 'Turn records into insight.',
        description:
            'Review attendance and grades together, export CSV records, and prepare print views. Spend less time assembling end-of-term reports.',
    },
    {
        number: '06',
        icon: Sparkles,
        title: 'Get a helping tentacle.',
        description:
            'Ask Octo about your class records or find your way around the app. With local AI configured, your teaching companion is a conversation away.',
    },
];
</script>

<template>
    <Head title="Your teaching day, together">
        <meta
            name="description"
            content="ClassCheck brings schedules, seating, attendance, grades, learning materials, and an AI teaching companion into one workspace. Use the web app or download for Windows."
        />
    </Head>
    <main class="landing min-h-screen overflow-x-clip bg-background text-foreground">
        <nav class="sticky top-0 z-30 border-b border-border/70 bg-background/95 backdrop-blur-xl" aria-label="Main navigation">
            <div class="mx-auto flex h-[72px] max-w-7xl items-center justify-between gap-3 px-5 lg:px-10">
                <Link href="/" class="flex items-center gap-2.5" aria-label="ClassCheck home">
                    <AppLogoIcon class-name="size-10" /><span class="text-xl tracking-tight">ClassCheck<span class="text-primary">.</span></span>
                </Link>
                <div class="hidden items-center gap-7 text-sm text-muted-foreground lg:flex">
                    <a href="#benefits">Why ClassCheck</a><a href="#octo">Meet Octo</a><a v-if="showDownload" href="#download">Get the desktop app</a>
                </div>
                <div class="flex items-center gap-2 sm:gap-4">
                    <Link
                        v-if="!hasWorkspace"
                        href="/login"
                        :prefetch="page.props.is_offline ? false : 'hover'"
                        class="px-2 py-2 text-sm hover:text-primary"
                        >Log in</Link
                    >
                    <Link
                        :href="workspaceHref"
                        :prefetch="page.props.is_offline ? false : 'hover'"
                        class="ink-button !px-3 !text-xs sm:!px-4 sm:!text-sm"
                    >
                        {{ hasWorkspace ? 'Open workspace' : 'Use in browser' }}<ArrowRight class="hidden size-4 sm:block" />
                    </Link>
                </div>
            </div>
        </nav>

        <section
            class="relative mx-auto grid max-w-7xl items-center gap-10 px-5 pb-16 pt-12 sm:pt-16 lg:grid-cols-[1.1fr_.9fr] lg:gap-8 lg:px-10 lg:pb-20"
        >
            <div class="stagger-enter relative z-10">
                <p class="eyebrow flex items-center gap-2"><span class="size-1.5 rounded-full bg-primary" /> A little more room to teach</p>
                <h1 class="mt-6 max-w-2xl font-display text-[3rem] leading-[1.08] tracking-[-.045em] sm:text-6xl lg:text-[4.5rem]">
                    Your teaching day.<br /><em class="font-normal text-primary">All together.</em>
                </h1>
                <p class="mt-6 max-w-lg text-base leading-relaxed text-muted-foreground sm:text-lg">
                    From the first lesson plan to the final grade. Bring your classes, schedules, attendance, learning materials, and assessments into
                    one calm workspace, with Octo by your side.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <Link :href="workspaceHref" :prefetch="page.props.is_offline ? false : 'hover'" class="ink-button !h-12 !px-5">
                        {{ hasWorkspace ? 'Open your workspace' : 'Use in browser' }}<ArrowRight class="size-4" />
                    </Link>
                    <a v-if="showDownload && props.installerUrl" :href="props.installerUrl" class="secondary-button !h-12 !px-5"
                        ><Download class="size-4" /> Download for Windows</a
                    >
                    <a v-else-if="showDownload" href="#download" class="secondary-button !h-12 !px-5"><Monitor class="size-4" /> Windows app</a>
                </div>
                <p class="mt-5 flex items-center gap-2 text-xs text-muted-foreground">
                    <CheckCircle2 class="size-4 shrink-0 text-primary" /> Your classroom essentials, with fewer things to juggle.
                </p>
                <a href="#benefits" class="mt-9 inline-flex items-center gap-2 py-2 text-sm text-primary"
                    >Explore what you can do <ArrowDown class="size-4"
                /></a>
            </div>
            <div class="relative mx-auto w-full max-w-md">
                <div class="mascot-stage relative rounded-[2.5rem] border border-border bg-card px-5 pb-6 pt-4">
                    <div class="flex items-center justify-between px-3 pt-3 text-xs text-muted-foreground">
                        <span class="flex items-center gap-2"><Sparkles class="size-4 text-amber-600" /> Your teaching companion</span
                        ><span>Meet Octo</span>
                    </div>
                    <button
                        type="button"
                        class="mascot-hello mx-auto block w-full rounded-3xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary"
                        :aria-label="greeted ? 'Wave to Octo again' : 'Say hello to Octo'"
                        @click="greeted = !greeted"
                    >
                        <OctoMascot
                            class="w-full"
                            size="custom"
                            custom-class="w-full aspect-square"
                            :interactive="true"
                            :forced-state="greeted ? 'happy' : null"
                            alt="Octo, a golden octopus wearing a graduation cap and holding a book"
                        />
                    </button>
                    <div class="relative mx-3 rounded-2xl border border-border bg-background px-4 py-3 text-sm">
                        <p class="text-primary" aria-live="polite">
                            {{
                                greeted ? 'Hello, teacher. Let’s make room for your next great lesson!' : 'Eight arms. One less thing on your plate.'
                            }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                greeted
                                    ? 'Explore the workspace to meet your AI companion.'
                                    : 'I’m Octo, your AI teaching companion. Tap to say hello.'
                            }}
                        </p>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-center gap-5 text-xs text-muted-foreground">
                    <span class="flex items-center gap-1.5"><CalendarDays class="size-3.5" /> Plan</span
                    ><span class="flex items-center gap-1.5"><BookOpen class="size-3.5" /> Teach</span
                    ><span class="flex items-center gap-1.5"><ChartNoAxesCombined class="size-3.5" /> Reflect</span>
                </div>
            </div>
        </section>

        <section id="benefits" class="border-y border-border bg-card/60">
            <div class="mx-auto max-w-7xl px-5 py-16 lg:px-10 lg:py-20">
                <div v-reveal class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <p class="eyebrow">The whole teaching day</p>
                        <h2 class="mt-3 max-w-xl font-display text-3xl leading-tight tracking-tight sm:text-4xl">
                            More than taking attendance.<br /><span class="text-primary">More time for what matters.</span>
                        </h2>
                    </div>
                    <p class="max-w-sm text-sm leading-relaxed text-muted-foreground">
                        Less switching between lists, files, and spreadsheets. More time to prepare, support your students, and see how they are
                        doing.
                    </p>
                </div>
                <div class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <article v-for="(feature, index) in features" :key="feature.number" v-reveal="index * 50" class="feature-card paper-card p-6">
                        <div class="flex items-center justify-between">
                            <span class="feature-icon grid size-10 place-items-center rounded-xl border border-primary/15 text-primary"
                                ><component :is="feature.icon" class="size-5" /></span
                            ><span class="text-xs text-muted-foreground">/ {{ feature.number }}</span>
                        </div>
                        <h3 class="mt-6 text-lg">{{ feature.title }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-muted-foreground">{{ feature.description }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="octo" class="mx-auto grid max-w-7xl items-center gap-10 px-5 py-16 lg:grid-cols-2 lg:gap-20 lg:px-10 lg:py-20">
            <div v-reveal>
                <p class="eyebrow">A companion with classroom context</p>
                <h2 class="mt-3 font-display text-3xl leading-tight sm:text-4xl">
                    Meet Octo.<br /><span class="text-primary">A helping hand. Or eight.</span>
                </h2>
                <p class="mt-5 text-sm leading-relaxed text-muted-foreground">
                    Need a clearer picture of your class? Octo can help you explore attendance, understand gradebook records, and find your way
                    through ClassCheck using the class context you choose.
                </p>
                <div class="mt-6 space-y-3 text-sm">
                    <p class="flex items-start gap-3"><Check class="mt-0.5 size-4 shrink-0 text-primary" /> Ask about a section or your classes.</p>
                    <p class="flex items-start gap-3">
                        <Check class="mt-0.5 size-4 shrink-0 text-primary" /> Get help navigating your teaching tools.
                    </p>
                    <p class="flex items-start gap-3">
                        <Check class="mt-0.5 size-4 shrink-0 text-primary" /> Review AI suggestions and keep the final say.
                    </p>
                </div>
                <p class="mt-6 text-xs leading-relaxed text-muted-foreground">
                    AI features require a configured Ollama connection and a downloaded model. Classroom tools work independently of AI.
                </p>
            </div>
            <div v-reveal="100" class="overflow-hidden rounded-3xl border border-border bg-card p-5 shadow-lg shadow-primary/5 sm:p-7">
                <div class="flex items-center gap-3 border-b border-border pb-4">
                    <OctoMascot size="lg" />
                    <div>
                        <h3 class="text-sm">Octo Copilot</h3>
                        <p class="mt-1 text-xs text-muted-foreground">A few things you can ask</p>
                    </div>
                    <span class="ml-auto rounded-full border border-border px-2 py-1 text-[10px] text-muted-foreground">Examples</span>
                </div>
                <div class="mt-5 space-y-3 text-sm">
                    <p class="ml-5 rounded-2xl rounded-tr-sm border border-border bg-background px-4 py-3">
                        “What does attendance look like in this section?”
                    </p>
                    <p class="ml-5 rounded-2xl rounded-tr-sm border border-border bg-background px-4 py-3">
                        “Help me understand this class’s gradebook.”
                    </p>
                    <p class="ml-5 rounded-2xl rounded-tr-sm border border-border bg-background px-4 py-3">“How do I organize my course modules?”</p>
                </div>
                <p class="mt-5 flex items-center gap-2 text-xs text-muted-foreground">
                    <Sparkles class="size-4 shrink-0 text-primary" /> Open Octo inside your workspace to start a conversation.
                </p>
            </div>
        </section>

        <section id="workflow" class="border-y border-border bg-card/60">
            <div class="mx-auto grid max-w-7xl items-center gap-10 px-5 py-16 lg:grid-cols-[.85fr_1.15fr] lg:gap-20 lg:px-10">
                <div v-reveal>
                    <p class="eyebrow">A familiar classroom rhythm</p>
                    <h2 class="mt-3 font-display text-3xl leading-tight sm:text-4xl">
                        A room you recognize.<br /><span class="text-primary">Records you can use.</span>
                    </h2>
                    <p class="mt-5 text-sm leading-relaxed text-muted-foreground">
                        The seating plan is just the beginning. Keep attendance and assessment records close to each student, then bring the bigger
                        picture into your reports.
                    </p>
                    <p class="mt-5 flex items-center gap-2 text-sm text-primary">
                        <ArrowRight class="size-4" /> Try the demo: tap a seat to change attendance.
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">Sample students only. Your real records stay untouched.</p>
                </div>
                <div v-reveal="100" class="rounded-3xl border border-border bg-card p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm">Narra · Room 204</h3>
                            <p class="mt-1 text-xs text-muted-foreground">Interactive seating preview</p>
                        </div>
                        <button type="button" class="secondary-button !h-9 !px-3 !text-xs" @click="resetPreview">
                            <RotateCcw class="size-3" /> Reset
                        </button>
                    </div>
                    <div
                        class="mx-auto my-5 w-2/3 rounded-b-lg border-x border-b border-primary/20 py-2 text-center text-[10px] uppercase tracking-[.2em] text-muted-foreground"
                    >
                        Teaching space
                    </div>
                    <div class="grid grid-cols-4 gap-2 sm:gap-3">
                        <button
                            v-for="seat in seats"
                            :key="seat.name"
                            type="button"
                            class="preview-seat flex min-w-0 flex-col items-center rounded-xl border px-1 py-3"
                            :class="
                                seat.present
                                    ? 'border-primary/20 bg-primary/5 text-primary dark:text-white'
                                    : 'border-rose-300 bg-rose-50 text-rose-700 dark:border-rose-500/40 dark:bg-rose-950/30 dark:text-rose-200'
                            "
                            :aria-pressed="seat.present"
                            :aria-label="'Mark ' + seat.name + ' as ' + (seat.present ? 'absent' : 'present')"
                            @click="seat.present = !seat.present"
                        >
                            <span class="relative grid size-9 place-items-center rounded-full border border-border bg-card text-[10px]"
                                >{{ seat.initials
                                }}<Check
                                    v-if="seat.present"
                                    class="absolute -bottom-1 -right-1 size-3.5 rounded-full bg-primary p-0.5 text-white" /></span
                            ><span class="mt-2 text-[11px]">{{ seat.name }}</span
                            ><span class="mt-1 text-[9px]">{{ seat.present ? 'Present' : 'Absent' }}</span>
                        </button>
                    </div>
                    <div class="mt-5 flex items-center gap-4 rounded-xl border border-border px-4 py-3" role="status" aria-live="polite">
                        <span class="text-2xl tabular-nums text-primary dark:text-white">{{ attendanceRate }}<span class="text-sm">%</span></span>
                        <div class="flex-1">
                            <div class="mb-2 flex justify-between text-[10px] text-muted-foreground">
                                <span>{{ presentCount }} present</span><span>{{ seats.length - presentCount }} absent</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-primary/10">
                                <div class="attendance-bar h-full rounded-full bg-primary" :style="{ width: attendanceRate + '%' }" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="showDownload" id="download" class="mx-auto max-w-7xl px-5 py-16 lg:px-10 lg:py-20">
            <div v-reveal class="rounded-3xl border border-border bg-card p-6 sm:p-10">
                <p class="eyebrow">Choose your workspace</p>
                <h2 class="mt-3 font-display text-3xl leading-tight sm:text-4xl">
                    In your browser.<br /><span class="text-primary">Or right on your computer.</span>
                </h2>
                <div class="mt-9 grid gap-8 md:grid-cols-2 md:gap-12">
                    <div>
                        <BookOpen class="size-6 text-primary" />
                        <h3 class="mt-4 text-xl">Start on the web</h3>
                        <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                            Open ClassCheck in your browser and sign in to your teacher account. Get to your web workspace without installing
                            anything.
                        </p>
                        <Link :href="workspaceHref" :prefetch="page.props.is_offline ? false : 'hover'" class="ink-button mt-6"
                            >{{ hasWorkspace ? 'Open workspace' : 'Use in browser' }}<ArrowRight class="size-4"
                        /></Link>
                    </div>
                    <div class="border-t border-border pt-8 md:border-l md:border-t-0 md:pl-10 md:pt-0">
                        <Monitor class="size-6 text-primary" />
                        <h3 class="mt-4 text-xl">Take ClassCheck with you</h3>
                        <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                            Install the Windows app to work with local classroom records, even without an internet connection. Launch it from your
                            desktop and use the familiar browser interface.
                        </p>
                        <a v-if="props.installerUrl" :href="props.installerUrl" class="secondary-button mt-6"
                            ><Download class="size-4" /> Download Windows installer</a
                        ><button v-else type="button" disabled class="secondary-button mt-6 cursor-not-allowed opacity-60">
                            <Download class="size-4" /> Installer coming soon
                        </button>
                        <p class="mt-3 text-xs text-muted-foreground">Windows · 64-bit · .exe installer</p>
                    </div>
                </div>
                <p class="mt-8 border-t border-border pt-5 text-xs leading-relaxed text-muted-foreground">
                    The desktop app keeps its own local records; web and desktop records do not automatically sync. Back up your desktop data
                    regularly. Local AI requires a separate Ollama model setup.
                </p>
            </div>
        </section>
        <footer
            class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-6 text-xs text-muted-foreground lg:px-10"
        >
            <span class="text-sm text-foreground">ClassCheck.</span><span>A little more order. A lot more room to teach.</span
            ><a v-if="showDownload" href="#download" class="text-primary">Get ClassCheck <ArrowRight class="ml-1 inline size-3" /></a>
        </footer>
    </main>
</template>

<style scoped>
.landing {
    font-family: Tahoma, sans-serif;
}
.landing h1,
.landing h2,
.landing h3 {
    font-weight: 400;
}
.mascot-stage {
    background-image: radial-gradient(ellipse at 50% 45%, #fff2ca 0, transparent 65%);
}
.preview-seat,
.feature-icon {
    transition:
        transform 250ms var(--ease-out),
        border-color 200ms;
}
.attendance-bar {
    transition: width 400ms var(--ease-out);
}
@media (hover: hover) and (prefers-reduced-motion: no-preference) {
    .preview-seat:hover {
        transform: translateY(-3px);
    }
    .feature-card:hover .feature-icon {
        transform: rotate(-5deg);
    }
}
@media (prefers-reduced-motion: reduce) {
    .preview-seat,
    .feature-icon,
    .attendance-bar {
        transition: none;
    }
}
</style>
