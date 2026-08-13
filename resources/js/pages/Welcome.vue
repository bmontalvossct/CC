<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Check, ClipboardCheck, Grid3X3, QrCode } from 'lucide-vue-next';

const page = usePage<SharedData>();
const seats = [
    { name: 'Mia S.', status: 'Present' },
    { name: 'John R.', status: 'Present' },
    { name: 'Lea M.', status: 'Absent' },
    { name: 'Noah C.', status: 'Present' },
    { name: 'Aya P.', status: 'Present' },
    { name: 'Luis D.', status: 'Present' },
    { name: 'Sam T.', status: 'Present' },
    { name: 'Bea G.', status: 'Present' },
];
</script>

<template>
    <Head title="ClassCheck — Classroom management, simplified" />
    <main class="min-h-screen overflow-hidden bg-white text-[#1d1d1f] dark:bg-background dark:text-foreground">
        <nav class="sticky top-0 z-20 border-b border-black/[0.06] bg-white/90 backdrop-blur-xl dark:border-white/10 dark:bg-background/90">
            <div class="mx-auto flex h-12 max-w-6xl items-center justify-between px-5 lg:px-6">
                <Link href="/" class="flex items-center gap-2.5" aria-label="ClassCheck home">
                    <span class="flex size-7 items-center justify-center rounded-md bg-[#0071e3] text-white"
                        ><AppLogoIcon class-name="size-5"
                    /></span>
                    <span class="text-[15px] font-semibold tracking-[-0.01em]">ClassCheck</span>
                </Link>
                <div class="hidden items-center gap-8 text-xs text-[#1d1d1f] dark:text-foreground md:flex">
                    <a href="#product" class="transition-colors hover:text-[#0071e3]">Product</a>
                    <a href="#workflow" class="transition-colors hover:text-[#0071e3]">How it works</a>
                    <a href="#features" class="transition-colors hover:text-[#0071e3]">Features</a>
                </div>
                <div class="flex items-center gap-1">
                    <Link v-if="page.props.auth.user" href="/dashboard" class="ink-button !h-8 !px-4 !text-xs">Open dashboard</Link>
                    <template v-else>
                        <Link href="/login" class="px-3 py-2 text-xs transition-colors hover:text-[#0071e3]">Log in</Link>
                        <Link href="/register" class="ink-button !h-8 !px-4 !text-xs">Get started</Link>
                    </template>
                </div>
            </div>
        </nav>

        <section id="product" class="page-enter mx-auto max-w-6xl px-5 pb-20 pt-20 text-center sm:pt-28 lg:px-6 lg:pb-28">
            <p class="text-[15px] font-semibold text-[#0071e3]">Classroom clarity, from the first bell.</p>
            <h1 class="mx-auto mt-4 max-w-4xl text-5xl font-semibold leading-[1.02] tracking-[-0.04em] sm:text-7xl lg:text-[5.4rem]">
                Every student.<br /><span class="text-[#86868b]">Right where they belong.</span>
            </h1>
            <p class="mx-auto mt-7 max-w-2xl text-lg leading-relaxed text-[#6e6e73] sm:text-2xl sm:leading-9">
                Build your classroom, take attendance, and record scores in one focused workspace designed for the pace of teaching.
            </p>
            <div class="mt-9 flex flex-wrap justify-center gap-3">
                <Link href="/register" class="ink-button">Start your first class <ArrowRight class="size-4" /></Link>
                <a href="#workflow" class="secondary-button">See how it works</a>
            </div>
        </section>

        <section class="bg-[#f5f5f7] px-5 py-16 dark:bg-secondary/40 sm:py-24">
            <div class="mx-auto max-w-5xl">
                <div class="mx-auto max-w-4xl overflow-hidden rounded-2xl border border-black/[0.06] bg-white dark:border-white/10 dark:bg-card">
                    <div class="flex items-center justify-between border-b border-[#e5e7eb] px-5 py-4 dark:border-border sm:px-7">
                        <div class="text-left">
                            <p class="text-xs text-[#86868b]">Science 10</p>
                            <h2 class="mt-0.5 text-lg font-semibold">Narra · Room 204</h2>
                        </div>
                        <span class="rounded-full bg-[#f5f5f7] px-3 py-1.5 text-xs text-[#6e6e73] dark:bg-secondary">32 students</span>
                    </div>
                    <div class="grid gap-8 p-5 sm:p-8 md:grid-cols-[1fr_230px]">
                        <div>
                            <div class="mx-auto mb-7 h-1.5 w-1/2 rounded-full bg-[#d1d1d6]"></div>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <button
                                    v-for="seat in seats"
                                    :key="seat.name"
                                    type="button"
                                    class="rounded-lg border p-3 text-left transition-colors hover:border-[#0071e3]"
                                    :class="
                                        seat.status === 'Absent'
                                            ? 'border-[#d93025]/30 bg-[#d93025]/[0.06]'
                                            : 'border-[#e5e7eb] bg-white dark:bg-card'
                                    "
                                >
                                    <span class="block text-xs font-medium">{{ seat.name }}</span>
                                    <span class="mt-5 block text-[11px]" :class="seat.status === 'Absent' ? 'text-[#d93025]' : 'text-[#86868b]'">{{
                                        seat.status
                                    }}</span>
                                </button>
                            </div>
                        </div>
                        <aside class="border-t border-[#e5e7eb] pt-6 text-left dark:border-border md:border-l md:border-t-0 md:pl-7 md:pt-0">
                            <p class="text-xs font-semibold text-[#86868b]">TODAY</p>
                            <p class="mt-3 text-4xl font-semibold tracking-tight">97%</p>
                            <p class="mt-1 text-sm text-[#86868b]">Attendance rate</p>
                            <div class="mt-7 space-y-3 text-sm">
                                <div class="flex justify-between"><span class="text-[#86868b]">Present</span><strong>31</strong></div>
                                <div class="flex justify-between"><span class="text-[#86868b]">Absent</span><strong>1</strong></div>
                                <div class="flex justify-between"><span class="text-[#86868b]">Late</span><strong>0</strong></div>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </section>

        <section id="workflow" class="mx-auto max-w-6xl px-5 py-24 lg:px-6 lg:py-32">
            <div class="mx-auto max-w-3xl text-center">
                <p class="text-[15px] font-semibold text-[#0071e3]">A simpler routine</p>
                <h2 class="mt-3 text-4xl font-semibold leading-tight tracking-[-0.03em] sm:text-6xl">Less admin. More attention for your class.</h2>
            </div>
            <div id="features" class="mt-16 grid gap-px overflow-hidden rounded-lg border border-[#e5e7eb] bg-[#e5e7eb] md:grid-cols-3">
                <article class="bg-white p-7 dark:bg-card sm:p-9">
                    <Grid3X3 class="size-7 text-[#0071e3]" />
                    <h3 class="mt-10 text-2xl font-semibold">Shape the room.</h3>
                    <p class="mt-3 text-[15px] leading-6 text-[#6e6e73]">Arrange seat blocks and aisles to mirror the real classroom.</p>
                </article>
                <article class="bg-white p-7 dark:bg-card sm:p-9">
                    <QrCode class="size-7 text-[#0071e3]" />
                    <h3 class="mt-10 text-2xl font-semibold">Let students settle in.</h3>
                    <p class="mt-3 text-[15px] leading-6 text-[#6e6e73]">A single QR code lets students claim their seats in seconds.</p>
                </article>
                <article class="bg-white p-7 dark:bg-card sm:p-9">
                    <ClipboardCheck class="size-7 text-[#0071e3]" />
                    <h3 class="mt-10 text-2xl font-semibold">Move at roll-call speed.</h3>
                    <p class="mt-3 text-[15px] leading-6 text-[#6e6e73]">Everyone starts present. Tap only the exceptions and keep teaching.</p>
                </article>
            </div>
        </section>

        <section class="bg-[#f5f5f7] px-5 py-24 text-center dark:bg-secondary/40">
            <Check class="mx-auto size-8 text-[#0071e3]" />
            <h2 class="mx-auto mt-6 max-w-2xl text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">Ready before the next class begins.</h2>
            <p class="mx-auto mt-4 max-w-xl text-lg text-[#6e6e73]">Create your free teacher account and set up your first classroom.</p>
            <Link href="/register" class="ink-button mt-8">Get started <ArrowRight class="size-4" /></Link>
        </section>
    </main>
</template>
