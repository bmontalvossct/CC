<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm } from '@inertiajs/vue3';
import { Calendar, Clock, Minus, Plus, School } from 'lucide-vue-next';
import { ref } from 'vue';

type StoredSchedule = { day_of_week: number; starts_at: string; ends_at: string };
type Schedule = { days: number[]; starts_at: string; ends_at: string };
type SectionData = {
    id: number;
    subject_code: string;
    subject_title: string;
    name: string;
    room: string | null;
    academic_term: { name: string; school_year: string; starts_on: string; ends_on: string };
    schedules: StoredSchedule[];
};

const props = defineProps<{ section?: SectionData }>();
const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
const today = new Date();
const currentSchoolYearStart = today.getMonth() >= 5 ? today.getFullYear() : today.getFullYear() - 1;
const currentSchoolYear = `${currentSchoolYearStart}-${currentSchoolYearStart + 1}`;
const schoolYearOptions = Array.from(
    new Set([
        props.section?.academic_term.school_year,
        ...Array.from({ length: 5 }, (_, index) => {
            const startYear = currentSchoolYearStart - 2 + index;
            return `${startYear}-${startYear + 1}`;
        }),
    ]),
).filter((schoolYear): schoolYear is string => Boolean(schoolYear));

const groupedSchedules = (schedules: StoredSchedule[]): Schedule[] => {
    const groups = new Map<string, Schedule>();

    schedules.forEach((schedule) => {
        if (schedule.day_of_week < 1 || schedule.day_of_week > 5) return;

        const startsAt = schedule.starts_at.slice(0, 5);
        const endsAt = schedule.ends_at.slice(0, 5);
        const key = `${startsAt}-${endsAt}`;
        const existing = groups.get(key);

        if (existing) existing.days.push(schedule.day_of_week);
        else groups.set(key, { days: [schedule.day_of_week], starts_at: startsAt, ends_at: endsAt });
    });

    return [...groups.values()].map((schedule) => ({ ...schedule, days: schedule.days.sort() }));
};

const form = useForm({
    subject_code: props.section?.subject_code ?? '',
    subject_title: props.section?.subject_title ?? '',
    name: props.section?.name ?? '',
    room: props.section?.room ?? '',
    term: {
        name: props.section?.academic_term.name ?? '1st Semester',
        school_year: props.section?.academic_term.school_year ?? currentSchoolYear,
        starts_on: props.section?.academic_term.starts_on?.slice(0, 10) ?? '',
        ends_on: props.section?.academic_term.ends_on?.slice(0, 10) ?? '',
    },
    schedules: props.section?.schedules?.length ? groupedSchedules(props.section.schedules) : [{ days: [1], starts_at: '08:00', ends_at: '09:00' }],
});

const fieldError = (key: string) => (form.errors as Record<string, string | undefined>)[key];
const scheduleError = ref('');
const selectClass =
    'h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary text-foreground';

const addSchedule = () => {
    form.schedules.push({ days: [], starts_at: '08:00', ends_at: '09:00' });
};

const toggleDay = (schedule: Schedule, day: number) => {
    schedule.days = schedule.days.includes(day) ? schedule.days.filter((selectedDay) => selectedDay !== day) : [...schedule.days, day].sort();
    scheduleError.value = '';
};

const submit = () => {
    if (form.schedules.some((schedule) => schedule.days.length === 0)) {
        scheduleError.value = 'Select at least one weekday for every schedule entry.';
        return;
    }

    form.transform((data) => ({
        ...data,
        schedules: data.schedules.flatMap((schedule) =>
            schedule.days.map((day) => ({
                day_of_week: day,
                starts_at: schedule.starts_at,
                ends_at: schedule.ends_at,
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
        <section class="paper-card p-6 md:p-8 grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <span class="eyebrow">01 / Course Identity</span>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Class & subject details</h2>
            </div>
            <div class="grid gap-2">
                <Label for="code" class="text-xs font-semibold">Subject code</Label>
                <Input id="code" v-model="form.subject_code" class="rounded-xl h-10 text-sm" placeholder="e.g. IT 101" />
                <InputError class="text-xs mt-1" :message="form.errors.subject_code" />
            </div>
            <div class="grid gap-2">
                <Label for="name" class="text-xs font-semibold">Section name</Label>
                <Input id="name" v-model="form.name" class="rounded-xl h-10 text-sm" placeholder="e.g. BSIT 1-A" />
                <InputError class="text-xs mt-1" :message="form.errors.name" />
            </div>
            <div class="grid gap-2 md:col-span-2">
                <Label for="title" class="text-xs font-semibold">Subject title</Label>
                <Input id="title" v-model="form.subject_title" class="rounded-xl h-10 text-sm" placeholder="e.g. Introduction to Computing" />
                <InputError class="text-xs mt-1" :message="form.errors.subject_title" />
            </div>
            <div class="grid gap-2">
                <Label for="room" class="text-xs font-semibold">Room <span class="text-muted-foreground font-normal">(optional)</span></Label>
                <Input id="room" v-model="form.room" class="rounded-xl h-10 text-sm" placeholder="e.g. Lab 3 / Room 204" />
            </div>
        </section>

        <!-- 02 Academic Term Panel -->
        <section class="paper-card p-6 md:p-8 grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <span class="eyebrow">02 / Academic Term</span>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Term & schedule period</h2>
            </div>
            <div class="grid gap-2">
                <Label class="text-xs font-semibold">Term name</Label>
                <Input v-model="form.term.name" class="rounded-xl h-10 text-sm" placeholder="1st Semester" />
                <InputError class="text-xs mt-1" :message="fieldError('term.name')" />
            </div>
            <div class="grid gap-2">
                <Label for="school-year" class="text-xs font-semibold">School year</Label>
                <select id="school-year" v-model="form.term.school_year" :class="selectClass">
                    <option v-for="schoolYear in schoolYearOptions" :key="schoolYear" :value="schoolYear">
                        SY {{ schoolYear }}
                    </option>
                </select>
                <InputError class="text-xs mt-1" :message="fieldError('term.school_year')" />
            </div>
            <div class="grid gap-2">
                <Label class="text-xs font-semibold">Starts on</Label>
                <Input v-model="form.term.starts_on" class="rounded-xl h-10 text-sm" type="date" />
                <InputError class="text-xs mt-1" :message="fieldError('term.starts_on')" />
            </div>
            <div class="grid gap-2">
                <Label class="text-xs font-semibold">Ends on</Label>
                <Input v-model="form.term.ends_on" class="rounded-xl h-10 text-sm" type="date" />
                <InputError class="text-xs mt-1" :message="fieldError('term.ends_on')" />
            </div>
        </section>

        <!-- 03 Weekly Schedule Panel -->
        <section class="paper-card p-6 md:p-8 grid gap-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <span class="eyebrow">03 / Weekly Schedule</span>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight">Meeting rhythm</h2>
                </div>
                <Button type="button" variant="outline" size="sm" class="rounded-xl text-xs font-semibold" @click="addSchedule">
                    <Plus class="mr-1 size-3.5" /> Add time entry
                </Button>
            </div>

            <div v-for="(schedule, index) in form.schedules" :key="index" class="grid gap-4 rounded-xl border border-border/80 bg-secondary/40 p-4">
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <Label :id="`schedule-days-${index}`" class="text-xs font-bold text-foreground">Meeting days</Label>
                        <span class="text-[11px] text-muted-foreground">Select one or more weekdays</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5" :aria-labelledby="`schedule-days-${index}`">
                        <label
                            v-for="(day, dayIndex) in days"
                            :key="day"
                            class="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-xl border px-3 text-xs font-semibold transition-all"
                            :class="
                                schedule.days.includes(dayIndex + 1)
                                    ? 'border-primary bg-primary/10 text-primary shadow-xs'
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

                <div class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_auto]">
                    <div class="grid gap-1.5">
                        <Label :for="`schedule-start-${index}`" class="text-xs font-semibold">Start time</Label>
                        <Input :id="`schedule-start-${index}`" v-model="schedule.starts_at" class="rounded-xl h-10 text-sm" type="time" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`schedule-end-${index}`" class="text-xs font-semibold">End time</Label>
                        <Input :id="`schedule-end-${index}`" v-model="schedule.ends_at" class="rounded-xl h-10 text-sm" type="time" />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded-xl"
                        :disabled="form.schedules.length === 1"
                        @click="form.schedules.splice(index, 1)"
                    >
                        <Minus class="size-4" />
                        <span class="sr-only">Remove schedule entry</span>
                    </Button>
                </div>
            </div>
            <InputError class="text-xs mt-1" :message="scheduleError || fieldError('schedules')" />
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
