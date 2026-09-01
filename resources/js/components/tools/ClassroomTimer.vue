<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Bell,
    BellOff,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Clock,
    Flag,
    Maximize2,
    Minimize2,
    Pause,
    Play,
    Plus,
    RotateCcw,
    Sparkles,
    Timer as TimerIcon,
    Volume2,
    VolumeX,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
    }>(),
    {
        modelValue: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', val: boolean): void;
}>();

type Mode = 'timer' | 'stopwatch';
const activeMode = ref<Mode>('timer');
const isMinimized = ref(false);
const isMuted = ref(false);

// Countdown Timer State
const timerDurationSeconds = ref(300); // default 5 minutes
const timerRemainingSeconds = ref(300);
const isTimerRunning = ref(false);
const isTimerExpired = ref(false);
let timerInterval: ReturnType<typeof setInterval> | null = null;

// Stopwatch State
const stopwatchElapsedSeconds = ref(0);
const isStopwatchRunning = ref(false);
const stopwatchLaps = ref<{ lapNumber: number; time: string; diff: string }[]>([]);
let stopwatchInterval: ReturnType<typeof setInterval> | null = null;

// Presets in seconds
const presets = [
    { label: '1m', seconds: 60 },
    { label: '2m', seconds: 120 },
    { label: '3m', seconds: 180 },
    { label: '5m', seconds: 300 },
    { label: '10m', seconds: 600 },
    { label: '15m', seconds: 900 },
    { label: '20m', seconds: 1200 },
    { label: '30m', seconds: 1800 },
];

// Web Audio API Synthesizer Chime (Offline, 0 assets)
const playChime = () => {
    if (isMuted.value) return;
    try {
        const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();

        const notes = [523.25, 659.25, 783.99, 1046.5]; // C5, E5, G5, C6 arpeggio
        notes.forEach((freq, idx) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, ctx.currentTime + idx * 0.12);

            gain.gain.setValueAtTime(0.01, ctx.currentTime + idx * 0.12);
            gain.gain.exponentialRampToValueAtTime(0.3, ctx.currentTime + idx * 0.12 + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + idx * 0.12 + 0.4);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start(ctx.currentTime + idx * 0.12);
            osc.stop(ctx.currentTime + idx * 0.12 + 0.45);
        });
    } catch {
        // audio context blocked or unavailable
    }
};

// Formatter Helpers
const formatTime = (totalSeconds: number): string => {
    const mins = Math.floor(totalSeconds / 60);
    const secs = totalSeconds % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
};

const formatStopwatch = (totalCentiseconds: number): string => {
    const mins = Math.floor(totalCentiseconds / 6000);
    const secs = Math.floor((totalCentiseconds % 6000) / 100);
    const cs = totalCentiseconds % 100;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}.${String(cs).padStart(2, '0')}`;
};

// Timer Actions
const selectPreset = (secs: number) => {
    isTimerRunning.value = false;
    if (timerInterval) clearInterval(timerInterval);
    timerDurationSeconds.value = secs;
    timerRemainingSeconds.value = secs;
    isTimerExpired.value = false;
};

const toggleTimer = () => {
    if (isTimerRunning.value) {
        pauseTimer();
    } else {
        startTimer();
    }
};

const startTimer = () => {
    if (timerRemainingSeconds.value <= 0) {
        timerRemainingSeconds.value = timerDurationSeconds.value;
        isTimerExpired.value = false;
    }
    isTimerRunning.value = true;
    isTimerExpired.value = false;

    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        if (timerRemainingSeconds.value > 0) {
            timerRemainingSeconds.value--;
        } else {
            isTimerRunning.value = false;
            isTimerExpired.value = true;
            if (timerInterval) clearInterval(timerInterval);
            playChime();
        }
    }, 1000);
};

const pauseTimer = () => {
    isTimerRunning.value = false;
    if (timerInterval) clearInterval(timerInterval);
};

const resetTimer = () => {
    pauseTimer();
    timerRemainingSeconds.value = timerDurationSeconds.value;
    isTimerExpired.value = false;
};

const addOneMinute = () => {
    timerRemainingSeconds.value += 60;
    timerDurationSeconds.value = Math.max(timerDurationSeconds.value, timerRemainingSeconds.value);
    isTimerExpired.value = false;
};

// Stopwatch Actions
const toggleStopwatch = () => {
    if (isStopwatchRunning.value) {
        pauseStopwatch();
    } else {
        startStopwatch();
    }
};

const startStopwatch = () => {
    isStopwatchRunning.value = true;
    if (stopwatchInterval) clearInterval(stopwatchInterval);
    stopwatchInterval = setInterval(() => {
        stopwatchElapsedSeconds.value++;
    }, 10);
};

const pauseStopwatch = () => {
    isStopwatchRunning.value = false;
    if (stopwatchInterval) clearInterval(stopwatchInterval);
};

const resetStopwatch = () => {
    pauseStopwatch();
    stopwatchElapsedSeconds.value = 0;
    stopwatchLaps.value = [];
};

const recordLap = () => {
    if (stopwatchElapsedSeconds.value === 0) return;
    const currentFormatted = formatStopwatch(stopwatchElapsedSeconds.value);
    const prevElapsed = stopwatchLaps.value.length > 0
        ? stopwatchLaps.value.reduce((_, lap) => stopwatchElapsedSeconds.value, 0)
        : 0;
    
    stopwatchLaps.value.unshift({
        lapNumber: stopwatchLaps.value.length + 1,
        time: currentFormatted,
        diff: `+${formatStopwatch(stopwatchElapsedSeconds.value - prevElapsed)}`,
    });
};

// Progress percentage for timer circle
const timerProgress = computed(() => {
    if (timerDurationSeconds.value <= 0) return 0;
    return Math.max(0, Math.min(100, ((timerDurationSeconds.value - timerRemainingSeconds.value) / timerDurationSeconds.value) * 100));
});

onBeforeUnmount(() => {
    if (timerInterval) clearInterval(timerInterval);
    if (stopwatchInterval) clearInterval(stopwatchInterval);
});

const closeWidget = () => {
    emit('update:modelValue', false);
};
</script>

<template>
    <!-- Floating Widget Container -->
    <aside
        v-if="modelValue"
        class="fixed bottom-5 right-5 z-50 transition-all duration-200 print:hidden"
        :class="isMinimized ? 'w-auto' : 'w-full max-w-sm'"
        aria-label="Classroom timer and stopwatch"
    >
        <!-- Minimized Floating Pill -->
        <div
            v-if="isMinimized"
            class="flex items-center gap-3 rounded-full border border-border/90 bg-card/95 px-4 py-2.5 shadow-2xl backdrop-blur-md transition-all hover:bg-card"
        >
            <div
                class="flex items-center gap-2 font-mono text-sm font-bold"
                :class="isTimerExpired ? 'animate-pulse text-rose-500' : 'text-foreground'"
            >
                <Clock class="size-4 text-primary" />
                <span>
                    {{ activeMode === 'timer' ? formatTime(timerRemainingSeconds) : formatStopwatch(stopwatchElapsedSeconds) }}
                </span>
            </div>

            <div class="flex items-center gap-1 border-l border-border/80 pl-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-7 rounded-full text-foreground hover:bg-secondary"
                    :title="activeMode === 'timer' ? (isTimerRunning ? 'Pause' : 'Start') : (isStopwatchRunning ? 'Pause' : 'Start')"
                    @click="activeMode === 'timer' ? toggleTimer() : toggleStopwatch()"
                >
                    <Pause v-if="activeMode === 'timer' ? isTimerRunning : isStopwatchRunning" class="size-3.5" />
                    <Play v-else class="size-3.5 fill-current" />
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-7 rounded-full text-muted-foreground hover:text-foreground"
                    title="Expand timer"
                    @click="isMinimized = false"
                >
                    <Maximize2 class="size-3.5" />
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-7 rounded-full text-muted-foreground hover:text-foreground"
                    title="Close"
                    @click="closeWidget"
                >
                    <X class="size-3.5" />
                </Button>
            </div>
        </div>

        <!-- Full Expanded Card -->
        <div
            v-else
            class="paper-card relative overflow-hidden border border-border/90 bg-card p-5 shadow-2xl duration-200 animate-in fade-in zoom-in-95"
            :class="isTimerExpired ? 'ring-2 ring-rose-500/80 bg-rose-500/5' : ''"
        >
            <!-- Header Bar -->
            <div class="flex items-center justify-between border-b border-border/70 pb-3">
                <!-- Mode Switcher Tabs -->
                <div class="flex items-center rounded-lg bg-secondary/80 p-0.5">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-md px-3 py-1 text-xs font-semibold transition-all"
                        :class="activeMode === 'timer' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeMode = 'timer'"
                    >
                        <TimerIcon class="size-3.5" />
                        <span>Timer</span>
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-md px-3 py-1 text-xs font-semibold transition-all"
                        :class="activeMode === 'stopwatch' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeMode = 'stopwatch'"
                    >
                        <Clock class="size-3.5" />
                        <span>Stopwatch</span>
                    </button>
                </div>

                <!-- Control Buttons -->
                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-7 rounded-lg text-muted-foreground hover:text-foreground"
                        :title="isMuted ? 'Unmute chime' : 'Mute chime'"
                        @click="isMuted = !isMuted"
                    >
                        <VolumeX v-if="isMuted" class="size-3.5 text-rose-500" />
                        <Volume2 v-else class="size-3.5" />
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-7 rounded-lg text-muted-foreground hover:text-foreground"
                        title="Minimize"
                        @click="isMinimized = true"
                    >
                        <Minimize2 class="size-3.5" />
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-7 rounded-lg text-muted-foreground hover:text-foreground"
                        title="Close"
                        @click="closeWidget"
                    >
                        <X class="size-3.5" />
                    </Button>
                </div>
            </div>

            <!-- Mode 1: Countdown Timer -->
            <div v-if="activeMode === 'timer'" class="mt-4 space-y-4">
                <!-- Large Digital Display -->
                <div class="text-center">
                    <div
                        class="font-mono text-5xl font-black tracking-tight text-foreground transition-colors sm:text-6xl"
                        :class="isTimerExpired ? 'text-rose-500 animate-pulse' : isTimerRunning ? 'text-primary' : 'text-foreground'"
                    >
                        {{ formatTime(timerRemainingSeconds) }}
                    </div>
                    <div class="mt-1 text-xs font-medium text-muted-foreground">
                        <span v-if="isTimerExpired" class="font-bold text-rose-500 uppercase tracking-wider">Time's Up!</span>
                        <span v-else-if="isTimerRunning">Counting down...</span>
                        <span v-else>Set duration below</span>
                    </div>

                    <!-- Subtle Progress Bar -->
                    <div class="mx-auto mt-3 h-1.5 w-48 overflow-hidden rounded-full bg-secondary">
                        <div
                            class="h-full bg-primary transition-all duration-300"
                            :style="{ width: `${100 - timerProgress}%` }"
                        />
                    </div>
                </div>

                <!-- Quick Presets Grid -->
                <div>
                    <div class="mb-1.5 text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Presets</div>
                    <div class="grid grid-cols-4 gap-1.5">
                        <button
                            v-for="preset in presets"
                            :key="preset.seconds"
                            type="button"
                            class="rounded-lg border px-2 py-1.5 font-mono text-xs font-semibold transition-all"
                            :class="[
                                timerDurationSeconds === preset.seconds && !isTimerRunning
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border/70 bg-secondary/30 text-foreground hover:bg-secondary hover:border-border',
                            ]"
                            @click="selectPreset(preset.seconds)"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                </div>

                <!-- Action Controls -->
                <div class="flex items-center gap-2 border-t border-border/70 pt-3">
                    <Button
                        type="button"
                        class="ink-button flex-1 !h-10 text-xs font-bold"
                        :class="isTimerRunning ? '!bg-amber-600 hover:!bg-amber-700 text-white' : ''"
                        @click="toggleTimer"
                    >
                        <Pause v-if="isTimerRunning" class="mr-1.5 size-4" />
                        <Play v-else class="mr-1.5 size-4 fill-current" />
                        <span>{{ isTimerRunning ? 'Pause' : isTimerExpired ? 'Restart' : 'Start Timer' }}</span>
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        class="h-10 rounded-xl px-3 text-xs font-medium"
                        title="Add 1 Minute"
                        @click="addOneMinute"
                    >
                        <Plus class="mr-1 size-3.5" />
                        <span>1m</span>
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        class="h-10 rounded-xl px-3 text-xs font-medium text-muted-foreground hover:text-foreground"
                        title="Reset"
                        @click="resetTimer"
                    >
                        <RotateCcw class="size-3.5" />
                    </Button>
                </div>
            </div>

            <!-- Mode 2: Stopwatch -->
            <div v-else class="mt-4 space-y-4">
                <!-- Large Digital Display -->
                <div class="text-center">
                    <div class="font-mono text-4xl font-black tracking-tight text-foreground sm:text-5xl">
                        {{ formatStopwatch(stopwatchElapsedSeconds) }}
                    </div>
                    <div class="mt-1 text-xs font-medium text-muted-foreground">
                        <span>{{ isStopwatchRunning ? 'Stopwatch active' : 'Ready' }}</span>
                    </div>
                </div>

                <!-- Laps Record List -->
                <div v-if="stopwatchLaps.length > 0" class="max-h-28 space-y-1 overflow-y-auto rounded-lg border border-border/70 bg-secondary/20 p-2 text-xs">
                    <div
                        v-for="lap in stopwatchLaps"
                        :key="lap.lapNumber"
                        class="flex items-center justify-between font-mono text-[11px]"
                    >
                        <span class="text-muted-foreground">Lap {{ lap.lapNumber }}</span>
                        <span class="font-semibold text-foreground">{{ lap.time }}</span>
                    </div>
                </div>

                <!-- Action Controls -->
                <div class="flex items-center gap-2 border-t border-border/70 pt-3">
                    <Button
                        type="button"
                        class="ink-button flex-1 !h-10 text-xs font-bold"
                        :class="isStopwatchRunning ? '!bg-amber-600 hover:!bg-amber-700 text-white' : ''"
                        @click="toggleStopwatch"
                    >
                        <Pause v-if="isStopwatchRunning" class="mr-1.5 size-4" />
                        <Play v-else class="mr-1.5 size-4 fill-current" />
                        <span>{{ isStopwatchRunning ? 'Pause' : 'Start Stopwatch' }}</span>
                    </Button>

                    <Button
                        v-if="isStopwatchRunning"
                        type="button"
                        variant="outline"
                        class="h-10 rounded-xl px-3 text-xs font-medium"
                        title="Record Lap"
                        @click="recordLap"
                    >
                        <Flag class="mr-1 size-3.5" />
                        <span>Lap</span>
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        class="h-10 rounded-xl px-3 text-xs font-medium text-muted-foreground hover:text-foreground"
                        title="Reset"
                        @click="resetStopwatch"
                    >
                        <RotateCcw class="size-3.5" />
                    </Button>
                </div>
            </div>
        </div>
    </aside>
</template>
