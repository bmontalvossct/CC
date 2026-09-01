<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Clock, Flame, GraduationCap, Grid, ListFilter, MapPin, UserCheck, Users, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PhilippineHoliday {
    date: string;
    name: string;
    filipino_name: string;
    type: 'regular' | 'special_non_working';
    description: string;
}

interface ClassMeeting {
    section_id: number;
    section_name: string;
    subject_code: string;
    subject_title: string;
    room: string | null;
    schedule_type: 'lecture' | 'lab';
    starts_at: string;
    ends_at: string;
    enrolled_count: number;
    is_conducted: boolean;
    attendance_session_id?: number | null;
    present_count: number;
    late_count: number;
    excused_count: number;
    absent_count: number;
    status: 'conducted' | 'today_pending' | 'upcoming' | 'no_record';
}

interface CalendarDay {
    date: string;
    day_number: number;
    day_name: string;
    is_current_month: boolean;
    is_today: boolean;
    is_past: boolean;
    holiday: PhilippineHoliday | null;
    classes: ClassMeeting[];
}

interface Props {
    month: string;
    monthLabel: string;
    prevMonth: string;
    nextMonth: string;
    todayDate: string;
    selectedSectionId: number | null;
    sections: Array<{ id: number; name: string; subject_code: string; subject_title: string }>;
    currentTerm: {
        id: number;
        name: string;
        school_year: string;
        starts_on: string;
        ends_on: string;
    };
    calendarDays: CalendarDay[];
    todayClasses: ClassMeeting[];
    stats: {
        total_scheduled_month: number;
        total_conducted_month: number;
        conducted_percentage: number;
        total_present_month: number;
        today_classes_count: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Schedule & Calendar', href: '/schedule' }];

const currentView = ref<'month' | 'timetable' | 'agenda'>('month');
const selectedDay = ref<CalendarDay | null>(null);

const weekdayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const navigateMonth = (targetMonth: string) => {
    router.get(
        route('schedule.index'),
        {
            month: targetMonth,
            section_id: props.selectedSectionId ?? undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const filterBySection = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    const value = target.value ? parseInt(target.value, 10) : undefined;
    router.get(
        route('schedule.index'),
        {
            month: props.month,
            section_id: value,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const openDayModal = (day: CalendarDay) => {
    selectedDay.value = day;
};

const formatTime12h = (time24: string) => {
    if (!time24) return '';
    const [hStr, mStr] = time24.split(':');
    let hours = parseInt(hStr, 10);
    const minutes = mStr || '00';
    if (isNaN(hours)) return time24;
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    return `${hours}:${minutes} ${ampm}`;
};

const formatDatePretty = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const daysWithEvents = computed(() => {
    return props.calendarDays.filter((day) => day.is_current_month && (day.classes.length > 0 || day.holiday !== null));
});
</script>

<template>
    <Head :title="`Schedule · ${monthLabel}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="page-enter min-h-full bg-background pb-20 text-foreground">
            <!-- Top Header & Navigation -->
            <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="eyebrow">Academic Schedule & Routine</span>
                            <span class="rounded-md bg-primary/10 px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-primary">
                                {{ currentTerm.name }} · SY {{ currentTerm.school_year }}
                            </span>
                        </div>
                        <h1 class="mt-1 font-display text-3xl font-bold tracking-tight text-foreground sm:text-4xl">Teacher Schedule</h1>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Track your weekly teaching meetings, conducted class attendance, and Philippine official holidays.
                        </p>
                    </div>

                    <!-- Actions & Filters -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Section Filter Dropdown -->
                        <div class="relative">
                            <select
                                :value="selectedSectionId || ''"
                                class="shadow-2xs h-10 rounded-xl border border-input bg-card px-3 py-2 text-xs font-semibold text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                @change="filterBySection"
                            >
                                <option value="">All Sections</option>
                                <option v-for="sec in sections" :key="sec.id" :value="sec.id">{{ sec.subject_code }} · {{ sec.name }}</option>
                            </select>
                        </div>

                        <!-- View Switcher -->
                        <div class="shadow-2xs flex items-center rounded-xl border border-border bg-muted/40 p-1">
                            <button
                                type="button"
                                class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all"
                                :class="
                                    currentView === 'month'
                                        ? 'shadow-xs bg-background text-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="currentView = 'month'"
                            >
                                <Grid class="size-3.5" />
                                <span>Month</span>
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all"
                                :class="
                                    currentView === 'agenda'
                                        ? 'shadow-xs bg-background text-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="currentView = 'agenda'"
                            >
                                <ListFilter class="size-3.5" />
                                <span>Agenda</span>
                            </button>
                        </div>

                        <Link
                            href="/settings/academic-term"
                            class="shadow-2xs flex items-center gap-1.5 rounded-xl border border-border bg-card px-3.5 py-2 text-xs font-bold text-foreground transition-colors hover:bg-secondary"
                        >
                            <Clock class="size-3.5 text-primary" />
                            <span>Semester Settings</span>
                        </Link>
                    </div>
                </div>

                <!-- Stats Overview Bar -->
                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="shadow-2xs rounded-2xl border border-border/80 bg-card p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Class Delivery</span>
                            <span class="grid size-6 place-items-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                <UserCheck class="size-3.5" />
                            </span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="font-mono text-2xl font-bold text-foreground">
                                {{ stats.total_conducted_month }}
                            </span>
                            <span class="text-xs text-muted-foreground">/ {{ stats.total_scheduled_month }} scheduled</span>
                        </div>
                        <div class="mt-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                            {{ stats.conducted_percentage }}% conducted this month
                        </div>
                    </div>

                    <div class="shadow-2xs rounded-2xl border border-border/80 bg-card p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Total Attendance</span>
                            <span class="grid size-6 place-items-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <Users class="size-3.5" />
                            </span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="font-mono text-2xl font-bold text-foreground">
                                {{ stats.total_present_month }}
                            </span>
                            <span class="text-xs text-muted-foreground">student check-ins</span>
                        </div>
                        <div class="mt-1 text-[11px] text-muted-foreground">Present & late roll calls</div>
                    </div>

                    <div class="shadow-2xs rounded-2xl border border-border/80 bg-card p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Today's Routine</span>
                            <span class="grid size-6 place-items-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                <Flame class="size-3.5" />
                            </span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="font-mono text-2xl font-bold text-foreground">
                                {{ stats.today_classes_count }}
                            </span>
                            <span class="text-xs text-muted-foreground">classes scheduled</span>
                        </div>
                        <div class="mt-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                            {{ stats.today_classes_count > 0 ? 'Active teaching day' : 'No classes scheduled today' }}
                        </div>
                    </div>

                    <div class="shadow-2xs rounded-2xl border border-border/80 bg-card p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Active Term</span>
                            <span class="grid size-6 place-items-center rounded-lg bg-primary/10 text-primary">
                                <GraduationCap class="size-3.5" />
                            </span>
                        </div>
                        <div class="mt-2 truncate text-sm font-bold text-foreground">
                            {{ currentTerm.name }}
                        </div>
                        <div class="mt-1 truncate font-mono text-[11px] text-muted-foreground">
                            {{ currentTerm.starts_on }} – {{ currentTerm.ends_on }}
                        </div>
                    </div>
                </div>

                <!-- Calendar Navigation Bar -->
                <div
                    class="shadow-xs mt-6 flex flex-col gap-3 rounded-2xl border border-border/80 bg-card p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                            {{ monthLabel }}
                        </h2>
                        <span
                            v-if="month === todayDate.slice(0, 7)"
                            class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary"
                        >
                            Current Month
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="grid size-9 place-items-center rounded-xl border border-border bg-background text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            title="Previous Month"
                            @click="navigateMonth(prevMonth)"
                        >
                            <ChevronLeft class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border border-border bg-background px-3 py-1.5 text-xs font-bold text-foreground transition-colors hover:bg-secondary"
                            @click="navigateMonth(todayDate.slice(0, 7))"
                        >
                            Today
                        </button>
                        <button
                            type="button"
                            class="grid size-9 place-items-center rounded-xl border border-border bg-background text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            title="Next Month"
                            @click="navigateMonth(nextMonth)"
                        >
                            <ChevronRight class="size-4" />
                        </button>
                    </div>
                </div>

                <!-- Month Grid View -->
                <div v-if="currentView === 'month'" class="shadow-xs mt-4 overflow-hidden rounded-2xl border border-border/80 bg-card">
                    <!-- Weekday Header Row -->
                    <div
                        class="grid grid-cols-7 border-b border-border/80 bg-muted/30 text-center text-xs font-bold uppercase tracking-wider text-muted-foreground"
                    >
                        <div v-for="w in weekdayNames" :key="w" class="py-3">
                            {{ w }}
                        </div>
                    </div>

                    <!-- Calendar Days Grid -->
                    <div class="grid grid-cols-7 divide-x divide-y divide-border/60">
                        <div
                            v-for="day in calendarDays"
                            :key="day.date"
                            class="group relative min-h-[120px] p-2 transition-colors sm:min-h-[140px]"
                            :class="[
                                !day.is_current_month ? 'bg-muted/10 opacity-40' : 'bg-card hover:bg-secondary/20',
                                day.is_today ? 'ring-2 ring-inset ring-primary/60' : '',
                            ]"
                            @click="openDayModal(day)"
                        >
                            <!-- Day Header Number & Badges -->
                            <div class="flex items-center justify-between">
                                <span
                                    class="grid size-6 place-items-center rounded-full font-mono text-xs font-bold transition-all"
                                    :class="
                                        day.is_today
                                            ? 'shadow-xs bg-primary font-black text-primary-foreground'
                                            : day.holiday
                                              ? 'font-black text-rose-600 dark:text-rose-400'
                                              : 'text-foreground/80'
                                    "
                                >
                                    {{ day.day_number }}
                                </span>

                                <span v-if="day.is_today" class="hidden font-mono text-[9px] font-extrabold uppercase text-primary sm:inline-block">
                                    Today
                                </span>
                            </div>

                            <!-- Philippine Holiday Pill -->
                            <div
                                v-if="day.holiday"
                                class="mt-1.5 truncate rounded-lg border px-1.5 py-0.5 text-[10px] font-bold"
                                :class="
                                    day.holiday.type === 'regular'
                                        ? 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300'
                                        : 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                "
                                :title="day.holiday.name + ' · ' + day.holiday.description"
                            >
                                🇵🇭 {{ day.holiday.name }}
                            </div>

                            <!-- Class Cards on this Day -->
                            <div class="mt-1.5 space-y-1">
                                <div
                                    v-for="(cls, cIdx) in day.classes.slice(0, 3)"
                                    :key="cIdx"
                                    class="group/item shadow-2xs hover:shadow-xs flex cursor-pointer flex-col rounded-lg border p-1.5 text-[11px] transition-all hover:scale-[1.02]"
                                    :title="`${cls.subject_title || cls.subject_code} (${cls.subject_code}) · ${cls.section_name} · ${formatTime12h(cls.starts_at)} - ${formatTime12h(cls.ends_at)}${cls.room ? ' · ' + cls.room : ''}`"
                                    :class="[
                                        cls.is_conducted
                                            ? 'border-emerald-500/30 bg-emerald-500/5 dark:bg-emerald-950/20'
                                            : cls.status === 'today_pending'
                                              ? 'border-blue-500/40 bg-blue-500/10'
                                              : cls.status === 'no_record'
                                                ? 'border-amber-500/30 bg-amber-500/5'
                                                : 'border-border/80 bg-background/80',
                                    ]"
                                >
                                    <div class="flex items-center justify-between gap-1">
                                        <!-- Section name first -->
                                        <span class="truncate font-bold text-foreground">
                                            {{ cls.section_name }}
                                        </span>
                                        <span class="shrink-0 font-mono text-[9px] text-muted-foreground">
                                            {{ formatTime12h(cls.starts_at) }}
                                        </span>
                                    </div>

                                    <div class="mt-0.5 flex items-center justify-between text-[10px]">
                                        <!-- Course code / title second -->
                                        <span class="truncate text-muted-foreground group-hover/item:text-primary font-medium" :title="cls.subject_title || cls.subject_code">
                                            {{ cls.subject_title || cls.subject_code }}
                                        </span>

                                        <!-- Status Badge -->
                                        <span
                                            v-if="cls.is_conducted"
                                            class="inline-flex items-center font-mono font-bold text-emerald-600 dark:text-emerald-400"
                                            title="Attendance Recorded"
                                        >
                                            ✓ {{ cls.present_count + cls.late_count }}/{{ cls.enrolled_count }}
                                        </span>
                                        <span v-else-if="cls.status === 'today_pending'" class="font-mono font-bold text-blue-600 dark:text-blue-400">
                                            Ready
                                        </span>
                                        <span
                                            v-else-if="cls.status === 'no_record'"
                                            class="font-mono font-semibold text-amber-600 dark:text-amber-400"
                                        >
                                            No record
                                        </span>
                                    </div>
                                </div>

                                <div v-if="day.classes.length > 3" class="text-center font-mono text-[10px] font-bold text-primary">
                                    +{{ day.classes.length - 3 }} more
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Agenda List View -->
                <div v-else-if="currentView === 'agenda'" class="mt-4 space-y-4">
                    <div v-for="day in daysWithEvents" :key="day.date" class="shadow-2xs rounded-2xl border border-border/80 bg-card p-5">
                        <div class="flex flex-col gap-2 border-b border-border/60 pb-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div
                                    class="shadow-2xs grid size-10 place-items-center rounded-xl font-mono text-base font-bold"
                                    :class="day.is_today ? 'bg-primary text-primary-foreground' : 'bg-secondary text-foreground'"
                                >
                                    {{ day.day_number }}
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-foreground">
                                        {{ formatDatePretty(day.date) }}
                                    </h3>
                                    <span v-if="day.is_today" class="font-mono text-xs font-bold uppercase text-primary"> Today </span>
                                </div>
                            </div>

                            <!-- Philippine Holiday Flag -->
                            <div
                                v-if="day.holiday"
                                class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold"
                                :class="
                                    day.holiday.type === 'regular'
                                        ? 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300'
                                        : 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                "
                            >
                                <span>🇵🇭</span>
                                <span>{{ day.holiday.name }} ({{ day.holiday.filipino_name }})</span>
                            </div>
                        </div>

                        <!-- Class List on this Day -->
                        <div v-if="day.classes.length > 0" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="(cls, cIdx) in day.classes"
                                :key="cIdx"
                                class="shadow-2xs flex flex-col justify-between rounded-xl border p-4 transition-all hover:bg-secondary/40"
                                :class="[
                                    cls.is_conducted
                                        ? 'border-emerald-500/30 bg-emerald-500/5'
                                        : cls.status === 'today_pending'
                                          ? 'border-blue-500/30 bg-blue-500/5'
                                          : 'border-border/80 bg-background',
                                ]"
                            >
                                <div>
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <h4 class="text-sm font-bold text-foreground">{{ cls.section_name }}</h4>
                                            <span class="font-mono text-xs font-bold text-primary">{{ cls.subject_code }}</span>
                                        </div>

                                        <span
                                            class="rounded-md px-2 py-0.5 font-mono text-[10px] font-bold uppercase"
                                            :class="
                                                cls.schedule_type === 'lab' ? 'bg-amber-500/10 text-amber-600' : 'bg-secondary text-muted-foreground'
                                            "
                                        >
                                            {{ cls.schedule_type }}
                                        </span>
                                    </div>

                                    <p class="mt-1 truncate text-xs text-muted-foreground">{{ cls.subject_title }}</p>

                                    <div class="mt-3 flex items-center gap-3 text-xs text-muted-foreground">
                                        <span class="flex items-center gap-1">
                                            <Clock class="size-3.5 text-primary" />
                                            <span>{{ formatTime12h(cls.starts_at) }} – {{ formatTime12h(cls.ends_at) }}</span>
                                        </span>
                                        <span v-if="cls.room" class="flex items-center gap-1">
                                            <MapPin class="size-3.5" />
                                            <span>{{ cls.room }}</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-4 flex items-center justify-between border-t border-border/60 pt-3">
                                    <!-- Conduct Status -->
                                    <div
                                        v-if="cls.is_conducted"
                                        class="flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                                    >
                                        <UserCheck class="size-4" />
                                        <span>Conducted · {{ cls.present_count + cls.late_count }} attended</span>
                                    </div>
                                    <div v-else-if="cls.status === 'today_pending'" class="text-xs font-semibold text-blue-600 dark:text-blue-400">
                                        ⏳ Scheduled Today
                                    </div>
                                    <div v-else-if="cls.status === 'no_record'" class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                                        ⚠️ No attendance record
                                    </div>
                                    <div v-else class="text-xs font-semibold text-muted-foreground">📅 Upcoming</div>

                                    <!-- Quick Actions -->
                                    <div class="flex items-center gap-1.5">
                                        <Link
                                            :href="`/sections/${cls.section_id}/attendance?date=${day.date}`"
                                            class="shadow-2xs rounded-lg border border-border bg-background px-2.5 py-1 text-[11px] font-bold text-foreground hover:bg-secondary"
                                        >
                                            {{ cls.is_conducted ? 'Attendance' : 'Roll Call' }}
                                        </Link>
                                        <Link
                                            :href="`/sections/${cls.section_id}`"
                                            class="shadow-2xs rounded-lg border border-border bg-background px-2.5 py-1 text-[11px] font-bold text-foreground hover:bg-secondary"
                                        >
                                            Floor
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Day & Class Details Drawer Modal -->
            <div
                v-if="selectedDay"
                v-modal-focus
                class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 animate-in fade-in"
            >
                <div class="w-full max-w-xl rounded-3xl border border-border/80 bg-card p-6 shadow-2xl animate-in zoom-in-95">
                    <div class="flex items-start justify-between border-b border-border/80 pb-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-primary">Day Schedule Details</span>
                                <span
                                    v-if="selectedDay.is_today"
                                    class="rounded-md bg-primary/10 px-2 py-0.5 font-mono text-[10px] font-bold uppercase text-primary"
                                >
                                    Today
                                </span>
                            </div>
                            <h3 class="mt-1 text-xl font-bold text-foreground">
                                {{ formatDatePretty(selectedDay.date) }}
                            </h3>
                        </div>
                        <button
                            type="button"
                            class="grid size-8 place-items-center rounded-xl text-muted-foreground hover:bg-secondary hover:text-foreground"
                            @click="selectedDay = null"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <!-- Philippine Holiday Banner -->
                    <div
                        v-if="selectedDay.holiday"
                        class="shadow-2xs mt-4 rounded-2xl border p-4"
                        :class="
                            selectedDay.holiday.type === 'regular'
                                ? 'border-rose-500/30 bg-rose-500/10 text-rose-900 dark:text-rose-200'
                                : 'border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-200'
                        "
                    >
                        <div class="flex items-center gap-2 text-sm font-bold">
                            <span>🇵🇭</span>
                            <span>{{ selectedDay.holiday.name }}</span>
                            <span class="font-mono text-xs opacity-80">({{ selectedDay.holiday.filipino_name }})</span>
                        </div>
                        <p class="mt-1 text-xs opacity-90">{{ selectedDay.holiday.description }}</p>
                    </div>

                    <!-- Scheduled Classes -->
                    <div class="mt-4 space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            Classes Scheduled ({{ selectedDay.classes.length }})
                        </h4>

                        <div
                            v-if="selectedDay.classes.length === 0"
                            class="rounded-2xl border border-dashed border-border/80 p-6 text-center text-xs text-muted-foreground"
                        >
                            No teaching classes scheduled for this day.
                        </div>

                        <div
                            v-for="(cls, idx) in selectedDay.classes"
                            :key="idx"
                            class="shadow-2xs rounded-2xl border p-4 transition-colors"
                            :class="[
                                cls.is_conducted
                                    ? 'border-emerald-500/30 bg-emerald-500/5'
                                    : cls.status === 'today_pending'
                                      ? 'border-blue-500/30 bg-blue-500/5'
                                      : 'border-border/80 bg-background',
                            ]"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-bold text-primary">{{ cls.subject_code }}</span>
                                        <span class="text-sm font-bold text-foreground">{{ cls.section_name }}</span>
                                        <span
                                            class="rounded-md bg-secondary px-2 py-0.5 font-mono text-[10px] font-bold uppercase text-muted-foreground"
                                        >
                                            {{ cls.schedule_type }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-muted-foreground">{{ cls.subject_title }}</p>
                                </div>

                                <div class="text-right">
                                    <div class="font-mono text-xs font-bold text-foreground">
                                        {{ formatTime12h(cls.starts_at) }} – {{ formatTime12h(cls.ends_at) }}
                                    </div>
                                    <div v-if="cls.room" class="mt-0.5 text-[11px] text-muted-foreground">📍 {{ cls.room }}</div>
                                </div>
                            </div>

                            <!-- Conduct Status Breakdown -->
                            <div class="mt-3 rounded-xl border border-border/60 bg-card p-3">
                                <div v-if="cls.is_conducted" class="space-y-1.5">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-bold text-emerald-600 dark:text-emerald-400">✓ Class Conducted</span>
                                        <span class="font-mono font-bold text-foreground">
                                            {{ cls.present_count + cls.late_count }} / {{ cls.enrolled_count }} attended
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 text-[11px] text-muted-foreground">
                                        <span class="font-semibold text-emerald-600">{{ cls.present_count }} Present</span>
                                        <span class="font-semibold text-amber-600">{{ cls.late_count }} Late</span>
                                        <span class="font-semibold text-blue-600">{{ cls.excused_count }} Excused</span>
                                        <span class="font-semibold text-rose-600">{{ cls.absent_count }} Absent</span>
                                    </div>
                                </div>
                                <div v-else-if="cls.status === 'today_pending'" class="text-xs font-semibold text-blue-600 dark:text-blue-400">
                                    ⏳ Scheduled for today. Ready for attendance roll call and classroom activities.
                                </div>
                                <div v-else-if="cls.status === 'no_record'" class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                                    ⚠️ No attendance session recorded on this date.
                                </div>
                                <div v-else class="text-xs text-muted-foreground">📅 Upcoming class schedule within the academic semester.</div>
                            </div>

                            <!-- 1-Click Action Buttons -->
                            <div class="mt-3 flex flex-wrap items-center gap-2 pt-2">
                                <Link
                                    :href="`/sections/${cls.section_id}/attendance?date=${selectedDay.date}`"
                                    class="shadow-2xs rounded-xl border border-border bg-background px-3 py-1.5 text-xs font-bold text-foreground transition-colors hover:bg-secondary"
                                >
                                    {{ cls.is_conducted ? 'View Attendance' : 'Take Attendance' }}
                                </Link>
                                <Link
                                    :href="`/sections/${cls.section_id}`"
                                    class="shadow-2xs rounded-xl border border-border bg-background px-3 py-1.5 text-xs font-bold text-foreground transition-colors hover:bg-secondary"
                                >
                                    Seat Plan & Floor
                                </Link>
                                <Link
                                    :href="`/sections/${cls.section_id}/recitation`"
                                    class="shadow-2xs rounded-xl border border-border bg-background px-3 py-1.5 text-xs font-bold text-foreground transition-colors hover:bg-secondary"
                                >
                                    Oral Recitation
                                </Link>
                                <Link
                                    :href="`/sections/${cls.section_id}/reports/gradebook`"
                                    class="shadow-2xs rounded-xl border border-border bg-background px-3 py-1.5 text-xs font-bold text-foreground transition-colors hover:bg-secondary"
                                >
                                    Gradebook
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end border-t border-border/80 pt-4">
                        <Button variant="outline" class="rounded-xl text-xs font-bold" @click="selectedDay = null"> Close </Button>
                    </div>
                </div>
            </div>
        </main>
    </AppLayout>
</template>
