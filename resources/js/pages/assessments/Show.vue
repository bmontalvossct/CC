<script setup lang="ts">
import ActivityFileButton from '@/components/ActivityFileButton.vue';
import DisabledReason from '@/components/DisabledReason.vue';
import AutocheckerModal from '@/components/assessments/AutocheckerModal.vue';
import CheckAllProgressModal, { type CheckProgressItem } from '@/components/assessments/CheckAllProgressModal.vue';
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import RubricManagerModal from '@/components/assessments/RubricManagerModal.vue';
import OctoSpinner from '@/components/OctoSpinner.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAiAssistant } from '@/composables/useAiAssistant';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bot,
    CalendarDays,
    CheckCircle2,
    Download,
    Keyboard,
    LoaderCircle,
    Paperclip,
    RefreshCw,
    RotateCcw,
    Save,
    Search,
    Settings,
    Sparkles,
    Trash2,
    TriangleAlert,
    UploadCloud,
    UserX,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';

const { warmModel } = useAiAssistant();

type Student = {
    id: number;
    student_number: string;
    last_name?: string;
    first_name?: string;
    full_name: string;
    seat_label: string | null;
    is_absent: boolean;
    score: string | null;
    remarks?: string | null;
    absence_override: boolean;
    attachment_path?: string | null;
    attachment_name?: string | null;
    attachment_mime?: string | null;
};
type Assessment = {
    id: number;
    type: string;
    assessment_number?: string | null;
    title: string;
    description?: string;
    conducted_on: string;
    max_points: string;
    rubric_type?: string | null;
    rubric_data?: any;
    activity_file_path?: string | null;
    activity_file_name?: string | null;
    attachment_path?: string;
    attachment_name?: string;
    attendance_session_id?: number;
    attendance_session?: { session_date: string; starts_at: string; ends_at: string };
};
type Session = { id: number; session_date: string; starts_at: string };

const props = defineProps<{
    section: { id: number; name: string; subject_code?: string; subject_title: string; grading_weights?: any };
    assessment: Assessment;
    students: Student[];
    summary: { graded: number; missing: number; absent: number; average: number | null };
    attendanceSessions: Session[];
}>();

const passingRatePct = computed(() => {
    const weights = props.section?.grading_weights;
    const rates = weights?.passing_rates;
    return Number(rates?.[props.assessment.type] ?? 75);
});

const passingThreshold = computed(() => passingRatePct.value / 100);

const showPreview = ref(false);
const showAutochecker = ref(false);
const showRubricManager = ref(false);
const editing = ref(false);
const showDeleteModal = ref(false);
const isDeleting = ref(false);

const hasRubric = computed(() => {
    if (props.assessment.attachment_path) return true;
    if (props.assessment.rubric_data) {
        const data = props.assessment.rubric_data;
        if (data.criteria && data.criteria.length > 0) return true;
        if (data.items && data.items.length > 0) return true;
    }
    return false;
});

const rubricBadgeLabel = computed(() => {
    if (props.assessment.rubric_type === 'answer_key' || props.assessment.rubric_data?.mode === 'answer_key') {
        const count = props.assessment.rubric_data?.items?.length || 0;
        return `Answer Key (${count})`;
    }
    if (props.assessment.rubric_type === 'percentage' || props.assessment.rubric_data?.mode === 'percentage') {
        const count = props.assessment.rubric_data?.criteria?.length || 0;
        return `Rubric (${count})`;
    }
    if (props.assessment.attachment_name) {
        return 'Rubric File';
    }
    return 'Rubrics';
});

// Student Output Preview & Upload State
const studentPreviewModal = ref<{
    show: boolean;
    studentId: number | null;
    studentName: string;
    fileName: string;
    fileUrl: string;
    downloadUrl: string;
    reuploadUrl: string;
    deleteUrl: string;
}>({
    show: false,
    studentId: null,
    studentName: '',
    fileName: '',
    fileUrl: '',
    downloadUrl: '',
    reuploadUrl: '',
    deleteUrl: '',
});

const studentUploading = ref<Record<number, boolean>>({});
const studentFileInputs = new Map<number, HTMLInputElement>();

const triggerStudentUpload = (studentId: number) => {
    const input = studentFileInputs.get(studentId);
    if (input) {
        input.click();
    }
};

const handleStudentFileUpload = async (student: Student, event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    studentUploading.value[student.id] = true;
    const formData = new FormData();
    formData.append('attachment', file);

    try {
        const response = await fetch(`/sections/${props.section.id}/assessments/${props.assessment.id}/scores/${student.id}/attachment`, {
            method: 'POST',
            body: formData,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
        });

        if (response.ok) {
            const data = await response.json();
            saveSuccessMessage.value = data.message || `Student output attached for ${student.full_name}.`;
            router.reload({ only: ['students', 'summary'] });
        } else {
            const err = await response.json();
            saveErrorMessage.value = err.message || 'Failed to upload student output.';
        }
    } catch (e: any) {
        saveErrorMessage.value = 'Failed to upload student output. Please check the file and try again.';
    } finally {
        studentUploading.value[student.id] = false;
        target.value = '';
    }
};

const openStudentOutputPreview = (student: Student) => {
    studentPreviewModal.value = {
        show: true,
        studentId: student.id,
        studentName: student.full_name,
        fileName: student.attachment_name || `${student.last_name || 'Student'}_Output`,
        fileUrl: `/sections/${props.section.id}/assessments/${props.assessment.id}/scores/${student.id}/attachment`,
        downloadUrl: `/sections/${props.section.id}/assessments/${props.assessment.id}/scores/${student.id}/attachment?download=1`,
        reuploadUrl: `/sections/${props.section.id}/assessments/${props.assessment.id}/scores/${student.id}/attachment`,
        deleteUrl: `/sections/${props.section.id}/assessments/${props.assessment.id}/scores/${student.id}/attachment`,
    };
};

const aiCheckingStudentId = ref<number | null>(null);

const checkingAll = ref(false);
const checkProgress = ref(0);
const checkFailures = ref<string[]>([]);
const showCheckAllModal = ref(false);
const shouldStopCheckAll = ref(false);
const currentEvaluatingName = ref('');
const checkProgressItems = ref<CheckProgressItem[]>([]);

const checkUnavailableReason = computed(() => {
    if (!hasRubric.value) return 'Configure a rubric (percentage rate or answer key) or attach a file in Rubrics before checking student outputs.';
    if (checkingAll.value) return 'Check all is running. Wait for the current batch to finish.';
    if (aiCheckingStudentId.value !== null) return 'A student output is being checked. Wait for it to finish.';
    return '';
});
const attachedStudents = computed(() => props.students.filter(student => student.attachment_path));
const runAiCheck = async (student: Student, batch = false): Promise<boolean> => {
    if ((checkingAll.value && !batch) || !hasRubric.value || !student.attachment_path) return false;
    if (aiCheckingStudentId.value !== null) return false;
    aiCheckingStudentId.value = student.id;
    saveErrorMessage.value = '';

    try {
        const response = await fetch(
            `/sections/${props.section.id}/assessments/${props.assessment.id}/scores/${student.id}/ai-check`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            },
        );

        const data = await response.json();

        if (response.ok && data.success) {
            scores[student.id] = data.score;
            remarks[student.id] = data.remarks ?? '';
            lastSavedScores[student.id] = normalize(data.score);
            lastSavedRemarks[student.id] = toCleanString(data.remarks ?? '');
            student.score = String(data.score);
            student.remarks = data.remarks;
            saveSuccessMessage.value = data.message || `AI graded ${student.full_name}: ${data.score} pts. Score and remarks saved.`;
            setTimeout(() => {
                if (saveSuccessMessage.value === data.message) {
                    saveSuccessMessage.value = '';
                }
            }, 6000);
            return true;
        } else {
            saveErrorMessage.value = data.message || 'AI document check failed. Please check the document and try again.';
        }
    } catch (e: any) {
        saveErrorMessage.value = 'AI document check failed: ' + (e?.message || 'Server error.');
    } finally {
        aiCheckingStudentId.value = null;
    }
    return false;
};

const checkAll = async () => {
    if (checkingAll.value || aiCheckingStudentId.value !== null || !hasRubric.value || !attachedStudents.value.length) return;
    checkingAll.value = true;
    shouldStopCheckAll.value = false;
    checkProgress.value = 0;
    checkFailures.value = [];
    showCheckAllModal.value = true;

    const students = [...attachedStudents.value];
    checkProgressItems.value = students.map((s) => ({
        id: s.id,
        title: s.full_name,
        subtitle: s.student_number,
        filename: s.attachment_name || undefined,
        status: 'pending',
        score: null,
        remarks: null,
        error: null,
    }));

    try {
        for (let i = 0; i < students.length; i++) {
            if (shouldStopCheckAll.value) break;

            const student = students[i];
            const item = checkProgressItems.value[i];
            if (item) {
                item.status = 'evaluating';
            }
            currentEvaluatingName.value = `${student.full_name}${student.attachment_name ? ` (${student.attachment_name})` : ''}`;

            const success = await runAiCheck(student, true);
            if (success) {
                if (item) {
                    item.status = 'success';
                    item.score = scores[student.id];
                    item.remarks = remarks[student.id];
                }
            } else {
                const err = saveErrorMessage.value || 'Evaluation failed';
                checkFailures.value.push(`${student.full_name}: ${err}`);
                if (item) {
                    item.status = 'failed';
                    item.error = err;
                }
            }
            checkProgress.value++;
        }
        const passedCount = students.length - checkFailures.value.length;
        saveSuccessMessage.value = `Checked ${passedCount} of ${students.length} students. ${checkFailures.value.length} failed.`;
    } finally {
        checkingAll.value = false;
        currentEvaluatingName.value = '';
    }
};

const handleStopCheckAll = () => {
    shouldStopCheckAll.value = true;
};

const handleAutocheckerApplied = () => {
    saveSuccessMessage.value = 'Autochecker scores successfully applied and saved!';
    router.reload({ only: ['students', 'summary'] });
};

onMounted(() => {
    if (hasRubric.value || attachedStudents.value.length > 0) {
        warmModel('code_grading');
        warmModel('general_grading');
    }
});

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(`/sections/${props.section.id}/assessments/${props.assessment.id}`, {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};

const editFileInputRef = ref<HTMLInputElement | null>(null);

const openRubrics = () => {
    showRubricManager.value = true;
};

const handleRubricSaved = () => {
    saveSuccessMessage.value = 'Rubric configuration saved successfully!';
    router.reload({ only: ['assessment'] });
};

const editForm = useForm<{
    type: string;
    assessment_number: string;
    title: string;
    description: string;
    conducted_on: string;
    max_points: number;
    attendance_session_id: number | '';
    attachment: File | null;
    remove_attachment: boolean;
    _method: string;
}>({
    type: props.assessment.type,
    assessment_number: props.assessment.assessment_number ?? '',
    title: props.assessment.title,
    description: props.assessment.description ?? '',
    conducted_on: props.assessment.conducted_on.slice(0, 10),
    max_points: Number(props.assessment.max_points),
    attendance_session_id: props.assessment.attendance_session_id ?? '',
    attachment: null,
    remove_attachment: false,
    _method: 'PUT',
});

const submitEdit = () => {
    editForm.post(`/sections/${props.section.id}/assessments/${props.assessment.id}`, {
        forceFormData: true,
        onSuccess: () => {
            editing.value = false;
            editForm.reset();
            editForm.remove_attachment = false;
            if (editFileInputRef.value) editFileInputRef.value.value = '';
        },
    });
};

const includeAbsent = ref(false);
const formatDate = (value: string) =>
    new Intl.DateTimeFormat('en-PH', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' }).format(new Date(value));

const toCleanString = (val: unknown): string => {
    if (val === null || val === undefined) return '';
    return String(val).trim();
};

const normalize = (val: unknown): string => {
    const str = toCleanString(val);
    if (str === '') return '';
    const n = Number(str);
    if (!Number.isFinite(n)) return str;
    return String(Number(n.toFixed(2)));
};

const initialMap = Object.fromEntries(props.students.map((student) => [student.id, normalize(student.score)]));
const scores = reactive<Record<number, string | number>>({ ...initialMap });
const lastSavedScores = reactive<Record<number, string | number>>({ ...initialMap });

const initialRemarksMap = Object.fromEntries(props.students.map((student) => [student.id, student.remarks ?? '']));
const remarks = reactive<Record<number, string>>({ ...initialRemarksMap });
const lastSavedRemarks = reactive<Record<number, string>>({ ...initialRemarksMap });

const inputs = new Map<number, HTMLInputElement>();

const isSaving = ref(false);
const saveSuccessMessage = ref('');
const saveErrorMessage = ref('');

const getScoreError = (studentId: number): string => {
    const raw = toCleanString(scores[studentId]);
    if (raw === '') return '';
    const num = Number(raw);
    if (!Number.isFinite(num)) return 'Invalid number';
    if (num < 0) return 'Cannot be below 0';
    if (num > Number(props.assessment.max_points)) return `Cannot exceed total score of ${props.assessment.max_points} pts`;
    return '';
};

const hasInvalidScore = (studentId: number): boolean => {
    return getScoreError(studentId) !== '';
};

const isUnsaved = (studentId: number): boolean => {
    return (
        normalize(scores[studentId]) !== normalize(lastSavedScores[studentId]) ||
        toCleanString(remarks[studentId]) !== toCleanString(lastSavedRemarks[studentId])
    );
};

const hasUnsavedChanges = computed(() => {
    return props.students.some((s) => isUnsaved(s.id));
});

const isPresetActive = (studentId: number, preset: string): boolean => {
    const text = (remarks[studentId] || '').toLowerCase();
    const query = preset.toLowerCase();
    return text.includes(query);
};

const getPresetClass = (studentId: number, preset: string): string => {
    const active = isPresetActive(studentId, preset);
    if (!active) {
        return 'border-border/60 bg-secondary/50 text-muted-foreground hover:border-primary/50 hover:bg-primary/10 hover:text-primary';
    }
    if (preset.includes('Late') || preset.includes('-5')) {
        return 'border-amber-500/70 bg-amber-500/20 text-amber-900 dark:border-amber-400/70 dark:bg-amber-400/25 dark:text-amber-100 font-semibold ring-1 ring-amber-500/40 shadow-xs';
    }
    if (preset.includes('Complete') || preset.includes('Outstanding')) {
        return 'border-emerald-500/70 bg-emerald-500/20 text-emerald-900 dark:border-emerald-400/70 dark:bg-emerald-400/25 dark:text-emerald-100 font-semibold ring-1 ring-emerald-500/40 shadow-xs';
    }
    if (preset.includes('Needs') || preset.includes('Incomplete')) {
        return 'border-rose-500/70 bg-rose-500/20 text-rose-900 dark:border-rose-400/70 dark:bg-rose-400/25 dark:text-rose-100 font-semibold ring-1 ring-rose-500/40 shadow-xs';
    }
    if (preset.includes('Bonus')) {
        return 'border-indigo-500/70 bg-indigo-500/20 text-indigo-900 dark:border-indigo-400/70 dark:bg-indigo-400/25 dark:text-indigo-100 font-semibold ring-1 ring-indigo-500/40 shadow-xs';
    }
    return 'border-primary/70 bg-primary/20 text-primary font-semibold ring-1 ring-primary/40 shadow-xs';
};

const toggleStudentPreset = (studentId: number, preset: string) => {
    const active = isPresetActive(studentId, preset);
    const maxPoints = Number(props.assessment.max_points) || 100;
    const currentScoreVal = scores[studentId];
    const numScore = currentScoreVal !== '' && currentScoreVal !== null && currentScoreVal !== undefined ? Number(currentScoreVal) : null;
    const bonusDelta = maxPoints <= 20 ? 2 : 5;

    if (!active) {
        // 1. Append to remarks
        const current = (remarks[studentId] || '').trim();
        if (!current) {
            remarks[studentId] = preset;
        } else if (!current.toLowerCase().includes(preset.toLowerCase())) {
            remarks[studentId] = `${current}; ${preset}`;
        }

        // 2. Adjust score dynamically
        if (preset === '-5 Late' || preset.includes('-5')) {
            const base = numScore !== null ? numScore : maxPoints;
            scores[studentId] = Math.max(0, Number((base - 5).toFixed(2)));
        } else if (preset === 'Complete requirements' || preset === 'Outstanding') {
            const isLate = isPresetActive(studentId, '-5 Late') || (remarks[studentId] || '').toLowerCase().includes('-5 late');
            scores[studentId] = isLate ? Math.max(0, Number((maxPoints - 5).toFixed(2))) : maxPoints;
        } else if (preset === 'Bonus points') {
            const base = numScore !== null ? numScore : maxPoints;
            scores[studentId] = Number((base + bonusDelta).toFixed(2));
        } else if (preset === 'Incomplete solution') {
            const half = Number((maxPoints * 0.5).toFixed(2));
            if (numScore === null || numScore > half) {
                scores[studentId] = half;
            }
        } else if (preset === 'Needs revision') {
            const partial = Number((maxPoints * 0.6).toFixed(2));
            if (numScore === null || numScore > partial) {
                scores[studentId] = partial;
            }
        }
    } else {
        // 1. Remove from remarks
        let rem = (remarks[studentId] || '').trim();
        const escaped = preset.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        rem = rem.replace(new RegExp(`(^|;\\s*)${escaped}(\\s*;|$)`, 'gi'), (match, p1, p2) => (p1 && p2 ? '; ' : '')).trim();
        rem = rem.replace(/^;\s*|;\s*$/g, '').trim();
        remarks[studentId] = rem;

        // 2. Revert score adjustment
        if (preset === '-5 Late' || preset.includes('-5')) {
            if (numScore !== null) {
                scores[studentId] = Math.min(maxPoints, Number((numScore + 5).toFixed(2)));
            }
        } else if (preset === 'Bonus points') {
            if (numScore !== null) {
                scores[studentId] = Math.max(0, Number((numScore - bonusDelta).toFixed(2)));
            }
        }
    }
};

const appendStudentRemark = (studentId: number, text: string) => {
    toggleStudentPreset(studentId, text);
};

const unsavedCount = computed(() => {
    return props.students.filter((s) => isUnsaved(s.id)).length;
});

const eligible = computed(() => props.students.filter((student) => !student.is_absent || includeAbsent.value));

// Search and Status Filters
const searchQuery = ref('');
const statusFilter = ref<'all' | 'unrecorded' | 'recorded' | 'absent'>('all');
const searchInputRef = ref<HTMLInputElement | null>(null);

const filteredStudents = computed(() => {
    let list = props.students;

    // 1. Filter by status tab
    if (statusFilter.value === 'unrecorded') {
        list = list.filter((s) => toCleanString(scores[s.id]) === '' && (!s.is_absent || includeAbsent.value));
    } else if (statusFilter.value === 'recorded') {
        list = list.filter((s) => toCleanString(scores[s.id]) !== '');
    } else if (statusFilter.value === 'absent') {
        list = list.filter((s) => s.is_absent);
    }

    // 2. Filter by search query
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return list;

    return list.filter((s) => {
        const fullName = (s.full_name || '').toLowerCase();
        const studentNum = (s.student_number || '').toLowerCase();
        const seatLabel = (s.seat_label || '').toLowerCase();
        return fullName.includes(q) || studentNum.includes(q) || seatLabel.includes(q) || `chair ${seatLabel}`.includes(q);
    });
});

const visibleEligible = computed(() =>
    filteredStudents.value.filter((student) => !student.is_absent || includeAbsent.value),
);

const counts = computed(() => {
    const total = props.students.length;
    const recorded = props.students.filter((s) => toCleanString(scores[s.id]) !== '').length;
    const unrecorded = props.students.filter(
        (s) => toCleanString(scores[s.id]) === '' && (!s.is_absent || includeAbsent.value),
    ).length;
    const absent = props.students.filter((s) => s.is_absent).length;
    return { total, recorded, unrecorded, absent };
});

const clearSearch = () => {
    searchQuery.value = '';
    searchInputRef.value?.focus();
};

const clearAllFilters = () => {
    searchQuery.value = '';
    statusFilter.value = 'all';
    searchInputRef.value?.focus();
};

const focusFirstMatch = () => {
    if (visibleEligible.value.length > 0) {
        const first = visibleEligible.value[0];
        nextTick(() => {
            const el = inputs.get(first.id);
            if (el) {
                el.focus();
                el.select();
            }
        });
    }
};

const liveSummary = computed(() => {
    const validScores: number[] = [];
    props.students.forEach((student) => {
        if (student.is_absent && !includeAbsent.value) return;
        const val = toCleanString(scores[student.id]);
        if (val !== '') {
            const num = Number(val);
            if (Number.isFinite(num) && num >= 0 && num <= Number(props.assessment.max_points)) {
                validScores.push(num);
            }
        }
    });

    const graded = validScores.length;
    const missing = Math.max(0, eligible.value.length - graded);
    const absent = props.students.filter((s) => s.is_absent).length;
    const average = graded > 0 ? (validScores.reduce((a, b) => a + b, 0) / graded).toFixed(2) : null;

    return { graded, missing, absent, average };
});

const move = (student: Student, direction: number) => {
    const list = visibleEligible.value;
    const index = list.findIndex((item) => item.id === student.id);
    if (index === -1) return;
    const nextIndex = index + direction;
    if (nextIndex >= 0 && nextIndex < list.length) {
        const target = list[nextIndex];
        const el = inputs.get(target.id);
        if (el) {
            el.focus();
            el.select();
        }
    }
};

const handleKey = (event: KeyboardEvent, student: Student) => {
    if (event.key === 'Enter' || event.key === 'Tab') {
        event.preventDefault();
        move(student, event.shiftKey ? -1 : 1);
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(student, 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(student, -1);
    } else if (event.key === 'Escape' || ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k')) {
        event.preventDefault();
        searchInputRef.value?.focus();
        searchInputRef.value?.select();
    }
};

const saveAll = () => {
    if (isSaving.value) return;

    saveErrorMessage.value = '';
    saveSuccessMessage.value = '';

    const maxPoints = Number(props.assessment.max_points);
    let firstInvalidStudentId: number | null = null;
    const payloadScores: Record<number, number | null> = {};
    const payloadRemarks: Record<number, string | null> = {};

    for (const student of props.students) {
        const err = getScoreError(student.id);
        if (err) {
            if (firstInvalidStudentId === null) {
                firstInvalidStudentId = student.id;
            }
        }
        const raw = toCleanString(scores[student.id]);
        payloadScores[student.id] = raw === '' ? null : Number(raw);

        const rawRemark = toCleanString(remarks[student.id]);
        payloadRemarks[student.id] = rawRemark === '' ? null : rawRemark;
    }

    if (firstInvalidStudentId !== null) {
        saveErrorMessage.value = `Cannot save: Some scores are below 0 or exceed the total score of ${maxPoints} pts. Please fix highlighted fields.`;
        inputs.get(firstInvalidStudentId)?.focus();
        inputs.get(firstInvalidStudentId)?.select();
        return;
    }

    isSaving.value = true;

    router.post(
        `/sections/${props.section.id}/assessments/${props.assessment.id}/scores/batch`,
        {
            scores: payloadScores,
            remarks: payloadRemarks,
            include_absent: includeAbsent.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isSaving.value = false;
                props.students.forEach((student) => {
                    lastSavedScores[student.id] = normalize(scores[student.id]);
                    lastSavedRemarks[student.id] = toCleanString(remarks[student.id]);
                });
                saveSuccessMessage.value = 'All scores and remarks have been saved successfully!';
                setTimeout(() => {
                    saveSuccessMessage.value = '';
                }, 4000);
            },
            onError: (errs) => {
                isSaving.value = false;
                const firstErr = Object.values(errs)[0] || 'Could not save scores.';
                saveErrorMessage.value = String(firstErr);
            },
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
};

const resetScores = () => {
    props.students.forEach((student) => {
        scores[student.id] = lastSavedScores[student.id] ?? '';
        remarks[student.id] = lastSavedRemarks[student.id] ?? '';
    });
    saveErrorMessage.value = '';
};

const handleGlobalKeydown = (e: KeyboardEvent) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        void saveAll();
    } else if (
        (e.key === '/' || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k')) &&
        !['INPUT', 'TEXTAREA', 'SELECT'].includes((e.target as HTMLElement)?.tagName)
    ) {
        e.preventDefault();
        searchInputRef.value?.focus();
        searchInputRef.value?.select();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
    <Head :title="`${assessment.assessment_number ? `${assessment.assessment_number}: ` : ''}${assessment.title} · Scores - ClassCheck`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Assessments', href: `/sections/${section.id}/assessments` },
            { title: assessment.assessment_number ? `${assessment.assessment_number}: ${assessment.title}` : assessment.title, href: '#' },
        ]"
    >
        <main class="page-enter mx-auto flex w-full max-w-[1360px] flex-1 flex-col gap-6 px-5 pb-24 pt-8 md:px-10 md:pt-10">
            <Link
                :href="`/sections/${section.id}/assessments`"
                prefetch="hover"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground transition-colors hover:text-primary"
            >
                <ArrowLeft class="size-3.5" /> Back to assessments
            </Link>

            <!-- Top Header & Primary Actions -->
            <header class="paper-card p-6 shadow-sm">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <span class="font-mono font-semibold uppercase text-primary">
                                {{ assessment.assessment_number || (assessment.type === 'laboratory' ? 'Laboratory' : assessment.type) }}
                            </span>
                            <span>·</span>
                            <span class="flex items-center gap-1">
                                <CalendarDays class="size-3.5" />
                                {{ formatDate(assessment.conducted_on) }}
                            </span>
                            <span>·</span>
                            <span class="font-mono font-medium text-foreground">{{ assessment.max_points }} max points</span>
                        </div>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">{{ assessment.title }}</h1>
                        <p v-if="assessment.description" class="mt-1.5 max-w-2xl text-sm text-muted-foreground">
                            {{ assessment.description }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            :disabled="isSaving"
                            :title="hasUnsavedChanges ? `Save All Scores (${unsavedCount} unsaved)` : 'Save All Scores'"
                            class="ink-button group !h-9 !rounded-xl !px-3 text-xs font-bold shadow-sm transition-all duration-300 hover:scale-[1.02]"
                            :class="hasUnsavedChanges ? 'ring-2 ring-primary ring-offset-2' : ''"
                            @click="saveAll"
                        >
                            <LoaderCircle v-if="isSaving" class="size-4 shrink-0 animate-spin" />
                            <Save v-else class="size-4 shrink-0" />
                            <span class="max-w-0 overflow-hidden whitespace-nowrap opacity-0 transition-all duration-300 ease-in-out group-hover:max-w-xs group-hover:opacity-100 group-hover:ml-1.5">{{
                                isSaving ? 'Saving all scores…' : hasUnsavedChanges ? `Save All Scores (${unsavedCount})` : 'Save All Scores'
                            }}</span>
                        </button>

                        <ActivityFileButton
                            :url="`/sections/${section.id}/assessments/${assessment.id}/activity-file`"
                            :file-name="assessment.activity_file_name"
                            :attached="!!assessment.activity_file_path"
                            :activity-title="assessment.title"
                            reload-prop="assessment"
                        />
                        <button
                            type="button"
                            class="shadow-xs inline-flex h-10 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-primary/40 bg-primary/10 px-4 text-sm font-semibold text-primary transition-colors hover:bg-primary hover:text-primary-foreground"
                            :title="hasRubric ? 'Edit rubric / answer key: ' + rubricBadgeLabel : 'Configure rubric or upload file'"
                            @click="openRubrics"
                        >
                            <Paperclip class="size-4 shrink-0" />
                            <span>{{ rubricBadgeLabel }}</span>
                        </button>
                        <DisabledReason :reason="!hasRubric ? 'Configure a rubric or attach a file in Rubrics before using the autochecker.' : checkingAll ? 'Check all is running. Wait for the current batch to finish.' : ''">
                            <button
                                type="button"
                                title="Bulk AI Autochecker"
                                :disabled="!hasRubric || checkingAll"
                                @mouseenter="warmModel('code_grading')"
                                @click="showAutochecker = true"
                                class="shadow-xs group inline-flex h-9 shrink-0 items-center justify-center whitespace-nowrap rounded-xl border border-primary/40 bg-primary/10 px-3 text-xs font-bold text-primary transition-all duration-300 hover:bg-primary hover:text-primary-foreground"
                            >
                                <Sparkles class="size-4 shrink-0" />
                                <span class="max-w-0 overflow-hidden whitespace-nowrap opacity-0 transition-all duration-300 ease-in-out group-hover:max-w-xs group-hover:opacity-100 group-hover:ml-1.5">Autochecker</span>
                            </button>
                        </DisabledReason>
                        <a
                            :href="`/sections/${section.id}/assessments/${assessment.id}/export`"
                            title="Export scores to CSV"
                            class="shadow-xs group inline-flex h-9 shrink-0 items-center justify-center whitespace-nowrap rounded-xl border border-border bg-card px-3 text-xs font-medium text-foreground transition-all duration-300 hover:bg-secondary"
                        >
                            <Download class="size-4 shrink-0 text-muted-foreground group-hover:text-foreground" />
                            <span class="max-w-0 overflow-hidden whitespace-nowrap opacity-0 transition-all duration-300 ease-in-out group-hover:max-w-xs group-hover:opacity-100 group-hover:ml-1.5">Export scores</span>
                        </a>
                        <button
                            type="button"
                            title="Edit assessment details"
                            @click="editing = true"
                            class="shadow-xs group inline-flex h-9 shrink-0 items-center justify-center whitespace-nowrap rounded-xl border border-border bg-card px-3 text-xs font-medium text-foreground transition-all duration-300 hover:bg-secondary"
                        >
                            <Settings class="size-4 shrink-0 text-primary" />
                            <span class="max-w-0 overflow-hidden whitespace-nowrap opacity-0 transition-all duration-300 ease-in-out group-hover:max-w-xs group-hover:opacity-100 group-hover:ml-1.5">Edit assessment</span>
                        </button>

                        <button
                            type="button"
                            class="shadow-xs group inline-flex h-9 shrink-0 items-center justify-center whitespace-nowrap rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 text-xs font-bold text-rose-600 transition-all duration-300 hover:bg-rose-600 hover:text-white dark:text-rose-400 dark:hover:text-white"
                            :title="`Delete this ${assessment.type}`"
                            @click="showDeleteModal = true"
                        >
                            <Trash2 class="size-4 shrink-0" />
                            <span class="max-w-0 overflow-hidden whitespace-nowrap opacity-0 transition-all duration-300 ease-in-out group-hover:max-w-xs group-hover:opacity-100 group-hover:ml-1.5 capitalize">Delete {{ assessment.type }}</span>
                        </button>
                    </div>
                </div>
            </header>

            <section class="grading-toolbar" aria-label="Submission checking">
                <div><p class="font-semibold">Student outputs</p><p class="text-sm text-muted-foreground">{{ attachedStudents.length }} attached • {{ hasRubric ? `${rubricBadgeLabel} ready to review` : 'Configure a rubric in Rubrics to enable checking.' }}</p></div>
                <DisabledReason :reason="checkUnavailableReason || (!attachedStudents.length ? 'Attach at least one student output to enable Check all.' : '')">
                    <button
                        type="button"
                        class="grading-check-button"
                        :disabled="!hasRubric || !attachedStudents.length || checkingAll || aiCheckingStudentId !== null"
                        @mouseenter="warmModel('code_grading'); warmModel('general_grading')"
                        @click="checkAll"
                    >
                        <OctoSpinner v-if="checkingAll" size="sm" class="mr-1.5" />
                        <Sparkles v-else class="size-4 mr-1.5 shrink-0" />
                        {{ checkingAll ? `Octo checking ${checkProgress} / ${attachedStudents.length}` : `Check all (${attachedStudents.length})` }}
                    </button>
                </DisabledReason>
                <progress v-if="checkingAll" class="w-full accent-primary" :value="checkProgress" :max="attachedStudents.length" aria-label="Checking progress" />
                <ul v-if="checkFailures.length" class="w-full space-y-1 text-sm text-rose-600" aria-live="polite"><li v-for="failure in checkFailures" :key="failure">{{ failure }}</li></ul>
            </section>

            <!-- Alerts Banner -->
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="transform -translate-y-2 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform -translate-y-2 opacity-0"
            >
                <div
                    v-if="saveSuccessMessage"
                    class="flex items-center justify-between rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-700 dark:text-emerald-400"
                >
                    <div class="flex items-center gap-2">
                        <CheckCircle2 class="size-4.5 text-emerald-600 dark:text-emerald-400" />
                        <span>{{ saveSuccessMessage }}</span>
                    </div>
                    <button type="button" class="text-emerald-700 hover:text-emerald-900 dark:text-emerald-400" @click="saveSuccessMessage = ''">
                        <X class="size-4" />
                    </button>
                </div>
            </transition>

            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="transform -translate-y-2 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform -translate-y-2 opacity-0"
            >
                <div
                    v-if="saveErrorMessage"
                    class="flex items-center justify-between rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-700 dark:text-rose-400"
                >
                    <div class="flex items-center gap-2">
                        <TriangleAlert class="size-4.5 text-rose-600 dark:text-rose-400" />
                        <span>{{ saveErrorMessage }}</span>
                    </div>
                    <button type="button" class="text-rose-700 hover:text-rose-900 dark:text-rose-400" @click="saveErrorMessage = ''">
                        <X class="size-4" />
                    </button>
                </div>
            </transition>

            <!-- KPI Metric Chips -->
            <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <article class="paper-card p-5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Scores recorded</span>
                    <p class="mt-3 text-2xl font-bold tracking-tight">
                        {{ liveSummary.graded }}<span class="text-xs font-normal text-muted-foreground">/{{ students.length }}</span>
                    </p>
                </article>

                <article class="paper-card p-5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Class average</span>
                    <p class="mt-3 text-2xl font-bold tracking-tight text-primary">
                        {{ liveSummary.average !== null ? `${liveSummary.average} pts` : '—' }}
                    </p>
                </article>

                <article class="paper-card p-5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Ungraded (Present)</span>
                    <p class="mt-3 text-2xl font-bold tracking-tight">
                        {{ liveSummary.missing }}
                    </p>
                </article>

                <article class="paper-card p-5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Absent from session</span>
                    <p class="mt-3 text-2xl font-bold tracking-tight text-rose-600 dark:text-rose-400">
                        {{ liveSummary.absent }}
                    </p>
                </article>
            </section>

            <!-- Score Entry Table -->
            <section class="paper-card overflow-hidden p-6 shadow-sm">
                <div class="flex flex-col gap-4 border-b border-border/80 pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-foreground">Score Entry Table</h2>
                        <p class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Keyboard class="size-3.5 text-primary" />
                            <span
                                >Type a score and press <strong>Tab</strong> or <strong>Enter</strong> to quickly advance down the list. Click
                                <strong>Save All Scores</strong> when finished (or press Ctrl+S).</span
                            >
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <label
                            class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-border/60 bg-secondary/50 px-3.5 py-2 text-xs font-medium transition-colors hover:bg-secondary"
                        >
                            <input v-model="includeAbsent" type="checkbox" class="size-4 rounded border-input text-primary focus:ring-primary" />
                            <span>Allow scoring absent students</span>
                        </label>

                        <button type="button" :disabled="isSaving" class="ink-button !h-9 !rounded-xl !px-4 text-xs font-bold" @click="saveAll">
                            <LoaderCircle v-if="isSaving" class="size-3.5 animate-spin" />
                            <Save v-else class="size-3.5" />
                            <span>Save All Scores</span>
                        </button>
                    </div>
                </div>

                <!-- Search & Filters Toolbar -->
                <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <!-- Search Input Box -->
                    <div class="relative w-full lg:max-w-md">
                        <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            ref="searchInputRef"
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search student by name, ID, or chair... (Press '/' to focus)"
                            class="w-full rounded-xl border border-input bg-card py-2 pl-10 pr-9 text-xs font-medium text-foreground shadow-2xs transition-all placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            @keydown.enter.prevent="focusFirstMatch"
                            @keydown.down.prevent="focusFirstMatch"
                            @keydown.esc="clearSearch"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-md p-1 text-muted-foreground hover:bg-secondary hover:text-foreground"
                            title="Clear search (Esc)"
                            @click="clearSearch"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>

                    <!-- Quick Filter Tabs -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            class="rounded-lg px-2.5 py-1 text-xs font-medium transition-all"
                            :class="
                                statusFilter === 'all'
                                    ? 'bg-primary text-primary-foreground font-bold shadow-xs'
                                    : 'border border-border/80 bg-secondary/40 text-muted-foreground hover:bg-secondary hover:text-foreground'
                            "
                            @click="statusFilter = 'all'"
                        >
                            All ({{ counts.total }})
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-2.5 py-1 text-xs font-medium transition-all"
                            :class="
                                statusFilter === 'unrecorded'
                                    ? 'bg-amber-600 text-white font-bold shadow-xs dark:bg-amber-500'
                                    : 'border border-border/80 bg-secondary/40 text-muted-foreground hover:bg-secondary hover:text-foreground'
                            "
                            @click="statusFilter = 'unrecorded'"
                        >
                            Needs Score ({{ counts.unrecorded }})
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-2.5 py-1 text-xs font-medium transition-all"
                            :class="
                                statusFilter === 'recorded'
                                    ? 'bg-emerald-600 text-white font-bold shadow-xs dark:bg-emerald-500'
                                    : 'border border-border/80 bg-secondary/40 text-muted-foreground hover:bg-secondary hover:text-foreground'
                            "
                            @click="statusFilter = 'recorded'"
                        >
                            Scored ({{ counts.recorded }})
                        </button>
                        <button
                            v-if="counts.absent > 0"
                            type="button"
                            class="rounded-lg px-2.5 py-1 text-xs font-medium transition-all"
                            :class="
                                statusFilter === 'absent'
                                    ? 'bg-rose-600 text-white font-bold shadow-xs dark:bg-rose-500'
                                    : 'border border-border/80 bg-secondary/40 text-muted-foreground hover:bg-secondary hover:text-foreground'
                            "
                            @click="statusFilter = 'absent'"
                        >
                            Absent ({{ counts.absent }})
                        </button>
                    </div>
                </div>

                <!-- Match Count & Active Filter Indicator Strip -->
                <div
                    v-if="searchQuery || statusFilter !== 'all'"
                    class="mt-3 flex items-center justify-between rounded-xl border border-primary/20 bg-primary/5 px-3.5 py-2 text-xs text-muted-foreground"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-foreground">
                            Showing {{ filteredStudents.length }} of {{ students.length }} students
                        </span>
                        <span v-if="searchQuery" class="text-muted-foreground">
                            matching "<strong class="text-primary">{{ searchQuery }}</strong>"
                        </span>
                        <span v-if="statusFilter !== 'all'" class="text-muted-foreground">
                            with status <strong class="capitalize text-foreground">{{ statusFilter }}</strong>
                        </span>
                        <span class="text-[11px] text-muted-foreground/80 italic">
                            (Press Enter or Down arrow from search box to start scoring)
                        </span>
                    </div>

                    <button
                        type="button"
                        class="shrink-0 font-semibold text-primary hover:underline hover:text-primary/80"
                        @click="clearAllFilters"
                    >
                        Clear filters & show all
                    </button>
                </div>

                <div class="mt-4 overflow-auto max-h-[calc(100vh-14rem)] min-h-[380px] rounded-xl border border-border/70 bg-card shadow-2xs scrollbar-thin print:max-h-none print:overflow-visible">
                    <table class="w-full min-w-[800px] border-separate border-spacing-0 text-sm">
                        <thead class="sticky top-0 z-20 border-b border-border/80 bg-secondary/95 backdrop-blur-md shadow-2xs print:static print:bg-gray-100">
                            <tr
                                class="text-left text-[11px] font-bold uppercase tracking-wider text-muted-foreground"
                            >
                                <th class="sticky top-0 left-0 z-30 min-w-64 border-b border-border/80 bg-secondary/95 px-4 py-3 backdrop-blur-md print:static print:bg-gray-100">Student Name & ID</th>
                                <th class="w-44 border-b border-border/80 px-4 py-3">Score / {{ assessment.max_points }} pts</th>
                                <th class="min-w-44 border-b border-border/80 px-4 py-3">Remarks / Feedback</th>
                                <th class="w-48 border-b border-border/80 px-4 py-3">Student Output</th>
                                <th class="w-32 border-b border-border/80 px-4 py-3">Status / Grade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <!-- Empty Search Results Row -->
                            <tr v-if="filteredStudents.length === 0">
                                <td colspan="5" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <Search class="size-8 text-muted-foreground/40" />
                                        <p class="font-bold text-foreground">No students found</p>
                                        <p class="text-xs text-muted-foreground">
                                            <span v-if="searchQuery">
                                                No students match "<strong class="text-foreground">{{ searchQuery }}</strong>". Try searching by name, student number, or chair.
                                            </span>
                                            <span v-else>
                                                No students found for status "{{ statusFilter }}".
                                            </span>
                                        </p>
                                        <button
                                            type="button"
                                            class="mt-2 inline-flex items-center gap-1.5 rounded-xl border border-border bg-card px-3.5 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-secondary"
                                            @click="clearAllFilters"
                                        >
                                            <RotateCcw class="size-3.5" />
                                            <span>Reset search & filters</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr
                                v-for="student in filteredStudents"
                                :key="student.id"
                                class="transition-colors hover:bg-secondary/30"
                                :class="[
                                    student.is_absent && !includeAbsent ? 'bg-muted/20 opacity-60' : '',
                                    searchQuery ? 'border-l-4 border-l-primary/60' : '',
                                    hasInvalidScore(student.id) ? 'bg-rose-500/5' : isUnsaved(student.id) ? 'bg-primary/5' : '',
                                ]"
                            >
                                <td class="sticky left-0 z-10 border-b border-r border-border/50 bg-card/95 px-4 py-3 backdrop-blur-xs print:static print:bg-white">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 font-mono text-xs font-bold uppercase text-primary"
                                        >
                                            {{ student.full_name?.split(' ')?.[0]?.[0] || 'S' }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <span class="block font-semibold text-foreground">{{ student.full_name }}</span>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-mono text-xs text-muted-foreground">{{ student.student_number }}</span>
                                                <span
                                                    v-if="student.seat_label"
                                                    class="rounded-md border border-border/60 bg-secondary/70 px-1.5 py-0.5 font-mono text-[10px] font-medium text-muted-foreground"
                                                    title="Assigned Chair"
                                                >
                                                    Chair {{ student.seat_label }}
                                                </span>
                                                <span
                                                    v-if="student.is_absent"
                                                    class="inline-flex items-center gap-1 rounded-full border border-rose-500/20 bg-rose-500/10 px-2 py-0.5 text-[10px] font-medium text-rose-600 dark:text-rose-400"
                                                >
                                                    <UserX class="size-3" /> Absent
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="border-b border-border/50 px-4 py-3">
                                    <div class="relative max-w-[220px]">
                                        <input
                                            :ref="
                                                (el) => {
                                                    if (el) inputs.set(student.id, el as HTMLInputElement);
                                                }
                                            "
                                            v-model="scores[student.id]"
                                            :disabled="student.is_absent && !includeAbsent"
                                            type="number"
                                            min="0"
                                            :max="assessment.max_points"
                                            step="0.01"
                                            placeholder="—"
                                            inputmode="decimal"
                                            class="w-full rounded-xl border px-3 py-1.5 font-mono text-base font-bold tabular-nums transition-all focus:outline-none disabled:cursor-not-allowed disabled:bg-muted/40"
                                            :class="[
                                                hasInvalidScore(student.id)
                                                    ? '!border-rose-500 !bg-rose-500/10 !text-rose-600 !ring-2 !ring-rose-500 dark:!text-rose-400'
                                                    : isUnsaved(student.id)
                                                      ? 'border-primary bg-primary/5 text-foreground ring-1 ring-primary/40 focus:ring-2 focus:ring-primary'
                                                      : 'border-input bg-background text-foreground focus:border-primary focus:ring-2 focus:ring-primary/20',
                                            ]"
                                            :aria-label="`Score for ${student.full_name}`"
                                            @focus="($event.target as HTMLInputElement)?.select()"
                                            @keydown="handleKey($event, student)"
                                        />
                                    </div>
                                    <p
                                        v-if="hasInvalidScore(student.id)"
                                        class="mt-1 flex items-center gap-1 text-[11px] font-bold text-rose-600 dark:text-rose-400"
                                    >
                                        <TriangleAlert class="size-3.5 shrink-0" />
                                        <span>{{ getScoreError(student.id) }}</span>
                                    </p>
                                </td>
                                <td class="border-b border-border/50 px-4 py-3">
                                    <div class="space-y-1">
                                        <textarea
                                            v-model="remarks[student.id]"
                                            rows="4" maxlength="10000"

                                            placeholder="Score justification / remarks..."
                                            class="resize-y leading-relaxed w-full rounded-xl border border-input bg-background px-3 py-1.5 text-xs text-foreground placeholder:text-muted-foreground/60 transition-all focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:bg-muted/40"
                                            :disabled="student.is_absent && !includeAbsent"
                                            :aria-label="`Remarks for ${student.full_name}`"
                                        />
                                        <div v-if="!student.is_absent || includeAbsent" class="flex flex-wrap items-center gap-1 pt-0.5">
                                            <button
                                                v-for="preset in ['Complete requirements', '-5 Late', 'Incomplete solution', 'Bonus points', 'Needs revision', 'Outstanding']"
                                                :key="preset"
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[9px] transition-all cursor-pointer select-none"
                                                :class="getPresetClass(student.id, preset)"
                                                :aria-pressed="isPresetActive(student.id, preset)"
                                                :title="isPresetActive(student.id, preset) ? `Click to remove '${preset}'` : `Click to apply '${preset}' and adjust score`"
                                                @click="toggleStudentPreset(student.id, preset)"
                                            >
                                                <span class="font-bold">{{ isPresetActive(student.id, preset) ? '✓' : '+' }}</span>
                                                <span>{{ preset }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td class="border-b border-border/50 px-4 py-3">
                                    <!-- Hidden File Input for this student -->
                                    <input
                                        :ref="(el) => { if (el) studentFileInputs.set(student.id, el as HTMLInputElement); }"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z,.rtf,.odt,.ods,.odp,.svg,.gif,.bmp,.heic,.pages,.numbers,.key,.json,.sql,.db,.sqlite,.sqlite3"
                                        class="hidden"
                                        @change="handleStudentFileUpload(student, $event)"
                                    />

                                    <!-- When attachment exists -->
                                    <div v-if="student.attachment_path" class="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            class="group inline-flex max-w-[150px] items-center gap-1.5 truncate rounded-lg border border-primary/30 bg-primary/10 px-2 py-1 text-xs font-semibold text-primary transition-colors hover:bg-primary hover:text-white"
                                            :title="`Preview / Download: ${student.attachment_name}`"
                                            @click="openStudentOutputPreview(student)"
                                        >
                                            <Paperclip class="size-3 shrink-0" />
                                            <span class="truncate font-mono text-[11px]">{{ student.attachment_name || 'View Output' }}</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                            title="Upload new file to replace"
                                            @click="triggerStudentUpload(student.id)"
                                        >
                                            <RefreshCw class="size-3" />
                                        </button>
                                        <DisabledReason :reason="checkUnavailableReason">
                                            <button
                                                type="button"
                                                :disabled="checkingAll || aiCheckingStudentId !== null || !hasRubric"
                                                class="inline-flex items-center gap-1 rounded-md border border-violet-500/30 bg-violet-500/10 px-2 py-1 text-[11px] font-semibold text-violet-700 transition-all hover:bg-violet-600 hover:text-white disabled:opacity-50 dark:border-violet-400/30 dark:bg-violet-400/10 dark:text-violet-300 dark:hover:bg-violet-500 dark:hover:text-white"
                                                :title="aiCheckingStudentId === student.id ? 'Octo AI is analyzing submission against rubrics...' : 'AI Check: Analyze document against activity rubrics and auto-score'"
                                                @mouseenter="warmModel('code_grading'); warmModel('general_grading')"
                                                @click="runAiCheck(student)"
                                            >
                                                <OctoSpinner v-if="aiCheckingStudentId === student.id" size="xs" class="mr-0.5" />
                                                <Sparkles v-else class="size-3 shrink-0" />
                                                <span>{{ aiCheckingStudentId === student.id ? 'Octo checking…' : 'Check' }}</span>
                                            </button>
                                        </DisabledReason>
                                    </div>

                                    <!-- When no attachment yet -->
                                    <div v-else class="flex items-center">
                                        <button
                                            type="button"
                                            :disabled="studentUploading[student.id]"
                                            class="inline-flex items-center gap-1 rounded-lg border border-dashed border-border/90 bg-secondary/30 px-2 py-1 text-[11px] font-medium text-muted-foreground transition-colors hover:border-primary/50 hover:bg-secondary hover:text-foreground disabled:opacity-50"
                                            @click="triggerStudentUpload(student.id)"
                                        >
                                            <LoaderCircle v-if="studentUploading[student.id]" class="size-3 animate-spin" />
                                            <UploadCloud v-else class="size-3" />
                                            <span>{{ studentUploading[student.id] ? 'Uploading…' : 'Attach Output' }}</span>
                                        </button>
                                    </div>
                                </td>
                                <td class="border-b border-border/50 px-4 py-3 text-xs font-medium">
                                    <div
                                        v-if="hasInvalidScore(student.id)"
                                        class="inline-flex items-center gap-1 rounded-lg border border-rose-500/30 bg-rose-500/10 px-2 py-0.5 font-mono text-xs font-bold text-rose-600 dark:text-rose-400"
                                    >
                                        <TriangleAlert class="size-3" />
                                        <span>Error</span>
                                    </div>
                                    <div v-else-if="toCleanString(scores[student.id]) !== ''" class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-lg px-2 py-0.5 font-mono text-xs font-bold"
                                            :class="
                                                Number(scores[student.id]) >= Number(assessment.max_points) * passingThreshold
                                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                    : Number(scores[student.id]) >= Number(assessment.max_points) * 0.5
                                                      ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                      : 'bg-rose-500/10 text-rose-600 dark:text-rose-400'
                                            "
                                        >
                                            {{ Math.round((Number(scores[student.id]) / Number(assessment.max_points)) * 100) }}%
                                        </span>
                                        <span
                                            v-if="isUnsaved(student.id)"
                                            class="font-mono text-[10px] font-bold text-amber-600 dark:text-amber-400"
                                            title="Unsaved changes"
                                        >
                                            ● Unsaved
                                        </span>
                                        <span v-else class="font-mono text-[10px] font-medium text-muted-foreground"> Saved </span>
                                    </div>
                                    <div v-else-if="student.is_absent" class="italic text-muted-foreground">Absent (Skipped)</div>
                                    <div v-else class="text-muted-foreground">Not recorded</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Save Action Bar -->
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-border/80 pt-4">
                    <div class="text-xs text-muted-foreground">
                        <span v-if="hasUnsavedChanges" class="font-semibold text-amber-600 dark:text-amber-400">
                            {{ unsavedCount }} student score{{ unsavedCount !== 1 ? 's' : '' }} modified
                        </span>
                        <span v-else class="text-muted-foreground"> All scores are up to date with the database. </span>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            v-if="hasUnsavedChanges"
                            type="button"
                            class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-border bg-card px-3 text-xs font-semibold text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            @click="resetScores"
                        >
                            <RotateCcw class="size-3.5" />
                            <span>Discard changes</span>
                        </button>

                        <button type="button" :disabled="isSaving" class="ink-button !h-9 !rounded-xl !px-5 text-xs font-bold" @click="saveAll">
                            <LoaderCircle v-if="isSaving" class="size-3.5 animate-spin" />
                            <Save v-else class="size-3.5" />
                            <span>{{ isSaving ? 'Saving…' : 'Save All Scores' }}</span>
                        </button>
                    </div>
                </div>
            </section>
        </main>

        <!-- Floating Sticky Bottom Bar when unsaved changes exist -->
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="transform translate-y-8 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform translate-y-8 opacity-0"
        >
            <div
                v-if="hasUnsavedChanges"
                class="fixed bottom-6 right-6 z-40 flex items-center gap-3 rounded-2xl border border-border/90 bg-card/95 p-3 shadow-2xl backdrop-blur-md"
            >
                <div class="hidden pl-2 text-xs sm:block">
                    <p class="font-bold text-foreground">{{ unsavedCount }} unsaved score{{ unsavedCount !== 1 ? 's' : '' }}</p>
                    <p class="text-[10px] text-muted-foreground">Press Ctrl+S to save anytime</p>
                </div>

                <button type="button" :disabled="isSaving" class="ink-button !h-10 !rounded-xl !px-5 text-xs font-bold shadow-lg" @click="saveAll">
                    <LoaderCircle v-if="isSaving" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    <span>{{ isSaving ? 'Saving…' : 'Save All Scores' }}</span>
                </button>
            </div>
        </transition>

        <!-- Edit Assessment Modal -->
        <div
            v-if="editing"
            v-modal-focus
            class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
        >
            <div
                class="paper-card relative w-full max-w-lg overflow-hidden border-border/90 p-8 shadow-2xl duration-200 animate-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                aria-label="Edit assessment"
            >
                <button
                    type="button"
                    class="absolute right-4 top-4 grid size-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                    @click="editing = false"
                >
                    <X class="size-4.5" />
                </button>

                <div
                    class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 font-mono text-[11px] font-medium uppercase tracking-wider text-primary"
                >
                    <Sparkles class="size-3.5" /> Edit Assessment
                </div>

                <h3 class="mt-3 text-2xl font-bold tracking-tight text-foreground">Assessment details</h3>
                <p class="mt-1 text-xs text-muted-foreground">Modify settings or upload a reference document below.</p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitEdit">
                    <label class="sm:col-span-1">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Type</span>
                        <select
                            v-model="editForm.type"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <option value="activity">Activity</option>
                            <option value="laboratory">Laboratory Activity</option>
                            <option value="quiz">Quiz</option>
                            <option value="exam">Exam</option>
                        </select>
                    </label>

                    <label class="sm:col-span-1">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            {{ editForm.type === 'quiz' ? 'Quiz #' : editForm.type === 'exam' ? 'Exam #' : editForm.type === 'laboratory' ? 'Lab #' : 'Activity #' }}
                        </span>
                        <input
                            v-model="editForm.assessment_number"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                            :placeholder="editForm.type === 'quiz' ? 'e.g. Quiz 1' : editForm.type === 'exam' ? 'e.g. Exam 1' : editForm.type === 'laboratory' ? 'e.g. Lab 1' : 'e.g. Activity 1'"
                        />
                        <small v-if="editForm.errors.assessment_number" class="mt-1 block text-xs text-rose-600">{{
                            editForm.errors.assessment_number
                        }}</small>
                    </label>

                    <label class="sm:col-span-2">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Title</span>
                        <input
                            v-model="editForm.title"
                            required
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                        <small v-if="editForm.errors.title" class="mt-1 block text-xs text-rose-600">{{ editForm.errors.title }}</small>
                    </label>

                    <label class="sm:col-span-1">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Max points</span>
                        <input
                            v-model="editForm.max_points"
                            required
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                        <small v-if="editForm.errors.max_points" class="mt-1 block text-xs text-rose-600">{{ editForm.errors.max_points }}</small>
                    </label>

                    <label class="sm:col-span-1">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Date conducted</span>
                        <input
                            v-model="editForm.conducted_on"
                            required
                            type="date"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <label class="sm:col-span-1">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">Link session</span>
                        <select
                            v-model="editForm.attendance_session_id"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <option value="">Auto-match by date</option>
                            <option v-for="session in attendanceSessions" :key="session.id" :value="session.id">
                                {{ session.session_date }} · {{ session.starts_at }}
                            </option>
                        </select>
                        <small v-if="editForm.errors.attendance_session_id" class="mt-1 block text-xs text-rose-600">{{
                            editForm.errors.attendance_session_id
                        }}</small>
                    </label>

                    <div class="space-y-2 sm:col-span-2">
                        <span class="block text-xs font-medium uppercase tracking-wider text-muted-foreground"
                            >Reference File / Questions <em class="font-normal normal-case text-muted-foreground">(optional, max 50MB)</em></span
                        >

                        <!-- Existing Attachment Controls -->
                        <div
                            v-if="assessment.attachment_name && !editForm.remove_attachment"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border/80 bg-muted/40 p-3 text-xs"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                <Paperclip class="size-4 text-primary shrink-0" />
                                <div class="min-w-0">
                                    <p class="font-semibold text-foreground truncate">{{ assessment.attachment_name }}</p>
                                    <p class="text-[10px] text-muted-foreground">Current attachment</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    class="inline-flex h-7 items-center gap-1 rounded-lg border border-primary/40 bg-primary/10 px-2.5 text-[11px] font-semibold text-primary hover:bg-primary hover:text-white transition-colors"
                                    @click="editFileInputRef?.click()"
                                >
                                    <RefreshCw class="size-3" />
                                    <span>Replace File</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-7 items-center gap-1 rounded-lg border border-rose-500/30 bg-rose-500/10 px-2.5 text-[11px] font-semibold text-rose-600 hover:bg-rose-600 hover:text-white transition-colors dark:text-rose-400"
                                    @click="editForm.remove_attachment = true; editForm.attachment = null;"
                                >
                                    <Trash2 class="size-3" />
                                    <span>Delete File</span>
                                </button>
                            </div>
                        </div>

                        <!-- Marked for Removal Banner -->
                        <div
                            v-else-if="assessment.attachment_name && editForm.remove_attachment"
                            class="flex items-center justify-between rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-700 dark:text-rose-300"
                        >
                            <div class="flex items-center gap-2">
                                <Trash2 class="size-4 shrink-0" />
                                <span>Attached file <strong>{{ assessment.attachment_name }}</strong> will be removed upon save.</span>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg border border-rose-500/40 bg-card px-2.5 py-1 text-[11px] font-bold text-foreground hover:bg-secondary"
                                @click="editForm.remove_attachment = false"
                            >
                                Undo
                            </button>
                        </div>

                        <!-- File Input -->
                        <div>
                            <input
                                ref="editFileInputRef"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z,.rtf,.odt,.ods,.odp,.svg,.json,.sql,.db,.sqlite,.sqlite3"
                                class="block w-full text-xs text-muted-foreground file:mr-2 file:rounded-lg file:border file:border-border file:bg-secondary file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-foreground hover:file:bg-secondary/80"
                                @change="
                                    editForm.attachment = ($event.target as HTMLInputElement).files?.[0] || null;
                                    editForm.remove_attachment = false;
                                "
                            />
                            <div v-if="editForm.attachment" class="mt-1.5 flex items-center justify-between rounded-lg bg-primary/10 px-2.5 py-1 text-xs text-primary font-mono">
                                <span>Selected replacement: {{ editForm.attachment.name }} ({{ (editForm.attachment.size / 1024 / 1024).toFixed(2) }} MB)</span>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground ml-2"
                                    @click="editForm.attachment = null; if (editFileInputRef) editFileInputRef.value = '';"
                                >
                                    <X class="size-3.5" />
                                </button>
                            </div>
                            <small v-if="editForm.errors.attachment" class="mt-1 block text-xs text-rose-600">{{ editForm.errors.attachment }}</small>
                        </div>
                    </div>

                    <label class="sm:col-span-2">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground"
                            >Notes <em class="font-normal normal-case text-muted-foreground">(optional)</em></span
                        >
                        <textarea
                            v-model="editForm.description"
                            rows="2"
                            placeholder="Instructions or rubric notes..."
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border/80 pt-4 sm:col-span-2">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-600 hover:text-white dark:text-rose-400 dark:hover:text-white"
                            @click="
                                editing = false;
                                showDeleteModal = true;
                            "
                        >
                            <Trash2 class="size-3.5" />
                            <span class="capitalize">Delete {{ assessment.type }}</span>
                        </button>

                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="shadow-xs inline-flex h-10 items-center justify-center rounded-xl border border-border bg-card px-5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                                @click="editing = false"
                            >
                                Cancel
                            </button>
                            <button type="submit" :disabled="editForm.processing" class="ink-button !h-10 !rounded-xl !px-5 text-xs font-medium">
                                {{ editForm.processing ? 'Saving…' : 'Save Changes' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div
            v-if="showDeleteModal"
            v-modal-focus
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
        >
            <div
                class="paper-card relative w-full max-w-lg border-border/90 p-6 shadow-2xl duration-200 animate-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                :aria-label="`Delete ${assessment.title}`"
            >
                <div class="flex items-start gap-4">
                    <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-rose-500/15 text-rose-700 dark:text-rose-400">
                        <Trash2 class="size-6" />
                    </div>
                    <div class="flex-1">
                        <span class="eyebrow text-rose-700 dark:text-rose-400">Permanent Deletion</span>
                        <h3 class="mt-1 text-xl font-bold text-foreground">Delete {{ assessment.title }}?</h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ assessment.type.toUpperCase() }} · {{ assessment.max_points }} max points · {{ formatDate(assessment.conducted_on) }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-rose-500/20 bg-rose-500/5 p-4 text-xs leading-relaxed text-foreground">
                    <p class="font-bold text-rose-700 dark:text-rose-400">
                        Warning: This action will permanently remove this assessment entry and its associated data:
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-4 text-muted-foreground">
                        <li>The assessment ledger record and task description</li>
                        <li>
                            All <strong class="text-foreground">{{ summary.graded }} recorded student scores</strong> for this assessment
                        </li>
                        <li v-if="assessment.attachment_name">Attached reference file: {{ assessment.attachment_name }}</li>
                    </ul>
                    <p class="mt-2 font-bold text-rose-700 dark:text-rose-400">This action cannot be undone.</p>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-border/80 pt-4">
                    <button
                        type="button"
                        class="shadow-xs inline-flex h-10 items-center justify-center rounded-xl border border-border bg-card px-5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                        :disabled="isDeleting"
                        @click="showDeleteModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-5 text-xs font-bold text-white shadow-sm transition-all hover:bg-rose-700 disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="confirmDelete"
                    >
                        <LoaderCircle v-if="isDeleting" class="size-4 animate-spin" />
                        <Trash2 v-else class="size-4" />
                        <span>{{ isDeleting ? 'Deleting assessment…' : 'Yes, Delete Assessment' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Assessment Guidelines Attachment Preview Modal -->
        <FilePreviewModal
            v-if="assessment.attachment_path"
            :show="showPreview"
            :title="`Rubrics: ${assessment.title}`"
            :file-name="assessment.attachment_name"
            :file-url="`/sections/${section.id}/assessments/${assessment.id}/attachment`"
            :download-url="`/sections/${section.id}/assessments/${assessment.id}/attachment?download=1`"
            :reupload-url="`/sections/${section.id}/assessments/${assessment.id}/attachment`"
            :delete-url="`/sections/${section.id}/assessments/${assessment.id}/attachment`"
            @close="showPreview = false"
            @deleted="showPreview = false"
            @reuploaded="showPreview = false"
        />

        <!-- Student Output Preview Modal -->
        <FilePreviewModal
            v-if="studentPreviewModal.show"
            :show="studentPreviewModal.show"
            :title="`Student Output: ${studentPreviewModal.studentName}`"
            :file-name="studentPreviewModal.fileName"
            :file-url="studentPreviewModal.fileUrl"
            :download-url="studentPreviewModal.downloadUrl"
            :reupload-url="studentPreviewModal.reuploadUrl"
            :delete-url="studentPreviewModal.deleteUrl"
            @close="studentPreviewModal.show = false"
            @reuploaded="
                studentPreviewModal.show = false;
                router.reload({ only: ['students', 'summary'] });
            "
            @deleted="
                studentPreviewModal.show = false;
                router.reload({ only: ['students', 'summary'] });
            "
        />

        <!-- Autochecker Modal -->
        <AutocheckerModal
            :show="showAutochecker"
            :section-id="section.id"
            :assessment="assessment"
            :students="students"
            @close="showAutochecker = false"
            @scores-applied="handleAutocheckerApplied"
        />

        <!-- Rubric Manager Modal (Percentage Rate, Answer Key, File Upload, Octo Study) -->
        <RubricManagerModal
            :show="showRubricManager"
            :section-id="section.id"
            :activity-id="assessment.id"
            activity-type="assessment"
            :title="assessment.title"
            :max-points="Number(assessment.max_points) || 100"
            :rubric-type="assessment.rubric_type"
            :rubric-data="assessment.rubric_data"
            :attachment-path="assessment.attachment_path"
            :attachment-name="assessment.attachment_name"
            :attachment-mime="assessment.attachment_mime"
            @close="showRubricManager = false"
            @preview-attachment="showPreview = true"
            @saved="handleRubricSaved"
        />

        <!-- Check All Live Progress Modal -->
        <CheckAllProgressModal
            :show="showCheckAllModal"
            :is-running="checkingAll"
            :total="attachedStudents.length"
            :current-progress="checkProgress"
            :current-item-name="currentEvaluatingName"
            :items="checkProgressItems"
            :activity-title="assessment.title"
            :max-points="assessment.max_points"
            @stop="handleStopCheckAll"
            @close="showCheckAllModal = false"
        />
    </AppLayout>
</template>
