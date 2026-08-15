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
            <header class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-zinc-950 via-zinc-900 to-zinc-950 p-7 text-white shadow-xl md:p-10 border border-white/10">
                <div class="relative">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/20 px-3 py-1 font-mono text-[11px] font-bold uppercase tracking-widest text-blue-400 border border-blue-400/30">
                        <Sparkles class="size-3" /> Student Self-Enrollment
                    </span>
                    <h1 class="mt-4 text-3xl font-extrabold tracking-tight md:text-5xl">
                        {{ section.subject_code }} <span class="text-primary">/</span> {{ section.name }}
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm md:text-base text-zinc-300">
                        {{ section.subject_title }}<span v-if="section.room"> · {{ section.room }}</span>
                    </p>
                </div>
            </header>

            <!-- Success Flash Notification -->
            <div v-if="page.props.flash?.success" class="paper-card mt-6 border-emerald-500/30 bg-emerald-500/10 p-8 text-center shadow-lg">
                <CheckCircle2 class="mx-auto size-12 text-emerald-600 dark:text-emerald-400" />
                <h2 class="mt-4 text-2xl font-extrabold text-foreground">Chair Reserved!</h2>
                <p class="mt-2 text-sm text-emerald-600 dark:text-emerald-400 font-medium">{{ page.props.flash.success }}</p>
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
                        <span v-if="selectedLabel" class="badge-primary font-mono text-xs font-bold">
                            Selected: {{ selectedLabel }}
                        </span>
                    </div>

                    <div class="my-6 rounded-xl bg-gradient-to-r from-zinc-900 via-zinc-800 to-zinc-900 py-2.5 text-center text-[10px] font-extrabold uppercase tracking-[0.25em] text-white shadow-xs">
                        Front / Teaching board
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
                                class="rounded-2xl border border-dashed border-border/80 bg-secondary/30 p-3.5"
                                :style="{ gridColumn: block.block_column, gridRow: block.block_row }"
                            >
                                <p class="mb-2.5 text-[10px] font-extrabold uppercase tracking-wider text-muted-foreground text-center">Block {{ block.label }}</p>
                                <div class="grid gap-2" :style="{ gridTemplateColumns: `repeat(${block.internal_columns}, 1fr)` }">
                                    <button
                                        v-for="seat in block.seats"
                                        :key="seat.id"
                                        type="button"
                                        :disabled="!seat.is_available"
                                        class="aspect-square rounded-xl border-2 p-1 transition-all flex flex-col items-center justify-center text-center"
                                        :class="
                                            seat.id === form.seat_id
                                                ? 'scale-105 border-primary bg-primary text-white shadow-md'
                                                : seat.is_available
                                                  ? 'border-border/90 bg-card text-muted-foreground hover:-translate-y-0.5 hover:border-primary hover:text-primary hover:bg-primary/5'
                                                  : 'cursor-not-allowed border-transparent bg-muted/40 text-muted-foreground/40 opacity-50'
                                        "
                                        @click="form.seat_id = seat.id"
                                    >
                                        <Armchair class="size-4" />
                                        <span class="mt-0.5 block font-mono text-[8.5px] font-bold">
                                            {{ seat.is_available ? seat.label : 'TAKEN' }}
                                        </span>
                                    </button>
                                </div>
                            </article>
                        </div>
                    </div>

                    <InputError class="mt-3 text-xs" :message="form.errors.seat_id || enrollmentError" />
                    <p class="mt-4 flex items-center gap-2 text-xs text-muted-foreground font-medium">
                        <LockKeyhole class="size-3.5 shrink-0 text-primary" />
                        <span>Only chair availability is visible. Other students' names and private data remain hidden.</span>
                    </p>
                </section>

                <!-- Step 2: Student Identity Form -->
                <section class="paper-card h-fit p-6 md:p-8 lg:sticky lg:top-6">
                    <span class="eyebrow">Step 2</span>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight">Your student info</h2>

                    <div class="mt-5 grid gap-4">
                        <div class="grid gap-1.5">
                            <Label for="student-number" class="text-xs font-semibold">Student number</Label>
                            <Input
                                id="student-number"
                                v-model="form.student_number"
                                autocomplete="off"
                                placeholder="e.g. 2026-00001"
                                class="rounded-xl h-10 text-sm"
                            />
                            <InputError class="text-xs mt-1" :message="form.errors.student_number" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="first-name" class="text-xs font-semibold">First name</Label>
                            <Input id="first-name" v-model="form.first_name" autocomplete="given-name" class="rounded-xl h-10 text-sm" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="middle-name" class="text-xs font-semibold">Middle name <span class="text-muted-foreground font-normal">(optional)</span></Label>
                            <Input id="middle-name" v-model="form.middle_name" autocomplete="additional-name" class="rounded-xl h-10 text-sm" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="last-name" class="text-xs font-semibold">Last name</Label>
                            <Input id="last-name" v-model="form.last_name" autocomplete="family-name" class="rounded-xl h-10 text-sm" />
                        </div>

                        <label
                            class="group grid cursor-pointer place-items-center rounded-2xl border-2 border-dashed border-border/80 p-5 text-center transition-all hover:border-primary hover:bg-primary/5"
                        >
                            <Camera class="size-6 text-primary group-hover:scale-110 transition-transform" />
                            <span class="mt-2 text-xs font-bold text-foreground">
                                {{ form.photo?.name || 'Add profile photo' }}
                            </span>
                            <span class="text-[10px] text-muted-foreground mt-0.5">JPG, PNG or WebP · Up to 5MB</span>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                capture="user"
                                @change="form.photo = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            />
                        </label>
                        <InputError class="text-xs mt-1" :message="form.errors.photo" />
                    </div>

                    <Button
                        type="submit"
                        class="ink-button !h-11 !w-full mt-6 !rounded-xl"
                        :disabled="form.processing || !form.seat_id"
                    >
                        {{ form.processing ? 'Claiming chair...' : `Reserve ${selectedLabel || 'Chair'}` }}
                    </Button>
                </section>
            </form>
        </div>
    </main>
</template>
