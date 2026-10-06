<script setup lang="ts">
import OctoMascot from '@/components/OctoMascot.vue';
import OctoSpinner from '@/components/OctoSpinner.vue';
import { AlertCircle, Check, CheckCircle2, Clock, Maximize2, Minimize2, Sparkles, Square, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

export type CheckProgressItem = {
    id: string | number;
    title: string;
    subtitle?: string;
    filename?: string;
    status: 'pending' | 'evaluating' | 'success' | 'failed';
    score?: number | string | null;
    maxPoints?: number | string;
    remarks?: string | null;
    error?: string | null;
};

const props = defineProps<{
    show: boolean;
    isRunning: boolean;
    total: number;
    currentProgress: number;
    currentItemName?: string;
    items: CheckProgressItem[];
    activityTitle?: string;
    maxPoints?: number | string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'stop'): void;
}>();

const isMinimized = ref(false);
const logContainerRef = ref<HTMLElement | null>(null);

const successCount = computed(() => props.items.filter((i) => i.status === 'success').length);
const failedCount = computed(() => props.items.filter((i) => i.status === 'failed').length);
const percentage = computed(() => {
    if (props.total <= 0) return 0;
    return Math.min(100, Math.round((props.currentProgress / props.total) * 100));
});

const isComplete = computed(() => !props.isRunning && props.currentProgress >= props.total);

// Auto-scroll logs container to newest item
watch(
    () => props.currentProgress,
    async () => {
        await nextTick();
        if (logContainerRef.value) {
            logContainerRef.value.scrollTop = logContainerRef.value.scrollHeight;
        }
    },
);
</script>

<template>
    <!-- Minimized Floating Widget -->
    <div
        v-if="show && isMinimized"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-2xl border border-border/90 bg-card p-3.5 shadow-2xl duration-200 animate-in slide-in-from-bottom-5"
    >
        <div class="flex items-center gap-3">
            <OctoMascot size="sm" :is-thinking="isRunning" :forced-state="isComplete ? 'happy' : null" />
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-foreground">
                        {{ isRunning ? 'Octo Checking All' : 'Checking Finished' }}
                    </span>
                    <span class="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary"> {{ currentProgress }} / {{ total }} </span>
                </div>
                <p class="max-w-[180px] truncate text-[11px] text-muted-foreground">
                    {{ isRunning ? currentItemName || 'Evaluating...' : `${successCount} graded, ${failedCount} failed` }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-1.5 border-l border-border/80 pl-2">
            <button
                type="button"
                class="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                title="Expand full progress"
                @click="isMinimized = false"
            >
                <Maximize2 class="size-4" />
            </button>
            <button
                v-if="!isRunning"
                type="button"
                class="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                title="Dismiss"
                @click="emit('close')"
            >
                <X class="size-4" />
            </button>
        </div>
    </div>

    <!-- Full Progress Modal Overlay -->
    <div
        v-else-if="show && !isMinimized"
        class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 duration-200 animate-in fade-in"
        role="dialog"
        aria-modal="true"
        aria-label="Octo Check All Progress"
    >
        <div
            class="paper-card relative flex w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-border/90 bg-card shadow-2xl duration-200 animate-in zoom-in-95"
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-border/80 bg-secondary/30 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary/15 text-primary">
                        <Sparkles class="size-5" />
                    </div>
                    <div>
                        <h3 class="flex items-center gap-2 text-sm font-bold text-foreground">
                            <span>{{ isComplete ? 'Octo Check All Completed' : 'Octo AI Submission Autochecker' }}</span>
                            <span
                                class="rounded-md border px-2 py-0.5 text-[10px] font-bold"
                                :class="
                                    isRunning
                                        ? 'border-primary/40 bg-primary/10 text-primary'
                                        : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                "
                            >
                                {{ isRunning ? 'Evaluating...' : 'Finished' }}
                            </span>
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            {{ activityTitle || 'Evaluating attached student outputs with grading rubrics' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        title="Minimize to floating pill"
                        @click="isMinimized = true"
                    >
                        <Minimize2 class="size-4" />
                    </button>
                    <button
                        v-if="!isRunning"
                        type="button"
                        class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        title="Close"
                        @click="emit('close')"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="space-y-6 p-6">
                <!-- Mascot & Current Target Banner -->
                <div class="shadow-2xs flex items-center gap-4 rounded-2xl border border-border/80 bg-secondary/20 p-4">
                    <OctoMascot size="lg" :is-thinking="isRunning" :forced-state="isComplete ? 'happy' : null" />

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-xs font-bold text-foreground">
                                {{
                                    isRunning
                                        ? currentItemName
                                            ? `Evaluating: ${currentItemName}`
                                            : 'Preparing next submission...'
                                        : 'Batch Evaluation Finished'
                                }}
                            </span>
                            <span class="font-mono text-xs font-bold text-primary"> {{ percentage }}% </span>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-secondary">
                            <div
                                class="h-full bg-gradient-to-r from-primary to-violet-500 transition-all duration-300 ease-out"
                                :style="{ width: `${percentage}%` }"
                            />
                        </div>

                        <div class="mt-2 flex items-center justify-between text-[11px] text-muted-foreground">
                            <span>{{ currentProgress }} of {{ total }} submissions processed</span>
                            <span v-if="isRunning" class="inline-flex items-center font-medium text-primary">
                                <OctoSpinner size="xs" class="mr-1" /> Octo is analyzing...
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Live Metrics Summary Cards -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-xl border border-border/80 bg-secondary/15 p-3 text-center">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Total Outputs</div>
                        <div class="mt-0.5 font-mono text-lg font-bold text-foreground">{{ total }}</div>
                    </div>
                    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-center">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Graded</div>
                        <div class="mt-0.5 font-mono text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ successCount }}</div>
                    </div>
                    <div class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-center">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">Failed / Errors</div>
                        <div class="mt-0.5 font-mono text-lg font-bold text-rose-600 dark:text-rose-400">{{ failedCount }}</div>
                    </div>
                </div>

                <!-- Live Evaluation Stream / Activity Log -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs font-bold text-muted-foreground">
                        <span>Live Evaluation Feed</span>
                        <span>{{ items.filter((i) => i.status !== 'pending').length }} / {{ items.length }} logged</span>
                    </div>

                    <div ref="logContainerRef" class="max-h-56 space-y-1.5 overflow-y-auto rounded-xl border border-border/80 bg-zinc-950/60 p-2.5">
                        <div
                            v-for="item in items"
                            :key="item.id"
                            class="flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-xs transition-all"
                            :class="[
                                item.status === 'evaluating'
                                    ? 'border border-primary/40 bg-primary/10 font-medium'
                                    : item.status === 'success'
                                      ? 'border border-emerald-500/20 bg-emerald-500/5'
                                      : item.status === 'failed'
                                        ? 'border border-rose-500/30 bg-rose-500/10'
                                        : 'border border-transparent bg-card/40 opacity-60',
                            ]"
                        >
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span v-if="item.status === 'evaluating'" class="shrink-0 text-primary">
                                    <OctoSpinner size="xs" />
                                </span>
                                <span v-else-if="item.status === 'success'" class="shrink-0 text-emerald-500">
                                    <CheckCircle2 class="size-4" />
                                </span>
                                <span v-else-if="item.status === 'failed'" class="shrink-0 text-rose-500">
                                    <AlertCircle class="size-4" />
                                </span>
                                <span v-else class="shrink-0 text-muted-foreground">
                                    <Clock class="size-3.5" />
                                </span>

                                <div class="min-w-0">
                                    <div class="truncate font-bold text-foreground">
                                        {{ item.title }}
                                        <span v-if="item.subtitle" class="text-[11px] font-normal text-muted-foreground">
                                            ({{ item.subtitle }})
                                        </span>
                                    </div>
                                    <p v-if="item.remarks" class="max-w-sm truncate text-[11px] text-muted-foreground">
                                        {{ item.remarks }}
                                    </p>
                                    <p v-else-if="item.error" class="max-w-sm truncate text-[11px] font-semibold text-rose-500">
                                        {{ item.error }}
                                    </p>
                                </div>
                            </div>

                            <div class="shrink-0 text-right">
                                <span
                                    v-if="item.status === 'success' && item.score !== null && item.score !== undefined"
                                    class="font-mono font-bold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ item.score }} pts
                                </span>
                                <span v-else-if="item.status === 'evaluating'" class="text-[10px] font-bold uppercase text-primary">
                                    Grading...
                                </span>
                                <span v-else-if="item.status === 'failed'" class="text-[10px] font-bold uppercase text-rose-500"> Failed </span>
                                <span v-else class="text-[10px] text-muted-foreground"> Pending </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between border-t border-border/80 bg-secondary/30 px-6 py-3.5">
                <div class="text-xs text-muted-foreground">
                    <span v-if="isRunning">Octo is processing batch evaluations sequentially.</span>
                    <span v-else>All scores and feedback have been synchronized to the gradebook.</span>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        v-if="isRunning"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-2 text-xs font-bold text-rose-600 transition-colors hover:bg-rose-500/20"
                        @click="emit('stop')"
                    >
                        <Square class="size-3.5" />
                        <span>Stop Checking</span>
                    </button>

                    <button
                        v-else
                        type="button"
                        class="shadow-xs inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2 text-xs font-bold text-primary-foreground transition-transform hover:bg-primary/90 active:scale-95"
                        @click="emit('close')"
                    >
                        <Check class="size-4" />
                        <span>Done & Review Gradebook</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
