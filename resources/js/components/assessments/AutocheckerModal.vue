<script setup lang="ts">
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    Bot,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Code2,
    Copy,
    Cpu,
    ExternalLink,
    FileCode2,
    FileText,
    FolderArchive,
    HelpCircle,
    ListFilter,
    Loader2,
    Minus,
    PenLine,
    Play,
    Plus,
    RefreshCw,
    Save,
    Search,
    ShieldAlert,
    ShieldCheck,
    Sliders,
    Sparkles,
    Square,
    Trash2,
    Upload,
    User,
    Wand2,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import OctoSpinner from '@/components/OctoSpinner.vue';

type Student = {
    id: number;
    student_number: string;
    full_name: string;
    seat_label: string | null;
    is_absent: boolean;
    score: string | null;
    remarks?: string | null;
};

type Assessment = {
    id: number;
    title: string;
    type: string;
    max_points: string | number;
    description?: string;
};

type RubricCriterion = {
    id: string;
    name: string;
    max_points: number;
    description: string;
};

type ItemPreviewLine = {
    line: number;
    content: string;
};

type SubmissionItem = {
    item_id: string;
    filename: string;
    extension: string;
    sha256: string;
    file_size_bytes: number;
    student_id: number | null;
    student_number: string | null;
    student_name: string | null;
    confidence: number;
    match_type: string;
    match_reason: string;
    content_success: boolean;
    line_count: number;
    preview_lines: ItemPreviewLine[];
    error?: string | null;
    // Local processing states
    is_evaluating?: boolean;
    evaluated?: boolean;
    approved?: boolean;
    proposed_score?: number | null;
    proposed_remarks?: string;
    criteria_scores?: Record<
        string,
        {
            name: string;
            score: number;
            max_points: number;
            rationale: string;
            evidence_quote?: string;
            strengths?: string;
            improvements?: string;
        }
    >;
    overall_summary?: string;
    key_strengths?: string[];
    key_improvements?: string[];
    eval_error?: string | null;
    overwrite_confirmed?: boolean;
    absence_override_confirmed?: boolean;
};

const props = defineProps<{
    show: boolean;
    sectionId: number;
    assessment: Assessment;
    students: Student[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'scores-applied'): void;
}>();

// UI Navigation Steps
const currentStep = ref<'upload' | 'rubric' | 'evaluation' | 'sandbox'>('upload');

// Temporary Run state
const runId = ref<string | null>(null);
const items = ref<SubmissionItem[]>([]);
const selectedItemId = ref<string | null>(null);
const uploadedFiles = ref<File[]>([]);
const uploadedZip = ref<File | null>(null);

// Status & Capabilities
const isOllamaOnline = ref(false);
const isLocal = ref(true);
const activeCodeModel = ref('');
const isDockerAvailable = ref(false);
const isInspecting = ref(false);
const isSavingScores = ref(false);
const saveSuccessMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

// Queue execution state
const isQueueRunning = ref(false);
const shouldStopQueue = ref(false);
const consecutiveErrors = ref(0);
const currentQueueIndex = ref(0);

// Rubric State
const rubricCriteria = ref<RubricCriterion[]>([
    { id: 'crit_func', name: 'Functionality & Correctness', max_points: 0, description: 'Core requirements and logic flow' },
    { id: 'crit_code', name: 'Code Quality & Structure', max_points: 0, description: 'Clean architecture, naming conventions' },
    { id: 'crit_doc', name: 'Documentation & Comments', max_points: 0, description: 'Explanatory notes and documentation' },
]);
const referenceSolution = ref('');
const assessmentInstructions = ref('');

// Computed Metrics
const assessmentMax = computed(() => Number(props.assessment.max_points) || 100);
const rubricTotal = computed(() =>
    rubricCriteria.value.reduce((sum, c) => sum + (Number(c.max_points) || 0), 0),
);
const isRubricBalanced = computed(
    () => Math.abs(rubricTotal.value - assessmentMax.value) <= 0.01,
);

const matchedItems = computed(() => items.value.filter((i) => i.student_id !== null));
const evaluatedItems = computed(() => items.value.filter((i) => i.evaluated));
const approvedItems = computed(() => items.value.filter((i) => i.approved && i.proposed_score !== null));

const activeItem = computed(
    () => items.value.find((i) => i.item_id === selectedItemId.value) || items.value[0] || null,
);

// Fetch Autochecker status on open
const fetchStatus = async () => {
    try {
        const res = await fetch(
            `/sections/${props.sectionId}/assessments/${props.assessment.id}/autochecker/status`,
        );
        if (res.ok) {
            const data = await res.json();
            isOllamaOnline.value = Boolean(data.ollama?.online);
            isLocal.value = Boolean(data.is_local);
            activeCodeModel.value = data.active_profiles?.code_grading || 'qwen2.5-coder:7b';
            isDockerAvailable.value = Boolean(data.sandbox?.available);
        }
    } catch {
        isOllamaOnline.value = false;
    }
};

// Initialize rubric point distribution based on assessment max
const autoBalanceRubric = () => {
    const total = assessmentMax.value;
    const count = rubricCriteria.value.length;
    if (count === 0) return;

    const base = Math.floor((total / count) * 10) / 10;
    const remainder = Number((total - base * count).toFixed(2));

    rubricCriteria.value.forEach((c, idx) => {
        c.max_points = idx === 0 ? Number((base + remainder).toFixed(2)) : base;
    });
};

watch(
    () => props.show,
    (show) => {
        if (show) {
            fetchStatus();
            autoBalanceRubric();
            assessmentInstructions.value = props.assessment.description || '';
        }
    },
    { immediate: true },
);

// File handling
const handleFilesSelect = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files) {
        const fileList = Array.from(target.files);
        const zips = fileList.filter((f) => f.name.toLowerCase().endsWith('.zip'));
        if (zips.length > 0) {
            uploadedZip.value = zips[0];
            uploadedFiles.value = [];
        } else {
            uploadedFiles.value = fileList.slice(0, 20);
            uploadedZip.value = null;
        }
    }
};

const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    if (e.dataTransfer?.files) {
        const fileList = Array.from(e.dataTransfer.files);
        const zips = fileList.filter((f) => f.name.toLowerCase().endsWith('.zip'));
        if (zips.length > 0) {
            uploadedZip.value = zips[0];
            uploadedFiles.value = [];
        } else {
            uploadedFiles.value = fileList.slice(0, 20);
            uploadedZip.value = null;
        }
    }
};

// Step 1: Inspect files and create server-side run
const inspectUploads = async () => {
    if (uploadedFiles.value.length === 0 && !uploadedZip.value) return;

    isInspecting.value = true;
    errorMessage.value = null;

    const formData = new FormData();
    if (uploadedZip.value) {
        formData.append('zip_file', uploadedZip.value);
    } else {
        uploadedFiles.value.forEach((f) => formData.append('files[]', f));
    }

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const res = await fetch(
            `/sections/${props.sectionId}/assessments/${props.assessment.id}/autochecker/inspect`,
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: formData,
            },
        );

        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.error || 'Failed to inspect files.');
        }

        runId.value = data.run_id;
        items.value = (data.items || []).map((item: SubmissionItem) => ({
            ...item,
            approved: false,
            evaluated: false,
            overwrite_confirmed: false,
            absence_override_confirmed: false,
        }));

        if (items.value.length > 0) {
            selectedItemId.value = items.value[0].item_id;
        }

        currentStep.value = 'rubric';
    } catch (e: any) {
        errorMessage.value = e.message || 'Failed to upload and parse files.';
    } finally {
        isInspecting.value = false;
    }
};

// Add / Remove criteria
const addCriterion = () => {
    rubricCriteria.value.push({
        id: 'crit_' + Date.now(),
        name: 'New Criterion',
        max_points: 0,
        description: 'Describe grading target',
    });
    autoBalanceRubric();
};

const removeCriterion = (idx: number) => {
    if (rubricCriteria.value.length <= 1) return;
    rubricCriteria.value.splice(idx, 1);
    autoBalanceRubric();
};

// Evaluate Single Item
const evaluateItem = async (item: SubmissionItem): Promise<boolean> => {
    if (!runId.value) return false;

    item.is_evaluating = true;
    item.eval_error = null;

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const res = await fetch(
            `/sections/${props.sectionId}/assessments/${props.assessment.id}/autochecker/evaluate`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    run_id: runId.value,
                    item_id: item.item_id,
                    rubric_criteria: rubricCriteria.value,
                    reference_solution: referenceSolution.value || null,
                    assessment_instructions: assessmentInstructions.value || null,
                }),
            },
        );

        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.error || 'Evaluation failed.');
        }

        const ev = data.evaluation;
        item.proposed_score = ev.score;
        item.criteria_scores = ev.criteria_scores;
        item.overall_summary = ev.overall_summary;
        item.key_strengths = ev.key_strengths;
        item.key_improvements = ev.key_improvements;
        item.proposed_remarks = ev.overall_summary;
        item.evaluated = true;
        item.approved = false; // Remains draft until approved by teacher

        return true;
    } catch (e: any) {
        item.eval_error = e.message || 'Evaluation error.';
        return false;
    } finally {
        item.is_evaluating = false;
    }
};

// Sequential Queue Runner (Concurrency = 1, pause after 2 errors)
const startBatchEvaluation = async () => {
    if (!runId.value || isQueueRunning.value) return;

    isQueueRunning.value = true;
    shouldStopQueue.value = false;
    consecutiveErrors.value = 0;
    currentStep.value = 'evaluation';

    for (let i = 0; i < items.value.length; i++) {
        if (shouldStopQueue.value) break;

        const item = items.value[i];
        if (item.evaluated) continue; // Skip already graded

        currentQueueIndex.value = i;
        selectedItemId.value = item.item_id;

        const success = await evaluateItem(item);

        if (!success) {
            consecutiveErrors.value++;
            if (consecutiveErrors.value >= 2) {
                errorMessage.value =
                    'Batch evaluation paused after 2 consecutive failures. Please check Ollama connection or review student file formats.';
                break;
            }
        } else {
            consecutiveErrors.value = 0;
        }
    }

    isQueueRunning.value = false;
};

const stopBatchEvaluation = () => {
    shouldStopQueue.value = true;
    isQueueRunning.value = false;
};

// Bulk Approval Actions
const approveAllGraded = () => {
    items.value.forEach((item) => {
        if (item.evaluated && item.proposed_score !== null) {
            item.approved = true;
        }
    });
};

// Step 4: Apply Approved Scores to Official Gradebook
const applyApprovedScores = async () => {
    if (approvedItems.value.length === 0 || !runId.value) return;

    isSavingScores.value = true;
    errorMessage.value = null;
    saveSuccessMessage.value = null;

    const payloadScores = items.value
        .filter((i) => i.approved && i.student_id !== null && i.proposed_score !== null)
        .map((i) => ({
            student_id: i.student_id,
            approved: true,
            score: i.proposed_score,
            remarks: i.proposed_remarks || i.overall_summary || null,
            overwrite_confirmed: Boolean(i.overwrite_confirmed),
            absence_override_confirmed: Boolean(i.absence_override_confirmed),
        }));

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const res = await fetch(
            `/sections/${props.sectionId}/assessments/${props.assessment.id}/autochecker/apply-scores`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    run_id: runId.value,
                    scores: payloadScores,
                }),
            },
        );

        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.error || 'Failed to save scores.');
        }

        saveSuccessMessage.value = data.message;
        emit('scores-applied');

        setTimeout(() => {
            emit('close');
        }, 1500);
    } catch (e: any) {
        errorMessage.value = e.message || 'Failed to apply scores to gradebook.';
    } finally {
        isSavingScores.value = false;
    }
};

const getExistingScore = (studentId: number | null) => {
    if (!studentId) return null;
    const student = props.students.find((s) => s.id === studentId);
    return student?.score !== null && student?.score !== undefined ? student.score : null;
};

const isStudentAbsent = (studentId: number | null) => {
    if (!studentId) return false;
    const student = props.students.find((s) => s.id === studentId);
    return Boolean(student?.is_absent);
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-3 sm:p-6 backdrop-blur-xs"
        role="dialog"
        aria-modal="true"
        aria-label="Bulk Activity Autochecker"
    >
        <div class="paper-card relative flex h-[94vh] max-h-[920px] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-border/90 bg-card shadow-2xl">
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-border/80 bg-secondary/30 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary shadow-xs">
                        <Sparkles class="size-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold tracking-tight text-foreground">
                                Autochecker &bull; {{ assessment.title }}
                            </h3>
                            <span class="rounded-md border border-primary/30 bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary">
                                Max {{ assessment.max_points }} pts
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Structured Rubrics & Evidence Ledger
                        </p>
                    </div>
                </div>

                <!-- Step Navigator & Close Button -->
                <div class="flex items-center gap-3">
                    <div class="hidden items-center gap-1.5 rounded-lg border border-border/70 bg-card px-2 py-1 text-xs font-semibold sm:flex">
                        <button
                            type="button"
                            class="rounded px-2 py-0.5 transition-colors"
                            :class="currentStep === 'upload' ? 'bg-primary text-primary-foreground font-bold' : 'text-muted-foreground hover:text-foreground'"
                            @click="currentStep = 'upload'"
                        >
                            1. Upload
                        </button>
                        <ChevronRight class="size-3 text-muted-foreground" />
                        <button
                            type="button"
                            class="rounded px-2 py-0.5 transition-colors"
                            :class="currentStep === 'rubric' ? 'bg-primary text-primary-foreground font-bold' : 'text-muted-foreground hover:text-foreground'"
                            @click="currentStep = 'rubric'"
                        >
                            2. Rubric
                        </button>
                        <ChevronRight class="size-3 text-muted-foreground" />
                        <button
                            type="button"
                            class="rounded px-2 py-0.5 transition-colors"
                            :class="currentStep === 'evaluation' ? 'bg-primary text-primary-foreground font-bold' : 'text-muted-foreground hover:text-foreground'"
                            @click="currentStep = 'evaluation'"
                        >
                            3. Ledger & Sync
                        </button>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground transition-colors"
                        @click="emit('close')"
                    >
                        <X class="size-5" />
                    </button>
                </div>
            </div>

            <!-- Error Banner -->
            <div v-if="errorMessage" class="flex items-center justify-between border-b border-rose-500/30 bg-rose-500/10 px-6 py-2.5 text-xs font-semibold text-rose-700 dark:text-rose-300">
                <div class="flex items-center gap-2">
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </div>
                <button type="button" @click="errorMessage = null" class="text-rose-700 hover:text-rose-900">
                    <X class="size-4" />
                </button>
            </div>

            <!-- Success Banner -->
            <div v-if="saveSuccessMessage" class="flex items-center gap-2 border-b border-emerald-500/30 bg-emerald-500/10 px-6 py-2.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                <CheckCircle2 class="size-4 shrink-0" />
                <span>{{ saveSuccessMessage }}</span>
            </div>

            <!-- STEP 1: Upload Submissions -->
            <div v-if="currentStep === 'upload'" class="flex-1 overflow-y-auto p-6 sm:p-8 flex flex-col items-center justify-center text-center">
                <div
                    class="w-full max-w-2xl rounded-2xl border-2 border-dashed border-border/90 bg-secondary/15 p-8 transition-colors hover:border-primary/50 cursor-pointer"
                    @dragover.prevent
                    @drop="handleDrop"
                >
                    <div class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-primary/10 text-primary mb-4">
                        <Upload class="size-8" />
                    </div>
                    <h4 class="text-base font-bold text-foreground">Upload Student Submissions</h4>
                    <p class="mt-1 text-xs text-muted-foreground max-w-md mx-auto">
                        Drag and drop up to 20 source code files (<code class="text-primary">.py, .java, .cpp, .js, .pdf</code>) or a single batch <code class="text-primary">.zip</code> archive.
                    </p>

                    <div class="mt-6 flex flex-col sm:flex-row items-center justify-center gap-3">
                        <label class="cursor-pointer inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-bold text-primary-foreground shadow-xs hover:bg-primary/90 transition-transform active:scale-95">
                            <FileCode2 class="size-4" />
                            <span>Select Direct Files (Max 20)</span>
                            <input type="file" multiple class="hidden" @change="handleFilesSelect" />
                        </label>
                        <label class="cursor-pointer inline-flex items-center gap-2 rounded-xl border border-border/80 bg-card px-4 py-2.5 text-xs font-bold text-foreground shadow-xs hover:bg-secondary transition-colors">
                            <FolderArchive class="size-4 text-amber-500" />
                            <span>Select ZIP Archive</span>
                            <input type="file" accept=".zip" class="hidden" @change="handleFilesSelect" />
                        </label>
                    </div>

                    <!-- Selected Files Preview -->
                    <div v-if="uploadedFiles.length > 0 || uploadedZip" class="mt-6 rounded-xl border border-border/80 bg-card p-3 text-left">
                        <div class="text-xs font-bold text-foreground mb-1">
                            {{ uploadedZip ? `Selected Archive: ${uploadedZip.name} (${(uploadedZip.size / (1024 * 1024)).toFixed(2)} MB)` : `${uploadedFiles.length} file(s) ready for extraction` }}
                        </div>
                        <ul v-if="uploadedFiles.length > 0" class="max-h-32 overflow-y-auto text-[11px] text-muted-foreground space-y-0.5">
                            <li v-for="(f, i) in uploadedFiles" :key="i" class="flex items-center gap-1.5">
                                <FileText class="size-3 text-primary" />
                                <span>{{ f.name }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-xs font-bold text-primary-foreground shadow-xs hover:bg-primary/90 disabled:opacity-50 transition-all"
                        :disabled="isInspecting || (uploadedFiles.length === 0 && !uploadedZip)"
                        @click="inspectUploads"
                    >
                        <OctoSpinner v-if="isInspecting" size="sm" />
                        <Sparkles v-else class="size-4" />
                        <span>{{ isInspecting ? 'Parsing & Matching Students...' : 'Inspect & Continue' }}</span>
                    </button>
                </div>
            </div>

            <!-- STEP 2: Strict Rubric Builder -->
            <div v-else-if="currentStep === 'rubric'" class="flex-1 overflow-y-auto p-6 sm:p-8 space-y-6">
                <div class="flex items-center justify-between border-b border-border/70 pb-3">
                    <div>
                        <h4 class="text-sm font-bold text-foreground">Rubric & Task Instructions</h4>
                        <p class="text-xs text-muted-foreground">
                            Configure criteria points strictly summing to {{ assessmentMax }} points.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold"
                            :class="isRubricBalanced ? 'bg-emerald-500/10 text-emerald-700 border border-emerald-500/30' : 'bg-rose-500/10 text-rose-700 border border-rose-500/30'"
                        >
                            <Check v-if="isRubricBalanced" class="size-3.5" />
                            <AlertCircle v-else class="size-3.5" />
                            <span>Total: {{ rubricTotal }} / {{ assessmentMax }} pts</span>
                        </span>
                        <button
                            type="button"
                            class="rounded-lg border border-border/80 bg-secondary/40 px-3 py-1 text-xs font-bold text-foreground hover:bg-secondary transition-colors"
                            @click="autoBalanceRubric"
                        >
                            Auto-Balance (100%)
                        </button>
                    </div>
                </div>

                <!-- Criteria Table -->
                <div class="space-y-3">
                    <div
                        v-for="(crit, idx) in rubricCriteria"
                        :key="crit.id"
                        class="flex flex-col sm:flex-row items-start sm:items-center gap-3 rounded-xl border border-border/80 bg-card p-3.5 shadow-2xs"
                    >
                        <div class="flex-1 space-y-1 w-full">
                            <input
                                v-model="crit.name"
                                type="text"
                                placeholder="Criterion name"
                                class="w-full rounded-lg border border-border/80 bg-secondary/20 px-2.5 py-1 text-xs font-bold text-foreground focus:border-primary focus:outline-none"
                            />
                            <input
                                v-model="crit.description"
                                type="text"
                                placeholder="Criterion guidelines & expectations"
                                class="w-full rounded-lg border border-border/60 bg-transparent px-2.5 py-0.5 text-[11px] text-muted-foreground focus:border-primary focus:outline-none"
                            />
                        </div>
                        <div class="flex items-center gap-2 self-end sm:self-center">
                            <div class="flex items-center gap-1">
                                <span class="text-xs font-semibold text-muted-foreground">Max pts:</span>
                                <input
                                    v-model.number="crit.max_points"
                                    type="number"
                                    min="0.1"
                                    step="0.5"
                                    class="w-20 rounded-lg border border-border/80 bg-secondary/20 px-2 py-1 text-xs font-bold text-center text-foreground focus:border-primary focus:outline-none"
                                />
                            </div>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-muted-foreground hover:bg-rose-500/10 hover:text-rose-600 transition-colors"
                                :disabled="rubricCriteria.length <= 1"
                                @click="removeCriterion(idx)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-dashed border-border/80 bg-secondary/20 px-4 py-2 text-xs font-bold text-muted-foreground hover:text-foreground hover:border-primary/50 transition-colors"
                        @click="addCriterion"
                    >
                        <Plus class="size-3.5" /> Add Criterion
                    </button>
                </div>

                <!-- Reference Solution / Spec -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-foreground">Reference Solution / Expected Spec (Optional):</label>
                    <textarea
                        v-model="referenceSolution"
                        rows="3"
                        placeholder="Paste sample code, correct algorithms, or ideal output expectations..."
                        class="w-full rounded-xl border border-border/80 bg-card p-3 text-xs text-foreground focus:border-primary focus:outline-none font-mono"
                    />
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between border-t border-border/70 pt-4">
                    <button
                        type="button"
                        class="rounded-xl border border-border/80 bg-card px-4 py-2 text-xs font-bold text-foreground hover:bg-secondary"
                        @click="currentStep = 'upload'"
                    >
                        Back
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2 text-xs font-bold text-primary-foreground shadow-xs hover:bg-primary/90 disabled:opacity-50"
                        :disabled="!isRubricBalanced"
                        @click="startBatchEvaluation"
                    >
                        <Play class="size-4" />
                        <span>Run Ollama Autochecker ({{ items.length }} Files)</span>
                    </button>
                </div>
            </div>

            <!-- STEP 3: Master-Detail Evidence Ledger -->
            <div v-else-if="currentStep === 'evaluation'" class="flex flex-1 overflow-hidden">
                <!-- Master Submissions Sidebar (Left) -->
                <div class="w-80 border-r border-border/80 bg-secondary/15 flex flex-col overflow-hidden shrink-0">
                    <!-- Ledger Header & Queue Control -->
                    <div class="border-b border-border/70 p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-foreground">
                                Submissions ({{ evaluatedItems.length }}/{{ items.length }})
                            </span>
                            <button
                                v-if="isQueueRunning"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-md border border-rose-500/40 bg-rose-500/10 px-2 py-0.5 text-[11px] font-bold text-rose-600 hover:bg-rose-500/20"
                                @click="stopBatchEvaluation"
                            >
                                <Square class="size-3" /> Stop
                            </button>
                            <button
                                v-else-if="evaluatedItems.length < items.length"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-md bg-primary px-2 py-0.5 text-[11px] font-bold text-primary-foreground hover:bg-primary/90"
                                @click="startBatchEvaluation"
                            >
                                <Play class="size-3" /> Resume
                            </button>
                        </div>

                        <!-- Progress Bar -->
                        <div class="w-full bg-secondary rounded-full h-1.5 overflow-hidden">
                            <div
                                class="bg-primary h-full transition-all duration-300"
                                :style="{ width: `${items.length > 0 ? (evaluatedItems.length / items.length) * 100 : 0}%` }"
                            />
                        </div>

                        <div class="flex items-center justify-between text-[11px]">
                            <button
                                type="button"
                                class="text-primary font-semibold hover:underline"
                                @click="approveAllGraded"
                            >
                                Approve All Graded
                            </button>
                            <span class="text-muted-foreground">
                                {{ approvedItems.length }} Approved
                            </span>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="flex-1 overflow-y-auto p-2 space-y-1.5">
                        <div
                            v-for="item in items"
                            :key="item.item_id"
                            class="cursor-pointer rounded-xl border p-2.5 text-xs transition-all"
                            :class="[
                                selectedItemId === item.item_id
                                    ? 'border-primary bg-card shadow-xs font-medium'
                                    : 'border-transparent bg-card/60 hover:bg-card hover:border-border/60',
                                item.eval_error ? 'border-rose-500/40 bg-rose-500/5' : '',
                            ]"
                            @click="selectedItemId = item.item_id"
                        >
                            <div class="flex items-start justify-between gap-1">
                                <div class="font-bold text-foreground truncate">
                                    {{ item.student_name || item.filename }}
                                </div>
                                <span
                                    v-if="item.is_evaluating"
                                    class="inline-flex items-center text-[10px] text-primary font-bold"
                                >
                                    <OctoSpinner size="xs" class="mr-1" />
                                    <span>Grading</span>
                                </span>
                                <span
                                    v-else-if="item.proposed_score !== null && item.proposed_score !== undefined"
                                    class="rounded px-1.5 py-0.2 font-mono font-bold text-[10px]"
                                    :class="item.approved ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'"
                                >
                                    {{ item.proposed_score }} pts
                                </span>
                                <span v-else-if="item.eval_error" class="text-[10px] text-rose-600 font-bold">
                                    Error
                                </span>
                            </div>

                            <div class="mt-1 flex items-center justify-between text-[10px] text-muted-foreground">
                                <span>{{ item.filename }}</span>
                                <span v-if="item.approved" class="text-emerald-600 font-bold flex items-center gap-0.5">
                                    <Check class="size-3" /> Approved
                                </span>
                                <span v-else-if="item.evaluated" class="text-amber-600 font-bold">
                                    Draft
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detail Panel (Right) -->
                <div v-if="activeItem" class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-5 bg-card">
                    <!-- Student & File Header Card -->
                    <div class="rounded-2xl border border-border/80 bg-secondary/20 p-4 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h4 class="text-sm font-bold text-foreground flex items-center gap-2">
                                    <span>{{ activeItem.student_name || 'Unmatched Submission' }}</span>
                                    <span v-if="activeItem.student_number" class="text-xs font-mono font-normal text-muted-foreground">
                                        ({{ activeItem.student_number }})
                                    </span>
                                </h4>
                                <p class="text-[11px] text-muted-foreground mt-0.5">
                                    File: <span class="font-mono text-foreground">{{ activeItem.filename }}</span> &bull; SHA256: <span class="font-mono">{{ activeItem.sha256.substring(0, 10) }}...</span>
                                </p>
                            </div>

                            <!-- Approval Action Checkbox -->
                            <div class="flex items-center gap-3">
                                <label class="inline-flex items-center gap-2 rounded-xl border border-border/80 bg-card px-3 py-1.5 text-xs font-bold cursor-pointer select-none">
                                    <input
                                        v-model="activeItem.approved"
                                        type="checkbox"
                                        class="size-4 rounded text-primary focus:ring-primary"
                                        :disabled="!activeItem.evaluated || activeItem.proposed_score === null"
                                    />
                                    <span>Approve & Stage Score</span>
                                </label>

                                <button
                                    type="button"
                                    class="rounded-lg border border-border/80 bg-card p-1.5 text-muted-foreground hover:text-foreground hover:bg-secondary"
                                    title="Re-evaluate with Ollama"
                                    :disabled="activeItem.is_evaluating"
                                    @click="evaluateItem(activeItem)"
                                >
                                    <RefreshCw class="size-4" :class="activeItem.is_evaluating ? 'animate-spin' : ''" />
                                </button>
                            </div>
                        </div>

                        <!-- Warnings (Existing score / Absent) -->
                        <div v-if="getExistingScore(activeItem.student_id) !== null" class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-2 text-xs text-amber-800 dark:text-amber-200 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <AlertTriangle class="size-4 shrink-0" />
                                <span>Existing score recorded: <strong>{{ getExistingScore(activeItem.student_id) }} pts</strong>.</span>
                            </div>
                            <label class="inline-flex items-center gap-1.5 cursor-pointer font-bold">
                                <input v-model="activeItem.overwrite_confirmed" type="checkbox" class="rounded text-amber-600" />
                                <span>Confirm Overwrite</span>
                            </label>
                        </div>

                        <div v-if="isStudentAbsent(activeItem.student_id)" class="rounded-lg border border-rose-500/30 bg-rose-500/10 p-2 text-xs text-rose-800 dark:text-rose-200 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <ShieldAlert class="size-4 shrink-0" />
                                <span>Student was marked <strong>ABSENT</strong> for this session.</span>
                            </div>
                            <label class="inline-flex items-center gap-1.5 cursor-pointer font-bold">
                                <input v-model="activeItem.absence_override_confirmed" type="checkbox" class="rounded text-rose-600" />
                                <span>Confirm Absence Override</span>
                            </label>
                        </div>
                    </div>

                    <!-- Evaluation Proposal Matrix -->
                    <div v-if="activeItem.evaluated" class="space-y-4">
                        <div class="flex items-center justify-between border-b border-border/70 pb-2">
                            <h5 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                Rubric Breakdown & Evidence Quotes
                            </h5>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground">Proposed Final Score:</span>
                                <input
                                    v-model.number="activeItem.proposed_score"
                                    type="number"
                                    min="0"
                                    :max="assessmentMax"
                                    step="0.5"
                                    class="w-20 rounded-lg border border-primary bg-primary/10 px-2 py-0.5 text-center text-xs font-bold text-primary focus:outline-none"
                                />
                                <span class="text-xs font-bold text-muted-foreground">/ {{ assessmentMax }} pts</span>
                            </div>
                        </div>

                        <!-- Criterion Cards -->
                        <div class="grid grid-cols-1 gap-3">
                            <div
                                v-for="(crit, cId) in activeItem.criteria_scores"
                                :key="cId"
                                class="rounded-xl border border-border/80 bg-card p-3.5 space-y-1.5 text-xs shadow-2xs"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-foreground">{{ crit.name }}</span>
                                    <span class="font-mono font-bold text-primary">{{ crit.score }} / {{ crit.max_points }} pts</span>
                                </div>
                                <p class="text-muted-foreground text-[11px] leading-relaxed">
                                    {{ crit.rationale }}
                                </p>
                                <div v-if="crit.evidence_quote" class="rounded-md border border-border/60 bg-secondary/30 p-2 font-mono text-[10px] text-foreground whitespace-pre-wrap">
                                    &ldquo;{{ crit.evidence_quote }}&rdquo;
                                </div>
                            </div>
                        </div>

                        <!-- Overall Summary & Remarks -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-foreground">Overall Feedback Remarks:</label>
                            <textarea
                                v-model="activeItem.proposed_remarks"
                                rows="2"
                                class="w-full rounded-xl border border-border/80 bg-card p-2.5 text-xs text-foreground focus:border-primary focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Numbered Code Preview -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs font-bold text-muted-foreground">
                            <span>Code / Submission Evidence Preview (First 25 Lines)</span>
                            <span>{{ activeItem.line_count }} total lines</span>
                        </div>
                        <div class="rounded-xl border border-border/80 bg-zinc-950 p-3 font-mono text-[11px] text-zinc-200 overflow-x-auto max-h-60 overflow-y-auto">
                            <div v-for="line in activeItem.preview_lines" :key="line.line" class="flex gap-3 leading-relaxed">
                                <span class="text-zinc-500 select-none w-6 text-right">{{ line.line }}</span>
                                <span class="text-zinc-100 whitespace-pre">{{ line.content }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer Controls -->
            <div class="flex items-center justify-between border-t border-border/80 bg-secondary/30 px-6 py-3.5">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-muted-foreground">
                        {{ approvedItems.length }} / {{ items.length }} student grades approved
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-border/80 bg-card px-4 py-2 text-xs font-bold text-foreground hover:bg-secondary"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2 text-xs font-bold text-primary-foreground shadow-xs hover:bg-primary/90 disabled:opacity-50 transition-all"
                        :disabled="isSavingScores || approvedItems.length === 0"
                        @click="applyApprovedScores"
                    >
                        <OctoSpinner v-if="isSavingScores" size="sm" />
                        <Save v-else class="size-4" />
                        <span>Sync {{ approvedItems.length }} Scores to Gradebook</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
