<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Armchair, Camera, CheckCircle2, LockKeyhole } from 'lucide-vue-next';
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
    <Head :title="`Join ${section.name}`" />
    <main class="min-h-screen bg-[#ede7d8] px-4 py-8 text-stone-950 md:py-14">
        <div class="mx-auto max-w-5xl">
            <header class="relative overflow-hidden rounded-[2rem] bg-stone-950 p-7 text-white shadow-2xl md:p-10">
                <div
                    class="absolute inset-0 opacity-20 [background-image:linear-gradient(rgba(255,255,255,.15)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.15)_1px,transparent_1px)] [background-size:28px_28px]"
                />
                <div class="relative">
                    <p class="font-mono text-xs font-bold uppercase tracking-[.28em] text-amber-400">Find your place</p>
                    <h1 class="mt-3 font-serif text-4xl font-black md:text-6xl">
                        {{ section.subject_code }} <span class="text-amber-400">/</span> {{ section.name }}
                    </h1>
                    <p class="mt-3 max-w-2xl text-stone-300">
                        {{ section.subject_title }}<span v-if="section.room"> - {{ section.room }}</span>
                    </p>
                </div>
            </header>

            <div v-if="page.props.flash?.success" class="mt-6 rounded-2xl border border-emerald-300 bg-emerald-50 p-6 text-center">
                <CheckCircle2 class="mx-auto size-10 text-emerald-600" />
                <h2 class="mt-3 font-serif text-2xl font-bold">Chair reserved.</h2>
                <p class="mt-1 text-emerald-800">{{ page.props.flash.success }}</p>
            </div>
            <div v-else-if="!section.enrollment_open" class="mt-6 rounded-2xl border border-amber-300 bg-amber-50 p-8 text-center">
                <LockKeyhole class="mx-auto size-9 text-amber-700" />
                <h2 class="mt-3 font-serif text-2xl font-bold">Enrollment is closed.</h2>
                <p class="mt-2 text-stone-600">Your teacher must open this room before a chair can be claimed.</p>
            </div>

            <form v-else class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]" @submit.prevent="submit">
                <section class="min-w-0 rounded-3xl border border-stone-300 bg-[#fffdf7] p-5 md:p-7">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="font-mono text-xs font-bold uppercase tracking-[.2em] text-amber-700">Step 1</p>
                            <h2 class="mt-1 font-serif text-3xl font-bold">Choose your chair.</h2>
                        </div>
                        <span v-if="selectedLabel" class="rounded-full bg-emerald-100 px-3 py-1 font-mono text-xs font-bold text-emerald-800">{{
                            selectedLabel
                        }}</span>
                    </div>
                    <div
                        class="my-7 rounded-lg bg-stone-800 py-2 text-center font-mono text-[10px] font-bold uppercase tracking-[.3em] text-stone-100"
                    >
                        Front / teaching wall
                    </div>
                    <div
                        class="grid min-w-[500px] gap-7 overflow-x-auto pb-3"
                        :style="{
                            gridTemplateColumns: `repeat(${Math.max(1, ...section.blocks.map((block: any) => block.block_column))}, minmax(190px, 1fr))`,
                        }"
                    >
                        <article
                            v-for="block in section.blocks"
                            :key="block.id"
                            class="rounded-xl border border-dashed border-stone-300 bg-stone-100/60 p-3"
                            :style="{ gridColumn: block.block_column, gridRow: block.block_row }"
                        >
                            <p class="mb-2 font-mono text-[10px] font-bold uppercase tracking-wider text-stone-500">Block {{ block.label }}</p>
                            <div class="grid gap-2" :style="{ gridTemplateColumns: `repeat(${block.internal_columns}, 1fr)` }">
                                <button
                                    v-for="seat in block.seats"
                                    :key="seat.id"
                                    type="button"
                                    :disabled="!seat.is_available"
                                    class="aspect-square rounded-lg border p-1 transition"
                                    :class="
                                        seat.id === form.seat_id
                                            ? 'scale-105 border-emerald-800 bg-emerald-600 text-white shadow-lg'
                                            : seat.is_available
                                              ? 'border-stone-300 bg-white text-stone-500 hover:-translate-y-1 hover:border-amber-600 hover:text-amber-700'
                                              : 'cursor-not-allowed border-transparent bg-stone-200 text-stone-300 opacity-60'
                                    "
                                    @click="form.seat_id = seat.id"
                                >
                                    <Armchair class="mx-auto size-5" /><span class="mt-1 block font-mono text-[8px] font-bold">{{
                                        seat.is_available ? seat.label : 'TAKEN'
                                    }}</span>
                                </button>
                            </div>
                        </article>
                    </div>
                    <InputError class="mt-3" :message="form.errors.seat_id || enrollmentError" />
                    <p class="mt-5 flex items-start gap-2 text-xs text-stone-500">
                        <LockKeyhole class="mt-0.5 size-3.5 shrink-0" /> Only chair availability is shown. No other student's identity or photo is
                        exposed.
                    </p>
                </section>

                <section class="h-fit rounded-3xl border border-stone-300 bg-[#fffdf7] p-6 lg:sticky lg:top-6">
                    <p class="font-mono text-xs font-bold uppercase tracking-[.2em] text-amber-700">Step 2</p>
                    <h2 class="mt-1 font-serif text-3xl font-bold">Add your name.</h2>
                    <div class="mt-6 grid gap-4">
                        <div class="grid gap-1.5">
                            <Label>Student number</Label
                            ><Input v-model="form.student_number" autocomplete="off" placeholder="2026-00001" /><InputError
                                :message="form.errors.student_number"
                            />
                        </div>
                        <div class="grid gap-1.5"><Label>First name</Label><Input v-model="form.first_name" autocomplete="given-name" /></div>
                        <div class="grid gap-1.5">
                            <Label>Middle name <span class="text-stone-400">(optional)</span></Label
                            ><Input v-model="form.middle_name" autocomplete="additional-name" />
                        </div>
                        <div class="grid gap-1.5"><Label>Last name</Label><Input v-model="form.last_name" autocomplete="family-name" /></div>
                        <label
                            class="group grid cursor-pointer place-items-center rounded-xl border border-dashed border-stone-300 p-5 text-center hover:border-amber-600 hover:bg-amber-50"
                            ><Camera class="size-6 text-amber-700" /><span class="mt-2 text-sm font-semibold">{{
                                form.photo?.name || 'Add a profile photo'
                            }}</span
                            ><span class="text-[11px] text-stone-500">JPG, PNG or WebP - up to 5 MB</span
                            ><input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                capture="user"
                                @change="form.photo = ($event.target as HTMLInputElement).files?.[0] ?? null" /></label
                        ><InputError :message="form.errors.photo" />
                    </div>
                    <Button class="mt-6 h-12 w-full bg-stone-950 text-white hover:bg-amber-800" :disabled="form.processing || !form.seat_id"
                        >Reserve {{ selectedLabel || 'a chair' }}</Button
                    >
                </section>
            </form>
        </div>
    </main>
</template>
