<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useClassReminders, type TodayRemindersResponse } from '@/composables/useClassReminders';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    Bell,
    BellOff,
    BellRing,
    Calendar,
    CheckCircle2,
    Clock,
    ExternalLink,
    Info,
    Laptop,
    MapPin,
    RefreshCw,
    Sparkles,
    Volume2,
    VolumeX,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    todayReminders?: TodayRemindersResponse;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Settings',
        href: '/settings/profile',
    },
    {
        title: 'Notifications',
        href: '/settings/notifications',
    },
];

const {
    isSupported,
    permission,
    enabled,
    leadTimeMinutes,
    soundEnabled,
    stickyNotification,
    todayClasses,
    todayMeta,
    isChecking,
    lastChecked,
    testSent,
    setEnabled,
    setLeadTime,
    setSoundEnabled,
    setStickyNotification,
    requestNotificationPermission,
    playAlertChime,
    sendTestNotification,
    fetchTodayReminders,
} = useClassReminders();

const isRequestingPermission = ref(false);

const handleRequestPermission = async () => {
    isRequestingPermission.value = true;
    try {
        await requestNotificationPermission();
    } finally {
        isRequestingPermission.value = false;
    }
};

const leadTimeOptions = [
    { value: 5, label: '5 minutes before', desc: 'Last minute warning' },
    { value: 10, label: '10 minutes before', desc: 'Short walk to room' },
    { value: 15, label: '15 minutes before', desc: 'Standard transition' },
    { value: 20, label: '20 minutes before', desc: 'Recommended · Plenty of time to prepare', recommended: true },
    { value: 30, label: '30 minutes before', desc: 'Early preparation' },
    { value: 45, label: '45 minutes before', desc: 'Extended lead time' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Class Reminders & Notifications" />

        <SettingsLayout>
            <div class="space-y-8">
                <!-- Page Header -->
                <div>
                    <HeadingSmall
                        title="Class Reminders & Push Notifications"
                        description="Receive Windows status notifications and audio alerts 20 minutes before your scheduled classes."
                    />
                </div>

                <!-- Windows Integration & Permission Status Card -->
                <Card class="border-border/80 shadow-sm">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <Laptop class="size-5" />
                                </div>
                                <div>
                                    <CardTitle class="text-base">Windows Status & Desktop Notifications</CardTitle>
                                    <CardDescription>
                                        Alerts appear directly in the Windows Action Center & taskbar status area.
                                    </CardDescription>
                                </div>
                            </div>

                            <!-- Live Permission Badge -->
                            <div>
                                <span
                                    v-if="permission === 'granted'"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    <CheckCircle2 class="size-3.5" />
                                    Active & Allowed
                                </span>
                                <span
                                    v-else-if="permission === 'denied'"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-destructive/10 px-3 py-1 text-xs font-semibold text-destructive"
                                >
                                    <AlertCircle class="size-3.5" />
                                    Blocked in Browser / OS
                                </span>
                                <span
                                    v-else-if="isSupported"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-600 dark:text-amber-400"
                                >
                                    <AlertTriangle class="size-3.5" />
                                    Permission Required
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
                                >
                                    Not Supported
                                </span>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="space-y-4">
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            When enabled, ClassCheck dispatches native notifications that slide into the bottom-right corner of your Windows desktop.
                            Clicking the notification automatically brings ClassCheck into focus and opens your class attendance roster.
                        </p>

                        <!-- If permission needed -->
                        <div
                            v-if="permission !== 'granted' && isSupported"
                            class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5"
                        >
                            <div class="flex items-start gap-2.5">
                                <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                <div class="space-y-0.5">
                                    <p class="text-xs font-medium text-amber-900 dark:text-amber-200">
                                        Enable Windows notifications for ClassCheck
                                    </p>
                                    <p class="text-[11px] text-amber-700/80 dark:text-amber-300/80">
                                        Your browser needs your permission to show desktop alert banners.
                                    </p>
                                </div>
                            </div>

                            <Button
                                size="sm"
                                class="shrink-0 rounded-xl"
                                :disabled="isRequestingPermission"
                                @click="handleRequestPermission"
                            >
                                <Bell class="mr-1.5 size-3.5" />
                                {{ isRequestingPermission ? 'Requesting...' : 'Allow Notifications' }}
                            </Button>
                        </div>

                        <!-- If blocked -->
                        <div
                            v-else-if="permission === 'denied'"
                            class="rounded-xl border border-destructive/30 bg-destructive/10 p-3.5 space-y-1.5"
                        >
                            <div class="flex items-center gap-2 text-xs font-semibold text-destructive">
                                <AlertCircle class="size-4" />
                                Notifications are blocked for this site
                            </div>
                            <p class="text-[11px] text-destructive/90">
                                To enable them in Windows & Chrome/Edge: click the lock or settings icon next to the address bar (<code class="rounded bg-background/50 px-1 py-0.5">localhost</code>), change Notifications to <strong>Allow</strong>, and refresh the page.
                            </p>
                        </div>

                        <!-- Windows Tips Note -->
                        <div class="flex items-start gap-2 rounded-xl bg-secondary/40 p-3 text-[11px] text-muted-foreground">
                            <Info class="mt-0.5 size-3.5 shrink-0 text-primary" />
                            <span>
                                <strong>Windows Status Tip:</strong> Ensure Windows <strong>Focus Assist</strong> or <strong>Do Not Disturb</strong> is not set to Priority-only or Alarms-only if you wish to hear sound alerts while working in other applications.
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Reminder Options Card -->
                <Card class="border-border/80 shadow-sm">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <Clock class="size-5" />
                                </div>
                                <div>
                                    <CardTitle class="text-base">Reminder Preferences</CardTitle>
                                    <CardDescription>Configure when and how class alerts are delivered.</CardDescription>
                                </div>
                            </div>

                            <!-- Master Toggle -->
                            <div class="flex items-center gap-2">
                                <Label for="reminder-toggle" class="text-xs font-medium text-muted-foreground">
                                    {{ enabled ? 'Reminders On' : 'Reminders Off' }}
                                </Label>
                                <button
                                    id="reminder-toggle"
                                    type="button"
                                    role="switch"
                                    :aria-checked="enabled"
                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden"
                                    :class="enabled ? 'bg-primary' : 'bg-muted-foreground/30'"
                                    @click="setEnabled(!enabled)"
                                >
                                    <span
                                        class="pointer-events-none inline-block size-5 transform rounded-full bg-background shadow-lg ring-0 transition duration-200 ease-in-out"
                                        :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                                    />
                                </button>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="space-y-6">
                        <!-- Lead Time Selector -->
                        <div class="space-y-3">
                            <div>
                                <Label class="text-xs font-semibold text-foreground">Reminder Lead Time</Label>
                                <p class="text-[11px] text-muted-foreground">
                                    How far in advance of each class start time should the Windows notification trigger?
                                </p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                                <button
                                    v-for="opt in leadTimeOptions"
                                    :key="opt.value"
                                    type="button"
                                    class="relative flex flex-col items-start rounded-xl border p-3 text-left transition-all hover:bg-muted/50 focus:outline-hidden"
                                    :class="[
                                        leadTimeMinutes === opt.value
                                            ? 'border-primary bg-primary/5 text-foreground ring-1 ring-primary'
                                            : 'border-border/80 bg-card text-muted-foreground',
                                    ]"
                                    @click="setLeadTime(opt.value)"
                                >
                                    <div class="flex w-full items-center justify-between">
                                        <span class="text-xs font-semibold text-foreground">{{ opt.label }}</span>
                                        <span
                                            v-if="opt.recommended"
                                            class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-primary"
                                        >
                                            <Sparkles class="size-2.5" />
                                            Default
                                        </span>
                                    </div>
                                    <span class="mt-1 text-[10px] text-muted-foreground">{{ opt.desc }}</span>
                                </button>
                            </div>
                        </div>

                        <Separator />

                        <!-- Audio Alert Chime Option -->
                        <div class="flex items-center justify-between">
                            <div class="space-y-0.5">
                                <div class="flex items-center gap-1.5">
                                    <Label class="text-xs font-semibold text-foreground">Sound Alert (Dual-Tone Bell Chime)</Label>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md bg-secondary/80 px-1.5 py-0.5 text-[10px] text-muted-foreground hover:bg-secondary hover:text-foreground transition-colors"
                                        title="Play sample chime"
                                        @click="playAlertChime"
                                    >
                                        <Volume2 class="size-2.5" />
                                        Test Chime
                                    </button>
                                </div>
                                <p class="text-[11px] text-muted-foreground">
                                    Synthesizes an elegant chime tone through your speakers even if Windows notifications are silent.
                                </p>
                            </div>

                            <button
                                type="button"
                                role="switch"
                                :aria-checked="soundEnabled"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden"
                                :class="soundEnabled ? 'bg-primary' : 'bg-muted-foreground/30'"
                                @click="setSoundEnabled(!soundEnabled)"
                            >
                                <span
                                    class="pointer-events-none inline-block size-4 transform rounded-full bg-background shadow-lg ring-0 transition duration-200 ease-in-out"
                                    :class="soundEnabled ? 'translate-x-4' : 'translate-x-0'"
                                />
                            </button>
                        </div>

                        <Separator />

                        <!-- Sticky Windows Notification -->
                        <div class="flex items-center justify-between">
                            <div class="space-y-0.5">
                                <Label class="text-xs font-semibold text-foreground">Keep Notification on Screen (Sticky Toast)</Label>
                                <p class="text-[11px] text-muted-foreground">
                                    Keeps the Windows status toast banner visible in Action Center until you dismiss it or click to open attendance.
                                </p>
                            </div>

                            <button
                                type="button"
                                role="switch"
                                :aria-checked="stickyNotification"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden"
                                :class="stickyNotification ? 'bg-primary' : 'bg-muted-foreground/30'"
                                @click="setStickyNotification(!stickyNotification)"
                            >
                                <span
                                    class="pointer-events-none inline-block size-4 transform rounded-full bg-background shadow-lg ring-0 transition duration-200 ease-in-out"
                                    :class="stickyNotification ? 'translate-x-4' : 'translate-x-0'"
                                />
                            </button>
                        </div>

                        <Separator />

                        <!-- Test Notification Button -->
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pt-1">
                            <div>
                                <p class="text-xs font-semibold text-foreground">Verify Windows Desktop Integration</p>
                                <p class="text-[11px] text-muted-foreground">
                                    Trigger a live Windows notification toast to verify sound, icon, and click handling.
                                </p>
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                class="rounded-xl border-border/80"
                                :disabled="testSent"
                                @click="sendTestNotification"
                            >
                                <BellRing class="mr-1.5 size-3.5 text-primary" />
                                {{ testSent ? 'Toast Dispatched!' : 'Send Test Notification to Windows' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Today's Schedule & Reminders Preview Card -->
                <Card class="border-border/80 shadow-sm">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <Calendar class="size-5" />
                                </div>
                                <div>
                                    <CardTitle class="text-base">Today's Class Reminders Schedule</CardTitle>
                                    <CardDescription>
                                        {{ todayMeta?.day_name || 'Today' }} · {{ todayMeta?.today_date || '' }}
                                        <span v-if="todayMeta?.term_name">({{ todayMeta.term_name }})</span>
                                    </CardDescription>
                                </div>
                            </div>

                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 gap-1 rounded-lg text-xs text-muted-foreground hover:text-foreground"
                                :disabled="isChecking"
                                @click="fetchTodayReminders"
                            >
                                <RefreshCw class="size-3.5" :class="{ 'animate-spin': isChecking }" />
                                Refresh
                            </Button>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <!-- Classes List -->
                        <div v-if="todayClasses.length > 0" class="space-y-3">
                            <div
                                v-for="item in todayClasses"
                                :key="item.section_id + '_' + item.starts_at"
                                class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border p-4 transition-all"
                                :class="[
                                    item.status === 'starting_soon'
                                        ? 'border-amber-500/40 bg-amber-500/5 shadow-xs'
                                        : item.is_conducted
                                          ? 'border-border/60 bg-muted/20 opacity-80'
                                          : 'border-border/80 bg-card',
                                ]"
                            >
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-foreground">
                                            {{ item.subject_code }} · {{ item.section_name }}
                                        </span>
                                        <span
                                            v-if="item.schedule_type"
                                            class="rounded-md bg-secondary/80 px-1.5 py-0.5 text-[10px] font-medium uppercase text-muted-foreground"
                                        >
                                            {{ item.schedule_type }}
                                        </span>
                                    </div>

                                    <p v-if="item.subject_title" class="text-xs text-muted-foreground line-clamp-1">
                                        {{ item.subject_title }}
                                    </p>

                                    <div class="flex flex-wrap items-center gap-3 pt-1 text-xs text-muted-foreground">
                                        <span class="flex items-center gap-1 font-mono font-medium text-foreground">
                                            <Clock class="size-3 text-primary" />
                                            {{ item.starts_at_formatted }} - {{ item.ends_at_formatted }}
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <MapPin class="size-3 text-muted-foreground" />
                                            {{ item.room }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Status Badge & Action -->
                                <div class="flex items-center gap-3 sm:self-center">
                                    <!-- Status tag -->
                                    <div class="text-right">
                                        <div v-if="item.is_conducted">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                <CheckCircle2 class="size-3" />
                                                Attendance Taken
                                            </span>
                                        </div>
                                        <div v-else-if="item.status === 'starting_soon'">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-600 dark:text-amber-400">
                                                <BellRing class="size-3 animate-bounce" />
                                                Starts in {{ item.minutes_until_start }}m
                                            </span>
                                            <p class="mt-0.5 text-[10px] text-amber-600/80 dark:text-amber-400/80">Reminder alert active</p>
                                        </div>
                                        <div v-else-if="item.status === 'in_progress'">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                                                Class in session
                                            </span>
                                        </div>
                                        <div v-else-if="item.status === 'completed'">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                                                Finished
                                            </span>
                                        </div>
                                        <div v-else>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                                                Upcoming (in {{ item.minutes_until_start }}m)
                                            </span>
                                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                                Alert at {{ Math.max(0, item.minutes_until_start - leadTimeMinutes) }}m
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Link to Attendance -->
                                    <Button as-child size="sm" variant="outline" class="rounded-xl border-border/80">
                                        <Link :href="item.attendance_url">
                                            Attendance
                                            <ExternalLink class="ml-1.5 size-3" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="rounded-2xl border border-dashed border-border/80 p-8 text-center">
                            <Calendar class="mx-auto size-8 text-muted-foreground/50" />
                            <h4 class="mt-2 text-sm font-semibold text-foreground">No Classes Scheduled Today</h4>
                            <p class="mt-1 text-xs text-muted-foreground max-w-sm mx-auto">
                                You have no section schedules for {{ todayMeta?.day_name || 'today' }}. Your 20-minute class reminders will automatically arm when classes are on your timetable.
                            </p>
                            <Button as-child variant="outline" size="sm" class="mt-4 rounded-xl">
                                <Link href="/schedule">
                                    View Full Schedule Calendar
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
