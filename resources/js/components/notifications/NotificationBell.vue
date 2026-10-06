<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useClassReminders } from '@/composables/useClassReminders';
import { Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    Bell,
    BellOff,
    BellRing,
    CheckCircle2,
    Clock,
    ExternalLink,
    MapPin,
    Settings,
    Volume2,
    VolumeX,
} from 'lucide-vue-next';

const {
    isSupported,
    permission,
    enabled,
    leadTimeMinutes,
    soundEnabled,
    todayClasses,
    urgentClass,
    nextUpcomingClass,
    testSent,
    setEnabled,
    setSoundEnabled,
    requestNotificationPermission,
    sendTestNotification,
} = useClassReminders();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="relative inline-flex size-9 items-center justify-center rounded-xl border transition-colors focus:outline-hidden"
                :class="[
                    urgentClass
                        ? 'border-amber-500/80 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                        : enabled
                          ? 'border-border/80 bg-secondary/50 text-muted-foreground hover:bg-secondary hover:text-foreground'
                          : 'border-border/60 bg-muted/40 text-muted-foreground/60 hover:bg-muted',
                ]"
                :title="urgentClass ? `Class starting soon: ${urgentClass.subject_code}` : 'Class Reminders & Notifications'"
                aria-label="Class Reminders & Notifications"
            >
                <BellRing v-if="urgentClass" class="size-4 animate-bounce text-amber-600 dark:text-amber-400" />
                <BellOff v-else-if="!enabled" class="size-4" />
                <Bell v-else class="size-4" />

                <!-- Pulse dot when a class is due in <= 20 minutes -->
                <span
                    v-if="urgentClass"
                    class="absolute -top-0.5 -right-0.5 flex size-2.5"
                >
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex size-2.5 rounded-full bg-amber-500"></span>
                </span>
                <!-- Blue dot if active today -->
                <span
                    v-else-if="enabled && todayClasses.length > 0 && permission === 'granted'"
                    class="absolute top-1.5 right-1.5 size-1.5 rounded-full bg-primary"
                ></span>
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent class="w-80 p-0 rounded-2xl border border-border/80 bg-popover shadow-xl" align="end" :side-offset="8">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-border/70 p-3.5">
                <div class="flex items-center gap-2">
                    <div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Bell class="size-4" />
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold text-foreground">Class Reminders</h4>
                        <p class="text-[10px] text-muted-foreground">Windows & desktop status alerts</p>
                    </div>
                </div>

                <span
                    v-if="permission === 'granted' && enabled"
                    class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                >
                    <CheckCircle2 class="size-2.5" />
                    Active ({{ leadTimeMinutes }}m)
                </span>
                <span
                    v-else-if="permission === 'denied'"
                    class="inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2 py-0.5 text-[10px] font-medium text-destructive"
                >
                    <AlertCircle class="size-2.5" />
                    Blocked in OS
                </span>
                <span
                    v-else-if="!enabled"
                    class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                >
                    Disabled
                </span>
            </div>

            <!-- Permission prompt banner if not yet granted -->
            <div
                v-if="isSupported && permission !== 'granted' && permission !== 'denied'"
                class="border-b border-amber-500/20 bg-amber-500/10 p-3"
            >
                <div class="flex items-start gap-2">
                    <AlertCircle class="mt-0.5 size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div class="space-y-1">
                        <p class="text-[11px] font-medium text-amber-900 dark:text-amber-200">
                            Allow Windows desktop notifications
                        </p>
                        <p class="text-[10px] text-amber-700/80 dark:text-amber-300/80">
                            Enable notifications to receive status toasts 20 minutes before each class.
                        </p>
                        <Button
                            size="sm"
                            class="mt-1 h-6 rounded-md px-2 text-[10px] font-medium"
                            @click="requestNotificationPermission"
                        >
                            Enable Notifications
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Next / Urgent Class Banner -->
            <div v-if="urgentClass" class="border-b border-amber-500/20 bg-amber-500/5 p-3">
                <div class="flex items-center justify-between">
                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                        <Clock class="size-3" />
                        Class in {{ urgentClass.minutes_until_start }}m
                    </span>
                    <span class="text-[10px] font-mono text-muted-foreground">{{ urgentClass.starts_at_formatted }}</span>
                </div>
                <div class="mt-1 flex items-baseline justify-between">
                    <p class="text-xs font-semibold text-foreground">
                        {{ urgentClass.subject_code }} · {{ urgentClass.section_name }}
                    </p>
                    <span class="flex items-center gap-1 text-[10px] text-muted-foreground">
                        <MapPin class="size-2.5" />
                        {{ urgentClass.room }}
                    </span>
                </div>
                <div class="mt-2 flex gap-2">
                    <Button as-child size="sm" class="h-6 w-full rounded-md text-[10px]">
                        <Link :href="urgentClass.attendance_url">
                            Open Attendance
                            <ExternalLink class="ml-1 size-2.5" />
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Upcoming Class info when not urgent -->
            <div v-else-if="nextUpcomingClass" class="border-b border-border/70 p-3">
                <div class="flex items-center justify-between text-[10px] text-muted-foreground">
                    <span class="flex items-center gap-1">
                        <Clock class="size-3" />
                        Next class in {{ nextUpcomingClass.minutes_until_start }}m
                    </span>
                    <span>Starts {{ nextUpcomingClass.starts_at_formatted }}</span>
                </div>
                <div class="mt-1 flex items-baseline justify-between">
                    <p class="text-xs font-medium text-foreground">
                        {{ nextUpcomingClass.subject_code }} · {{ nextUpcomingClass.section_name }}
                    </p>
                    <span class="flex items-center gap-1 text-[10px] text-muted-foreground">
                        <MapPin class="size-2.5" />
                        {{ nextUpcomingClass.room }}
                    </span>
                </div>
            </div>

            <!-- Empty State for Today -->
            <div v-else-if="todayClasses.length === 0" class="p-4 text-center">
                <Clock class="mx-auto size-5 text-muted-foreground/60" />
                <p class="mt-1.5 text-xs font-medium text-foreground">No classes scheduled today</p>
                <p class="text-[10px] text-muted-foreground">Enjoy your day off or review course records.</p>
            </div>

            <!-- Quick Controls -->
            <div class="grid grid-cols-2 gap-1.5 p-2.5">
                <button
                    type="button"
                    class="flex items-center justify-between rounded-lg border border-border/70 px-2.5 py-1.5 text-[11px] transition-colors hover:bg-muted"
                    :class="{ 'bg-secondary/60 font-medium': enabled }"
                    @click="setEnabled(!enabled)"
                >
                    <span class="flex items-center gap-1.5">
                        <Bell class="size-3 text-muted-foreground" />
                        20m Alerts
                    </span>
                    <span class="text-[10px] font-semibold" :class="enabled ? 'text-primary' : 'text-muted-foreground'">
                        {{ enabled ? 'On' : 'Off' }}
                    </span>
                </button>

                <button
                    type="button"
                    class="flex items-center justify-between rounded-lg border border-border/70 px-2.5 py-1.5 text-[11px] transition-colors hover:bg-muted"
                    :class="{ 'bg-secondary/60 font-medium': soundEnabled }"
                    @click="setSoundEnabled(!soundEnabled)"
                >
                    <span class="flex items-center gap-1.5">
                        <Volume2 v-if="soundEnabled" class="size-3 text-muted-foreground" />
                        <VolumeX v-else class="size-3 text-muted-foreground/60" />
                        Chime Sound
                    </span>
                    <span class="text-[10px] font-semibold" :class="soundEnabled ? 'text-primary' : 'text-muted-foreground'">
                        {{ soundEnabled ? 'On' : 'Off' }}
                    </span>
                </button>
            </div>

            <DropdownMenuSeparator class="my-0" />

            <!-- Action Buttons -->
            <div class="flex items-center justify-between bg-muted/30 p-2 text-xs">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                    :disabled="testSent"
                    @click="sendTestNotification"
                >
                    <BellRing class="size-3" />
                    {{ testSent ? 'Toast Sent!' : 'Test Windows Toast' }}
                </button>

                <Button as-child variant="ghost" size="sm" class="h-6 gap-1 px-2 text-[11px] text-muted-foreground hover:text-foreground">
                    <Link href="/settings/notifications">
                        <Settings class="size-3" />
                        Settings
                    </Link>
                </Button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
