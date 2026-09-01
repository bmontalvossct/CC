<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    Calendar,
    Check,
    Clock,
    Info,
    LoaderCircle,
    Mic,
    User,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

export type StudentRow = {
    id: number;
    student_number: string;
    full_name: string;
    attendance: {
        total_sessions: number;
        present_count: number;
        late_count: number;
        absent_count: number;
        present_dates?: string[];
        late_dates?: string[];
        percentage: number | null;
    };
    recitation: {
        count: number;
        total_score?: number;
        avg_score: number | null;
        percentage: number | null;
        bonus_points?: number;
    };
};

const props = withDefaults(
    defineProps<{
        open: boolean;
        section: { id: number; name: string; subject_code?: string; subject_title: string };
        students: StudentRow[];
        preselectedStudent?: StudentRow | null;
        gradingWeights: Record<string, number>;
    }>(),
    {
        preselectedStudent: null,
    },
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved'): void;
}>();

const mode = ref<'single' | 'all'>('single');
const selectedStudentId = ref<number | null>(null);
const pointsInput = ref<number>(0);
const includeLate = ref<boolean>(false);
const showDatesList = ref<boolean>(false);
const saveError = ref<string | null>(null);

const bonusCap = computed(() => props.gradingWeights.recitation ?? 5);

// Keep selected student synced
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            saveError.value = null;
            if (props.preselectedStudent) {
                mode.value = 'single';
                selectedStudentId.value = props.preselectedStudent.id;
                pointsInput.value = props.preselectedStudent.recitation.total_score ?? 0;
            } else if (props.students.length > 0 && !selectedStudentId.value) {
                selectedStudentId.value = props.students[0].id;
                pointsInput.value = props.students[0].recitation.total_score ?? 0;
            }
        }
    },
    { immediate: true },
);

watch(
    () => props.preselectedStudent,
    (student) => {
        if (student) {
            mode.value = 'single';
            selectedStudentId.value = student.id;
            pointsInput.value = student.recitation.total_score ?? 0;
        }
    },
);

const selectedStudent = computed(() => {
    return props.students.find((s) => s.id === selectedStudentId.value) || null;
});

const onStudentChange = () => {
    saveError.value = null;
    if (selectedStudent.value) {
        pointsInput.value = selectedStudent.value.recitation.total_score ?? 0;
    }
};

// Eligible dates for current selected student
const eligibleDates = computed<string[]>(() => {
    if (!selectedStudent.value) return [];
    const present = selectedStudent.value.attendance.present_dates || [];
    const late = includeLate.value ? selectedStudent.value.attendance.late_dates || [] : [];
    return Array.from(new Set([...present, ...late])).sort();
});

const daysCount = computed(() => eligibleDates.value.length);
const maxOralPoints = computed(() => daysCount.value * 10);

// Validation
const surpassesMax = computed(() => {
    if (mode.value !== 'single') return false;
    return pointsInput.value > maxOralPoints.value;
});

const hasZeroDays = computed(() => {
    if (mode.value !== 'single') return false;
    return daysCount.value === 0;
});

// Real-time calculations
const perDayScore = computed(() => {
    if (daysCount.value === 0 || pointsInput.value <= 0) return 0;
    return Math.round((pointsInput.value / daysCount.value) * 100) / 100;
});

const projectedAvg = computed(() => {
    if (daysCount.value === 0 || pointsInput.value <= 0) return null;
    return Math.min(10, perDayScore.value);
});

const projectedBonus = computed(() => {
    if (projectedAvg.value === null || bonusCap.value <= 0) return 0;
    return Math.round((projectedAvg.value / 10) * bonusCap.value * 100) / 100;
});

// Quick point setter presets
const setPreset = (percentage: number) => {
    const raw = (maxOralPoints.value * percentage) / 100;
    pointsInput.value = Math.round(raw * 100) / 100;
};

// Form submission
const form = useForm({
    student_id: null as number | null,
    apply_to_all: false,
    points: 0,
    include_late: false,
});

const submitOverride = () => {
    saveError.value = null;

    if (mode.value === 'single') {
        if (!selectedStudent.value) {
            saveError.value = 'Please select a student.';
            return;
        }
        if (hasZeroDays.value) {
            saveError.value = 'This student has 0 eligible attendance days. Oral points can only be allocated to days present.';
            return;
        }
        if (surpassesMax.value) {
            saveError.value = `Points cannot surpass the maximum oral points (${maxOralPoints.value} pts across ${daysCount.value} eligible days at max 10 pts/day).`;
            return;
        }
    }

    if (pointsInput.value < 0) {
        saveError.value = 'Points cannot be negative.';
        return;
    }

    form.student_id = mode.value === 'single' ? selectedStudentId.value : null;
    form.apply_to_all = mode.value === 'all';
    form.points = pointsInput.value;
    form.include_late = includeLate.value;

    form.post(`/sections/${props.section.id}/reports/gradebook/override-oral`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            emit('close');
        },
        onError: (errs) => {
            saveError.value = Object.values(errs).join(' ');
        },
    });
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/60 p-4 sm:p-6 backdrop-blur-xs duration-200 animate-in fade-in print:hidden"
    >
        <div
            class="paper-card relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden border border-border/80 bg-card p-6 sm:p-8 shadow-xl duration-200 animate-in zoom-in-95"
            role="dialog"
            aria-modal="true"
            aria-label="Manual Oral Points Override"
        >
            <!-- Modal Header -->
            <div class="flex items-start justify-between gap-4 border-b border-border/70 pb-4">
                <div class="flex items-center gap-3">
                    <div class="grid size-9 place-items-center rounded-lg border border-border bg-secondary/50 text-muted-foreground">
                        <Mic class="size-4" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold tracking-tight text-foreground">Manual Oral Points Override</h2>
                            <span class="rounded border border-border/70 bg-secondary/60 px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground">
                                Max 10/day
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Allocates points equally across days present. Points cannot surpass the maximum oral points cap.
                        </p>
                    </div>
                </div>

                <Button type="button" variant="ghost" size="icon" class="size-8 rounded-lg text-muted-foreground hover:text-foreground" title="Close" @click="emit('close')">
                    <X class="size-4" />
                </Button>
            </div>

            <!-- Modal Content (Scrollable with generous breathing room) -->
            <div class="flex-1 space-y-6 overflow-y-auto px-1 py-5">
                <!-- Scope Selection: Single Student vs All Students -->
                <div class="grid grid-cols-2 gap-1.5 rounded-lg border border-border/70 bg-secondary/30 p-1">
                    <button
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-md py-1.5 text-xs font-medium transition-all"
                        :class="
                            mode === 'single'
                                ? 'border border-border/80 bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="mode = 'single'"
                    >
                        <User class="size-3.5" />
                        <span>Specific Student</span>
                    </button>
                    <button
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-md py-1.5 text-xs font-medium transition-all"
                        :class="
                            mode === 'all'
                                ? 'border border-border/80 bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="mode = 'all'"
                    >
                        <Users class="size-3.5" />
                        <span>All Students in Section</span>
                    </button>
                </div>

                <!-- Single Student Mode: Picker & Status -->
                <div v-if="mode === 'single'" class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Select Student
                        </label>
                        <select
                            v-model="selectedStudentId"
                            class="w-full rounded-lg border border-input bg-background px-3.5 py-2 text-sm font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-foreground"
                            @change="onStudentChange"
                        >
                            <option v-for="student in students" :key="student.id" :value="student.id">
                                {{ student.full_name }} ({{ student.student_number }}) — {{ student.attendance.present_count }} days present
                            </option>
                        </select>
                    </div>

                    <!-- Selected Student Attendance & Oral Status Card (Clean Neutral) -->
                    <div v-if="selectedStudent" class="rounded-xl border border-border/70 bg-secondary/20 p-4 sm:p-5 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="font-mono text-[11px] text-muted-foreground">{{ selectedStudent.student_number }}</span>
                                <h3 class="text-sm font-semibold text-foreground">{{ selectedStudent.full_name }}</h3>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-md border border-border/70 bg-card px-2.5 py-1 text-xs font-medium text-muted-foreground">
                                    {{ daysCount }} {{ daysCount === 1 ? 'day' : 'days' }} eligible
                                </span>
                                <span class="rounded-md border border-border/70 bg-card px-2.5 py-1 font-mono text-xs font-medium text-muted-foreground">
                                    Max: {{ maxOralPoints }} pts
                                </span>
                            </div>
                        </div>

                        <!-- Mini stats grid (Clean Neutral) -->
                        <div class="grid grid-cols-3 gap-2 border-t border-border/50 pt-3 text-center text-xs">
                            <div class="rounded-lg bg-card/60 p-2 border border-border/40">
                                <span class="block text-[11px] text-muted-foreground">Days Present</span>
                                <span class="mt-0.5 block font-mono text-sm font-semibold text-foreground">
                                    {{ selectedStudent.attendance.present_count }}
                                </span>
                            </div>
                            <div class="rounded-lg bg-card/60 p-2 border border-border/40">
                                <span class="block text-[11px] text-muted-foreground">Days Late</span>
                                <span class="mt-0.5 block font-mono text-sm font-semibold text-foreground">
                                    {{ selectedStudent.attendance.late_count }}
                                </span>
                            </div>
                            <div class="rounded-lg bg-card/60 p-2 border border-border/40">
                                <span class="block text-[11px] text-muted-foreground">Current Total</span>
                                <span class="mt-0.5 block font-mono text-sm font-semibold text-foreground">
                                    {{ selectedStudent.recitation.total_score ?? 0 }} pts
                                </span>
                            </div>
                        </div>

                        <!-- Toggle Include Late -->
                        <div class="flex items-center justify-between border-t border-border/50 pt-2.5 text-xs">
                            <label class="flex cursor-pointer items-center gap-2 text-muted-foreground hover:text-foreground">
                                <input
                                    v-model="includeLate"
                                    type="checkbox"
                                    class="size-3.5 rounded border-input text-foreground focus:ring-foreground"
                                />
                                <span>Include days marked Late ({{ selectedStudent.attendance.late_count }} days)</span>
                            </label>

                            <button
                                v-if="daysCount > 0"
                                type="button"
                                class="text-xs text-muted-foreground hover:text-foreground hover:underline"
                                @click="showDatesList = !showDatesList"
                            >
                                {{ showDatesList ? 'Hide dates' : 'View eligible dates' }}
                            </button>
                        </div>

                        <!-- Expandable Eligible Dates List -->
                        <div v-if="showDatesList && daysCount > 0" class="max-h-24 overflow-y-auto rounded-lg border border-border/60 bg-card p-2.5">
                            <span class="mb-1 block text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Eligible Sessions:</span>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="d in eligibleDates"
                                    :key="d"
                                    class="rounded border border-border/60 bg-secondary/50 px-2 py-0.5 font-mono text-[10px] text-foreground"
                                >
                                    {{ d }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- All Students Mode Card (Clean Neutral) -->
                <div v-else class="rounded-xl border border-border/70 bg-secondary/20 p-4 sm:p-5 space-y-3">
                    <div class="flex items-start gap-2.5">
                        <Info class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div>
                            <h4 class="text-xs font-semibold text-foreground">Section-Wide Oral Allocation</h4>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                The specified points will be allocated to all students in this section. Each student will receive an equal daily rate
                                across their present days. Any student whose present days would be exceeded will be automatically capped at their
                                personal max (present days × 10).
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center border-t border-border/50 pt-2.5">
                        <label class="flex cursor-pointer items-center gap-2 text-xs text-muted-foreground hover:text-foreground">
                            <input
                                v-model="includeLate"
                                type="checkbox"
                                class="size-3.5 rounded border-input text-foreground focus:ring-foreground"
                            />
                            <span>Include days marked Late as eligible days</span>
                        </label>
                    </div>
                </div>

                <!-- Points Input Field -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Total Oral Points to Allocate
                        </label>
                        <span v-if="mode === 'single'" class="font-mono text-xs text-muted-foreground">
                            Max: {{ maxOralPoints }} pts
                        </span>
                    </div>

                    <div class="relative">
                        <input
                            v-model.number="pointsInput"
                            type="number"
                            min="0"
                            :max="mode === 'single' ? maxOralPoints : 1000"
                            step="any"
                            placeholder="e.g. 100"
                            class="w-full rounded-lg border border-input bg-background px-3.5 py-2.5 font-mono text-sm font-semibold text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-foreground"
                            :class="surpassesMax ? 'border-rose-500 text-rose-600 focus-visible:ring-rose-500' : ''"
                        />
                        <span class="absolute right-3.5 top-1/2 -translate-y-1/2 font-mono text-xs text-muted-foreground">
                            pts
                        </span>
                    </div>

                    <!-- Quick Preset Buttons (Single student, clean neutral) -->
                    <div v-if="mode === 'single' && daysCount > 0" class="flex flex-wrap items-center gap-1.5 pt-1">
                        <span class="text-[11px] text-muted-foreground mr-1">Quick Set:</span>
                        <button
                            type="button"
                            class="rounded-md border border-border/70 bg-secondary/40 px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="setPreset(100)"
                        >
                            Max ({{ maxOralPoints }} pts)
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-border/70 bg-secondary/40 px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="setPreset(75)"
                        >
                            75% ({{ (maxOralPoints * 0.75).toFixed(1) }} pts)
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-border/70 bg-secondary/40 px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                            @click="setPreset(50)"
                        >
                            50% ({{ (maxOralPoints * 0.5).toFixed(1) }} pts)
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-border/70 bg-secondary/40 px-2.5 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            @click="setPreset(0)"
                        >
                            Clear (0 pts)
                        </button>
                    </div>
                </div>

                <!-- Live Allocation Preview Card (Clean Neutral) -->
                <div v-if="mode === 'single'">
                    <!-- Warning: 0 days present -->
                    <div v-if="hasZeroDays" class="flex items-start gap-2.5 rounded-xl border border-rose-500/30 bg-rose-500/5 p-4 text-rose-700 dark:text-rose-400">
                        <AlertCircle class="mt-0.5 size-4 shrink-0" />
                        <div class="text-xs">
                            <p class="font-semibold">Zero Attendance Days Recorded</p>
                            <p class="mt-0.5 text-muted-foreground">
                                This student has no eligible attendance sessions. Oral participation points can only be allocated to days present in
                                class.
                            </p>
                        </div>
                    </div>

                    <!-- Warning: Surpasses Max -->
                    <div v-else-if="surpassesMax" class="flex items-start gap-2.5 rounded-xl border border-rose-500/30 bg-rose-500/5 p-4 text-rose-700 dark:text-rose-400">
                        <AlertCircle class="mt-0.5 size-4 shrink-0" />
                        <div class="text-xs">
                            <p class="font-semibold">Surpasses Maximum Oral Points</p>
                            <p class="mt-0.5">
                                {{ pointsInput }} points surpasses the maximum allowed ({{ maxOralPoints }} pts) for {{ daysCount }} days present (max
                                10 pts/day). Please enter {{ maxOralPoints }} pts or less.
                            </p>
                        </div>
                    </div>

                    <!-- Valid Allocation Preview (Clean Neutral, no loud colors) -->
                    <div v-else class="rounded-xl border border-border/70 bg-secondary/20 p-4 sm:p-5 space-y-2.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Equal Daily Rate:</span>
                            <span class="font-mono text-sm font-semibold text-foreground">
                                {{ perDayScore }} pts / day
                            </span>
                        </div>
                        <div class="flex items-center justify-between border-t border-border/50 pt-2 text-muted-foreground">
                            <span>Total across {{ daysCount }} days:</span>
                            <span class="font-mono text-foreground font-medium">
                                {{ pointsInput }} / {{ maxOralPoints }} max pts
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span>Projected Recitation Average:</span>
                            <span class="font-mono text-foreground font-medium">
                                {{ projectedAvg !== null ? `${projectedAvg} / 10` : '—' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span>Projected Activity Bonus:</span>
                            <span class="font-mono text-foreground font-medium">
                                +{{ projectedBonus }} pts to Activities
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Error Banner -->
                <div
                    v-if="saveError"
                    class="flex items-start gap-2.5 rounded-xl border border-rose-500/40 bg-rose-500/10 p-3.5 text-xs text-rose-700 dark:text-rose-400"
                >
                    <AlertCircle class="mt-0.5 size-4 shrink-0" />
                    <p class="font-semibold">{{ saveError }}</p>
                </div>
            </div>

            <!-- Modal Footer Actions -->
            <div class="flex items-center justify-end gap-2.5 border-t border-border/70 pt-4">
                <Button type="button" variant="outline" class="rounded-lg h-9 px-4 text-xs font-medium" @click="emit('close')">
                    Cancel
                </Button>

                <Button
                    type="button"
                    :disabled="form.processing || (mode === 'single' && (hasZeroDays || surpassesMax))"
                    class="ink-button !h-9 !rounded-lg !px-4 text-xs font-medium"
                    @click="submitOverride"
                >
                    <LoaderCircle v-if="form.processing" class="mr-1.5 size-3.5 animate-spin" />
                    <Check v-else class="mr-1.5 size-3.5" />
                    <span>{{ form.processing ? 'Allocating…' : 'Apply Oral Override' }}</span>
                </Button>
            </div>
        </div>
    </div>
</template>
