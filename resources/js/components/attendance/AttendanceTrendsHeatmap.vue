<script setup lang="ts">
import {
    Activity,
    AlertTriangle,
    Calendar,
    CheckCircle2,
    Clock,
    TrendingDown,
    TrendingUp,
    UserCheck,
    UserX,
} from 'lucide-vue-next';
import { computed } from 'vue';

export interface DayTrend {
    day: string;
    name: string;
    sessions: number;
    present: number;
    absent: number;
    late: number;
    total_marks: number;
    attendance_rate: number | null;
}

const props = defineProps<{
    trends: DayTrend[];
}>();

const activeDays = computed(() => {
    return (props.trends || []).filter((d) => d.sessions > 0);
});

const bestDay = computed(() => {
    if (activeDays.value.length === 0) return null;
    return [...activeDays.value].sort((a, b) => (b.attendance_rate ?? 0) - (a.attendance_rate ?? 0))[0];
});

const worstDay = computed(() => {
    if (activeDays.value.length === 0) return null;
    return [...activeDays.value].sort((a, b) => (a.attendance_rate ?? 0) - (b.attendance_rate ?? 0))[0];
});

const getRateColor = (rate: number | null) => {
    if (rate === null) return 'bg-secondary text-muted-foreground border-border';
    if (rate >= 90) return 'bg-emerald-500/15 border-emerald-500/30 text-emerald-600 dark:text-emerald-400';
    if (rate >= 80) return 'bg-sky-500/15 border-sky-500/30 text-sky-600 dark:text-sky-400';
    if (rate >= 70) return 'bg-amber-500/15 border-amber-500/30 text-amber-600 dark:text-amber-400';
    return 'bg-rose-500/15 border-rose-500/30 text-rose-600 dark:text-rose-400';
};

const getBarColor = (rate: number | null) => {
    if (rate === null) return 'bg-muted-foreground/20';
    if (rate >= 90) return 'bg-emerald-500';
    if (rate >= 80) return 'bg-sky-500';
    if (rate >= 70) return 'bg-amber-500';
    return 'bg-rose-500';
};
</script>

<template>
    <section class="paper-card p-5 sm:p-6" aria-label="Day of week attendance trends">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/70 pb-4">
            <div class="flex items-center gap-2.5">
                <span class="grid size-9 place-items-center rounded-xl bg-primary/10 text-primary">
                    <Activity class="size-5" />
                </span>
                <div>
                    <h3 class="text-base font-bold tracking-tight text-foreground sm:text-lg">
                        Attendance Trends & Absenteeism Analytics
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Day-of-week attendance distribution and absenteeism patterns
                    </p>
                </div>
            </div>

            <!-- Insights Badges -->
            <div v-if="activeDays.length > 0" class="flex flex-wrap items-center gap-2">
                <div
                    v-if="bestDay && bestDay.attendance_rate !== null"
                    class="flex items-center gap-1.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                >
                    <TrendingUp class="size-3.5" />
                    <span>Peak: {{ bestDay.name }} ({{ bestDay.attendance_rate }}%)</span>
                </div>

                <div
                    v-if="worstDay && worstDay.attendance_rate !== null && worstDay.name !== bestDay?.name"
                    class="flex items-center gap-1.5 rounded-lg border border-rose-500/30 bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-600 dark:text-rose-400"
                >
                    <TrendingDown class="size-3.5" />
                    <span>Low: {{ worstDay.name }} ({{ worstDay.attendance_rate }}%)</span>
                </div>
            </div>
        </div>

        <!-- Weekly Days Grid -->
        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
            <div
                v-for="trend in trends"
                :key="trend.day"
                class="flex flex-col justify-between rounded-xl border p-3.5 transition-all"
                :class="trend.sessions > 0 ? 'border-border/80 bg-card hover:border-primary/50' : 'border-border/40 bg-secondary/10 opacity-60'"
            >
                <div>
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-xs text-foreground uppercase tracking-wider">{{ trend.name }}</span>
                        <span
                            class="rounded-md border px-1.5 py-0.5 font-mono text-[10px] font-bold"
                            :class="getRateColor(trend.attendance_rate)"
                        >
                            {{ trend.attendance_rate === null ? '—' : `${trend.attendance_rate}%` }}
                        </span>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-secondary">
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :class="getBarColor(trend.attendance_rate)"
                            :style="{ width: `${trend.attendance_rate || 0}%` }"
                        />
                    </div>
                </div>

                <div class="mt-3 border-t border-border/60 pt-2 text-[11px] text-muted-foreground">
                    <div v-if="trend.sessions > 0" class="space-y-0.5">
                        <div class="flex items-center justify-between font-mono">
                            <span>Sessions:</span>
                            <strong class="text-foreground">{{ trend.sessions }}</strong>
                        </div>
                        <div class="flex items-center justify-between font-mono text-[10px]">
                            <span class="text-emerald-600 dark:text-emerald-400">Pres: {{ trend.present }}</span>
                            <span class="text-rose-600 dark:text-rose-400">Abs: {{ trend.absent }}</span>
                        </div>
                    </div>
                    <div v-else class="text-center italic text-muted-foreground/70 text-[10px]">
                        No sessions
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
