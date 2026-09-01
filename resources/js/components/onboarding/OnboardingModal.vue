<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useAiAssistant } from '@/composables/useAiAssistant';
import {
    AlertTriangle,
    Armchair,
    ArrowLeft,
    ArrowRight,
    Award,
    Bot,
    Calendar,
    CalendarCheck2,
    CalendarDays,
    Check,
    CheckCircle2,
    Cpu,
    Download,
    ExternalLink,
    GraduationCap,
    HardDrive,
    LayoutGrid,
    Loader2,
    PlusCircle,
    QrCode,
    RefreshCw,
    School,
    ShieldCheck,
    Sparkles,
    Trophy,
    User,
    UserCheck,
    Users,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        teacherName?: string;
        currentTerm?: {
            id?: number;
            name?: string;
            school_year?: string;
            starts_on?: string;
            ends_on?: string;
        } | null;
        firstSectionId?: number | null;
    }>(),
    {
        teacherName: '',
        currentTerm: null,
        firstSectionId: null,
    },
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'updated', payload: { teacherName: string; term: any }): void;
}>();

const page = usePage<SharedData>();
const isOffline = computed(() => Boolean(page.props.is_offline));

const {
    isOllamaOnline,
    availableModels,
    fetchStatus,
    setAiEnabled,
    isAiEnabled,
    isPullingModel,
    pullProgress,
    pullModel,
} = useAiAssistant();

const isCheckingOllama = ref(false);
const downloadError = ref('');
const wantsAiSetup = ref<boolean>(isAiEnabled.value);

const hasHermes3Model = computed(() =>
    availableModels.value.some((m) => m.name.toLowerCase().includes('hermes3')),
);

const runOllamaCheck = async () => {
    isCheckingOllama.value = true;
    downloadError.value = '';
    await fetchStatus();
    isCheckingOllama.value = false;
};

const handleSelectAiChoice = (enabled: boolean) => {
    wantsAiSetup.value = enabled;
    setAiEnabled(enabled);
    if (enabled) {
        runOllamaCheck();
    }
};

const handleDownloadHermes = async () => {
    downloadError.value = '';
    const success = await pullModel('hermes3:8b');
    if (success) {
        setAiEnabled(true);
    } else {
        downloadError.value = pullProgress.value.status || 'Failed to download Hermes 3 model.';
    }
};

// 5-Phase Stepper: 0: Ask Name, 1: Current Semester, 2: AI Setup, 3: System Guides, 4: Create Section
const currentStep = ref(0);
const isSaving = ref(false);
const saveError = ref('');
const activeGuideTab = ref(0);

// Form Data
const form = ref({
    name: props.teacherName && props.teacherName !== 'Teacher' ? props.teacherName : '',
    term_name: props.currentTerm?.name || '1st Semester',
    school_year: props.currentTerm?.school_year || '2026-2027',
    starts_on: props.currentTerm?.starts_on || new Date().toISOString().split('T')[0],
    ends_on:
        props.currentTerm?.ends_on ||
        new Date(new Date().setMonth(new Date().getMonth() + 5)).toISOString().split('T')[0],
});

// Simulation tick for visual guides
const simulationTick = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    timer = setInterval(() => {
        simulationTick.value = (simulationTick.value + 1) % 6;
    }, 2000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            currentStep.value = 0;
            saveError.value = '';
            if (props.teacherName && props.teacherName !== 'Teacher') {
                form.value.name = props.teacherName;
            }
            if (props.currentTerm) {
                form.value.term_name = props.currentTerm.name || form.value.term_name;
                form.value.school_year = props.currentTerm.school_year || form.value.school_year;
                if (props.currentTerm.starts_on) form.value.starts_on = props.currentTerm.starts_on;
                if (props.currentTerm.ends_on) form.value.ends_on = props.currentTerm.ends_on;
            }
        }
    },
);

const addPrefix = (prefix: string) => {
    const cleanName = form.value.name.replace(/^(Prof\.|Dr\.|Engr\.|Mr\.|Ms\.|Mrs\.)\s*/i, '');
    form.value.name = `${prefix} ${cleanName}`.trim();
};

const guideTabs = computed(() => [
    {
        id: 'floor',
        title: 'Floor Plan & Layouts',
        badge: 'Interactive Seating',
        icon: LayoutGrid,
        description: 'Create a digital twin of your classroom with custom seating blocks, rows, columns, and podium aisles.',
        highlights: [
            { title: 'Custom Blocks & Aisles', text: 'Split your room into left, center, and right wings with custom spacing.' },
            { title: 'Drag & Drop Seating', text: 'Easily assign, swap, or rearrange student seats with real-time visual feedback.' },
            { title: 'Realistic Podium View', text: 'Orientation matches what you see from the front teaching wall.' },
        ],
    },
    {
        id: 'roster',
        title: isOffline.value ? 'Rosters & Enrolled Students' : 'Rosters & QR Enrollment',
        badge: 'Fast Student Management',
        icon: isOffline.value ? Users : QrCode,
        description: isOffline.value
            ? 'Import official student rosters in bulk via CSV or quickly add and seat individual students.'
            : 'Import class lists via CSV or display the room QR code on your projector for instant student self-claiming.',
        highlights: isOffline.value
            ? [
                  { title: 'CSV Batch Import', text: 'Upload class rosters with student IDs and names in one click.' },
                  { title: 'Quick Roster Edit', text: 'Add student photos, update names, or remove inactive accounts.' },
                  { title: 'Instant Chair Badges', text: 'Student names and photos appear directly on their classroom chairs.' },
              ]
            : [
                  { title: 'Instant QR Claiming', text: 'Students scan the QR code and tap their chair to claim their seat.' },
                  { title: 'CSV Batch Import', text: 'Upload class rosters with student IDs and names in one click.' },
                  { title: 'Instant Chair Badges', text: 'Student names and photos appear directly on their classroom chairs.' },
              ],
    },
    {
        id: 'attendance',
        title: '1-Tap Visual Roll Call',
        badge: 'Rapid Attendance',
        icon: CalendarCheck2,
        description: 'Take attendance by glancing at your room. Tap any chair to toggle Present, Late, or Absent, or mark all in 1 tap.',
        highlights: [
            { title: 'Visual Room Status', text: 'Color-coded seats give you an immediate visual overview of room occupancy.' },
            { title: 'Bulk Roll Call Actions', text: 'Mark All Present or All Absent instantly with save safety.' },
            { title: 'Seamless Gradebook Sync', text: 'Attendance sessions feed directly into student attendance scores.' },
        ],
    },
    {
        id: 'grading',
        title: 'Recitations & Gradebooks',
        badge: 'Oral Bonus & Grades',
        icon: Award,
        description: 'Call students fairly with the random caller, record daily oral recitation scores (0-10), and compute weighted college grades.',
        highlights: [
            { title: 'Fair Random Picker', text: 'Calls students fairly and logs oral participation scores.' },
            { title: 'Weighted Gradebook', text: 'Customizable weights for Activities, Quizzes, Exams, Projects, and Attendance.' },
            { title: 'Deficiency Reports', text: 'Export student slips and grade sheets to Excel, CSV, or PDF.' },
        ],
    },
]);

// Step Navigation Handlers
const goToStep = (step: number) => {
    if (step === 1 && !form.value.name.trim()) {
        saveError.value = 'Please enter your name or title before proceeding.';
        return;
    }
    saveError.value = '';
    currentStep.value = step;
};

const saveAndContinueToAi = async () => {
    if (!form.value.name.trim()) {
        saveError.value = 'Please enter your name or title.';
        currentStep.value = 0;
        return;
    }
    if (!form.value.term_name.trim() || !form.value.school_year.trim()) {
        saveError.value = 'Please specify your semester and school year.';
        return;
    }

    isSaving.value = true;
    saveError.value = '';

    try {
        const response = await fetch('/onboarding/quick-setup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                name: form.value.name.trim(),
                term_name: form.value.term_name.trim(),
                school_year: form.value.school_year.trim(),
                starts_on: form.value.starts_on,
                ends_on: form.value.ends_on,
            }),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            emit('updated', {
                teacherName: data.teacher_name,
                term: data.term,
            });
            currentStep.value = 2; // Advance to Local AI Setup
            runOllamaCheck();
        } else {
            saveError.value = data.message || 'Unable to save setup. Please check your inputs.';
        }
    } catch {
        saveError.value = 'An error occurred while saving setup.';
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/75 p-3 sm:p-6 backdrop-blur-xs duration-200 animate-in fade-in print:hidden"
    >
        <div
            class="paper-card relative flex max-h-[94vh] w-full max-w-2xl flex-col overflow-hidden border border-border/80 bg-card p-5 sm:p-7 shadow-2xl duration-200 animate-in zoom-in-95"
            role="dialog"
            aria-modal="true"
            aria-label="ClassCheck Onboarding Setup"
        >
            <!-- Header Bar with Progress Indicator -->
            <div class="flex items-center justify-between border-b border-border/70 pb-3.5">
                <div class="flex items-center gap-2">
                    <span class="rounded-md border border-border/80 bg-secondary/60 px-2.5 py-0.5 font-mono text-xs font-semibold text-muted-foreground">
                        Step {{ currentStep + 1 }} of 5
                    </span>
                    <span class="text-xs font-semibold text-foreground">
                        {{
                            currentStep === 0
                                ? '· Teacher Profile'
                                : currentStep === 1
                                  ? '· Academic Semester'
                                  : currentStep === 2
                                    ? '· Local AI (Octo)'
                                    : currentStep === 3
                                      ? '· System Guides'
                                      : '· Ready to Create Section'
                        }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 rounded-lg text-muted-foreground hover:text-foreground"
                        title="Close setup"
                        @click="emit('close')"
                    >
                        <X class="size-4" />
                    </Button>
                </div>
            </div>

            <!-- Error Banner -->
            <div
                v-if="saveError"
                class="mt-3 rounded-xl border border-rose-500/20 bg-rose-500/10 px-4 py-2.5 text-xs font-medium text-rose-600 dark:text-rose-400"
            >
                {{ saveError }}
            </div>

            <!-- STEP 1: Ask Teacher Name -->
            <div v-if="currentStep === 0" class="flex-1 space-y-6 overflow-y-auto py-5 pr-1">
                <div class="flex items-center gap-3">
                    <div class="grid size-12 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <GraduationCap class="size-6" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">Welcome to ClassCheck</h2>
                        <p class="text-xs text-muted-foreground sm:text-sm">Let's personalize your workspace. What is your name or preferred title?</p>
                    </div>
                </div>

                <div class="space-y-4 rounded-2xl border border-border/70 bg-secondary/20 p-5">
                    <div>
                        <Label for="teacher-name-input" class="text-xs font-semibold text-foreground"> Your Full Name or Title </Label>
                        <div class="mt-2 flex gap-2">
                            <Input
                                id="teacher-name-input"
                                v-model="form.name"
                                type="text"
                                placeholder="e.g., Prof. Juan Dela Cruz, Dr. Santos"
                                class="h-11 rounded-xl bg-card text-sm font-medium"
                                autofocus
                                @keydown.enter.prevent="goToStep(1)"
                            />
                        </div>
                    </div>

                    <!-- Quick Title Prefixes -->
                    <div>
                        <span class="text-[11px] font-medium text-muted-foreground">Quick prefixes:</span>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            <button
                                v-for="prefix in ['Prof.', 'Dr.', 'Engr.', 'Mr.', 'Ms.', 'Mrs.']"
                                :key="prefix"
                                type="button"
                                class="rounded-lg border border-border/70 bg-card px-2.5 py-1 text-xs font-semibold text-muted-foreground transition-colors hover:border-primary hover:bg-primary/10 hover:text-primary"
                                @click="addPrefix(prefix)"
                            >
                                + {{ prefix }}
                            </button>
                        </div>
                    </div>

                    <!-- Live Workspace Greeting Preview -->
                    <div class="rounded-xl border border-border/60 bg-card p-3.5 text-xs">
                        <div class="flex items-center gap-2 text-muted-foreground">
                            <Sparkles class="size-3.5 text-amber-500" />
                            <span>Workspace Greeting Preview:</span>
                        </div>
                        <div class="mt-1 text-sm font-bold text-foreground">
                            Good day, {{ form.name ? form.name.split(' ')[form.name.split(' ').length - 1] : 'Teacher' }}!
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Ask Current Semester & School Year -->
            <div v-else-if="currentStep === 1" class="flex-1 space-y-5 overflow-y-auto py-5 pr-1">
                <div class="flex items-center gap-3">
                    <div class="grid size-12 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <CalendarDays class="size-6" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">Set Current Semester</h2>
                        <p class="text-xs text-muted-foreground sm:text-sm">Configure your active academic term for your classes and sections.</p>
                    </div>
                </div>

                <div class="space-y-4 rounded-2xl border border-border/70 bg-secondary/20 p-5">
                    <!-- Semester Chips -->
                    <div>
                        <Label class="text-xs font-semibold text-foreground"> Academic Semester </Label>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <button
                                v-for="sem in ['1st Semester', '2nd Semester', 'Summer Term', 'Midyear Term']"
                                :key="sem"
                                type="button"
                                class="rounded-xl border p-2.5 text-center text-xs font-semibold transition-all"
                                :class="
                                    form.term_name === sem
                                        ? 'border-primary bg-primary text-white shadow-xs'
                                        : 'border-border/80 bg-card text-foreground hover:bg-secondary'
                                "
                                @click="form.term_name = sem"
                            >
                                {{ sem }}
                            </button>
                        </div>
                    </div>

                    <!-- School Year -->
                    <div>
                        <div class="flex items-center justify-between">
                            <Label for="school-year-input" class="text-xs font-semibold text-foreground"> School Year </Label>
                            <div class="flex gap-1.5">
                                <button
                                    v-for="sy in ['2025-2026', '2026-2027', '2027-2028']"
                                    :key="sy"
                                    type="button"
                                    class="rounded-md border border-border/70 bg-card px-2 py-0.5 font-mono text-[10px] font-medium text-muted-foreground hover:border-primary hover:text-primary"
                                    @click="form.school_year = sy"
                                >
                                    {{ sy }}
                                </button>
                            </div>
                        </div>
                        <Input
                            id="school-year-input"
                            v-model="form.school_year"
                            type="text"
                            placeholder="e.g., 2026-2027"
                            class="mt-1.5 h-10 rounded-xl bg-card text-sm font-medium"
                        />
                    </div>

                    <!-- Start and End Dates -->
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <Label for="starts-on-input" class="text-xs font-medium text-muted-foreground"> Term Starts On </Label>
                            <Input id="starts-on-input" v-model="form.starts_on" type="date" class="mt-1 h-10 rounded-xl bg-card text-xs font-medium" />
                        </div>
                        <div>
                            <Label for="ends-on-input" class="text-xs font-medium text-muted-foreground"> Term Ends On </Label>
                            <Input id="ends-on-input" v-model="form.ends_on" type="date" class="mt-1 h-10 rounded-xl bg-card text-xs font-medium" />
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- STEP 3: Local AI & Hermes 3 Setup (Step index 2) -->
            <div v-else-if="currentStep === 2" class="flex-1 space-y-4 overflow-y-auto py-3 pr-1">
                <div class="flex items-center gap-3">
                    <div class="grid size-12 place-items-center rounded-2xl bg-primary/10 text-primary shrink-0">
                        <Bot class="size-6 text-primary" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">Octo AI & Hermes 3 Setup</h2>
                        <p class="text-xs text-muted-foreground sm:text-sm">Choose whether you want to install and enable local AI features powered by Hermes 3.</p>
                    </div>
                </div>

                <!-- Primary Choice: Install / Enable AI vs Skip AI -->
                <div class="grid gap-3 sm:grid-cols-2">
                    <!-- Choice A: YES, Install / Enable AI -->
                    <button
                        type="button"
                        class="relative flex flex-col justify-between rounded-2xl border p-4 text-left transition-all duration-200 cursor-pointer"
                        :class="
                            wantsAiSetup
                                ? 'border-primary bg-primary/5 ring-2 ring-primary/20 shadow-sm'
                                : 'border-border/80 bg-card hover:border-border hover:bg-secondary/20'
                        "
                        @click="handleSelectAiChoice(true)"
                    >
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="grid size-6 place-items-center rounded-full bg-primary/10 text-primary">
                                        <Sparkles class="size-3.5" />
                                    </span>
                                    <h3 class="text-sm font-bold text-foreground">Yes, Install & Enable AI</h3>
                                </div>
                                <span
                                    v-if="wantsAiSetup"
                                    class="rounded-full bg-primary px-2 py-0.5 font-mono text-[10px] font-bold text-primary-foreground"
                                >
                                    Selected
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground leading-relaxed">
                                Install Hermes 3 (8B) for interactive class insights, student absence lookups, and auto-grading.
                            </p>
                        </div>
                        <div class="mt-3 flex items-center gap-1.5 font-mono text-[10px] text-primary font-medium">
                            <Zap class="size-3" />
                            <span>8GB+ RAM Recommended · 100% Offline</span>
                        </div>
                    </button>

                    <!-- Choice B: NO, Skip AI -->
                    <button
                        type="button"
                        class="relative flex flex-col justify-between rounded-2xl border p-4 text-left transition-all duration-200 cursor-pointer"
                        :class="
                            !wantsAiSetup
                                ? 'border-foreground/80 bg-secondary/30 ring-2 ring-foreground/10 shadow-sm'
                                : 'border-border/80 bg-card hover:border-border hover:bg-secondary/20'
                        "
                        @click="handleSelectAiChoice(false)"
                    >
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="grid size-6 place-items-center rounded-full bg-secondary text-muted-foreground">
                                        <HardDrive class="size-3.5" />
                                    </span>
                                    <h3 class="text-sm font-bold text-foreground">No, Skip AI Setup</h3>
                                </div>
                                <span
                                    v-if="!wantsAiSetup"
                                    class="rounded-full bg-secondary px-2 py-0.5 font-mono text-[10px] font-bold text-foreground"
                                >
                                    Selected
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground leading-relaxed">
                                Run ClassCheck in lightning-fast standard offline mode without downloading any AI models.
                            </p>
                        </div>
                        <div class="mt-3 flex items-center gap-1.5 font-mono text-[10px] text-muted-foreground font-medium">
                            <ShieldCheck class="size-3 text-emerald-600" />
                            <span>Runs on all hardware (4GB RAM)</span>
                        </div>
                    </button>
                </div>

                <!-- Detailed Card based on Choice -->
                <div class="space-y-3 rounded-2xl border border-border/70 bg-secondary/20 p-4 sm:p-5">
                    <!-- IF YES: AI Selected -->
                    <div v-if="wantsAiSetup" class="space-y-3.5">
                        <!-- Case 1: Ollama Online & Hermes 3 is Already Installed -->
                        <div v-if="isOllamaOnline && hasHermes3Model" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <CheckCircle2 class="size-5 text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0" />
                                    <div>
                                        <h4 class="text-sm font-bold text-emerald-950 dark:text-emerald-200">Hermes 3 (8B) is Installed & Ready</h4>
                                        <p class="text-xs text-emerald-800/80 dark:text-emerald-300/80 mt-0.5 leading-relaxed">
                                            Local Ollama service and the <strong class="font-mono text-[11px]">hermes3:8b</strong> model are fully configured. Octo Copilot is ready to assist with real-time class queries.
                                        </p>
                                    </div>
                                </div>
                                <span class="rounded-full bg-emerald-500/20 px-2.5 py-0.5 font-mono text-[10px] font-bold text-emerald-700 dark:text-emerald-300 shrink-0">
                                    Hermes 3 Active
                                </span>
                            </div>
                        </div>

                        <!-- Case 2: Ollama Online BUT Hermes 3 is NOT installed yet -->
                        <div v-else-if="isOllamaOnline && !hasHermes3Model" class="rounded-xl border border-border/80 bg-card p-4 space-y-3">
                            <div class="flex items-start gap-3">
                                <Download class="size-5 text-primary mt-0.5 shrink-0" />
                                <div>
                                    <h4 class="text-sm font-bold text-foreground">Install Hermes 3 Model (4.7 GB)</h4>
                                    <p class="text-xs text-muted-foreground mt-0.5 leading-relaxed">
                                        Ollama is connected on your computer. Click below to download and install the official <code class="font-mono text-[11px] bg-secondary px-1 py-0.5 rounded">hermes3:8b</code> model directly into Ollama.
                                    </p>
                                </div>
                            </div>

                            <!-- Live Download Progress Bar -->
                            <div v-if="isPullingModel" class="space-y-2 rounded-xl border border-primary/30 bg-primary/5 p-3.5">
                                <div class="flex items-center justify-between text-xs font-semibold text-primary">
                                    <div class="flex items-center gap-2">
                                        <Loader2 class="size-3.5 animate-spin" />
                                        <span>{{ pullProgress.status || 'Downloading Hermes 3...' }}</span>
                                    </div>
                                    <span v-if="pullProgress.percent !== undefined" class="font-mono font-bold">{{ pullProgress.percent }}%</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-secondary">
                                    <div
                                        class="h-full bg-primary transition-all duration-300 ease-out"
                                        :style="{ width: `${pullProgress.percent ?? 0}%` }"
                                    />
                                </div>
                            </div>

                            <!-- Download Error Message -->
                            <div v-if="downloadError" class="rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-600 dark:text-rose-400">
                                {{ downloadError }}
                            </div>

                            <!-- Install Button -->
                            <div v-if="!isPullingModel" class="flex items-center gap-2 pt-1">
                                <Button
                                    type="button"
                                    class="ink-button !h-9 !rounded-lg !px-4 text-xs font-semibold"
                                    @click="handleDownloadHermes"
                                >
                                    <Download class="mr-1.5 size-3.5" />
                                    <span>Download & Install Hermes 3</span>
                                </Button>
                            </div>
                        </div>

                        <!-- Case 3: Ollama is NOT running or NOT installed -->
                        <div v-else class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 space-y-3">
                            <div class="flex items-start gap-3">
                                <AlertTriangle class="size-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
                                <div>
                                    <h4 class="text-sm font-bold text-amber-950 dark:text-amber-200">Ollama Not Running on This PC</h4>
                                    <p class="text-xs text-amber-800/80 dark:text-amber-300/80 mt-0.5 leading-relaxed">
                                        To run Hermes 3 offline on your hardware, Ollama must be installed. You can download the official Windows installer below.
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                <a
                                    href="https://ollama.com/download/OllamaSetup.exe"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-card px-3 text-xs font-semibold text-foreground transition-colors hover:bg-secondary"
                                >
                                    <Download class="size-3.5 text-primary" />
                                    <span>Download Ollama for Windows</span>
                                    <ExternalLink class="size-3 text-muted-foreground" />
                                </a>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-8 rounded-lg text-xs"
                                    :disabled="isCheckingOllama"
                                    @click="runOllamaCheck"
                                >
                                    <Loader2 v-if="isCheckingOllama" class="mr-1.5 size-3.5 animate-spin" />
                                    <RefreshCw v-else class="mr-1.5 size-3.5" />
                                    <span>Retry Detection</span>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- IF NO: Skip AI Selected -->
                    <div v-else class="rounded-xl border border-border/80 bg-card p-4 space-y-2">
                        <div class="flex items-center gap-2.5">
                            <ShieldCheck class="size-5 text-emerald-600" />
                            <h4 class="text-sm font-bold text-foreground">Standard Offline Mode Configured</h4>
                        </div>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            No models will be downloaded and no background AI processes will run. All core features (interactive seating layout, 1-tap roll call, oral participation, and weighted gradebook computation) will operate with zero network dependency.
                        </p>
                        <p class="text-[11px] text-muted-foreground/80 italic pt-1">
                            Note: You can install Ollama and enable Hermes 3 anytime from Settings.
                        </p>
                    </div>
                </div>
            </div>

            <!-- STEP 4: System Guides (What the system is about) -->
            <div v-else-if="currentStep === 3" class="flex-1 space-y-4 overflow-y-auto py-3 pr-1">
                <!-- Navigation Tabs between 4 Pillars -->
                <div class="flex items-center gap-1 rounded-xl border border-border/70 bg-secondary/50 p-1">
                    <button
                        v-for="(guide, gIdx) in guideTabs"
                        :key="guide.id"
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg py-2 text-center text-xs font-medium transition-colors"
                        :class="activeGuideTab === gIdx ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeGuideTab = gIdx"
                    >
                        <component :is="guide.icon" class="size-3.5" />
                        <span class="hidden sm:inline">{{ guide.title.split('&')[0].trim() }}</span>
                    </button>
                </div>

                <!-- Active Guide Content -->
                <div class="space-y-4 rounded-2xl border border-border/80 bg-zinc-950 p-4 text-zinc-100 shadow-inner sm:p-5">
                    <!-- Dynamic Visual Preview Box -->
                    <div class="mb-3.5 flex items-center justify-between border-b border-zinc-800/80 pb-2.5 text-xs text-zinc-400">
                        <div class="flex items-center gap-1.5">
                            <span class="size-2.5 rounded-full bg-rose-500/80" />
                            <span class="size-2.5 rounded-full bg-amber-500/80" />
                            <span class="size-2.5 rounded-full bg-emerald-500/80" />
                            <span class="ml-2 font-mono text-[11px] text-zinc-400">classcheck // {{ guideTabs[activeGuideTab].id }}_preview</span>
                        </div>
                        <div class="flex items-center gap-1.5 font-mono text-[10px] text-zinc-400">
                            <Loader2 class="size-3 animate-spin text-primary" />
                            <span>interactive</span>
                        </div>
                    </div>

                    <!-- Pillar 0: Seating Grid -->
                    <div v-if="activeGuideTab === 0" class="space-y-3 py-1">
                        <div class="rounded-xl border border-zinc-800 bg-zinc-900/80 p-3 text-center">
                            <div class="mx-auto flex max-w-xs items-center justify-center gap-2 rounded-lg border border-zinc-700 bg-zinc-800/90 py-1 font-mono text-[11px] font-semibold text-zinc-300">
                                <span>FRONT PODIUM / WHITEBOARD</span>
                            </div>
                            <div class="mt-3 grid grid-cols-4 gap-2">
                                <div
                                    v-for="seat in ['A1', 'A2', 'A3', 'A4', 'B1', 'B2', 'B3', 'B4']"
                                    :key="seat"
                                    class="flex flex-col items-center justify-center rounded-lg border border-zinc-700/60 bg-zinc-800/60 p-2 transition-all"
                                    :class="seat === 'A2' ? 'border-primary bg-primary/20 text-primary' : 'text-zinc-400'"
                                >
                                    <Armchair class="size-4" />
                                    <span class="mt-1 font-mono text-[10px] font-bold">{{ seat }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pillar 1: Roster -->
                    <div v-else-if="activeGuideTab === 1" class="space-y-2 py-1">
                        <div
                            v-for="(student, sIdx) in [
                                { name: 'Dela Cruz, Juan M.', id: '2024-00101', status: 'Enrolled' },
                                { name: 'Santos, Maria C.', id: '2024-00102', status: 'Seat A2' },
                            ]"
                            :key="sIdx"
                            class="flex items-center justify-between rounded-lg border border-zinc-800 bg-zinc-900/80 p-2.5 text-xs"
                        >
                            <div class="flex items-center gap-2.5">
                                <div class="grid size-7 place-items-center rounded-full bg-primary/20 font-bold text-primary">
                                    {{ student.name.charAt(0) }}
                                </div>
                                <div>
                                    <div class="font-semibold text-zinc-200">{{ student.name }}</div>
                                    <div class="font-mono text-[10px] text-zinc-400">{{ student.id }}</div>
                                </div>
                            </div>
                            <span class="rounded bg-zinc-800 px-2 py-0.5 font-mono text-[10px] text-zinc-300">
                                {{ student.status }}
                            </span>
                        </div>
                    </div>

                    <!-- Pillar 2: Attendance Roll Call -->
                    <div v-else-if="activeGuideTab === 2" class="space-y-2 py-1">
                        <div class="grid grid-cols-3 gap-2">
                            <div class="rounded-lg border border-emerald-500/40 bg-emerald-950/30 p-2.5 text-center">
                                <div class="text-xs font-bold text-emerald-400">Present (38)</div>
                                <div class="text-[10px] text-zinc-400">95% Today</div>
                            </div>
                            <div class="rounded-lg border border-amber-500/40 bg-amber-950/30 p-2.5 text-center">
                                <div class="text-xs font-bold text-amber-400">Late (1)</div>
                                <div class="text-[10px] text-zinc-400">Grace period</div>
                            </div>
                            <div class="rounded-lg border border-rose-500/40 bg-rose-950/30 p-2.5 text-center">
                                <div class="text-xs font-bold text-rose-400">Absent (1)</div>
                                <div class="text-[10px] text-zinc-400">Excused</div>
                            </div>
                        </div>
                    </div>

                    <!-- Pillar 3: Grading & Recitation -->
                    <div v-else class="space-y-2.5 py-1">
                        <div class="flex items-center justify-between rounded-lg border border-amber-500/40 bg-amber-950/25 p-3">
                            <div class="flex items-center gap-2.5">
                                <div class="grid size-8 place-items-center rounded-lg bg-amber-500/20 text-amber-400">
                                    <Trophy class="size-4" />
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-amber-200">Random Recitation Winner</div>
                                    <div class="text-[11px] text-zinc-300">Mendoza, Elena P. — +10 Oral Points</div>
                                </div>
                            </div>
                            <span class="rounded bg-amber-500/20 px-2 py-0.5 font-mono text-[10px] font-bold text-amber-300"> Max 10/day </span>
                        </div>
                    </div>
                </div>

                <!-- Text Breakdown for Selected Pillar -->
                <div>
                    <h3 class="text-base font-bold text-foreground">{{ guideTabs[activeGuideTab].title }}</h3>
                    <p class="mt-1 text-xs text-muted-foreground leading-relaxed">{{ guideTabs[activeGuideTab].description }}</p>
                </div>

                <div class="grid gap-2 sm:grid-cols-3">
                    <div
                        v-for="(item, idx) in guideTabs[activeGuideTab].highlights"
                        :key="idx"
                        class="rounded-xl border border-border/70 bg-secondary/25 p-3"
                    >
                        <div class="flex items-center gap-1.5">
                            <CheckCircle2 class="size-3 text-primary shrink-0" />
                            <h4 class="text-xs font-semibold text-foreground">{{ item.title }}</h4>
                        </div>
                        <p class="mt-1 text-[11px] text-muted-foreground leading-normal">{{ item.text }}</p>
                    </div>
                </div>
            </div>

            <!-- STEP 5: Ready to Create Section (Step index 4) -->
            <div v-else-if="currentStep === 4" class="flex-1 space-y-6 overflow-y-auto py-5 pr-1">
                <div class="flex items-center gap-3">
                    <div class="grid size-12 place-items-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <Sparkles class="size-6" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">You're All Set!</h2>
                        <p class="text-xs text-muted-foreground sm:text-sm">Your teacher profile, active semester, and preferences are ready. Let's create your first section.</p>
                    </div>
                </div>

                <!-- Summary Card -->
                <div class="rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-5 shadow-sm space-y-3.5">
                    <div class="flex items-center justify-between border-b border-border/60 pb-3">
                        <div class="flex items-center gap-2">
                            <User class="size-4 text-primary" />
                            <span class="text-xs font-semibold text-muted-foreground">Instructor</span>
                        </div>
                        <span class="text-sm font-bold text-foreground">{{ form.name }}</span>
                    </div>

                    <div class="flex items-center justify-between border-b border-border/60 pb-3">
                        <div class="flex items-center gap-2">
                            <Calendar class="size-4 text-primary" />
                            <span class="text-xs font-semibold text-muted-foreground">Active Term</span>
                        </div>
                        <span class="text-sm font-bold text-foreground">{{ form.term_name }} · SY {{ form.school_year }}</span>
                    </div>

                    <div class="flex items-center justify-between border-b border-border/60 pb-3">
                        <div class="flex items-center gap-2">
                            <Bot class="size-4 text-primary" />
                            <span class="text-xs font-semibold text-muted-foreground">Octo Local AI</span>
                        </div>
                        <span class="text-xs font-semibold" :class="isAiEnabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'">
                            {{ isAiEnabled ? (isOllamaOnline ? 'Enabled (Ollama Online)' : 'Enabled') : 'Disabled (Lightweight Mode)' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <School class="size-4 text-primary" />
                            <span class="text-xs font-semibold text-muted-foreground">Next Action</span>
                        </div>
                        <span class="text-xs font-semibold text-primary">Create Course Classroom & Seating Layout</span>
                    </div>
                </div>

                <div class="rounded-xl border border-dashed border-border bg-secondary/30 p-4 text-center">
                    <p class="text-xs text-muted-foreground">
                        In the next step, you'll specify your subject code (e.g. <strong>IT 413</strong>), room number, and class schedule.
                    </p>
                </div>
            </div>

            <!-- Footer & Stepper Action Controls -->
            <div class="flex items-center justify-between border-t border-border/70 pt-3.5">
                <!-- Stepper Progress Dots -->
                <div class="flex items-center gap-1.5">
                    <button
                        v-for="i in 5"
                        :key="i"
                        type="button"
                        class="h-2 rounded-full transition-all"
                        :class="i - 1 === currentStep ? 'w-6 bg-foreground' : 'w-2 bg-muted-foreground/30 hover:bg-muted-foreground/50'"
                        :title="`Go to step ${i}`"
                        @click="goToStep(i - 1)"
                    />
                </div>

                <!-- Back / Next / Submit Buttons -->
                <div class="flex items-center gap-2">
                    <Button
                        v-if="currentStep > 0"
                        type="button"
                        variant="outline"
                        class="h-9 rounded-lg px-3 text-xs font-medium"
                        @click="currentStep--"
                    >
                        <ArrowLeft class="mr-1.5 size-3.5" />
                        <span>Back</span>
                    </Button>

                    <!-- Step 0 Action -->
                    <Button
                        v-if="currentStep === 0"
                        type="button"
                        class="ink-button !h-9 !rounded-lg !px-4 text-xs font-medium"
                        @click="goToStep(1)"
                    >
                        <span>Next: Semester</span>
                        <ArrowRight class="ml-1.5 size-3.5" />
                    </Button>

                    <!-- Step 1 Action: Saves Name and Semester, moves to Step 2 AI -->
                    <Button
                        v-else-if="currentStep === 1"
                        type="button"
                        :disabled="isSaving"
                        class="ink-button !h-9 !rounded-lg !px-4 text-xs font-medium"
                        @click="saveAndContinueToAi"
                    >
                        <Loader2 v-if="isSaving" class="mr-1.5 size-3.5 animate-spin" />
                        <span>{{ isSaving ? 'Saving...' : 'Save & Next: AI Setup' }}</span>
                        <ArrowRight v-if="!isSaving" class="ml-1.5 size-3.5" />
                    </Button>

                    <!-- Step 2 Action: Advances to Step 3 Guides -->
                    <Button
                        v-else-if="currentStep === 2"
                        type="button"
                        class="ink-button !h-9 !rounded-lg !px-4 text-xs font-medium"
                        @click="currentStep = 3"
                    >
                        <span>Next: System Guides</span>
                        <ArrowRight class="ml-1.5 size-3.5" />
                    </Button>

                    <!-- Step 3 Action: Advances to Step 4 Summary -->
                    <Button
                        v-else-if="currentStep === 3"
                        type="button"
                        class="ink-button !h-9 !rounded-lg !px-4 text-xs font-medium"
                        @click="currentStep = 4"
                    >
                        <span>Next: Create Section</span>
                        <ArrowRight class="ml-1.5 size-3.5" />
                    </Button>

                    <!-- Step 4 Actions: Primary Create Section CTA + Secondary Close -->
                    <div v-else class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="h-9 rounded-lg px-3 text-xs font-medium"
                            @click="emit('close')"
                        >
                            <span>Dashboard</span>
                        </Button>

                        <Link
                            href="/sections/create"
                            class="ink-button !h-9 !rounded-lg !px-4 text-xs font-bold shadow-sm hover:scale-[1.02] transition-all"
                            @click="emit('close')"
                        >
                            <PlusCircle class="mr-1.5 size-4" />
                            <span>Create Your First Section</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
