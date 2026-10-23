<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { gradingComponents, type GradingFormulaSettings, type RoundingMode } from '@/types/grading';
import { Head, useForm } from '@inertiajs/vue3';
import { Calculator, Check, LoaderCircle, RotateCcw, Save } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ formula: GradingFormulaSettings; defaults: GradingFormulaSettings; hasCustomFormula: boolean }>();
const passingComponents = (['activity', 'quiz', 'exam', 'project'] as const).map((key) => ({
    key,
    label: gradingComponents.find((component) => component.key === key)!.label,
}));
const clone = (value: GradingFormulaSettings): GradingFormulaSettings => JSON.parse(JSON.stringify(value));
const form = useForm<GradingFormulaSettings>(clone(props.formula));
const errors = (key: string) => (form.errors as Record<string, string | undefined>)[key];
const weightTotal = computed(() =>
    gradingComponents.reduce(
        (sum, component) => sum + (component.key === 'recitation' && form.recitation_mode === 'bonus' ? 0 : Number(form.weights[component.key]) || 0),
        0,
    ),
);
const periodTotal = computed(() => Number(form.midterm_weight) + Number(form.final_weight));
const validTotals = computed(() => Math.abs(weightTotal.value - 100) < 0.00001 && periodTotal.value === 100);
const rounded = (value: number, mode: RoundingMode) => {
    const factor = 10 ** Number(form.decimal_places);
    const scaled = Math.round(value * factor * 1e8) / 1e8;
    return (mode === 'down' ? Math.floor(scaled) : mode === 'up' ? Math.ceil(scaled) : Math.round(scaled)) / factor;
};
const componentGrade = computed(() => Number(form.worst_grade) - (Number(form.worst_grade) - Number(form.best_grade)) * 0.8);
const exampleMidterm = computed(() => rounded(componentGrade.value, form.period_rounding));
const exampleFinal = computed(() => rounded(Number(form.best_grade), form.period_rounding));
const exampleGrade = computed(() => {
    const average = (exampleMidterm.value * Number(form.midterm_weight) + exampleFinal.value * Number(form.final_weight)) / 100;
    return form.replace_failing_grade && rounded(average, 'nearest') > Number(form.passing_grade)
        ? Number(form.failed_grade)
        : rounded(average, form.final_rounding);
});
const format = (value: number) => (Number.isFinite(value) ? value.toFixed(Math.max(0, Math.min(4, Number(form.decimal_places) || 0))) : '—');
const resetDefaults = () => {
    Object.assign(form, clone(props.defaults));
    form.clearErrors();
};
const save = () =>
    form.put('/settings/grading-formula', {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults(clone(props.formula));
            try {
                localStorage.setItem('classcheck:grading-formula-updated', String(Date.now()));
            } catch {
                /* Other tabs refresh when focused. */
            }
        },
    });
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Grading formula', href: '/settings/grading-formula' }]">
        <Head title="Grading formula" />
        <SettingsLayout>
            <form class="space-y-8" @submit.prevent="save">
                <header>
                    <p class="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-primary">
                        <Calculator class="size-4" /> Grading rules
                    </p>
                    <h1 class="text-2xl font-semibold tracking-tight">Your grading formula</h1>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Apply one formula across all your sections. Saving recalculates the gradebook, pass/fail analytics, printed reports, and
                        exports from the recorded scores.
                    </p>
                    <p v-if="!hasCustomFormula" class="mt-3 text-xs text-muted-foreground">
                        Section-specific weights remain active until you save an account formula here.
                    </p>
                </header>

                <section class="rounded-xl border border-primary/25 bg-primary/5 p-5" aria-label="Formula preview">
                    <h2 class="text-sm font-semibold">Live formula preview</h2>
                    <p class="mt-3 break-words font-mono text-sm leading-6">
                        Component = {{ form.worst_grade }} − ({{ Number(form.worst_grade) - Number(form.best_grade) }}) × earned / possible
                    </p>
                    <p class="mt-2 font-mono text-xs leading-6">
                        Final = Midterm × {{ form.midterm_weight }}% + Final period × {{ form.final_weight }}%
                    </p>
                    <div class="mt-4 grid grid-cols-3 gap-3 border-t border-primary/15 pt-4">
                        <div>
                            <p class="text-xs text-muted-foreground">Midterm at 80%</p>
                            <p class="mt-1 font-mono text-xl">{{ format(exampleMidterm) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Final at 100%</p>
                            <p class="mt-1 font-mono text-xl">{{ format(exampleFinal) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Final grade</p>
                            <p class="mt-1 font-mono text-xl font-semibold text-primary">{{ validTotals ? format(exampleGrade) : '—' }}</p>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Lower numerical grades are better. Intermediate component values keep full precision; period and final rounding follow your
                        choices below.
                    </p>
                </section>

                <section class="space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-lg font-medium">Component weights</h2>
                        <p class="font-mono text-sm" :class="Math.abs(weightTotal - 100) < 0.00001 ? 'text-primary' : 'text-destructive'">
                            {{ weightTotal }} / 100%
                        </p>
                    </div>
                    <p class="text-sm leading-relaxed text-muted-foreground">
                        Each weighted component uses its combined earned / possible score. Components with no possible points are left out and the
                        available weights are normalized.
                    </p>
                    <label class="block text-sm font-medium"
                        >Oral recitation treatment
                        <select v-model="form.recitation_mode" class="mt-2 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm">
                            <option value="bonus">Bonus points added to activities</option>
                            <option value="weighted">Weighted component</option>
                        </select>
                    </label>
                    <div class="grid grid-cols-2 gap-4">
                        <label v-for="component in gradingComponents" :key="component.key" class="block text-sm font-medium"
                            >{{ component.label }} {{ component.key === 'recitation' && form.recitation_mode === 'bonus' ? '(bonus cap)' : '(%)' }}
                            <input
                                v-model.number="form.weights[component.key]"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                                class="mt-2 w-full rounded-lg border border-input bg-background px-3 py-2 font-mono text-sm"
                            />
                            <InputError :message="errors(`weights.${component.key}`)" class="mt-1" />
                        </label>
                    </div>
                    <InputError :message="errors('weights')" />
                    <p v-if="form.recitation_mode === 'bonus'" class="text-xs text-muted-foreground">
                        The oral recitation cap is extra credit and is excluded from the 100% weight total.
                    </p>
                </section>

                <section class="space-y-4 border-t border-border pt-6">
                    <h2 class="text-lg font-medium">Grade scale & passing</h2>
                    <p class="text-sm leading-relaxed text-muted-foreground">
                        These values set the component formula. Best grade is awarded at 100%; worst grade is awarded at 0%.
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <label
                            v-for="field in [
                                { key: 'best_grade', label: 'Best grade (100%)' },
                                { key: 'worst_grade', label: 'Worst grade (0%)' },
                                { key: 'passing_grade', label: 'Highest passing grade' },
                                { key: 'failed_grade', label: 'Failing grade replacement' },
                            ] as const"
                            :key="field.key"
                            class="block text-sm font-medium"
                            >{{ field.label }}
                            <input
                                v-model.number="form[field.key]"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                                class="mt-2 w-full rounded-lg border border-input bg-background px-3 py-2 font-mono text-sm"
                            />
                            <InputError :message="errors(field.key)" class="mt-1" />
                        </label>
                    </div>
                    <label class="flex items-start gap-3 text-sm"
                        ><input
                            v-model="form.replace_failing_grade"
                            type="checkbox"
                            class="mt-1 rounded border-input text-primary focus:ring-primary"
                        /><span
                            >Replace a failing final grade with the failing grade above.<small
                                class="mt-1 block leading-relaxed text-muted-foreground"
                                >The weighted final average is rounded to the selected decimal places before checking the passing threshold.</small
                            ></span
                        ></label
                    >
                </section>

                <section class="space-y-4 border-t border-border pt-6">
                    <h2 class="text-lg font-medium">Period split & rounding</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <label
                            v-for="field in [
                                { key: 'midterm_weight', label: 'Midterm weight (%)' },
                                { key: 'final_weight', label: 'Final-period weight (%)' },
                            ] as const"
                            :key="field.key"
                            class="block text-sm font-medium"
                            >{{ field.label }}
                            <input
                                v-model.number="form[field.key]"
                                type="number"
                                min="0"
                                max="100"
                                step="1"
                                required
                                class="mt-2 w-full rounded-lg border border-input bg-background px-3 py-2 font-mono text-sm"
                            />
                            <InputError :message="errors(field.key)" class="mt-1" />
                        </label>
                        <label
                            v-for="field in [
                                { key: 'period_rounding', label: 'Period rounding' },
                                { key: 'final_rounding', label: 'Final-grade rounding' },
                            ] as const"
                            :key="field.key"
                            class="block text-sm font-medium"
                            >{{ field.label }}
                            <select v-model="form[field.key]" class="mt-2 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm">
                                <option value="nearest">Round to nearest</option>
                                <option value="down">Round down</option>
                                <option value="up">Round up</option>
                            </select>
                        </label>
                        <label class="block text-sm font-medium"
                            >Decimal places<input
                                v-model.number="form.decimal_places"
                                type="number"
                                min="0"
                                max="4"
                                step="1"
                                required
                                class="mt-2 w-full rounded-lg border border-input bg-background px-3 py-2 font-mono text-sm" /><InputError
                                :message="errors('decimal_places')"
                        /></label>
                    </div>
                    <p class="font-mono text-xs" :class="periodTotal === 100 ? 'text-muted-foreground' : 'text-destructive'">
                        Period total: {{ periodTotal }} / 100%
                    </p>
                </section>

                <section class="space-y-4 border-t border-border pt-6">
                    <h2 class="text-lg font-medium">Component passing rates</h2>
                    <p class="text-sm text-muted-foreground">
                        Used to identify low-scoring components and student deficiencies. The final pass/fail result uses the numerical threshold
                        above.
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <label v-for="component in passingComponents" :key="component.key" class="block text-sm font-medium"
                            >{{ component.label }} passing score (%)
                            <input
                                v-model.number="form.passing_rates[component.key]"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                                class="mt-2 w-full rounded-lg border border-input bg-background px-3 py-2 font-mono text-sm"
                            />
                            <InputError :message="errors(`passing_rates.${component.key}`)" class="mt-1" />
                        </label>
                    </div>
                    <label class="block text-sm font-medium"
                        >Reporting schedule
                        <select v-model="form.reporting_frequency" class="mt-2 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm">
                            <option value="once_per_sem">Once per semester (final period)</option>
                            <option value="twice_per_sem">Twice per semester (midterm and final)</option>
                        </select>
                    </label>
                </section>

                <div class="sticky bottom-0 -mx-1 border-t border-border bg-background/95 px-1 py-4 backdrop-blur-sm">
                    <p v-if="!validTotals" role="alert" class="mb-3 text-sm text-destructive">
                        Component weights and period weights must each total 100%.
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            :disabled="form.processing || !validTotals"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground disabled:opacity-50"
                        >
                            <LoaderCircle v-if="form.processing" class="size-4 animate-spin motion-reduce:animate-none" /><Save
                                v-else
                                class="size-4"
                            />{{ form.processing ? 'Saving…' : 'Save grading formula' }}
                        </button>
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2.5 text-sm disabled:opacity-50"
                            @click="resetDefaults"
                        >
                            <RotateCcw class="size-4" />Restore defaults
                        </button>
                        <p v-if="form.recentlySuccessful" role="status" class="flex items-center gap-1 text-sm text-primary">
                            <Check class="size-4" />Saved. Gradebooks updated.
                        </p>
                    </div>
                </div>
            </form>
        </SettingsLayout>
    </AppLayout>
</template>
