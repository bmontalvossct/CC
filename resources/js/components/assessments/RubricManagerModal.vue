<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
    Percent,
    KeyRound,
    FileText,
    Sparkles,
    Plus,
    Trash2,
    Save,
    X,
    Upload,
    Paperclip,
    AlertCircle,
    CheckCircle2,
    HelpCircle,
    Eye,
    FileCheck2,
    SlidersHorizontal,
} from 'lucide-vue-next';
import axios from 'axios';
import OctoSpinner from '@/components/OctoSpinner.vue';

interface RubricCriterion {
    id: string;
    name: string;
    percentage: number;
    max_points: number;
    description: string;
}

interface AnswerKeyItem {
    id: string;
    item_number: number;
    question: string;
    correct_answer: string;
    points: number;
    percentage: number;
    case_sensitive: boolean;
    explanation?: string;
}

const props = withDefaults(
    defineProps<{
        show: boolean;
        sectionId: number | string;
        activityId: number | string;
        activityType?: 'assessment' | 'project';
        title: string;
        maxPoints: number;
        rubricType?: string | null;
        rubricData?: any;
        attachmentPath?: string | null;
        attachmentName?: string | null;
        attachmentMime?: string | null;
    }>(),
    {
        activityType: 'assessment',
        maxPoints: 100,
        rubricType: 'percentage',
        rubricData: null,
        attachmentPath: null,
        attachmentName: null,
        attachmentMime: null,
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved', payload: any): void;
    (e: 'previewAttachment'): void;
}>();

// Selected rubric grading mode: 'percentage' | 'answer_key'
const activeMode = ref<'percentage' | 'answer_key'>('percentage');

// Percentage Criteria State
const criteria = ref<RubricCriterion[]>([]);

// Answer Key Items State
const answerKeyItems = ref<AnswerKeyItem[]>([]);

// File attachment state for uploading new rubric file
const selectedFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const removeAttachment = ref(false);

// AI Study with Octo state
const isStudying = ref(false);
const studyError = ref<string | null>(null);
const studySuccess = ref<string | null>(null);
const studyRawText = ref('');
const showPasteStudy = ref(false);

// Saving state
const isSaving = ref(false);
const saveError = ref<string | null>(null);

// Initialize or reset state when modal opens or props change
const initData = () => {
    saveError.value = null;
    studyError.value = null;
    studySuccess.value = null;
    selectedFile.value = null;
    removeAttachment.value = false;
    showPasteStudy.value = false;
    studyRawText.value = '';

    const rawData = props.rubricData;
    const mode = rawData?.mode || props.rubricType || 'percentage';
    activeMode.value = mode === 'answer_key' ? 'answer_key' : 'percentage';

    // Populate Percentage Criteria
    if (rawData?.criteria && Array.isArray(rawData.criteria) && rawData.criteria.length > 0) {
        criteria.value = rawData.criteria.map((c: any, idx: number) => ({
            id: c.id || `crit_${idx + 1}`,
            name: c.name || `Criterion ${idx + 1}`,
            percentage: Number(c.percentage) || 25,
            max_points: Number(c.max_points) || roundToTwo(((Number(c.percentage) || 25) / 100) * props.maxPoints),
            description: c.description || '',
        }));
    } else {
        // Default standard percentage criteria
        const p1 = 40;
        const p2 = 30;
        const p3 = 30;
        criteria.value = [
            {
                id: 'crit_1',
                name: 'Correctness & Functionality',
                percentage: p1,
                max_points: roundToTwo((p1 / 100) * props.maxPoints),
                description: 'Meets all core functional and technical requirements.',
            },
            {
                id: 'crit_2',
                name: 'Structure & Quality',
                percentage: p2,
                max_points: roundToTwo((p2 / 100) * props.maxPoints),
                description: 'Code organization, formatting, syntax, and standards.',
            },
            {
                id: 'crit_3',
                name: 'Explanation & Notes',
                percentage: p3,
                max_points: roundToTwo((p3 / 100) * props.maxPoints),
                description: 'Clear documentation, comments, and reflection notes.',
            },
        ];
    }

    // Populate Answer Key Items
    if (rawData?.items && Array.isArray(rawData.items) && rawData.items.length > 0) {
        answerKeyItems.value = rawData.items.map((it: any, idx: number) => ({
            id: it.id || `item_${idx + 1}`,
            item_number: it.item_number || idx + 1,
            question: it.question || '',
            correct_answer: it.correct_answer || it.expected_answer || it.answer || '',
            points: Number(it.points) || 1,
            percentage: Number(it.percentage) || 0,
            case_sensitive: Boolean(it.case_sensitive),
            explanation: it.explanation || '',
        }));
    } else {
        answerKeyItems.value = [
            {
                id: 'item_1',
                item_number: 1,
                question: 'Question 1',
                correct_answer: '',
                points: roundToTwo(props.maxPoints / 5 || 1),
                percentage: 20,
                case_sensitive: false,
                explanation: '',
            },
        ];
    }
};

watch(() => props.show, (shown) => {
    if (shown) {
        initData();
    }
});

const roundToTwo = (num: number) => Math.round((num + Number.EPSILON) * 100) / 100;

// Percentage Totals & Validation
const totalPercentage = computed(() => {
    return roundToTwo(criteria.value.reduce((sum, c) => sum + (Number(c.percentage) || 0), 0));
});

const totalCriterionPoints = computed(() => {
    return roundToTwo(criteria.value.reduce((sum, c) => sum + (Number(c.max_points) || 0), 0));
});

const isPercentageBalanced = computed(() => {
    return Math.abs(totalPercentage.value - 100) < 0.01;
});

// Answer Key Totals
const totalAnswerKeyPoints = computed(() => {
    return roundToTwo(answerKeyItems.value.reduce((sum, it) => sum + (Number(it.points) || 0), 0));
});

// Criteria Actions
const addCriterion = () => {
    const nextIdx = criteria.value.length + 1;
    const remaining = Math.max(0, 100 - totalPercentage.value);
    const pct = remaining > 0 ? remaining : 10;
    criteria.value.push({
        id: `crit_${Date.now()}_${nextIdx}`,
        name: `Criterion ${nextIdx}`,
        percentage: pct,
        max_points: roundToTwo((pct / 100) * props.maxPoints),
        description: '',
    });
};

const removeCriterion = (index: number) => {
    if (criteria.value.length <= 1) return;
    criteria.value.splice(index, 1);
};

const handlePercentageChange = (criterion: RubricCriterion) => {
    criterion.max_points = roundToTwo(((Number(criterion.percentage) || 0) / 100) * props.maxPoints);
};

const autoBalancePercentages = () => {
    const count = criteria.value.length;
    if (count === 0) return;
    const basePct = Math.floor((100 / count) * 100) / 100;
    let runningPct = 0;
    let runningPts = 0;

    criteria.value.forEach((c, idx) => {
        if (idx === count - 1) {
            c.percentage = roundToTwo(100 - runningPct);
            c.max_points = roundToTwo(props.maxPoints - runningPts);
        } else {
            c.percentage = basePct;
            c.max_points = roundToTwo((basePct / 100) * props.maxPoints);
            runningPct += c.percentage;
            runningPts += c.max_points;
        }
    });
};

// Answer Key Actions
const addAnswerKeyItem = () => {
    const nextNum = answerKeyItems.value.length + 1;
    answerKeyItems.value.push({
        id: `item_${Date.now()}_${nextNum}`,
        item_number: nextNum,
        question: `Question ${nextNum}`,
        correct_answer: '',
        points: 1,
        percentage: 0,
        case_sensitive: false,
        explanation: '',
    });
};

const removeAnswerKeyItem = (index: number) => {
    if (answerKeyItems.value.length <= 1) return;
    answerKeyItems.value.splice(index, 1);
    // Renumber
    answerKeyItems.value.forEach((it, idx) => {
        it.item_number = idx + 1;
    });
};

const autoDistributeAnswerKeyPoints = () => {
    const count = answerKeyItems.value.length;
    if (count === 0) return;
    const basePts = Math.floor((props.maxPoints / count) * 100) / 100;
    let runningPts = 0;

    answerKeyItems.value.forEach((it, idx) => {
        if (idx === count - 1) {
            it.points = roundToTwo(props.maxPoints - runningPts);
        } else {
            it.points = basePts;
            runningPts += it.points;
        }
        it.percentage = roundToTwo((it.points / props.maxPoints) * 100);
    });
};

// File Attachment Handling
const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        selectedFile.value = target.files[0];
        removeAttachment.value = false;
    }
};

const triggerFileInput = () => {
    fileInputRef.value?.click();
};

const clearSelectedFile = () => {
    selectedFile.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
};

// AI Study with Octo
const studyWithOcto = async () => {
    studyError.value = null;
    studySuccess.value = null;
    isStudying.value = true;

    try {
        const url = props.activityType === 'project'
            ? `/sections/${props.sectionId}/projects/${props.activityId}/rubrics/study`
            : `/sections/${props.sectionId}/assessments/${props.activityId}/rubrics/study`;

        const payload: any = {};
        if (showPasteStudy.value && studyRawText.value.trim()) {
            payload.raw_text = studyRawText.value.trim();
        }

        const response = await axios.post(url, payload);
        if (response.data.success && response.data.studied) {
            const studied = response.data.studied;
            activeMode.value = studied.mode === 'answer_key' ? 'answer_key' : 'percentage';

            if (studied.mode === 'answer_key' && studied.items) {
                answerKeyItems.value = studied.items.map((it: any, idx: number) => ({
                    id: `item_${idx + 1}`,
                    item_number: it.item_number || idx + 1,
                    question: it.question || `Question ${idx + 1}`,
                    correct_answer: it.correct_answer || '',
                    points: Number(it.points) || 1,
                    percentage: Number(it.percentage) || 0,
                    case_sensitive: Boolean(it.case_sensitive),
                    explanation: it.explanation || '',
                }));
            } else if (studied.criteria) {
                criteria.value = studied.criteria.map((c: any, idx: number) => ({
                    id: c.id || `crit_${idx + 1}`,
                    name: c.name || `Criterion ${idx + 1}`,
                    percentage: Number(c.percentage) || 25,
                    max_points: Number(c.max_points) || roundToTwo(((Number(c.percentage) || 25) / 100) * props.maxPoints),
                    description: c.description || '',
                }));
            }

            studySuccess.value = `Octo successfully studied the document! Detected mode: ${studied.mode === 'answer_key' ? 'Answer Key (Itemized)' : 'Percentage Criteria Rubrics'}.`;
            showPasteStudy.value = false;
        } else {
            studyError.value = response.data.message || 'Octo could not automatically extract rubrics.';
        }
    } catch (err: any) {
        studyError.value = err.response?.data?.message || err.message || 'Failed to study rubric with Octo.';
    } finally {
        isStudying.value = false;
    }
};

// Save Rubric Configuration
const saveRubric = async () => {
    saveError.value = null;

    if (activeMode.value === 'percentage' && !isPercentageBalanced.value) {
        saveError.value = `Total percentages must equal exactly 100% (currently ${totalPercentage.value}%). Please click "Auto-Balance" or adjust manually.`;
        return;
    }

    isSaving.value = true;

    try {
        const url = props.activityType === 'project'
            ? `/sections/${props.sectionId}/projects/${props.activityId}/rubrics`
            : `/sections/${props.sectionId}/assessments/${props.activityId}/rubrics`;

        const rubricPayload: any = {
            mode: activeMode.value,
            title: props.title,
            max_points: props.maxPoints,
        };

        if (activeMode.value === 'percentage') {
            rubricPayload.criteria = criteria.value.map(c => ({
                id: c.id,
                name: c.name.trim(),
                percentage: Number(c.percentage),
                max_points: Number(c.max_points),
                description: c.description.trim(),
            }));
        } else {
            rubricPayload.items = answerKeyItems.value.map(it => ({
                id: it.id,
                item_number: Number(it.item_number),
                question: it.question.trim(),
                correct_answer: it.correct_answer.trim(),
                points: Number(it.points),
                percentage: Number(it.percentage) || roundToTwo((Number(it.points) / props.maxPoints) * 100),
                case_sensitive: Boolean(it.case_sensitive),
                explanation: it.explanation?.trim() || '',
            }));
        }

        const formData = new FormData();
        formData.append('rubric_type', activeMode.value);
        formData.append('rubric_data', JSON.stringify(rubricPayload));

        if (selectedFile.value) {
            formData.append('attachment', selectedFile.value);
        }
        if (removeAttachment.value) {
            formData.append('remove_attachment', '1');
        }

        const response = await axios.post(url, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        if (response.data.success) {
            emit('saved', response.data);
            emit('close');
        } else {
            saveError.value = response.data.message || 'Failed to save rubric.';
        }
    } catch (err: any) {
        saveError.value = err.response?.data?.message || err.message || 'Failed to save rubric.';
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm transition-all"
    >
        <div
            class="relative flex max-h-[92vh] w-full max-w-4xl flex-col rounded-2xl border border-slate-700/80 bg-slate-900 text-slate-100 shadow-2xl transition-all"
        >
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-400 ring-1 ring-indigo-500/20">
                        <SlidersHorizontal class="h-5 w-5" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white">Grading Rubric & Answer Key Manager</h2>
                        <p class="text-xs text-slate-400">
                            {{ title }} · Maximum Points: <span class="font-semibold text-indigo-300">{{ maxPoints }} pts</span>
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="flex-1 space-y-6 overflow-y-auto px-6 py-5">
                <!-- Mode Selection -->
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Grading Structure Mode
                    </label>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <!-- Percentage Rate Option -->
                        <button
                            type="button"
                            @click="activeMode = 'percentage'"
                            :class="[
                                'flex items-start gap-3 rounded-xl border p-4 text-left transition-all',
                                activeMode === 'percentage'
                                    ? 'border-indigo-500 bg-indigo-500/10 text-white ring-1 ring-indigo-500/40 shadow-sm'
                                    : 'border-slate-800 bg-slate-800/50 text-slate-300 hover:border-slate-700 hover:bg-slate-800'
                            ]"
                        >
                            <div
                                :class="[
                                    'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
                                    activeMode === 'percentage' ? 'bg-indigo-500 text-white' : 'bg-slate-700 text-slate-400'
                                ]"
                            >
                                <Percent class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold">Percentage Rate Rubric</span>
                                    <span
                                        v-if="activeMode === 'percentage'"
                                        class="rounded-full bg-indigo-500/20 px-2 py-0.5 text-[10px] font-semibold text-indigo-300"
                                    >
                                        Active
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Analytical weighted criteria (e.g. Correctness 40%, Quality 30%, Notes 30%) totaling 100%. Ideal for labs, essays, and projects.
                                </p>
                            </div>
                        </button>

                        <!-- Answer Key Option -->
                        <button
                            type="button"
                            @click="activeMode = 'answer_key'"
                            :class="[
                                'flex items-start gap-3 rounded-xl border p-4 text-left transition-all',
                                activeMode === 'answer_key'
                                    ? 'border-emerald-500 bg-emerald-500/10 text-white ring-1 ring-emerald-500/40 shadow-sm'
                                    : 'border-slate-800 bg-slate-800/50 text-slate-300 hover:border-slate-700 hover:bg-slate-800'
                            ]"
                        >
                            <div
                                :class="[
                                    'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
                                    activeMode === 'answer_key' ? 'bg-emerald-500 text-white' : 'bg-slate-700 text-slate-400'
                                ]"
                            >
                                <KeyRound class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold">Answer Key (Itemized)</span>
                                    <span
                                        v-if="activeMode === 'answer_key'"
                                        class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-[10px] font-semibold text-emerald-300"
                                    >
                                        Active
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Question-by-question exact answer key matching with case-sensitive options and individual points. Ideal for quizzes and exams.
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- AI Study with Octo & File Section -->
                <div class="rounded-xl border border-indigo-500/20 bg-gradient-to-r from-indigo-950/40 via-slate-900 to-purple-950/30 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500/20 text-indigo-400 ring-1 ring-indigo-500/30">
                                <Sparkles class="h-4 w-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-white">Study Rubric with Octo AI</h3>
                                <p class="text-xs text-slate-400">
                                    Let Octo automatically study your attached rubric document or prompt into structured criteria.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                @click="showPasteStudy = !showPasteStudy"
                                class="rounded-lg border border-slate-700 bg-slate-800/80 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:bg-slate-700 hover:text-white"
                            >
                                {{ showPasteStudy ? 'Hide Text Input' : 'Paste Text / Rubric' }}
                            </button>
                            <button
                                type="button"
                                @click="studyWithOcto"
                                :disabled="isStudying || (!attachmentPath && !showPasteStudy && !selectedFile)"
                                class="flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <OctoSpinner v-if="isStudying" class="h-4 w-4" />
                                <Sparkles v-else class="h-3.5 w-3.5" />
                                <span>{{ isStudying ? 'Octo is Studying…' : 'Study Document' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Optional Paste text area -->
                    <div v-if="showPasteStudy" class="mt-3">
                        <textarea
                            v-model="studyRawText"
                            rows="4"
                            placeholder="Paste your rubric guidelines, exam answer key, or grading instructions here..."
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 p-3 text-xs text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <!-- Study Success / Error alerts -->
                    <div v-if="studySuccess" class="mt-3 flex items-center gap-2 rounded-lg bg-emerald-500/10 p-2.5 text-xs text-emerald-300 ring-1 ring-emerald-500/20">
                        <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-400" />
                        <span>{{ studySuccess }}</span>
                    </div>
                    <div v-if="studyError" class="mt-3 flex items-center gap-2 rounded-lg bg-rose-500/10 p-2.5 text-xs text-rose-300 ring-1 ring-rose-500/20">
                        <AlertCircle class="h-4 w-4 shrink-0 text-rose-400" />
                        <span>{{ studyError }}</span>
                    </div>
                </div>

                <!-- Attached Activity / Rubric File -->
                <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Attached Rubric / Document</span>
                            <div class="mt-1 flex items-center gap-2">
                                <div v-if="attachmentPath && !removeAttachment" class="flex items-center gap-2 text-xs text-indigo-300">
                                    <Paperclip class="h-4 w-4 text-indigo-400" />
                                    <span class="font-medium text-slate-200">{{ attachmentName || 'Attached Document' }}</span>
                                    <button
                                        type="button"
                                        @click="emit('previewAttachment')"
                                        class="ml-2 flex items-center gap-1 rounded bg-slate-800 px-2 py-0.5 text-[11px] text-slate-300 hover:bg-slate-700 hover:text-white"
                                    >
                                        <Eye class="h-3 w-3" />
                                        <span>Preview</span>
                                    </button>
                                </div>
                                <div v-else class="text-xs text-slate-500">
                                    No rubric file attached to this activity.
                                </div>
                            </div>
                        </div>

                        <!-- Upload / Replace Button -->
                        <div class="flex items-center gap-2">
                            <input
                                ref="fileInputRef"
                                type="file"
                                class="hidden"
                                accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.png,.jpg,.jpeg"
                                @change="handleFileSelect"
                            />
                            <button
                                type="button"
                                @click="triggerFileInput"
                                class="flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-slate-700"
                            >
                                <Upload class="h-3.5 w-3.5" />
                                <span>{{ attachmentPath ? 'Replace File' : 'Upload File' }}</span>
                            </button>
                            <button
                                v-if="attachmentPath && !removeAttachment"
                                type="button"
                                @click="removeAttachment = true"
                                class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-rose-400"
                                title="Remove Attachment"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Selected New File Preview -->
                    <div v-if="selectedFile" class="mt-2.5 flex items-center justify-between rounded-lg bg-indigo-500/10 p-2 text-xs text-indigo-300 ring-1 ring-indigo-500/20">
                        <div class="flex items-center gap-2">
                            <FileCheck2 class="h-4 w-4 text-indigo-400" />
                            <span>Ready to upload: <strong>{{ selectedFile.name }}</strong> ({{ (selectedFile.size / 1024).toFixed(1) }} KB)</span>
                        </div>
                        <button type="button" @click="clearSelectedFile" class="text-slate-400 hover:text-white">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>

                <!-- MODE 1: Percentage Rate Criteria Editor -->
                <div v-if="activeMode === 'percentage'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white">Weighted Grading Criteria</h3>
                            <p class="text-xs text-slate-400">Total percentages must sum to exactly 100%.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                :class="[
                                    'flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-bold',
                                    isPercentageBalanced
                                        ? 'bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/30'
                                        : 'bg-amber-500/10 text-amber-400 ring-1 ring-amber-500/30'
                                ]"
                            >
                                <span>Total: {{ totalPercentage }}%</span>
                                <span class="text-slate-400">({{ totalCriterionPoints }} / {{ maxPoints }} pts)</span>
                            </div>
                            <button
                                type="button"
                                @click="autoBalancePercentages"
                                class="rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-300 hover:bg-slate-700 hover:text-white"
                            >
                                Auto-Balance
                            </button>
                            <button
                                type="button"
                                @click="addCriterion"
                                class="flex items-center gap-1 rounded-lg bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-500"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <span>Add Criterion</span>
                            </button>
                        </div>
                    </div>

                    <!-- Criteria List -->
                    <div class="space-y-3">
                        <div
                            v-for="(crit, index) in criteria"
                            :key="crit.id"
                            class="rounded-xl border border-slate-800 bg-slate-950/80 p-3.5 transition hover:border-slate-700"
                        >
                            <div class="grid grid-cols-12 gap-3 items-start">
                                <!-- Name & Description -->
                                <div class="col-span-12 sm:col-span-7 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-800 text-[10px] font-bold text-slate-300">
                                            {{ index + 1 }}
                                        </span>
                                        <input
                                            v-model="crit.name"
                                            type="text"
                                            placeholder="Criterion Name (e.g. Correctness)"
                                            class="w-full rounded-lg border border-slate-700 bg-slate-900 px-2.5 py-1.5 text-xs font-semibold text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                                        />
                                    </div>
                                    <textarea
                                        v-model="crit.description"
                                        rows="2"
                                        placeholder="Description and scoring expectations..."
                                        class="w-full rounded-lg border border-slate-800 bg-slate-900/60 p-2 text-xs text-slate-300 placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                                    ></textarea>
                                </div>

                                <!-- Percentage & Max Points -->
                                <div class="col-span-10 sm:col-span-4 grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] uppercase font-semibold text-slate-400">Weight (%)</label>
                                        <div class="relative mt-1">
                                            <input
                                                v-model.number="crit.percentage"
                                                type="number"
                                                min="1"
                                                max="100"
                                                step="0.5"
                                                @input="handlePercentageChange(crit)"
                                                class="w-full rounded-lg border border-slate-700 bg-slate-900 px-2.5 py-1.5 text-xs font-bold text-indigo-300 focus:border-indigo-500 focus:outline-none"
                                            />
                                            <span class="absolute right-2.5 top-1.5 text-xs text-slate-500">%</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] uppercase font-semibold text-slate-400">Max Points</label>
                                        <input
                                            :value="crit.max_points"
                                            readonly
                                            type="text"
                                            class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-900/40 px-2.5 py-1.5 text-xs font-bold text-slate-400 cursor-not-allowed"
                                        />
                                    </div>
                                </div>

                                <!-- Delete Criterion -->
                                <div class="col-span-2 sm:col-span-1 flex justify-end pt-5">
                                    <button
                                        type="button"
                                        @click="removeCriterion(index)"
                                        :disabled="criteria.length <= 1"
                                        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-800 hover:text-rose-400 disabled:opacity-30"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MODE 2: Answer Key Editor -->
                <div v-if="activeMode === 'answer_key'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white">Itemized Answer Key</h3>
                            <p class="text-xs text-slate-400">Specify expected solutions for each question.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="rounded-lg bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-400 ring-1 ring-emerald-500/30">
                                Total: {{ totalAnswerKeyPoints }} / {{ maxPoints }} pts
                            </div>
                            <button
                                type="button"
                                @click="autoDistributeAnswerKeyPoints"
                                class="rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-300 hover:bg-slate-700 hover:text-white"
                            >
                                Distribute Evenly
                            </button>
                            <button
                                type="button"
                                @click="addAnswerKeyItem"
                                class="flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-500"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <span>Add Item</span>
                            </button>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-3">
                        <div
                            v-for="(item, index) in answerKeyItems"
                            :key="item.id"
                            class="rounded-xl border border-slate-800 bg-slate-950/80 p-3.5 transition hover:border-slate-700"
                        >
                            <div class="grid grid-cols-12 gap-3 items-start">
                                <!-- Item Number & Question -->
                                <div class="col-span-12 sm:col-span-5 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-xs font-bold text-emerald-400 ring-1 ring-emerald-500/30">
                                            #{{ item.item_number }}
                                        </span>
                                        <input
                                            v-model="item.question"
                                            type="text"
                                            placeholder="Question or Problem Prompt..."
                                            class="w-full rounded-lg border border-slate-700 bg-slate-900 px-2.5 py-1.5 text-xs text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <!-- Correct Answer & Options -->
                                <div class="col-span-8 sm:col-span-5 space-y-1.5">
                                    <input
                                        v-model="item.correct_answer"
                                        type="text"
                                        placeholder="Expected Correct Solution / Key..."
                                        class="w-full rounded-lg border border-slate-700 bg-slate-900 px-2.5 py-1.5 text-xs font-mono font-semibold text-emerald-300 placeholder-slate-500 focus:border-emerald-500 focus:outline-none"
                                    />
                                    <label class="flex items-center gap-2 cursor-pointer text-[11px] text-slate-400 select-none">
                                        <input
                                            v-model="item.case_sensitive"
                                            type="checkbox"
                                            class="rounded border-slate-700 bg-slate-900 text-emerald-600 focus:ring-emerald-500"
                                        />
                                        <span>Case-sensitive matching</span>
                                    </label>
                                </div>

                                <!-- Points allocated -->
                                <div class="col-span-3 sm:col-span-1">
                                    <input
                                        v-model.number="item.points"
                                        type="number"
                                        min="0.1"
                                        step="0.5"
                                        placeholder="Pts"
                                        class="w-full rounded-lg border border-slate-700 bg-slate-900 px-2 py-1.5 text-center text-xs font-bold text-white focus:border-emerald-500 focus:outline-none"
                                    />
                                </div>

                                <!-- Delete -->
                                <div class="col-span-1 flex justify-end pt-1">
                                    <button
                                        type="button"
                                        @click="removeAnswerKeyItem(index)"
                                        :disabled="answerKeyItems.length <= 1"
                                        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-800 hover:text-rose-400 disabled:opacity-30"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error notice on save -->
                <div v-if="saveError" class="flex items-center gap-2 rounded-xl bg-rose-500/10 p-3 text-xs text-rose-300 ring-1 ring-rose-500/30">
                    <AlertCircle class="h-4 w-4 shrink-0 text-rose-400" />
                    <span>{{ saveError }}</span>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between border-t border-slate-800 px-6 py-4">
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-xs font-semibold text-slate-300 transition hover:bg-slate-800 hover:text-white"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="saveRubric"
                    :disabled="isSaving"
                    class="flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-semibold text-white shadow-lg transition hover:bg-indigo-500 disabled:opacity-50"
                >
                    <OctoSpinner v-if="isSaving" class="h-4 w-4" />
                    <Save v-else class="h-4 w-4" />
                    <span>{{ isSaving ? 'Saving Configuration…' : 'Save Rubric' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
