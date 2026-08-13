<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm } from '@inertiajs/vue3';
import { Minus, Plus } from 'lucide-vue-next';
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
const panelClass =
    'rounded-2xl border border-[#d7d0c2] bg-[#fffdf8] p-6 text-[#17231f] shadow-[0_18px_60px_-45px_rgba(44,56,49,.45)] dark:border-[#31433c] dark:bg-[#17231f] dark:text-[#f8f3e8] dark:shadow-[0_22px_70px_-48px_rgba(0,0,0,.9)]';
const inputClass =
    'border-[#c9c0b0] bg-[#faf7ef] text-[#17231f] placeholder:text-[#718078] focus-visible:border-[#a9472d] focus-visible:ring-[#a9472d] dark:border-[#40534b] dark:bg-[#0d1512] dark:text-[#f8f3e8] dark:placeholder:text-[#82928b] dark:focus-visible:border-[#f08a68] dark:focus-visible:ring-[#f08a68]';
const selectClass =
    'h-11 w-full rounded-md border px-4 py-[11px] text-[15px] ring-offset-background focus:border-[#a9472d] focus:outline-none focus:ring-1 focus:ring-[#a9472d] dark:focus:border-[#f08a68] dark:focus:ring-[#f08a68] ' +
    inputClass;
const labelClass = 'text-[#34443d] dark:text-[#d8e0dc]';
const stepClass = 'font-mono text-xs font-bold uppercase tracking-[.2em] text-[#a9472d] dark:text-[#f08a68]';
const headingClass = 'mt-1 font-serif text-2xl font-bold text-[#17231f] dark:text-[#fffaf0]';

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
        <section :class="[panelClass, 'grid gap-5 md:grid-cols-2']">
            <div class="md:col-span-2">
                <p :class="stepClass">01 / Identity</p>
                <h2 :class="headingClass">What class meets here?</h2>
            </div>
            <div class="grid gap-2">
                <Label for="code" :class="labelClass">Subject code</Label
                ><Input id="code" v-model="form.subject_code" :class="inputClass" placeholder="MATH 101" /><InputError
                    :message="form.errors.subject_code"
                />
            </div>
            <div class="grid gap-2">
                <Label for="name" :class="labelClass">Section</Label
                ><Input id="name" v-model="form.name" :class="inputClass" placeholder="BSIT 1-A" /><InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2 md:col-span-2">
                <Label for="title" :class="labelClass">Subject title</Label
                ><Input id="title" v-model="form.subject_title" :class="inputClass" placeholder="Mathematics in the Modern World" /><InputError
                    :message="form.errors.subject_title"
                />
            </div>
            <div class="grid gap-2">
                <Label for="room" :class="labelClass">Room <span class="text-[#718078] dark:text-[#91a19a]">(optional)</span></Label
                ><Input id="room" v-model="form.room" :class="inputClass" placeholder="Room 204" />
            </div>
        </section>

        <section :class="[panelClass, 'grid gap-5 md:grid-cols-2']">
            <div class="md:col-span-2">
                <p :class="stepClass">02 / Term</p>
                <h2 :class="headingClass">Set the record window</h2>
            </div>
            <div class="grid gap-2">
                <Label :class="labelClass">Term name</Label
                ><Input v-model="form.term.name" :class="inputClass" placeholder="1st Semester" /><InputError :message="fieldError('term.name')" />
            </div>
            <div class="grid gap-2">
                <Label for="school-year" :class="labelClass">School year</Label>
                <select id="school-year" v-model="form.term.school_year" :class="selectClass">
                    <option v-for="schoolYear in schoolYearOptions" :key="schoolYear" :value="schoolYear">
                        {{ schoolYear }}
                    </option>
                </select>
                <InputError :message="fieldError('term.school_year')" />
            </div>
            <div class="grid gap-2">
                <Label :class="labelClass">Starts on</Label><Input v-model="form.term.starts_on" :class="inputClass" type="date" /><InputError
                    :message="fieldError('term.starts_on')"
                />
            </div>
            <div class="grid gap-2">
                <Label :class="labelClass">Ends on</Label><Input v-model="form.term.ends_on" :class="inputClass" type="date" /><InputError
                    :message="fieldError('term.ends_on')"
                />
            </div>
        </section>

        <section :class="[panelClass, 'grid gap-5']">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p :class="stepClass">03 / Rhythm</p>
                    <h2 :class="headingClass">Weekly schedule</h2>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="border-[#b9ae9c] bg-[#faf7ef] text-[#34443d] hover:bg-[#eee7da] dark:border-[#40534b] dark:bg-[#0d1512] dark:text-[#e3ebe7] dark:hover:bg-[#24332d]"
                    @click="addSchedule"
                    ><Plus class="mr-1 size-4" /> Add time</Button
                >
            </div>
            <div
                v-for="(schedule, index) in form.schedules"
                :key="index"
                class="grid gap-4 rounded-xl border border-[#ddd5c7] bg-[#f7f2e8] p-4 dark:border-[#354940] dark:bg-[#101a16]"
            >
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <Label :id="`schedule-days-${index}`" :class="labelClass">Meeting days</Label>
                        <span class="text-xs text-[#66766f] dark:text-[#9baba4]">Choose one or more</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5" :aria-labelledby="`schedule-days-${index}`">
                        <label
                            v-for="(day, dayIndex) in days"
                            :key="day"
                            class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border bg-[#fffdf8] px-3 text-sm font-semibold transition-colors hover:border-[#a9472d] dark:bg-[#17231f] dark:hover:border-[#f08a68]"
                            :class="
                                schedule.days.includes(dayIndex + 1)
                                    ? 'border-[#a9472d] bg-[#f7e5dc] text-[#8f351f] dark:border-[#f08a68] dark:bg-[#3a241d] dark:text-[#ffc0aa]'
                                    : 'border-[#cfc6b7] text-[#2b3a34] dark:border-[#40534b] dark:text-[#d8e0dc]'
                            "
                        >
                            <input
                                type="checkbox"
                                class="size-4 rounded border-[#8e9a94] text-[#a9472d] focus:ring-[#a9472d] dark:border-[#718078] dark:bg-[#0d1512] dark:text-[#f08a68] dark:focus:ring-[#f08a68]"
                                :checked="schedule.days.includes(dayIndex + 1)"
                                @change="toggleDay(schedule, dayIndex + 1)"
                            />
                            {{ day }}
                        </label>
                    </div>
                </div>
                <div class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_auto]">
                    <div class="grid gap-2">
                        <Label :for="`schedule-start-${index}`" :class="labelClass">Start time</Label>
                        <Input :id="`schedule-start-${index}`" v-model="schedule.starts_at" :class="inputClass" type="time" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`schedule-end-${index}`" :class="labelClass">End time</Label>
                        <Input :id="`schedule-end-${index}`" v-model="schedule.ends_at" :class="inputClass" type="time" />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="text-[#56665f] hover:bg-[#e8e0d2] hover:text-[#8f351f] dark:text-[#a9b7b1] dark:hover:bg-[#24332d] dark:hover:text-[#ffc0aa]"
                        :disabled="form.schedules.length === 1"
                        @click="form.schedules.splice(index, 1)"
                        ><Minus class="size-4" /><span class="sr-only">Remove schedule entry</span></Button
                    >
                </div>
            </div>
            <InputError :message="scheduleError || fieldError('schedules')" />
        </section>

        <div class="flex justify-end gap-3">
            <Button as-child variant="ghost" class="text-[#43534c] hover:bg-[#e7dfd1] dark:text-[#c4d0ca] dark:hover:bg-[#24332d]"
                ><Link href="/sections">Cancel</Link></Button
            ><Button
                class="bg-[#a9472d] text-white hover:bg-[#8f351f] dark:bg-[#e87854] dark:text-[#17231f] dark:hover:bg-[#f08a68]"
                :disabled="form.processing"
                >{{ section ? 'Save changes' : 'Create section' }}</Button
            >
        </div>
    </form>
</template>
