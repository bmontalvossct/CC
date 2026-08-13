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
        school_year: props.section?.academic_term.school_year ?? '',
        starts_on: props.section?.academic_term.starts_on?.slice(0, 10) ?? '',
        ends_on: props.section?.academic_term.ends_on?.slice(0, 10) ?? '',
    },
    schedules: props.section?.schedules?.length ? groupedSchedules(props.section.schedules) : [{ days: [1], starts_at: '08:00', ends_at: '09:00' }],
});

const fieldError = (key: string) => (form.errors as Record<string, string | undefined>)[key];
const scheduleError = ref('');

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
        <section class="grid gap-5 rounded-2xl border border-[#e5e7eb] bg-[#ffffff] p-6 shadow-[0_18px_60px_-45px_rgba(28,25,23,.65)] md:grid-cols-2">
            <div class="md:col-span-2">
                <p class="font-mono text-xs font-bold uppercase tracking-[.2em] text-[#0071e3]">01 / Identity</p>
                <h2 class="mt-1 font-serif text-2xl font-bold text-[#1d1d1f]">What class meets here?</h2>
            </div>
            <div class="grid gap-2">
                <Label for="code">Subject code</Label><Input id="code" v-model="form.subject_code" placeholder="MATH 101" /><InputError
                    :message="form.errors.subject_code"
                />
            </div>
            <div class="grid gap-2">
                <Label for="name">Section</Label><Input id="name" v-model="form.name" placeholder="BSIT 1-A" /><InputError
                    :message="form.errors.name"
                />
            </div>
            <div class="grid gap-2 md:col-span-2">
                <Label for="title">Subject title</Label
                ><Input id="title" v-model="form.subject_title" placeholder="Mathematics in the Modern World" /><InputError
                    :message="form.errors.subject_title"
                />
            </div>
            <div class="grid gap-2">
                <Label for="room">Room <span class="text-[#86868b]">(optional)</span></Label
                ><Input id="room" v-model="form.room" placeholder="Room 204" />
            </div>
        </section>

        <section class="grid gap-5 rounded-2xl border border-[#e5e7eb] bg-[#ffffff] p-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <p class="font-mono text-xs font-bold uppercase tracking-[.2em] text-[#0071e3]">02 / Term</p>
                <h2 class="mt-1 font-serif text-2xl font-bold">Set the record window</h2>
            </div>
            <div class="grid gap-2">
                <Label>Term name</Label><Input v-model="form.term.name" placeholder="1st Semester" /><InputError :message="fieldError('term.name')" />
            </div>
            <div class="grid gap-2">
                <Label>School year</Label><Input v-model="form.term.school_year" placeholder="2026-2027" /><InputError
                    :message="fieldError('term.school_year')"
                />
            </div>
            <div class="grid gap-2">
                <Label>Starts on</Label><Input v-model="form.term.starts_on" type="date" /><InputError :message="fieldError('term.starts_on')" />
            </div>
            <div class="grid gap-2">
                <Label>Ends on</Label><Input v-model="form.term.ends_on" type="date" /><InputError :message="fieldError('term.ends_on')" />
            </div>
        </section>

        <section class="grid gap-5 rounded-2xl border border-[#e5e7eb] bg-[#ffffff] p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs font-bold uppercase tracking-[.2em] text-[#0071e3]">03 / Rhythm</p>
                    <h2 class="mt-1 font-serif text-2xl font-bold">Weekly schedule</h2>
                </div>
                <Button type="button" variant="outline" size="sm" @click="addSchedule"><Plus class="mr-1 size-4" /> Add time</Button>
            </div>
            <div v-for="(schedule, index) in form.schedules" :key="index" class="grid gap-4 rounded-xl border border-[#e5e7eb] bg-[#f5f5f7]/70 p-4">
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <Label :id="`schedule-days-${index}`">Meeting days</Label>
                        <span class="text-xs text-[#86868b]">Choose one or more</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5" :aria-labelledby="`schedule-days-${index}`">
                        <label
                            v-for="(day, dayIndex) in days"
                            :key="day"
                            class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border bg-white px-3 text-sm font-medium transition-colors hover:border-[#0071e3]"
                            :class="schedule.days.includes(dayIndex + 1) ? 'border-[#0071e3] text-[#0066cc]' : 'border-[#e5e7eb] text-[#1d1d1f]'"
                        >
                            <input
                                type="checkbox"
                                class="size-4 rounded border-[#86868b] text-[#0071e3] focus:ring-[#0071e3]"
                                :checked="schedule.days.includes(dayIndex + 1)"
                                @change="toggleDay(schedule, dayIndex + 1)"
                            />
                            {{ day }}
                        </label>
                    </div>
                </div>
                <div class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_auto]">
                    <div class="grid gap-2">
                        <Label :for="`schedule-start-${index}`">Start time</Label>
                        <Input :id="`schedule-start-${index}`" v-model="schedule.starts_at" type="time" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`schedule-end-${index}`">End time</Label>
                        <Input :id="`schedule-end-${index}`" v-model="schedule.ends_at" type="time" />
                    </div>
                    <Button type="button" variant="ghost" size="icon" :disabled="form.schedules.length === 1" @click="form.schedules.splice(index, 1)"
                        ><Minus class="size-4" /><span class="sr-only">Remove schedule entry</span></Button
                    >
                </div>
            </div>
            <InputError :message="scheduleError || fieldError('schedules')" />
        </section>

        <div class="flex justify-end gap-3">
            <Button as-child variant="ghost"><Link href="/sections">Cancel</Link></Button
            ><Button class="bg-[#0071e3] text-white hover:bg-[#0066cc]" :disabled="form.processing">{{
                section ? 'Save changes' : 'Create section'
            }}</Button>
        </div>
    </form>
</template>
