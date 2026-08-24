<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm } from '@inertiajs/vue3';
import { Minus, Plus } from 'lucide-vue-next';
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
    academic_term: TermSummary;
    schedules: StoredSchedule[];
};

const props = defineProps<{
    section?: SectionData;
    currentTerm?: TermSummary;
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

const form = useForm({
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
    const previousType = lastSchedule ? lastSchedule.schedule_type : 'lecture';
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
            <div class="md:col-span-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-border/60 pb-4">
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
