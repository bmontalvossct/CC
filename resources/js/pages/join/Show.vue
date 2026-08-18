<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Armchair, Camera, CheckCircle2, LockKeyhole, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ section: any; token: string }>();
const page = usePage<any>();
const form = useForm<{
    student_number: string;
    first_name: string;
    middle_name: string;
    last_name: string;
    seat_id: number | null;
    photo: File | null;
}>({ student_number: '', first_name: '', middle_name: '', last_name: '', seat_id: null, photo: null });

const enrollmentError = computed(() => (form.errors as Record<string, string | undefined>).enrollment);
const selectedLabel = computed(() => props.section.blocks.flatMap((block: any) => block.seats).find((seat: any) => seat.id === form.seat_id)?.label);
const submit = () => form.post(`/join/${props.token}`, { forceFormData: true, preserveScroll: true });
</script>

<template>
    <Head :title="`Join ${section.name} - ClassCheck`" />
    <main class="page-enter min-h-screen bg-background px-4 py-8 text-foreground md:py-12">
        <div class="mx-auto max-w-5xl">
            <!-- Hero Header -->
            <header
                class="relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-zinc-950 via-zinc-900 to-zinc-950 p-7 text-white shadow-xl md:p-10"
            >
                <div class="relative">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-blue-400/30 bg-primary/20 px-3 py-1 font-mono text-[11px] font-bold uppercase tracking-widest text-blue-400"
                    >
                        <Sparkles class="size-3" /> Student Self-Enrollment
                    </span>
                    <h1 class="mt-4 text-3xl font-extrabold tracking-tight md:text-5xl">
                        {{ section.subject_code }} <span class="text-primary">/</span> {{ section.name }}
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-zinc-300 md:text-base">
                        {{ section.subject_title }}<span v-if="section.room"> · {{ section.room }}</span>
                    </p>
                </div>
            </header>

            <!-- Success Flash Notification -->
            <div v-if="page.props.flash?.success" class="paper-card mt-6 border-emerald-500/30 bg-emerald-500/10 p-8 text-center shadow-lg">
                <CheckCircle2 class="mx-auto size-12 text-emerald-600 dark:text-emerald-400" />
                <h2 class="mt-4 text-2xl font-extrabold text-foreground">Chair Reserved!</h2>
                <p class="mt-2 text-sm font-medium text-emerald-600 dark:text-emerald-400">{{ page.props.flash.success }}</p>
            </div>

            <!-- Enrollment Closed State -->
            <div v-else-if="!section.enrollment_open" class="paper-card mt-6 border-rose-500/30 bg-rose-500/10 p-10 text-center shadow-lg">
                <LockKeyhole class="mx-auto size-10 text-rose-600 dark:text-rose-400" />
                <h2 class="mt-4 text-2xl font-extrabold text-foreground">Enrollment is closed</h2>
                <p class="mt-2 text-sm text-muted-foreground">Your teacher must open room enrollment before a chair can be claimed.</p>
            </div>

            <!-- Step-by-Step Join Form -->
            <form v-else class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]" @submit.prevent="submit">
                <!-- Step 1: Chair Selection Map -->
                <section class="paper-card min-w-0 p-6 md:p-8">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <span class="eyebrow">Step 1</span>
                            <h2 class="mt-1 text-2xl font-bold tracking-tight">Choose your chair</h2>
                        </div>
                        <span v-if="selectedLabel" class="badge-primary font-mono text-xs font-bold"> Selected: {{ selectedLabel }} </span>
                    </div>

                    <!-- Teaching Wall Bar -->
                    <div
                        class="my-6 flex items-center justify-center rounded-2xl bg-[#164e3f] py-3 px-6 text-center text-xs font-bold uppercase tracking-[0.25em] text-white shadow-xs dark:bg-[#134e48]"
                    >
                        Teaching Wall / Front Board
                    </div>

                    <div
                        class="w-full max-w-full overflow-x-auto overscroll-x-contain pb-3 [-webkit-overflow-scrolling:touch] [scrollbar-gutter:stable]"
                        role="region"
                        aria-label="Scrollable classroom seating chart"
                        tabindex="0"
                    >
                        <div
                            class="grid min-w-[480px] gap-6"
                            :style="{
                                gridTemplateColumns: `repeat(${Math.max(1, ...section.blocks.map((block: any) => block.block_column))}, minmax(190px, 1fr))`,
                            }"
                        >
                            <article
                                v-for="block in section.blocks"
                                :key="block.id"
                                class="rounded-2xl border border-border/80 bg-card p-4 shadow-xs"
                                :style="{ gridColumn: block.block_column, gridRow: block.block_row }"
                            >
                                <p v-if="block.label && block.label !== 'Classroom'" class="mb-3 text-center text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                    {{ block.label }}
                                </p>
                                <div class="grid gap-2.5" :style="{ gridTemplateColumns: `repeat(${block.internal_columns}, 1fr)` }">
                                    <button
                                        v-for="seat in block.seats"
                                        :key="seat.id"
                                        type="button"
                                        :disabled="!seat.is_available"
                                        class="flex min-h-[4.75rem] flex-col items-center justify-center rounded-2xl border-2 p-2 text-center transition-all duration-150"
                                        :class="
                                            seat.id === form.seat_id
                                                ? 'scale-105 border-emerald-400 bg-[#164e3f] text-white shadow-md ring-2 ring-emerald-400 ring-offset-2'
                                                : seat.is_available
                                                  ? 'border-slate-200/90 bg-card text-muted-foreground hover:-translate-y-0.5 hover:border-primary/50 hover:bg-secondary/40 hover:text-foreground dark:border-border/80'
                                                  : 'cursor-not-allowed border-transparent bg-muted/40 text-muted-foreground/40 opacity-50'
                                        "
                                        @click="form.seat_id = seat.id"
                                    >
                                        <Armchair class="size-4.5" :class="seat.id === form.seat_id ? 'text-white' : 'text-slate-400 dark:text-muted-foreground/60'" />
                                        <span class="mt-1 block font-mono text-[9px] font-bold uppercase tracking-wider">
                                            {{ seat.is_available ? seat.label : 'TAKEN' }}
                                        </span>
                                    </button>
                                </div>
                            </article>
                        </div>
                    </div>

                    <InputError class="mt-3 text-xs" :message="form.errors.seat_id || enrollmentError" />
                    <p class="mt-4 flex items-center gap-2 text-xs font-medium text-muted-foreground">
                        <LockKeyhole class="size-3.5 shrink-0 text-primary" />
                        <span>Only chair availability is visible. Other students' names and private data remain hidden.</span>
                    </p>
                </section>

                <!-- Step 2: Student Identity Form -->
                <section class="paper-card h-fit p-6 md:p-8 lg:sticky lg:top-6">
                    <span class="eyebrow">Step 2</span>
                    <h2 class="mt-1 text-2xl font-medium tracking-tight">Your student info</h2>

                    <div class="mt-5 grid gap-4">
                        <div class="grid gap-1.5">
                            <Label for="student-number" class="text-xs font-medium"
                                >ID / Student number <span class="font-normal text-muted-foreground">(optional)</span></Label
                            >
                            <Input
                                id="student-number"
                                v-model="form.student_number"
                                autocomplete="off"
                                placeholder="e.g. 2026-00001 (optional)"
                                class="h-10 rounded-xl text-sm"
                            />
                            <InputError class="mt-1 text-xs" :message="form.errors.student_number" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="first-name" class="text-xs font-medium">First name</Label>
                            <Input id="first-name" v-model="form.first_name" autocomplete="given-name" class="h-10 rounded-xl text-sm" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="middle-name" class="text-xs font-medium"
                                >Middle name <span class="font-normal text-muted-foreground">(optional)</span></Label
                            >
                            <Input id="middle-name" v-model="form.middle_name" autocomplete="additional-name" class="h-10 rounded-xl text-sm" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="last-name" class="text-xs font-medium">Last name</Label>
                            <Input id="last-name" v-model="form.last_name" autocomplete="family-name" class="h-10 rounded-xl text-sm" />
                        </div>

                        <label
                            class="group grid cursor-pointer place-items-center rounded-2xl border-2 border-dashed border-border/80 p-5 text-center transition-all hover:border-primary hover:bg-primary/5"
                        >
                            <Camera class="size-6 text-primary transition-transform group-hover:scale-110" />
                            <span class="mt-2 text-xs font-medium text-foreground">
                                {{ form.photo?.name || 'Upload profile photo' }}
                            </span>
                            <span class="mt-0.5 text-[10px] text-muted-foreground">JPG, PNG or WebP · Up to 5MB</span>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                @change="form.photo = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            />
                        </label>
                        <InputError class="mt-1 text-xs" :message="form.errors.photo" />
                    </div>

                    <Button type="submit" class="ink-button mt-6 !h-11 !w-full !rounded-xl" :disabled="form.processing || !form.seat_id">
                        {{ form.processing ? 'Claiming chair...' : `Reserve ${selectedLabel || 'Chair'}` }}
                    </Button>
                </section>
            </form>
        </div>
    </main>
</template>
