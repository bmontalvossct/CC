<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { TransitionRoot } from '@headlessui/vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Calendar, CheckCircle2, Clock, Sparkles } from 'lucide-vue-next';

interface TermItem {
    id: number;
    name: string;
    school_year: string;
    starts_on: string;
    ends_on: string;
    is_current: boolean;
    default_starts_at: string;
    default_ends_at: string;
    sections_count: number;
}

interface Props {
    currentTerm: TermItem;
    terms: TermItem[];
}

const props = defineProps<Props>();
const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Academic Term settings',
        href: '/settings/academic-term',
    },
];

const today = new Date();
const currentSchoolYearStart = today.getMonth() >= 5 ? today.getFullYear() : today.getFullYear() - 1;
const schoolYearOptions = Array.from(
    new Set([
        props.currentTerm.school_year,
        ...Array.from({ length: 6 }, (_, index) => {
            const startYear = currentSchoolYearStart - 2 + index;
            return `${startYear}-${startYear + 1}`;
        }),
    ]),
);

const form = useForm({
    name: props.currentTerm.name || '1st Semester',
    school_year: props.currentTerm.school_year || `${currentSchoolYearStart}-${currentSchoolYearStart + 1}`,
    starts_on: props.currentTerm.starts_on || '',
    ends_on: props.currentTerm.ends_on || '',
    default_starts_at: props.currentTerm.default_starts_at || '08:00',
    default_ends_at: props.currentTerm.default_ends_at || '09:30',
});

const submit = () => {
    form.put(route('academic-term.update'), {
        preserveScroll: true,
    });
};

const makeTermActive = (term: TermItem) => {
    router.post(route('academic-term.make-current', { term: term.id }), {}, {
        preserveScroll: true,
        onSuccess: () => {
            form.name = term.name;
            form.school_year = term.school_year;
            form.starts_on = term.starts_on;
            form.ends_on = term.ends_on;
            form.default_starts_at = term.default_starts_at;
            form.default_ends_at = term.default_ends_at;
        },
    });
};

const selectClass =
    'h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary text-foreground font-medium';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Universal Academic Term Settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-8">
                <div>
                    <HeadingSmall
                        title="Universal Academic Semester Schedule"
                        description="Configure your active semester schedule, calendar duration, and default class meeting times. Newly added sections will automatically inherit these settings."
                    />
                </div>

                <!-- Flash Success Notification -->
                <div
                    v-if="page.props.flash?.success"
                    class="flex items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-semibold text-emerald-700 dark:text-emerald-400"
                >
                    <CheckCircle2 class="size-4" />
                    <span>{{ page.props.flash.success }}</span>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Term Name & School Year -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="term_name" class="text-xs font-semibold uppercase tracking-wider">
                                Semester / Term Name
                            </Label>
                            <Input
                                id="term_name"
                                v-model="form.name"
                                class="h-10 rounded-xl font-medium"
                                placeholder="e.g. 1st Semester"
                                required
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="school_year" class="text-xs font-semibold uppercase tracking-wider">
                                School Year
                            </Label>
                            <select id="school_year" v-model="form.school_year" :class="selectClass">
                                <option v-for="sy in schoolYearOptions" :key="sy" :value="sy">SY {{ sy }}</option>
                            </select>
                            <InputError :message="form.errors.school_year" />
                        </div>
                    </div>

                    <!-- Semester Start & End Dates -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="starts_on" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider">
                                <Calendar class="size-3.5 text-primary" />
                                <span>Semester Start Date</span>
                            </Label>
                            <Input
                                id="starts_on"
                                v-model="form.starts_on"
                                type="date"
                                class="h-10 rounded-xl font-medium"
                                required
                            />
                            <InputError :message="form.errors.starts_on" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="ends_on" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider">
                                <Calendar class="size-3.5 text-primary" />
                                <span>Semester End Date</span>
                            </Label>
                            <Input
                                id="ends_on"
                                v-model="form.ends_on"
                                type="date"
                                class="h-10 rounded-xl font-medium"
                                required
                            />
                            <InputError :message="form.errors.ends_on" />
                        </div>
                    </div>

                    <!-- Default Class Times -->
                    <div class="rounded-2xl border border-border/80 bg-secondary/30 p-4">
                        <div class="mb-3">
                            <span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-foreground">
                                <Clock class="size-3.5 text-primary" />
                                <span>Default Class Meeting Times</span>
                            </span>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Default start and end times automatically suggested when adding new section schedules.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="default_starts_at" class="text-xs font-medium">Default Start Time</Label>
                                <Input
                                    id="default_starts_at"
                                    v-model="form.default_starts_at"
                                    type="time"
                                    class="h-10 rounded-xl bg-background font-medium"
                                />
                                <InputError :message="form.errors.default_starts_at" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="default_ends_at" class="text-xs font-medium">Default End Time</Label>
                                <Input
                                    id="default_ends_at"
                                    v-model="form.default_ends_at"
                                    type="time"
                                    class="h-10 rounded-xl bg-background font-medium"
                                />
                                <InputError :message="form.errors.default_ends_at" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="form.processing" class="rounded-xl px-5 text-xs font-bold">
                            {{ form.processing ? 'Saving…' : 'Save Universal Schedule' }}
                        </Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Saved successfully.</p>
                        </TransitionRoot>
                    </div>
                </form>

                <!-- Registered Semesters / Terms List -->
                <div class="border-t border-border/80 pt-6">
                    <div class="mb-4">
                        <h4 class="text-sm font-bold text-foreground">Your Academic Semesters & Terms</h4>
                        <p class="text-xs text-muted-foreground">
                            Switch between terms or review past semester durations and enrolled classes.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="term in terms"
                            :key="term.id"
                            class="flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between transition-colors"
                            :class="
                                term.is_current
                                    ? 'border-primary/40 bg-primary/5 shadow-xs'
                                    : 'border-border/80 bg-card hover:bg-secondary/40'
                            "
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-sm text-foreground">{{ term.name }}</span>
                                    <span class="font-mono text-xs font-semibold text-muted-foreground">SY {{ term.school_year }}</span>
                                    <span
                                        v-if="term.is_current"
                                        class="inline-flex items-center gap-1 rounded-md bg-primary/10 px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-primary"
                                    >
                                        <Sparkles class="size-2.5" />
                                        Active
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ term.starts_on }} to {{ term.ends_on }} · {{ term.sections_count }} {{ term.sections_count === 1 ? 'class' : 'classes' }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    v-if="!term.is_current"
                                    type="button"
                                    class="rounded-xl border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                                    @click="makeTermActive(term)"
                                >
                                    Set as Active
                                </button>
                                <span v-else class="text-xs font-semibold text-primary">Current Universal Term</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
