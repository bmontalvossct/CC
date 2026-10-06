<script setup lang="ts">
import OctoSpinner from '@/components/OctoSpinner.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { modalBackdropVariants, modalContentVariants, tabIndicatorTransition } from '@/lib/motion';
import { router } from '@inertiajs/vue3';
import { AnimatePresence, motion } from 'motion-v';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    BookOpen,
    Check,
    CheckSquare,
    ChevronDown,
    ChevronUp,
    Code,
    Copy,
    Cpu,
    ExternalLink,
    FileCheck,
    FileCode,
    FileDown,
    FileText,
    HelpCircle,
    Layers,
    ListChecks,
    Loader2,
    Minus,
    PenLine,
    Plus,
    Printer,
    RefreshCw,
    Save,
    Search,
    Sliders,
    Sparkles,
    Square,
    Trash2,
    Wand2,
    X,
} from 'lucide-vue-next';
import { onClickOutside } from '@vueuse/core';
import { computed, nextTick, onMounted, ref, watch } from 'vue';


export type CourseModuleItem = {
    id: number;
    section_id: number;
    module_number: string;
    title: string;
    description: string | null;
    link_url?: string | null;
    has_file: boolean;
    file_name: string | null;
    file_size?: number | null;
    formatted_file_size?: string | null;
    file_mime?: string | null;
    sort_order: number;
};

export type TestTypeConfig = {
    type: 'identification' | 'enumeration' | 'explanation' | 'code_review' | 'multiple_choice' | 'true_false';
    label: string;
    description: string;
    enabled: boolean;
    items_count: number;
    points_per_item: number;
};

const props = defineProps<{
    open: boolean;
    section: {
        id: number;
        name: string;
        subject_code?: string;
        subject_title: string;
    };
    initialModules?: CourseModuleItem[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'created', assessment: any): void;
}>();

// Navigation & Steps: 'config' | 'generating' | 'review'
const currentStep = ref<'config' | 'generating' | 'review'>('config');

// Modules state
const modules = ref<CourseModuleItem[]>(props.initialModules || []);
const isLoadingModules = ref(false);
const moduleSearch = ref('');
const selectedModuleIds = ref<number[]>([]);

// Model & AI status
const isCheckingStatus = ref(false);
const hermesStatus = ref<{
    online: boolean;
    model: string;
    is_hermes: boolean;
    latency_ms: number | null;
    gemini_available?: boolean;
    gemini_model?: string;
    active_engine?: 'gemini' | 'ollama';
}>({
    online: true,
    model: 'gemini-2.5-flash',
    is_hermes: false,
    latency_ms: null,
    gemini_available: false,
});
const selectedEngine = ref<'auto' | 'gemini' | 'ollama'>('auto');

// Exam Configuration Form
const examTitle = ref('');
const termPeriod = ref<'midterm' | 'final' | 'prelim'>('midterm');
const difficulty = ref<'balanced' | 'conceptual' | 'rigorous'>('balanced');
const customInstructions = ref('');

// Test Types Configuration
const testTypes = ref<TestTypeConfig[]>([
    {
        type: 'identification',
        label: 'Identification',
        description: 'Direct concept, definition, and terminology recall.',
        enabled: true,
        items_count: 10,
        points_per_item: 1,
    },
    {
        type: 'enumeration',
        label: 'Enumeration',
        description: 'Listing key elements, principles, stages, or components.',
        enabled: true,
        items_count: 5,
        points_per_item: 2,
    },
    {
        type: 'explanation',
        label: 'Explanation / Essay',
        description: 'Conceptual understanding, synthesis, and deep reasoning.',
        enabled: true,
        items_count: 3,
        points_per_item: 5,
    },
    {
        type: 'code_review',
        label: 'Code Review & Analysis',
        description: 'Spot syntax/logic bugs, predict outputs, or optimize snippets.',
        enabled: true,
        items_count: 2,
        points_per_item: 5,
    },
    {
        type: 'multiple_choice',
        label: 'Multiple Choice',
        description: 'Four lettered options (A, B, C, D) with one correct key.',
        enabled: false,
        items_count: 10,
        points_per_item: 1,
    },
    {
        type: 'true_false',
        label: 'True or False',
        description: 'Direct factual statements evaluating theoretical grasp.',
        enabled: false,
        items_count: 5,
        points_per_item: 1,
    },
]);

// Live Totals
const activeTestTypes = computed(() => testTypes.value.filter((t) => t.enabled));
const totalItems = computed(() => activeTestTypes.value.reduce((sum, t) => sum + (Number(t.items_count) || 0), 0));
const totalPoints = computed(() =>
    activeTestTypes.value.reduce((sum, t) => sum + (Number(t.items_count) || 0) * (Number(t.points_per_item) || 0), 0),
);

// Filtered modules based on search query
const filteredModules = computed(() => {
    const q = moduleSearch.value.trim().toLowerCase();
    if (!q) return modules.value;
    return modules.value.filter(
        (m) =>
            m.module_number.toLowerCase().includes(q) ||
            m.title.toLowerCase().includes(q) ||
            (m.description && m.description.toLowerCase().includes(q)) ||
            (m.file_name && m.file_name.toLowerCase().includes(q)),
    );
});

// Quick Selection Helpers
const selectAllModules = () => {
    selectedModuleIds.value = modules.value.map((m) => m.id);
};

const deselectAllModules = () => {
    selectedModuleIds.value = [];
};

const toggleModuleSelection = (id: number) => {
    const idx = selectedModuleIds.value.indexOf(id);
    if (idx >= 0) {
        selectedModuleIds.value.splice(idx, 1);
    } else {
        selectedModuleIds.value.push(id);
    }
};

const isAllSelected = computed(() => modules.value.length > 0 && selectedModuleIds.value.length === modules.value.length);

// Stream generation state
const isGenerating = ref(false);
const generationStatusText = ref('Connecting to Hermes 3...');
const streamedContent = ref('');
const streamContainerRef = ref<HTMLDivElement | null>(null);
let abortController: AbortController | null = null;
const errorMessage = ref('');

// Generation Results
const studentPaper = ref('');
const teacherAnswerKey = ref('');
const structuredRubric = ref<any>(null);
const activeReviewTab = ref<'student' | 'answers' | 'editor'>('student');
const editableFullExam = ref('');
const copiedTarget = ref<'student' | 'answers' | 'full' | null>(null);

// Saving state
const isSaving = ref(false);
const saveSuccessMessage = ref('');

// Load modules if not provided or when section changes
const fetchSectionModules = async () => {
    isLoadingModules.value = true;
    try {
        const res = await fetch(`/sections/${props.section.id}/exam-generator/modules`, {
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            modules.value = data.modules || [];
            // Pre-select all available modules by default for convenience
            if (selectedModuleIds.value.length === 0) {
                selectedModuleIds.value = modules.value.map((m) => m.id);
            }
        }
    } catch (err) {
        console.error('Failed to load modules for exam generator:', err);
    } finally {
        isLoadingModules.value = false;
    }
};

const checkHermesStatus = async () => {
    isCheckingStatus.value = true;
    try {
        const res = await fetch(`/sections/${props.section.id}/exam-generator/status`, {
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            hermesStatus.value = data;
            if (data.gemini_available) {
                selectedEngine.value = 'gemini';
            } else {
                selectedEngine.value = 'ollama';
            }
        }
    } catch {
        // Keep defaults
    } finally {
        isCheckingStatus.value = false;
    }
};

// Title Presets
const setTitlePreset = (preset: 'midterm' | 'final' | 'prelim' | 'quiz') => {
    const code = props.section.subject_code ? `${props.section.subject_code} - ` : '';
    if (preset === 'midterm') {
        termPeriod.value = 'midterm';
        examTitle.value = `${code}Midterm Examination`;
    } else if (preset === 'final') {
        termPeriod.value = 'final';
        examTitle.value = `${code}Final Examination`;
    } else if (preset === 'prelim') {
        termPeriod.value = 'prelim';
        examTitle.value = `${code}Prelim Examination`;
    } else {
        examTitle.value = `${code}Comprehensive Assessment`;
    }
};

// Reset dialog state when opened
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            currentStep.value = 'config';
            errorMessage.value = '';
            saveSuccessMessage.value = '';
            if (!examTitle.value) {
                setTitlePreset('midterm');
            }
            if (!props.initialModules || props.initialModules.length === 0) {
                fetchSectionModules();
            } else {
                modules.value = props.initialModules;
                if (selectedModuleIds.value.length === 0) {
                    selectedModuleIds.value = props.initialModules.map((m) => m.id);
                }
            }
            checkHermesStatus();
        } else {
            if (abortController) {
                abortController.abort();
                abortController = null;
            }
        }
    },
    { immediate: true },
);

// Stream generation handler
const handleStartGeneration = async () => {
    errorMessage.value = '';
    if (!examTitle.value.trim()) {
        errorMessage.value = 'Please provide an examination title.';
        return;
    }
    if (activeTestTypes.value.length === 0) {
        errorMessage.value = 'Please enable at least one test type (e.g. Identification, Enumeration).';
        return;
    }

    currentStep.value = 'generating';
    isGenerating.value = true;
    streamedContent.value = '';
    const engineLabel = selectedEngine.value === 'gemini' ? 'Gemini 2.5 Flash' : 'Hermes 3';
    generationStatusText.value = `${engineLabel} is organizing examination structure...`;

    abortController = new AbortController();

    const payload = {
        title: examTitle.value.trim(),
        term_period: termPeriod.value,
        module_ids: selectedModuleIds.value,
        provider: selectedEngine.value,
        test_types: activeTestTypes.value.map((t) => ({
            type: t.type,
            label: t.label,
            items_count: Number(t.items_count),
            points_per_item: Number(t.points_per_item),
        })),
        difficulty: difficulty.value,
        instructions: customInstructions.value.trim() || null,
    };

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(`/sections/${props.section.id}/exam-generator/generate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/x-ndjson',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
            signal: abortController.signal,
        });

        if (!response.ok) {
            const errBody = await response.text();
            let parsedMsg = errBody;
            try {
                const j = JSON.parse(errBody);
                parsedMsg = j.message || j.error || errBody;
            } catch {
                // Ignore parse error
            }
            throw new Error(parsedMsg || `Failed to generate exam (HTTP ${response.status})`);
        }

        const reader = response.body?.getReader();
        if (!reader) {
            throw new Error('Unable to read streaming response.');
        }

        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';

            for (const line of lines) {
                const trimmed = line.trim();
                if (!trimmed) continue;

                try {
                    const event = JSON.parse(trimmed);
                    if (event.type === 'status') {
                        generationStatusText.value = event.message || 'Generating...';
                    } else if (event.type === 'delta') {
                        streamedContent.value += event.text || '';
                        // Auto scroll
                        if (streamContainerRef.value) {
                            streamContainerRef.value.scrollTop = streamContainerRef.value.scrollHeight;
                        }
                    } else if (event.type === 'done') {
                        studentPaper.value = event.student_paper || '';
                        teacherAnswerKey.value = event.answer_key || '';
                        structuredRubric.value = event.structured_rubric || null;
                        editableFullExam.value = event.full_exam || streamedContent.value;
                    } else if (event.type === 'error') {
                        throw new Error(event.message || 'Generation failed.');
                    }
                } catch (e: any) {
                    if (e.message && e.message.includes('Generation failed')) {
                        throw e;
                    }
                }
            }
        }

        // Final split check if not handled by event
        if (!studentPaper.value) {
            studentPaper.value = streamedContent.value;
        }
        if (!editableFullExam.value) {
            editableFullExam.value = streamedContent.value;
        }

        currentStep.value = 'review';
        activeReviewTab.value = 'student';
    } catch (err: any) {
        if (err.name !== 'AbortError') {
            errorMessage.value = err.message || 'An error occurred during exam generation.';
            currentStep.value = 'config';
        }
    } finally {
        isGenerating.value = false;
        abortController = null;
    }
};

const handleCancelGeneration = () => {
    if (abortController) {
        abortController.abort();
        abortController = null;
    }
    isGenerating.value = false;
    currentStep.value = 'config';
};

// Copy actions
const copyToClipboard = async (text: string, target: 'student' | 'answers' | 'full') => {
    try {
        await navigator.clipboard.writeText(text);
        copiedTarget.value = target;
        setTimeout(() => {
            if (copiedTarget.value === target) {
                copiedTarget.value = null;
            }
        }, 2200);
    } catch (err) {
        console.error('Failed to copy to clipboard:', err);
    }
};

// Print Action
const handlePrintExam = () => {
    const printContent = activeReviewTab.value === 'answers' ? teacherAnswerKey.value : studentPaper.value;

    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${examTitle.value || 'ClassCheck Examination'}</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    padding: 30px 40px;
                    color: #09090b;
                    line-height: 1.6;
                    font-size: 13pt;
                }
                h1, h2, h3, h4 { color: #000; margin-top: 1.2em; margin-bottom: 0.4em; }
                h1 { font-size: 18pt; text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; }
                h2 { font-size: 15pt; }
                h3 { font-size: 13pt; }
                pre, code { font-family: "Courier New", Courier, monospace; background: #f4f4f5; padding: 2px 5px; border-radius: 4px; font-size: 11pt; }
                pre { padding: 12px; border: 1px solid #d4d4d8; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
                hr { border: none; border-top: 1px solid #d4d4d8; margin: 20px 0; }
                @media print {
                    body { padding: 10mm 15mm; }
                    button { display: none; }
                }
            </style>
        </head>
        <body>
            <div style="white-space: pre-wrap;">${printContent.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>
            <script>
                window.onload = function() { window.print(); }
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
};

// Word (.docx) Export state & handler
const isExportingDocx = ref(false);
const showDocxDropdown = ref(false);
const docxDropdownRef = ref<HTMLDivElement | null>(null);

onClickOutside(docxDropdownRef, () => {
    showDocxDropdown.value = false;
});

const handleExportDocx = async (mode: 'student' | 'both' | 'answers' = 'student') => {
    isExportingDocx.value = true;
    showDocxDropdown.value = false;
    errorMessage.value = '';

    const payload = {
        title: examTitle.value.trim() || 'Examination',
        exam_content: activeReviewTab.value === 'editor' ? editableFullExam.value : (studentPaper.value || editableFullExam.value),
        answer_key: teacherAnswerKey.value || null,
        mode,
        max_points: totalPoints.value,
    };

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(`/sections/${props.section.id}/exam-generator/export-docx`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            let errorMsg = `Failed to export Word document (HTTP ${response.status})`;
            try {
                const errJson = await response.json();
                errorMsg = errJson.message || errorMsg;
            } catch {
                const errTxt = await response.text();
                errorMsg = errTxt || errorMsg;
            }
            throw new Error(errorMsg);
        }

        const disposition = response.headers.get('content-disposition') || '';
        let filename = `${(examTitle.value || 'Exam').replace(/\s+/g, '_')}_${mode}.docx`;
        const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
        if (match && match[1]) {
            filename = match[1].replace(/['"]/g, '').trim();
        }

        const blob = await response.blob();
        const blobUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(blobUrl);
    } catch (err: any) {
        errorMessage.value = err.message || 'Failed to download Word document.';
    } finally {
        isExportingDocx.value = false;
    }
};

// Save directly as Section Assessment
const handleSaveAsAssessment = async () => {
    isSaving.value = true;
    errorMessage.value = '';
    saveSuccessMessage.value = '';

    const payload = {
        title: examTitle.value.trim(),
        term_period: termPeriod.value,
        conducted_on: new Date().toISOString().split('T')[0],
        max_points: totalPoints.value,
        exam_content: activeReviewTab.value === 'editor' ? editableFullExam.value : studentPaper.value,
        answer_key: teacherAnswerKey.value || null,
        structured_rubric: structuredRubric.value || null,
    };

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch(`/sections/${props.section.id}/exam-generator/save-assessment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();

        if (res.ok && data.success) {
            saveSuccessMessage.value = data.message || 'Exam saved successfully!';
            emit('created', data.assessment);
            setTimeout(() => {
                emit('close');
                if (data.redirect_url) {
                    router.visit(data.redirect_url);
                } else {
                    router.reload();
                }
            }, 800);
        } else {
            throw new Error(data.message || data.error || 'Failed to save assessment.');
        }
    } catch (err: any) {
        errorMessage.value = err.message || 'An error occurred while saving the assessment.';
    } finally {
        isSaving.value = false;
    }
};

const handleClose = () => {
    if (isGenerating.value && abortController) {
        abortController.abort();
    }
    emit('close');
};
</script>

<template>
    <AnimatePresence>
        <motion.div
            v-if="open"
            :initial="modalBackdropVariants.initial"
            :animate="modalBackdropVariants.animate"
            :exit="modalBackdropVariants.exit"
            :transition="modalBackdropVariants.transition"
            class="backdrop-blur-xs fixed inset-0 z-50 grid place-items-center bg-zinc-950/75 p-2 sm:p-4 md:p-6 print:hidden"
        >
            <motion.div
                :initial="modalContentVariants.initial"
                :animate="modalContentVariants.animate"
                :exit="modalContentVariants.exit"
                :transition="modalContentVariants.transition"
                class="paper-card relative flex max-h-[96vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-border/80 bg-card shadow-2xl"
                role="dialog"
                aria-modal="true"
                aria-label="Hermes Exam Generator"
            >
            <!-- Top Header Bar -->
            <div class="flex items-center justify-between border-b border-border/70 px-5 py-3.5 sm:px-6">
                <div class="flex items-center gap-3">
                    <div class="grid size-9 place-items-center rounded-xl bg-primary/10 text-primary">
                        <Sparkles class="size-4.5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold tracking-tight text-foreground sm:text-lg">
                                AI Exam Generator
                            </h2>
                            <span
                                v-if="hermesStatus.gemini_available"
                                class="inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 font-mono text-[10px] font-bold text-emerald-600 dark:text-emerald-400"
                                title="Connected to Google Gemini Flash API"
                            >
                                <Sparkles class="size-3" />
                                Gemini 2.5 Flash
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full border border-primary/30 bg-primary/10 px-2 py-0.5 font-mono text-[10px] font-bold text-primary"
                                title="Local Ollama AI runner"
                            >
                                <Cpu class="size-3" />
                                {{ hermesStatus.model || 'hermes3:8b' }} (Local)
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ section.subject_code ? `${section.subject_code} · ` : '' }}{{ section.subject_title || section.name }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 rounded-lg text-muted-foreground hover:text-foreground"
                        title="Close generator"
                        @click="handleClose"
                    >
                        <X class="size-4" />
                    </Button>
                </div>
            </div>

            <!-- Error Banner -->
            <div
                v-if="errorMessage"
                class="mx-5 mt-3 flex items-center justify-between gap-2 rounded-xl border border-rose-500/20 bg-rose-500/10 px-4 py-2.5 text-xs font-medium text-rose-600 dark:text-rose-400 sm:mx-6"
            >
                <div class="flex items-center gap-2">
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </div>
                <button
                    type="button"
                    class="text-rose-500 hover:text-rose-700 dark:hover:text-rose-300"
                    @click="errorMessage = ''"
                >
                    <X class="size-3.5" />
                </button>
            </div>

            <!-- Success Banner -->
            <div
                v-if="saveSuccessMessage"
                class="mx-5 mt-3 flex items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-2.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400 sm:mx-6"
            >
                <Check class="size-4" />
                <span>{{ saveSuccessMessage }}</span>
            </div>

            <!-- ================= STEP 1: CONFIGURATION ================= -->
            <div
                v-if="currentStep === 'config'"
                class="flex-1 space-y-5 overflow-y-auto px-5 py-4 sm:px-6"
            >
                <!-- Title & Period Settings -->
                <div class="space-y-3 rounded-2xl border border-border/70 bg-secondary/20 p-4 sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <Label for="exam-title-input" class="text-xs font-bold text-foreground">
                            Examination Title
                        </Label>
                        <div class="flex items-center gap-1.5 text-[11px]">
                            <span class="text-muted-foreground">Presets:</span>
                            <button
                                type="button"
                                class="rounded border border-border/70 bg-card px-2 py-0.5 font-medium text-muted-foreground transition hover:border-primary hover:text-primary"
                                @click="setTitlePreset('prelim')"
                            >
                                Prelim
                            </button>
                            <button
                                type="button"
                                class="rounded border border-border/70 bg-card px-2 py-0.5 font-medium text-muted-foreground transition hover:border-primary hover:text-primary"
                                @click="setTitlePreset('midterm')"
                            >
                                Midterm
                            </button>
                            <button
                                type="button"
                                class="rounded border border-border/70 bg-card px-2 py-0.5 font-medium text-muted-foreground transition hover:border-primary hover:text-primary"
                                @click="setTitlePreset('final')"
                            >
                                Final
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <Input
                                id="exam-title-input"
                                v-model="examTitle"
                                type="text"
                                placeholder="e.g., Midterm Examination - Object-Oriented Programming"
                                class="h-10 rounded-xl bg-card text-sm font-medium"
                            />
                        </div>
                        <div>
                            <select
                                v-model="termPeriod"
                                class="h-10 w-full rounded-xl border border-input bg-card px-3 text-xs font-semibold text-foreground focus:ring-1 focus:ring-primary"
                            >
                                <option value="midterm">Midterm Period</option>
                                <option value="final">Final Period</option>
                                <option value="prelim">Prelim Period</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Module Selection via Checkboxes -->
                <div class="space-y-3 rounded-2xl border border-border/70 bg-secondary/20 p-4 sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <Layers class="size-4 text-primary" />
                            <div>
                                <h3 class="text-xs font-bold text-foreground">
                                    Ground on Course Modules ({{ selectedModuleIds.length }}/{{ modules.length }} Selected · Optional)
                                </h3>
                                <p class="text-[11px] text-muted-foreground">
                                    Checked modules supply lecture slides and syllabus notes. If none are selected, questions will be generated directly from your Custom Topics and Instructions below.
                                </p>
                            </div>
                        </div>

                        <div v-if="modules.length > 0" class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-border/70 bg-card px-2.5 py-1 text-xs font-semibold text-muted-foreground transition hover:text-foreground"
                                @click="isAllSelected ? deselectAllModules() : selectAllModules()"
                            >
                                <CheckSquare v-if="!isAllSelected" class="size-3.5" />
                                <Square v-else class="size-3.5" />
                                <span>{{ isAllSelected ? 'Deselect All' : 'Select All' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Search filter if multiple modules -->
                    <div v-if="modules.length > 3" class="relative">
                        <Search class="absolute left-3 top-2.5 size-3.5 text-muted-foreground" />
                        <input
                            v-model="moduleSearch"
                            type="text"
                            placeholder="Filter modules by topic or filename..."
                            class="h-8 w-full rounded-lg border border-border/60 bg-card pl-8 pr-3 text-xs text-foreground placeholder:text-muted-foreground/60"
                        />
                    </div>

                    <!-- Modules List Checkboxes -->
                    <div
                        v-if="isLoadingModules"
                        class="grid place-items-center py-8 text-center text-xs text-muted-foreground"
                    >
                        <Loader2 class="mb-2 size-5 animate-spin text-primary" />
                        <span>Loading section modules...</span>
                    </div>

                    <div
                        v-else-if="filteredModules.length === 0"
                        class="rounded-xl border border-dashed border-border/80 bg-card/60 p-4 text-center text-xs text-muted-foreground"
                    >
                        <p class="font-medium text-foreground">No slide modules uploaded yet.</p>
                        <p class="mt-0.5 text-[11px]">
                            No problem! You can still generate complete exams by typing your topics or prompts in the "Custom Topics & Instructions" field below.
                        </p>
                    </div>

                    <div v-else class="grid max-h-56 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                        <div
                            v-for="m in filteredModules"
                            :key="m.id"
                            class="group relative flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-all select-none"
                            :class="
                                selectedModuleIds.includes(m.id)
                                    ? 'border-primary/50 bg-primary/5 shadow-2xs'
                                    : 'border-border/70 bg-card hover:border-border hover:bg-secondary/30'
                            "
                            @click="toggleModuleSelection(m.id)"
                        >
                            <input
                                :id="`module-checkbox-${m.id}`"
                                type="checkbox"
                                :checked="selectedModuleIds.includes(m.id)"
                                class="mt-0.5 size-4 rounded border-border text-primary focus:ring-primary"
                                @click.stop="toggleModuleSelection(m.id)"
                            />

                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="rounded bg-secondary px-1.5 py-0.5 font-mono text-[10px] font-bold text-foreground"
                                    >
                                        {{ m.module_number }}
                                    </span>
                                    <h4 class="truncate text-xs font-semibold text-foreground">
                                        {{ m.title }}
                                    </h4>
                                </div>

                                <p
                                    v-if="m.description"
                                    class="line-clamp-2 text-[11px] text-muted-foreground leading-relaxed"
                                >
                                    {{ m.description }}
                                </p>

                                <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                    <span
                                        v-if="m.has_file"
                                        class="inline-flex items-center gap-1 rounded bg-secondary/80 px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground"
                                    >
                                        <FileText class="size-3 text-primary" />
                                        <span class="max-w-[120px] truncate">{{ m.file_name }}</span>
                                        <span v-if="m.formatted_file_size" class="text-[9px] opacity-70">
                                            ({{ m.formatted_file_size }})
                                        </span>
                                    </span>
                                    <span
                                        v-if="m.link_url"
                                        class="inline-flex items-center gap-1 rounded bg-secondary/80 px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground"
                                    >
                                        <ExternalLink class="size-3 text-teal-500" />
                                        <span>Slides Link</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Test Types & Points Configuration -->
                <div class="space-y-3 rounded-2xl border border-border/70 bg-secondary/20 p-4 sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-xs font-bold text-foreground">
                                Test Types, Items Count & Point Allocations
                            </h3>
                            <p class="text-[11px] text-muted-foreground">
                                Enable or disable question types and specify points per item.
                            </p>
                        </div>

                        <!-- Live totals pill -->
                        <div class="flex items-center gap-2">
                            <span class="rounded-lg bg-card border border-border px-2.5 py-1 text-xs font-bold text-foreground shadow-2xs">
                                Total: <strong class="font-mono text-primary">{{ totalItems }}</strong> items · <strong class="font-mono text-emerald-600 dark:text-emerald-400">{{ totalPoints }}</strong> pts
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <div
                            v-for="tt in testTypes"
                            :key="tt.type"
                            class="rounded-xl border p-3.5 transition-all"
                            :class="
                                tt.enabled
                                    ? 'border-border/80 bg-card shadow-2xs'
                                    : 'border-border/50 bg-secondary/30 opacity-70'
                            "
                        >
                            <div class="flex items-start justify-between gap-3">
                                <label class="flex cursor-pointer items-start gap-2.5 select-none">
                                    <input
                                        v-model="tt.enabled"
                                        type="checkbox"
                                        class="mt-0.5 size-4 rounded border-border text-primary focus:ring-primary"
                                    />
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs font-bold text-foreground">{{ tt.label }}</span>
                                            <span
                                                v-if="tt.enabled"
                                                class="rounded-full bg-primary/10 px-1.5 py-0.2 font-mono text-[9px] font-bold text-primary"
                                            >
                                                {{ (Number(tt.items_count) || 0) * (Number(tt.points_per_item) || 0) }} pts
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-muted-foreground leading-normal">
                                            {{ tt.description }}
                                        </p>
                                    </div>
                                </label>
                            </div>

                            <!-- Inputs row when enabled -->
                            <div v-if="tt.enabled" class="mt-3 flex items-center justify-between border-t border-border/50 pt-2 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] font-medium text-muted-foreground">Items:</span>
                                    <Input
                                        v-model.number="tt.items_count"
                                        type="number"
                                        min="1"
                                        max="100"
                                        class="h-7 w-16 rounded-lg bg-background text-center font-mono text-xs font-bold"
                                    />
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] font-medium text-muted-foreground">Pts each:</span>
                                    <Input
                                        v-model.number="tt.points_per_item"
                                        type="number"
                                        min="0.5"
                                        max="50"
                                        step="0.5"
                                        class="h-7 w-16 rounded-lg bg-background text-center font-mono text-xs font-bold"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Advanced / Tone & Focus Details -->
                <div class="space-y-3 rounded-2xl border border-border/70 bg-secondary/20 p-4 sm:p-5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <Label class="text-xs font-bold text-foreground">
                                Academic Rigor / Focus
                            </Label>
                            <select
                                v-model="difficulty"
                                class="mt-1 h-9 w-full rounded-xl border border-input bg-card px-3 text-xs font-medium text-foreground focus:ring-1 focus:ring-primary"
                            >
                                <option value="balanced">Balanced (Knowledge + Practical Application)</option>
                                <option value="conceptual">Conceptual (Theories, Definitions & Reasoning)</option>
                                <option value="rigorous">Technical & Hands-On (Rigorous Code & Problem Solving)</option>
                            </select>
                        </div>

                        <div>
                            <Label class="text-xs font-bold text-foreground">
                                Special Instructor Focus (Optional)
                            </Label>
                            <Input
                                v-model="customInstructions"
                                type="text"
                                placeholder="e.g. Focus on CSS Flexbox, DOM Events, and loop invariants"
                                class="mt-1 h-9 rounded-xl bg-card text-xs font-medium"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= STEP 2: STREAMING GENERATION ================= -->
            <div
                v-else-if="currentStep === 'generating'"
                class="flex flex-1 flex-col overflow-hidden px-5 py-4 sm:px-6"
            >
                <div class="flex items-center justify-between border-b border-border/60 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="grid size-8 place-items-center rounded-lg bg-primary/10 text-primary">
                            <OctoSpinner size="sm" speed="fast" />
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-foreground">
                                Hermes 3 is Drafting Your Exam
                            </h3>
                            <p class="text-[11px] text-muted-foreground animate-pulse">
                                {{ generationStatusText }}
                            </p>
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 rounded-lg text-xs font-semibold"
                        @click="handleCancelGeneration"
                    >
                        Stop / Cancel
                    </Button>
                </div>

                <!-- Real-time code stream window -->
                <div
                    ref="streamContainerRef"
                    class="mt-3 flex-1 overflow-y-auto rounded-xl border border-zinc-800 bg-zinc-950 p-4 font-mono text-xs text-zinc-100 shadow-inner"
                >
                    <pre class="whitespace-pre-wrap leading-relaxed">{{ streamedContent || 'Preparing curriculum context and initializing Hermes 3...' }}</pre>
                </div>
            </div>

            <!-- ================= STEP 3: REVIEW, PRINT & SAVE ================= -->
            <div
                v-else-if="currentStep === 'review'"
                class="flex flex-1 flex-col overflow-hidden px-5 py-4 sm:px-6"
            >
                <!-- Navigation Sub-tabs -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 pb-3">
                    <div class="flex items-center gap-1 rounded-xl border border-border/70 bg-secondary/50 p-1">
                        <button
                            type="button"
                            class="relative inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                            :class="
                                activeReviewTab === 'student'
                                    ? 'text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="activeReviewTab = 'student'"
                        >
                            <motion.div
                                v-if="activeReviewTab === 'student'"
                                layout-id="exam-generator-active-review-tab"
                                class="absolute inset-0 rounded-lg bg-card shadow-xs"
                                :transition="tabIndicatorTransition"
                            />
                            <span class="relative z-10 inline-flex items-center gap-1.5">
                                <FileText class="size-3.5" />
                                <span>Student Questionnaire</span>
                            </span>
                        </button>

                        <button
                            type="button"
                            class="relative inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                            :class="
                                activeReviewTab === 'answers'
                                    ? 'text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="activeReviewTab = 'answers'"
                        >
                            <motion.div
                                v-if="activeReviewTab === 'answers'"
                                layout-id="exam-generator-active-review-tab"
                                class="absolute inset-0 rounded-lg bg-card shadow-xs"
                                :transition="tabIndicatorTransition"
                            />
                            <span class="relative z-10 inline-flex items-center gap-1.5">
                                <FileCheck class="size-3.5" />
                                <span>Teacher Answer Key</span>
                            </span>
                        </button>

                        <button
                            type="button"
                            class="relative inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                            :class="
                                activeReviewTab === 'editor'
                                    ? 'text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="activeReviewTab = 'editor'"
                        >
                            <motion.div
                                v-if="activeReviewTab === 'editor'"
                                layout-id="exam-generator-active-review-tab"
                                class="absolute inset-0 rounded-lg bg-card shadow-xs"
                                :transition="tabIndicatorTransition"
                            />
                            <span class="relative z-10 inline-flex items-center gap-1.5">
                                <PenLine class="size-3.5" />
                                <span>Edit Markdown</span>
                            </span>
                        </button>
                    </div>

                    <!-- Right toolbar: Copy, Print, Regenerate -->
                    <div class="flex items-center gap-1.5">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 rounded-lg text-xs font-semibold"
                            @click="
                                copyToClipboard(
                                    activeReviewTab === 'answers' ? teacherAnswerKey : studentPaper,
                                    activeReviewTab === 'answers' ? 'answers' : 'student',
                                )
                            "
                        >
                            <Check v-if="copiedTarget" class="mr-1.5 size-3.5 text-emerald-500" />
                            <Copy v-else class="mr-1.5 size-3.5 text-muted-foreground" />
                            <span>{{ copiedTarget ? 'Copied!' : 'Copy' }}</span>
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 rounded-lg text-xs font-semibold"
                            @click="handlePrintExam"
                        >
                            <Printer class="mr-1.5 size-3.5 text-muted-foreground" />
                            <span>Print Paper</span>
                        </Button>

                        <!-- Word (.docx) Export Dropdown -->
                        <div ref="docxDropdownRef" class="relative">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 rounded-lg border-sky-500/30 text-xs font-semibold text-sky-600 hover:bg-sky-500/10 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300"
                                :disabled="isExportingDocx"
                                @click="showDocxDropdown = !showDocxDropdown"
                            >
                                <Loader2 v-if="isExportingDocx" class="mr-1.5 size-3.5 animate-spin" />
                                <FileDown v-else class="mr-1.5 size-3.5 text-sky-500" />
                                <span>Word (.docx)</span>
                                <ChevronDown
                                    class="ml-1 size-3 text-muted-foreground transition-transform duration-200"
                                    :class="{ 'rotate-180': showDocxDropdown }"
                                />
                            </Button>

                            <!-- Dropdown Menu -->
                            <div
                                v-if="showDocxDropdown"
                                class="absolute right-0 top-full z-50 mt-1.5 w-64 rounded-xl border border-border/80 bg-card p-1.5 shadow-xl ring-1 ring-black/5"
                            >
                                <div class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                    Generate Word Document
                                </div>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-xs font-medium text-foreground transition-colors hover:bg-secondary/80"
                                    @click="handleExportDocx('student')"
                                >
                                    <FileText class="size-4 shrink-0 text-sky-500" />
                                    <div>
                                        <div class="font-semibold text-foreground">Student Questionnaire</div>
                                        <div class="text-[10px] text-muted-foreground">Clean exam ready for students/printing</div>
                                    </div>
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-xs font-medium text-foreground transition-colors hover:bg-secondary/80"
                                    @click="handleExportDocx('both')"
                                >
                                    <FileCheck class="size-4 shrink-0 text-indigo-500" />
                                    <div>
                                        <div class="font-semibold text-foreground">Exam + Answer Key</div>
                                        <div class="text-[10px] text-muted-foreground">Includes confidential grading rubric page</div>
                                    </div>
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-xs font-medium text-foreground transition-colors hover:bg-secondary/80"
                                    @click="handleExportDocx('answers')"
                                >
                                    <Sparkles class="size-4 shrink-0 text-emerald-500" />
                                    <div>
                                        <div class="font-semibold text-foreground">Teacher Answer Key Only</div>
                                        <div class="text-[10px] text-muted-foreground">Instructor grading reference only</div>
                                    </div>
                                </button>
                            </div>
                        </div>


                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-8 rounded-lg text-xs font-semibold text-muted-foreground hover:text-foreground"
                            @click="currentStep = 'config'"
                        >
                            <Sliders class="mr-1.5 size-3.5" />
                            <span>Adjust</span>
                        </Button>
                    </div>
                </div>

                <!-- Tab 1: Student Questionnaire Preview -->
                <div
                    v-if="activeReviewTab === 'student'"
                    class="mt-3 flex-1 overflow-y-auto rounded-xl border border-border/80 bg-card p-5 font-sans text-xs text-foreground shadow-inner"
                >
                    <pre class="whitespace-pre-wrap font-sans text-xs leading-relaxed">{{ studentPaper || editableFullExam }}</pre>
                </div>

                <!-- Tab 2: Teacher Answer Key Preview -->
                <div
                    v-else-if="activeReviewTab === 'answers'"
                    class="mt-3 flex-1 overflow-y-auto rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-5 font-sans text-xs text-foreground shadow-inner"
                >
                    <div v-if="!teacherAnswerKey" class="py-8 text-center text-muted-foreground">
                        <HelpCircle class="mx-auto mb-2 size-6 text-muted-foreground/60" />
                        <p>Answer key section was embedded in the full examination text.</p>
                        <button
                            type="button"
                            class="mt-2 text-xs font-semibold text-primary underline"
                            @click="activeReviewTab = 'editor'"
                        >
                            View Full Text in Markdown Editor
                        </button>
                    </div>
                    <pre v-else class="whitespace-pre-wrap font-sans text-xs leading-relaxed">{{ teacherAnswerKey }}</pre>
                </div>

                <!-- Tab 3: Direct Markdown Editor -->
                <div
                    v-else-if="activeReviewTab === 'editor'"
                    class="mt-3 flex-1 overflow-hidden"
                >
                    <textarea
                        v-model="editableFullExam"
                        class="h-full w-full resize-none rounded-xl border border-border/80 bg-card p-4 font-mono text-xs leading-relaxed text-foreground focus:ring-1 focus:ring-primary"
                        placeholder="Edit generated examination content..."
                    ></textarea>
                </div>
            </div>

            <!-- Bottom Action Footer -->
            <div class="flex items-center justify-between border-t border-border/70 bg-secondary/30 px-5 py-3 sm:px-6">
                <!-- Left info -->
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <span v-if="currentStep === 'config'">
                        <strong>{{ selectedModuleIds.length }}</strong> module(s) · <strong>{{ totalItems }}</strong> items (<strong>{{ totalPoints }}</strong> pts)
                    </span>
                    <span v-else-if="currentStep === 'review'">
                        Ready to print or save directly to section assessments.
                    </span>
                </div>

                <!-- Right button actions -->
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-9 rounded-xl text-xs font-semibold"
                        @click="handleClose"
                    >
                        {{ currentStep === 'review' ? 'Close' : 'Cancel' }}
                    </Button>

                    <Button
                        v-if="currentStep === 'config'"
                        type="button"
                        class="ink-button !h-9 !rounded-xl !px-4 text-xs font-semibold"
                        :disabled="selectedModuleIds.length === 0 || totalItems === 0 || isGenerating"
                        @click="handleStartGeneration"
                    >
                        <Sparkles class="mr-1.5 size-3.5" />
                        <span>Draft Exam with Hermes</span>
                    </Button>

                    <template v-else-if="currentStep === 'review'">
                        <Button
                            type="button"
                            variant="outline"
                            class="!h-9 !rounded-xl !px-3.5 text-xs font-semibold border-sky-500/30 text-sky-600 hover:bg-sky-500/10 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300"
                            :disabled="isExportingDocx"
                            @click="handleExportDocx('student')"
                        >
                            <Loader2 v-if="isExportingDocx" class="mr-1.5 size-3.5 animate-spin" />
                            <FileDown v-else class="mr-1.5 size-3.5 text-sky-500" />
                            <span>Export Word (.docx)</span>
                        </Button>

                        <Button
                            type="button"
                            class="ink-button !h-9 !rounded-xl !px-4 text-xs font-semibold"
                            :disabled="isSaving"
                            @click="handleSaveAsAssessment"
                        >
                            <Loader2 v-if="isSaving" class="mr-1.5 size-3.5 animate-spin" />
                            <Save v-else class="mr-1.5 size-3.5" />
                            <span>Save as Section Assessment</span>
                        </Button>
                    </template>

                </div>
            </div>
            </motion.div>
        </motion.div>
    </AnimatePresence>
</template>
