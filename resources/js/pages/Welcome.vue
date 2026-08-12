<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
const page = usePage<SharedData>();

import { ArrowRight, Check, ClipboardCheck, Grid3X3, QrCode } from 'lucide-vue-next';
</script>

<template>
    <Head title="ClassCheck — Roll call at a glance" />
    <main class="min-h-screen overflow-hidden bg-[#f7f1e3] text-[#18352f]">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-6 lg:px-8">
            <div class="flex items-center gap-3">
                <span class="flex size-11 items-center justify-center rounded-2xl bg-[#18352f] text-[#f4c65d]"
                    ><AppLogoIcon class-name="size-8" /></span
                ><span class="font-display text-2xl font-bold">ClassCheck</span>
            </div>
            <div class="flex items-center gap-2">
                <Link v-if="page.props.auth.user" href="/dashboard" class="ink-button">Open dashboard <ArrowRight class="size-4" /></Link>
                <template v-else
                    ><Link href="/login" class="rounded-xl px-4 py-2.5 font-bold">Log in</Link
                    ><Link href="/register" class="ink-button">Start a class <ArrowRight class="size-4" /></Link
                ></template>
            </div>
        </nav>

        <section class="relative mx-auto grid max-w-7xl items-center gap-12 px-5 pb-20 pt-12 lg:grid-cols-[1.03fr_.97fr] lg:px-8 lg:pb-28 lg:pt-20">
            <div>
                <p class="eyebrow">Built for the moment names are called</p>
                <h1 class="font-display mt-5 max-w-3xl text-6xl font-bold leading-[.94] tracking-[-.045em] sm:text-7xl lg:text-[6.4rem]">
                    Your classroom, <span class="text-[#b85d3d]">at a glance.</span>
                </h1>
                <p class="mt-7 max-w-xl text-lg leading-relaxed text-[#52635d]">
                    Turn the real seating plan into a fast attendance and score-entry desk. One tap for absence. One Tab key to move to the next
                    student.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link href="/register" class="ink-button px-5 py-3.5">Create your teacher account <ArrowRight class="size-4" /></Link
                    ><a href="#workflow" class="rounded-xl border border-[#bfb49e] bg-white/55 px-5 py-3.5 font-bold">See the workflow</a>
                </div>
                <div class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm font-bold text-[#52635d]">
                    <span class="flex items-center gap-2"><Check class="size-4 text-[#2c715d]" /> QR seat claiming</span
                    ><span class="flex items-center gap-2"><Check class="size-4 text-[#2c715d]" /> Present by default</span
                    ><span class="flex items-center gap-2"><Check class="size-4 text-[#2c715d]" /> CSV-ready records</span>
                </div>
            </div>

            <div class="relative">
                <div class="absolute -inset-8 rotate-3 rounded-[3rem] bg-[#f4c65d]/45 blur-2xl"></div>
                <div class="relative rounded-[2rem] border border-[#1b4037]/20 bg-[#18352f] p-5 shadow-[0_35px_80px_rgba(24,53,47,.28)] sm:p-7">
                    <div class="mb-6 flex items-center justify-between text-[#fff8e8]">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[.2em] text-[#f4c65d]">Room 204 · Front</p>
                            <h2 class="font-display mt-1 text-2xl font-bold">Science 10 — Narra</h2>
                        </div>
                        <span class="rounded-full bg-white/10 px-3 py-1 text-xs">32 students</span>
                    </div>
                    <div class="rounded-[1.3rem] bg-[#f7f1e3] p-4 sm:p-6">
                        <div class="mx-auto mb-6 h-3 w-2/3 rounded-full bg-[#b85d3d]/70"></div>
                        <div class="grid grid-cols-4 gap-3">
                            <div
                                v-for="seat in 20"
                                :key="seat"
                                class="group relative aspect-[.9] rounded-xl border border-[#c8bea8] bg-[#fffdf7] p-2 shadow-[0_5px_0_#d9ceb5]"
                            >
                                <span class="absolute left-1/2 top-[-5px] h-2 w-6 -translate-x-1/2 rounded-full bg-[#315c51]"></span>
                                <span
                                    class="flex h-full items-center justify-center rounded-lg text-xs font-bold"
                                    :class="seat === 7 || seat === 16 ? 'bg-[#f1d3c8] text-[#9c452e]' : 'bg-[#dcebe5] text-[#245246]'"
                                    >{{ seat === 7 || seat === 16 ? 'Absent' : 'Present' }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
                <div class="absolute -bottom-7 -left-4 flex items-center gap-3 rounded-2xl border border-[#d8d0bd] bg-[#fffdf7] p-4 shadow-xl">
                    <QrCode class="size-7 text-[#b85d3d]" />
                    <div><strong class="block">Scan. Pick. Sit.</strong><span class="text-xs text-[#66736e]">Seats update instantly</span></div>
                </div>
            </div>
        </section>

        <section id="workflow" class="border-t border-[#d8d0bd] bg-[#fffaf0] py-20">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <p class="eyebrow">A familiar classroom rhythm</p>
                <h2 class="font-display mt-3 max-w-2xl text-4xl font-bold sm:text-5xl">Less spreadsheet. More teaching.</h2>
                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    <article class="paper-card p-6">
                        <Grid3X3 class="size-7 text-[#b85d3d]" />
                        <h3 class="mt-5 text-2xl font-bold">Shape the room</h3>
                        <p class="mt-2 leading-relaxed text-muted-foreground">
                            Build grouped chair blocks with aisles and see the exact room every time.
                        </p>
                    </article>
                    <article class="paper-card p-6">
                        <ClipboardCheck class="size-7 text-[#2c715d]" />
                        <h3 class="mt-5 text-2xl font-bold">Tap the exceptions</h3>
                        <p class="mt-2 leading-relaxed text-muted-foreground">Everyone starts present. Uncheck only the chairs that are absent.</p>
                    </article>
                    <article class="paper-card p-6">
                        <QrCode class="size-7 text-[#8a5a20]" />
                        <h3 class="mt-5 text-2xl font-bold">Move at roll-call speed</h3>
                        <p class="mt-2 leading-relaxed text-muted-foreground">
                            Students claim seats by QR; scores follow the same chair order with Tab.
                        </p>
                    </article>
                </div>
            </div>
        </section>
    </main>
</template>
