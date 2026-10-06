<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm } from '@inertiajs/vue3';
import { Minus, Plus, Target } from 'lucide-vue-next';
import { ref } from 'vue';

type StoredSchedule = {
    day_of_week: number;
    starts_at: string;
    ends_at: string;
    room?: string | null;
    schedule_type?: 'lecture' | 'lab' | null;
};

type Schedule = {
    days: number[];
    starts_at: string;
    ends_at: string;
    room: string;
    schedule_type: 'lecture' | 'lab';
};

type TermSummary = {
    id?: number;
    name: string;
    school_year: string;
    starts_on?: string;
    ends_on?: string;
    default_starts_at?: string;
    default_ends_at?: string;
};

type SectionData = {
    id: number;
    subject_code: string;
    subject_title: string;
    name: string;
    room: string | null;
    grading_weights?: {
        passing_rates?: {
            quiz?: number;
            activity?: number;
            project?: number;
            exam?: number;
        };
        [key: string]: any;
    } | null;
    academic_term: TermSummary;
    schedules: StoredSchedule[];
};

const props = defineProps<{
    section?: SectionData;
    currentTerm?: TermSummary;
    defaultPassingRates?: {
        quiz?: number;
        activity?: number;
        project?: number;
        exam?: number;
    };
}>();

const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const today = new Date();
const currentSchoolYearStart = today.getMonth() >= 5 ? today.getFullYear() : today.getFullYear() - 1;
const currentSchoolYear = props.currentTerm?.school_year ?? `${currentSchoolYearStart}-${currentSchoolYearStart + 1}`;

const calculateSuggestedEndTime = (startTime: string, type: 'lecture' | 'lab'): string => {
    if (!startTime) return '';
    const [hStr, mStr] = startTime.split(':');
    const hours = parseInt(hStr, 10);
    const minutes = parseInt(mStr, 10);
    if (isNaN(hours) || isNaN(minutes)) return '';

    const addMinutes = type === 'lab' ? 90 : 60;
    const totalMinutes = hours * 60 + minutes + addMinutes;
    const newHours = Math.floor(totalMinutes / 60) % 24;
    const newMinutes = totalMinutes % 60;

    return `${String(newHours).padStart(2, '0')}:${String(newMinutes).padStart(2, '0')}`;
};

const groupedSchedules = (schedules: StoredSchedule[]): Schedule[] => {
    const groups = new Map<string, Schedule>();

    schedules.forEach((schedule) => {
        if (schedule.day_of_week < 1 || schedule.day_of_week > 6) return;

        const startsAt = schedule.starts_at.slice(0, 5);
        const endsAt = schedule.ends_at.slice(0, 5);
        const room = schedule.room ?? props.section?.room ?? '';
        const scheduleType = schedule.schedule_type === 'lab' ? 'lab' : 'lecture';
        const key = `${startsAt}-${endsAt}-${room}-${scheduleType}`;
        const existing = groups.get(key);

        if (existing) {
            existing.days.push(schedule.day_of_week);
        } else {
            groups.set(key, {
                days: [schedule.day_of_week],
                starts_at: startsAt,
                ends_at: endsAt,
                room,
                schedule_type: scheduleType,
            });
        }
    });

    return [...groups.values()].map((schedule) => ({ ...schedule, days: schedule.days.sort() }));
};

const defaultInitialStart = props.currentTerm?.default_starts_at || '08:00';
const defaultInitialEnd = props.currentTerm?.default_ends_at || calculateSuggestedEndTime(defaultInitialStart, 'lecture');

const form = useForm<{
    subject_code: string;
    subject_title: string;
    name: string;
    term: {
        name: string;
        school_year: string;
        starts_on: string;
        ends_on: string;
    };
    schedules: Schedule[];
    passing_rates: {
        quiz: number;
        activity: number;
        project: number;
        exam: number;
    };
}>({
    subject_code: props.section?.subject_code ?? '',
    subject_title: props.section?.subject_title ?? '',
    name: props.section?.name ?? '',
    term: {
        name: props.section?.academic_term?.name ?? props.currentTerm?.name ?? '1st Semester',
        school_year: props.section?.academic_term?.school_year ?? props.currentTerm?.school_year ?? currentSchoolYear,
        starts_on: props.section?.academic_term?.starts_on?.slice(0, 10) ?? props.currentTerm?.starts_on?.slice(0, 10) ?? '',
        ends_on: props.section?.academic_term?.ends_on?.slice(0, 10) ?? props.currentTerm?.ends_on?.slice(0, 10) ?? '',
    },
    schedules: props.section?.schedules?.length
        ? groupedSchedules(props.section.schedules)
        : [
              {
                  days: [1],
                  starts_at: defaultInitialStart,
                  ends_at: defaultInitialEnd,
                  room: props.section?.room ?? '',
                  schedule_type: 'lecture',
              },
          ],
    passing_rates: {
        quiz: Number(props.section?.grading_weights?.passing_rates?.quiz ?? props.defaultPassingRates?.quiz ?? 75),
        activity: Number(props.section?.grading_weights?.passing_rates?.activity ?? props.defaultPassingRates?.activity ?? 75),
        project: Number(props.section?.grading_weights?.passing_rates?.project ?? props.defaultPassingRates?.project ?? 75),
        exam: Number(props.section?.grading_weights?.passing_rates?.exam ?? props.defaultPassingRates?.exam ?? 75),
    },
});

const scheduleError = ref('');

const fieldError = (field: string): string | undefined => {
    if (form.errors[field as keyof typeof form.errors]) {
        return form.errors[field as keyof typeof form.errors] as string;
    }
    const matchingKey = Object.keys(form.errors).find((key) => key === field || key.startsWith(`${field}.`));
    return matchingKey ? (form.errors as Record<string, string>)[matchingKey] : undefined;
};

const addSchedule = () => {
    const lastSchedule = form.schedules[form.schedules.length - 1];
    const previousRoom = lastSchedule ? lastSchedule.room : '';
    const previousType: 'lecture' | 'lab' = lastSchedule?.schedule_type === 'lab' ? 'lab' : 'lecture';
    const defaultStart = props.currentTerm?.default_starts_at || '08:00';
    const defaultEnd = props.currentTerm?.default_ends_at || calculateSuggestedEndTime(defaultStart, previousType);

    form.schedules.push({
        days: [],
        starts_at: defaultStart,
        ends_at: defaultEnd,
        room: previousRoom,
        schedule_type: previousType,
    });
};

const toggleDay = (schedule: Schedule, day: number) => {
    schedule.days = schedule.days.includes(day) ? schedule.days.filter((selectedDay) => selectedDay !== day) : [...schedule.days, day].sort();
    scheduleError.value = '';
};

const onStartTimeChange = (schedule: Schedule) => {
    if (schedule.starts_at) {
        schedule.ends_at = calculateSuggestedEndTime(schedule.starts_at, schedule.schedule_type);
    }
    scheduleError.value = '';
};

const onScheduleTypeChange = (schedule: Schedule, type: 'lecture' | 'lab') => {
    schedule.schedule_type = type;
    if (schedule.starts_at) {
        schedule.ends_at = calculateSuggestedEndTime(schedule.starts_at, type);
    }
    scheduleError.value = '';
};

const submit = () => {
    scheduleError.value = '';

    for (let i = 0; i < form.schedules.length; i++) {
        const schedule = form.schedules[i];
        if (schedule.days.length === 0) {
            scheduleError.value = `Select at least one meeting day for time entry #${i + 1}.`;
            return;
        }
        if (!schedule.starts_at || !schedule.ends_at) {
            scheduleError.value = `Start time and end time are required for time entry #${i + 1}.`;
            return;
        }
        if (schedule.ends_at <= schedule.starts_at) {
            scheduleError.value = `End time (${schedule.ends_at}) must be later than start time (${schedule.starts_at}) for time entry #${i + 1}.`;
            return;
        }
    }

    form.transform((data) => ({
        ...data,
        room: data.schedules[0]?.room ?? '',
        schedules: data.schedules.flatMap((schedule) =>
            schedule.days.map((day) => ({
                day_of_week: day,
                starts_at: schedule.starts_at,
                ends_at: schedule.ends_at,
                room: schedule.room || null,
                schedule_type: schedule.schedule_type,
            })),
        ),
    }));

    if (props.section) form.put(`/sections/${props.section.id}`);
    else form.post('/sections');
};
</script>

<template>
    <form class="grid gap-8" @submit.prevent="submit">
        <!-- 01 Identity Panel -->
        <section class="paper-card grid gap-5 p-6 md:grid-cols-2 md:p-8">
            <div class="flex flex-col gap-3 border-b border-border/60 pb-4 sm:flex-row sm:items-center sm:justify-between md:col-span-2">
                <div>
                    <span class="eyebrow">01 / Course Identity</span>
                    <h2 class="mt-1 text-2xl font-medium tracking-tight">Class & subject details</h2>
                </div>

                <!-- Universal Semester Indicator -->
                <div class="flex items-center gap-2 rounded-2xl border border-primary/20 bg-primary/5 px-3.5 py-2 text-xs">
                    <div>
                        <span class="font-bold text-foreground">{{ form.term.name }} · SY {{ form.term.school_year }}</span>
                        <span v-if="form.term.starts_on && form.term.ends_on" class="block text-[11px] text-muted-foreground">
                            {{ form.term.starts_on }} to {{ form.term.ends_on }}
                        </span>
                    </div>
                    <Link
                        href="/settings/academic-term"
                        class="ml-2 rounded-lg border border-border/80 bg-background px-2.5 py-1 text-[11px] font-semibold text-primary transition-colors hover:bg-secondary"
                    >
                        Change
                    </Link>
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="code" class="text-xs font-medium">Subject code</Label>
                <Input id="code" v-model="form.subject_code" class="h-10 rounded-xl text-sm font-medium" placeholder="e.g. IT 101" />
                <InputError class="mt-1 text-xs" :message="form.errors.subject_code" />
            </div>
            <div class="grid gap-2">
                <Label for="name" class="text-xs font-medium">Section name</Label>
                <Input id="name" v-model="form.name" class="h-10 rounded-xl text-sm font-medium" placeholder="e.g. BSIT 1-A" />
                <InputError class="mt-1 text-xs" :message="form.errors.name" />
            </div>
            <div class="grid gap-2 md:col-span-2">
                <Label for="title" class="text-xs font-medium">Subject title</Label>
                <Input
                    id="title"
                    v-model="form.subject_title"
                    class="h-10 rounded-xl text-sm font-medium"
                    placeholder="e.g. Introduction to Computing"
                />
                <InputError class="mt-1 text-xs" :message="form.errors.subject_title" />
            </div>
        </section>

        <!-- 02 Weekly Schedule Panel -->
        <section class="paper-card grid gap-5 p-6 md:p-8">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <span class="eyebrow">02 / Weekly Schedule</span>
                    <h2 class="mt-1 text-2xl font-medium tracking-tight">Meeting rhythm</h2>
                </div>
                <Button type="button" variant="outline" size="sm" class="rounded-xl text-xs font-medium" @click="addSchedule">
                    <Plus class="mr-1 size-3.5" /> Add time entry
                </Button>
            </div>

            <div v-for="(schedule, index) in form.schedules" :key="index" class="grid gap-4 rounded-xl border border-border/80 bg-secondary/40 p-4">
                <!-- Class Type Radio Buttons (Lecture / Lab) and Room -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <!-- Room Input directly above Meeting days in each schedule entry -->
                    <div class="grid w-full gap-1.5 sm:max-w-xs">
                        <Label :for="`schedule-room-${index}`" class="text-xs font-medium text-foreground">
                            Room <span class="font-normal text-muted-foreground">(optional)</span>
                        </Label>
                        <Input
                            :id="`schedule-room-${index}`"
                            v-model="schedule.room"
                            class="h-10 rounded-xl bg-background text-sm font-medium"
                            placeholder="e.g. Lab 3 / Room 204"
                        />
                    </div>

                    <!-- Type Radio Selector -->
                    <div class="flex flex-col gap-1.5">
                        <span class="text-xs font-medium text-foreground">Class format</span>
                        <div class="flex items-center gap-4">
                            <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-foreground">
                                <input
                                    type="radio"
                                    :name="`schedule-type-${index}`"
                                    value="lecture"
                                    :checked="schedule.schedule_type === 'lecture'"
                                    class="size-4 text-primary focus:ring-primary"
                                    @change="onScheduleTypeChange(schedule, 'lecture')"
                                />
                                <span>Lecture</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-foreground">
                                <input
                                    type="radio"
                                    :name="`schedule-type-${index}`"
                                    value="lab"
                                    :checked="schedule.schedule_type === 'lab'"
                                    class="size-4 text-primary focus:ring-primary"
                                    @change="onScheduleTypeChange(schedule, 'lab')"
                                />
                                <span>Lab</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <Label :id="`schedule-days-${index}`" class="text-xs font-medium text-foreground">Meeting days</Label>
                        <span class="text-[11px] text-muted-foreground">Select one or more meeting days</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6" :aria-labelledby="`schedule-days-${index}`">
                        <label
                            v-for="(day, dayIndex) in days"
                            :key="day"
                            class="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-xl border px-3 text-xs font-medium transition-all"
                            :class="
                                schedule.days.includes(dayIndex + 1)
                                    ? 'shadow-xs border-primary bg-primary/10 text-primary'
                                    : 'border-border bg-card text-muted-foreground hover:border-primary/50'
                            "
                        >
                            <input
                                type="checkbox"
                                class="size-4 rounded border-input bg-background text-primary focus:ring-primary"
                                :checked="schedule.days.includes(dayIndex + 1)"
                                @change="toggleDay(schedule, dayIndex + 1)"
                            />
                            <span>{{ day }}</span>
                        </label>
                    </div>
                </div>

                <div>
                    <div class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_auto]">
                        <div class="grid gap-1.5">
                            <Label :for="`schedule-start-${index}`" class="text-xs font-medium">Start time</Label>
                            <Input
                                :id="`schedule-start-${index}`"
                                v-model="schedule.starts_at"
                                class="h-10 rounded-xl text-sm font-medium"
                                type="time"
                                @input="onStartTimeChange(schedule)"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label :for="`schedule-end-${index}`" class="text-xs font-medium"
                                >End time <span class="text-[11px] text-muted-foreground">(suggested)</span></Label
                            >
                            <Input
                                :id="`schedule-end-${index}`"
                                v-model="schedule.ends_at"
                                class="h-10 rounded-xl text-sm font-medium"
                                type="time"
                                @input="scheduleError = ''"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="rounded-xl text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            :disabled="form.schedules.length === 1"
                            @click="form.schedules.splice(index, 1)"
                        >
                            <Minus class="size-4" />
                            <span class="sr-only">Remove schedule entry</span>
                        </Button>
                    </div>

                    <!-- Inline warning when end time <= start time -->
                    <p v-if="schedule.starts_at && schedule.ends_at && schedule.ends_at <= schedule.starts_at" class="mt-2 text-xs text-destructive">
                        End time must be later than start time.
                    </p>
                </div>
            </div>
            <InputError class="mt-1 text-xs" :message="scheduleError || fieldError('schedules')" />
        </section>

        <!-- 03 Passing Benchmarks Panel -->
        <section class="paper-card grid gap-5 p-6 md:p-8">
            <div class="flex flex-col gap-2 border-b border-border/60 pb-4">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="eyebrow">03 / Passing Benchmarks</span>
                        <h2 class="mt-1 text-2xl font-medium tracking-tight">Passing percentage rates</h2>
                    </div>
                    <div class="hidden sm:flex items-center gap-1.5 text-xs text-muted-foreground bg-muted/50 rounded-xl px-3 py-1.5 border border-border/50">
                        <Target class="size-3.5 text-primary" />
                        <span>Standard benchmark: 75%</span>
                    </div>
                </div>
                <p class="text-xs sm:text-sm text-muted-foreground">
                    Set the minimum percentage score required for a student to pass each category. Submissions below this rate are flagged as deficient in student records, gradebooks, and performance analytics.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Quizzes -->
                <div class="relative flex flex-col justify-between rounded-2xl border border-border/70 bg-card/60 p-4 transition-all hover:border-primary/40">
                    <div>
                        <div class="flex items-center justify-between">
                            <Label for="pass-quiz" class="text-xs font-semibold text-foreground">Quizzes</Label>
                            <span class="rounded-md bg-primary/10 px-2 py-0.5 text-[11px] font-bold text-primary font-mono">
                                {{ form.passing_rates.quiz }}%
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-muted-foreground">Formative quizzes & unit tests</p>
                    </div>

                    <div class="mt-3">
                        <div class="relative">
                            <Input
                                id="pass-quiz"
                                v-model.number="form.passing_rates.quiz"
                                type="number"
                                min="0"
                                max="100"
                                class="h-10 rounded-xl pr-8 text-sm font-semibold font-mono"
                            />
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-muted-foreground">%</span>
                        </div>
                        <InputError class="mt-1 text-xs" :message="fieldError('passing_rates.quiz')" />

                        <!-- Preset Buttons -->
                        <div class="mt-2.5 flex items-center gap-1">
                            <button
                                v-for="preset in [60, 70, 75, 80]"
                                :key="preset"
                                type="button"
                                class="rounded-lg px-2 py-0.5 text-[10px] font-medium transition-colors"
                                :class="form.passing_rates.quiz === preset ? 'bg-primary text-primary-foreground font-bold' : 'bg-muted text-muted-foreground hover:bg-secondary hover:text-foreground'"
                                @click="form.passing_rates.quiz = preset"
                            >
                                {{ preset }}%
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Activities -->
                <div class="relative flex flex-col justify-between rounded-2xl border border-border/70 bg-card/60 p-4 transition-all hover:border-primary/40">
                    <div>
                        <div class="flex items-center justify-between">
                            <Label for="pass-activity" class="text-xs font-semibold text-foreground">Activities</Label>
                            <span class="rounded-md bg-primary/10 px-2 py-0.5 text-[11px] font-bold text-primary font-mono">
                                {{ form.passing_rates.activity }}%
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-muted-foreground">Classwork & group activities</p>
                    </div>

                    <div class="mt-3">
                        <div class="relative">
                            <Input
                                id="pass-activity"
                                v-model.number="form.passing_rates.activity"
                                type="number"
                                min="0"
                                max="100"
                                class="h-10 rounded-xl pr-8 text-sm font-semibold font-mono"
                            />
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-muted-foreground">%</span>
                        </div>
                        <InputError class="mt-1 text-xs" :message="fieldError('passing_rates.activity')" />

                        <!-- Preset Buttons -->
                        <div class="mt-2.5 flex items-center gap-1">
                            <button
                                v-for="preset in [60, 70, 75, 80]"
                                :key="preset"
                                type="button"
                                class="rounded-lg px-2 py-0.5 text-[10px] font-medium transition-colors"
                                :class="form.passing_rates.activity === preset ? 'bg-primary text-primary-foreground font-bold' : 'bg-muted text-muted-foreground hover:bg-secondary hover:text-foreground'"
                                @click="form.passing_rates.activity = preset"
                            >
                                {{ preset }}%
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Projects -->
                <div class="relative flex flex-col justify-between rounded-2xl border border-border/70 bg-card/60 p-4 transition-all hover:border-primary/40">
                    <div>
                        <div class="flex items-center justify-between">
                            <Label for="pass-project" class="text-xs font-semibold text-foreground">Projects</Label>
                            <span class="rounded-md bg-primary/10 px-2 py-0.5 text-[11px] font-bold text-primary font-mono">
                                {{ form.passing_rates.project }}%
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-muted-foreground">Major outputs & reports</p>
                    </div>

                    <div class="mt-3">
                        <div class="relative">
                            <Input
                                id="pass-project"
                                v-model.number="form.passing_rates.project"
                                type="number"
                                min="0"
                                max="100"
                                class="h-10 rounded-xl pr-8 text-sm font-semibold font-mono"
                            />
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-muted-foreground">%</span>
                        </div>
                        <InputError class="mt-1 text-xs" :message="fieldError('passing_rates.project')" />

                        <!-- Preset Buttons -->
                        <div class="mt-2.5 flex items-center gap-1">
                            <button
                                v-for="preset in [60, 70, 75, 80]"
                                :key="preset"
                                type="button"
                                class="rounded-lg px-2 py-0.5 text-[10px] font-medium transition-colors"
                                :class="form.passing_rates.project === preset ? 'bg-primary text-primary-foreground font-bold' : 'bg-muted text-muted-foreground hover:bg-secondary hover:text-foreground'"
                                @click="form.passing_rates.project = preset"
                            >
                                {{ preset }}%
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Major Exams -->
                <div class="relative flex flex-col justify-between rounded-2xl border border-border/70 bg-card/60 p-4 transition-all hover:border-primary/40">
                    <div>
                        <div class="flex items-center justify-between">
                            <Label for="pass-exam" class="text-xs font-semibold text-foreground">Exams</Label>
                            <span class="rounded-md bg-primary/10 px-2 py-0.5 text-[11px] font-bold text-primary font-mono">
                                {{ form.passing_rates.exam }}%
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-muted-foreground">Midterm & final term exams</p>
                    </div>

                    <div class="mt-3">
                        <div class="relative">
                            <Input
                                id="pass-exam"
                                v-model.number="form.passing_rates.exam"
                                type="number"
                                min="0"
                                max="100"
                                class="h-10 rounded-xl pr-8 text-sm font-semibold font-mono"
                            />
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-muted-foreground">%</span>
                        </div>
                        <InputError class="mt-1 text-xs" :message="fieldError('passing_rates.exam')" />

                        <!-- Preset Buttons -->
                        <div class="mt-2.5 flex items-center gap-1">
                            <button
                                v-for="preset in [60, 70, 75, 80]"
                                :key="preset"
                                type="button"
                                class="rounded-lg px-2 py-0.5 text-[10px] font-medium transition-colors"
                                :class="form.passing_rates.exam === preset ? 'bg-primary text-primary-foreground font-bold' : 'bg-muted text-muted-foreground hover:bg-secondary hover:text-foreground'"
                                @click="form.passing_rates.exam = preset"
                            >
                                {{ preset }}%
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="flex items-center justify-end gap-3 border-t border-border/80 pt-6">
            <Button as-child variant="ghost" class="rounded-xl text-sm font-medium">
                <Link href="/sections" prefetch="hover">Cancel</Link>
            </Button>
            <Button class="ink-button !rounded-xl font-medium" :disabled="form.processing">
                {{ form.processing ? 'Saving...' : section ? 'Save changes' : 'Create section' }}
            </Button>
        </div>
    </form>
</template>
