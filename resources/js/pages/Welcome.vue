<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, ClipboardCheck, Grid3X3, QrCode, Sparkles } from 'lucide-vue-next';

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
    <Head title="ClassCheck — Intelligent Classroom Seating & Management" />
    <main class="min-h-screen overflow-hidden bg-background text-foreground antialiased">
        <!-- Navigation -->
        <nav class="sticky top-0 z-30 border-b border-border/80 bg-background/80 backdrop-blur-xl">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-5 lg:px-8">
                <Link href="/" class="flex items-center gap-2.5" aria-label="ClassCheck home">
                    <span class="flex size-9 items-center justify-center rounded-xl border border-border/80 bg-card p-0.5 shadow-sm">
                        <AppLogoIcon class-name="size-7" />
                    </span>
                    <span class="text-base font-medium tracking-tight">ClassCheck</span>
                </Link>
                <div class="hidden items-center gap-8 text-xs font-semibold text-muted-foreground md:flex">
                    <a href="#product" class="transition-colors hover:text-primary">Product</a>
                    <a href="#workflow" class="transition-colors hover:text-primary">How it works</a>
                    <a href="#features" class="transition-colors hover:text-primary">Features</a>
                </div>
                <div class="flex items-center gap-2">
                    <Link v-if="page.props.auth.user" href="/dashboard" prefetch="hover" class="ink-button !h-9 !rounded-xl !px-4 !text-xs">
                        Open dashboard
                    </Link>
                    <template v-else>
                        <Link
                            href="/login"
                            prefetch="hover"
                            class="px-3.5 py-2 text-xs font-semibold text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Log in
                        </Link>
                        <Link href="/register" prefetch="hover" class="ink-button !h-9 !rounded-xl !px-4 !text-xs"> Get started </Link>
                    </template>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section id="product" class="page-enter relative mx-auto max-w-6xl px-5 pb-16 pt-16 text-center sm:pt-24 lg:px-8 lg:pb-24">
            <div
                class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3.5 py-1 text-xs font-semibold text-primary"
            >
                <Sparkles class="size-3.5" />
                <span>Classroom clarity, from the first bell</span>
            </div>

            <h1 class="mx-auto mt-6 max-w-4xl text-5xl font-extrabold leading-[1.05] tracking-tight sm:text-7xl lg:text-[5rem]">
                Every student.<br />
                <span class="bg-gradient-to-r from-primary to-blue-600 bg-clip-text text-transparent">Right where they belong.</span>
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-muted-foreground sm:text-xl">
                Build your classroom seating floor, take instant roll-call attendance, and record scores in one focused workspace designed for the
                speed of teaching.
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <Link href="/register" prefetch="hover" class="ink-button !h-11 !rounded-xl !px-6 !text-sm">
                    <span>Start your first class</span>
                    <ArrowRight class="size-4" />
                </Link>
                <a href="#workflow" class="secondary-button !h-11 !rounded-xl !px-6 !text-sm"> See how it works </a>
            </div>
        </section>

        <!-- Live Classroom Preview Showcase -->
        <section class="border-y border-border/80 bg-secondary/40 px-5 py-16 sm:py-24">
            <div class="mx-auto max-w-5xl">
                <div class="paper-card overflow-hidden border-border/80 p-0 shadow-xl">
                    <div class="flex items-center justify-between border-b border-border/80 bg-secondary/50 px-6 py-4">
                        <div class="text-left">
                            <span class="badge-primary font-mono text-[10px]">CS 101</span>
                            <h2 class="mt-1 text-base font-bold">Narra · Room 204</h2>
                        </div>
                        <span class="badge-muted font-bold">32 students enrolled</span>
                    </div>

                    <div class="grid gap-8 p-6 sm:p-8 md:grid-cols-[1fr_240px]">
                        <div>
                            <div class="mx-auto mb-6 h-2 w-1/2 rounded-full bg-zinc-900 dark:bg-zinc-700" />
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div
                                    v-for="seat in seats"
                                    :key="seat.name"
                                    class="rounded-xl border-2 p-3 text-left transition-all"
                                    :class="
                                        seat.status === 'Absent'
                                            ? 'border-rose-500/80 bg-rose-500/10 text-rose-600 dark:text-rose-400'
                                            : 'border-primary/70 bg-primary/10 text-primary'
                                    "
                                >
                                    <span class="block text-xs font-bold">{{ seat.name }}</span>
                                    <span class="mt-4 block font-mono text-[10px] font-extrabold uppercase">{{ seat.status }}</span>
                                </div>
                            </div>
                        </div>

                        <aside class="border-t border-border/80 pt-6 text-left md:border-l md:border-t-0 md:pl-6 md:pt-0">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Today's session</span>
                            <p class="mt-2 text-4xl font-extrabold tracking-tight text-primary">97%</p>
                            <p class="mt-0.5 text-xs font-medium text-muted-foreground">Attendance rate</p>
                            <div class="mt-6 space-y-2.5 text-xs">
                                <div class="flex justify-between font-medium">
                                    <span class="text-muted-foreground">Present</span>
                                    <strong class="text-emerald-600 dark:text-emerald-400">31</strong>
                                </div>
                                <div class="flex justify-between font-medium">
                                    <span class="text-muted-foreground">Absent</span>
                                    <strong class="text-rose-600 dark:text-rose-400">1</strong>
                                </div>
                                <div class="flex justify-between font-medium">
                                    <span class="text-muted-foreground">Late</span>
                                    <strong>0</strong>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </section>

        <!-- Workflow & Features -->
        <section id="workflow" class="mx-auto max-w-6xl px-5 py-20 lg:px-8 lg:py-28">
            <div class="mx-auto max-w-3xl text-center">
                <span class="eyebrow">A simpler routine</span>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-5xl">Less admin. More attention for your class.</h2>
            </div>

            <div id="features" class="mt-14 grid gap-6 md:grid-cols-3">
                <article class="paper-card p-8 transition-all hover:border-primary/40 hover:shadow-md">
                    <span class="grid size-12 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <Grid3X3 class="size-6" />
                    </span>
                    <h3 class="mt-6 text-xl font-bold">Shape the room.</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Arrange seat blocks, rows, columns, and walkways to mirror your exact physical classroom.
                    </p>
                </article>

                <article class="paper-card p-8 transition-all hover:border-primary/40 hover:shadow-md">
                    <span class="grid size-12 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <QrCode class="size-6" />
                    </span>
                    <h3 class="mt-6 text-xl font-bold">Let students settle in.</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Project a single QR code on the wall. Students scan and claim their chairs in seconds.
                    </p>
                </article>

                <article class="paper-card p-8 transition-all hover:border-primary/40 hover:shadow-md">
                    <span class="grid size-12 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <ClipboardCheck class="size-6" />
                    </span>
                    <h3 class="mt-6 text-xl font-bold">Move at roll-call speed.</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Everyone initializes as present. Tap only the empty seats and start teaching without delay.
                    </p>
                </article>
            </div>
        </section>

        <!-- Call to Action Banner -->
        <section class="border-t border-border/80 bg-secondary/40 px-5 py-20 text-center">
            <CheckCircle2 class="mx-auto size-10 text-primary" />
            <h2 class="mx-auto mt-4 max-w-xl text-3xl font-extrabold tracking-tight sm:text-4xl">Ready before the next class begins.</h2>
            <p class="mx-auto mt-3 max-w-md text-sm text-muted-foreground">Create your free teacher account and set up your first classroom today.</p>
            <Link href="/register" prefetch="hover" class="ink-button mt-6 !h-11 !rounded-xl !px-6 !text-sm">
                <span>Get started now</span>
                <ArrowRight class="size-4" />
            </Link>
        </section>
    </main>
</template>
