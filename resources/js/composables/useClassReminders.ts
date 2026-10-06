import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

export interface ClassScheduleItem {
    section_id: number;
    section_name: string;
    subject_code: string;
    subject_title: string;
    room: string;
    schedule_type: string;
    starts_at: string;
    ends_at: string;
    starts_at_formatted: string;
    ends_at_formatted: string;
    minutes_until_start: number;
    is_conducted: boolean;
    attendance_session_id: number | null;
    attendance_url: string;
    status: 'conducted' | 'starting_soon' | 'in_progress' | 'upcoming' | 'completed';
}

export interface TodayRemindersResponse {
    today_date: string;
    day_name: string;
    current_time: string;
    is_holiday: boolean;
    holiday_name: string | null;
    term_name: string | null;
    classes: ClassScheduleItem[];
}

// Global reactive state shared across all components
const isSupported = ref(typeof window !== 'undefined' && 'Notification' in window);
const permission = ref<'granted' | 'denied' | 'default' | 'unsupported'>(
    typeof window !== 'undefined' && 'Notification' in window ? Notification.permission : 'unsupported',
);

const enabled = ref(true);
const leadTimeMinutes = ref(20);
const soundEnabled = ref(true);
const stickyNotification = ref(true);

const todayClasses = ref<ClassScheduleItem[]>([]);
const todayMeta = ref<Omit<TodayRemindersResponse, 'classes'> | null>(null);
const isChecking = ref(false);
const lastChecked = ref<string | null>(null);
const testSent = ref(false);

let intervalId: ReturnType<typeof setInterval> | null = null;
let swRegistration: ServiceWorkerRegistration | null = null;

// Initialize settings from localStorage
function loadPreferences() {
    if (typeof window === 'undefined') return;

    try {
        const savedEnabled = localStorage.getItem('cc_reminders_enabled');
        if (savedEnabled !== null) {
            enabled.value = savedEnabled === 'true';
        }

        const savedLeadTime = localStorage.getItem('cc_reminders_lead_time');
        if (savedLeadTime !== null) {
            leadTimeMinutes.value = parseInt(savedLeadTime, 10) || 20;
        }

        const savedSound = localStorage.getItem('cc_reminders_sound');
        if (savedSound !== null) {
            soundEnabled.value = savedSound === 'true';
        }

        const savedSticky = localStorage.getItem('cc_reminders_sticky');
        if (savedSticky !== null) {
            stickyNotification.value = savedSticky === 'true';
        }
    } catch {
        // Fall back to defaults
    }
}

// Synthesize a chime using Web Audio API
export function playAlertChime() {
    if (typeof window === 'undefined') return;

    try {
        const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
        if (!AudioCtx) return;

        const ctx = new AudioCtx();
        if (ctx.state === 'suspended') {
            ctx.resume();
        }

        const now = ctx.currentTime;

        // First chime tone: C5 (523.25 Hz)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(523.25, now);
        gain1.gain.setValueAtTime(0, now);
        gain1.gain.linearRampToValueAtTime(0.3, now + 0.05);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.35);

        // Second chime tone: G5 (783.99 Hz)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(783.99, now + 0.12);
        gain2.gain.setValueAtTime(0, now + 0.12);
        gain2.gain.linearRampToValueAtTime(0.4, now + 0.18);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.65);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.12);
        osc2.stop(now + 0.65);
    } catch {
        // AudioContext not allowed or not supported
    }
}

// Request Windows & browser notification permission
export async function requestNotificationPermission(): Promise<boolean> {
    if (!isSupported.value) return false;

    try {
        const result = await Notification.requestPermission();
        permission.value = result;

        if (result === 'granted' && 'serviceWorker' in navigator) {
            try {
                swRegistration = await navigator.serviceWorker.register('/sw.js');
            } catch {
                // Service worker registration optional
            }
        }

        return result === 'granted';
    } catch {
        return false;
    }
}

// Register service worker if available
async function initServiceWorker() {
    if (typeof window !== 'undefined' && 'serviceWorker' in navigator) {
        try {
            swRegistration = await navigator.serviceWorker.register('/sw.js');
        } catch {
            // Service worker fallback
        }
    }
}

// Trigger a native Windows status notification toast
async function showNativeNotification(title: string, options: NotificationOptions, targetUrl?: string) {
    if (permission.value !== 'granted') return;

    if (soundEnabled.value) {
        playAlertChime();
    }

    try {
        if (swRegistration && 'showNotification' in swRegistration) {
            await swRegistration.showNotification(title, options);
        } else {
            const notif = new Notification(title, options);
            if (targetUrl) {
                notif.onclick = () => {
                    window.focus();
                    notif.close();
                    router.visit(targetUrl);
                };
            }
        }
    } catch {
        // Fallback: standard Notification constructor
        try {
            const notif = new Notification(title, options);
            if (targetUrl) {
                notif.onclick = () => {
                    window.focus();
                    notif.close();
                    router.visit(targetUrl);
                };
            }
        } catch {
            // Ignored if blocked
        }
    }
}

let lastFetchTimestamp = 0;
const REMINDER_CACHE_TTL_MS = 120000;

// Fetch today's classes and reminder status from the server
export async function fetchTodayReminders(force = false) {
    if (typeof window === 'undefined') return;

    // Return cached schedule if fetched recently and not forced
    if (!force && lastFetchTimestamp > 0 && Date.now() - lastFetchTimestamp < REMINDER_CACHE_TTL_MS && todayClasses.value.length > 0) {
        checkAndAlertReminders();
        return;
    }

    isChecking.value = true;
    try {
        const res = await fetch('/schedule/today-reminders', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (res.ok) {
            const data: TodayRemindersResponse = await res.json();
            todayClasses.value = data.classes || [];
            todayMeta.value = {
                today_date: data.today_date,
                day_name: data.day_name,
                current_time: data.current_time,
                is_holiday: data.is_holiday,
                holiday_name: data.holiday_name,
                term_name: data.term_name,
            };
            lastChecked.value = new Date().toLocaleTimeString();
            lastFetchTimestamp = Date.now();

            // Run reminder check against fetched classes
            checkAndAlertReminders();
        }
    } catch {
        // Network/offline error; keep cached classes
    } finally {
        isChecking.value = false;
    }
}

// Check schedule and alert when a class is in <= leadTime minutes
export function checkAndAlertReminders() {
    if (!enabled.value || permission.value !== 'granted' || todayClasses.value.length === 0) {
        return;
    }

    const todayDate = todayMeta.value?.today_date || new Date().toISOString().split('T')[0];
    const now = new Date();

    for (const item of todayClasses.value) {
        // Skip if class is already conducted
        if (item.is_conducted) continue;

        // Parse class start time
        const [hours, minutes] = item.starts_at.split(':').map(Number);
        const classStartTime = new Date();
        classStartTime.setHours(hours, minutes, 0, 0);

        const diffMinutes = Math.round((classStartTime.getTime() - now.getTime()) / 60000);

        // Update local item minutes_until_start for UI reactive display
        item.minutes_until_start = diffMinutes;

        // Check if class starts within the lead time (e.g. 20 min) and hasn't ended yet
        if (diffMinutes <= leadTimeMinutes.value && diffMinutes >= -5) {
            const dedupeKey = `cc_notif_${item.section_id}_${todayDate}_${item.starts_at}`;

            if (!localStorage.getItem(dedupeKey)) {
                // Mark as notified in localStorage
                localStorage.setItem(dedupeKey, new Date().toISOString());

                const minutesText = diffMinutes <= 1 ? 'starting momentarily' : `in ${diffMinutes} minutes`;
                const title = `Class ${minutesText}: ${item.subject_code} (${item.section_name})`;
                const body = `Starts at ${item.starts_at_formatted} in ${item.room || 'TBA'}. Click to record attendance.`;

                const options: NotificationOptions = {
                    body,
                    icon: '/images/logo.png',
                    badge: '/images/logo.png',
                    tag: dedupeKey,
                    data: { url: item.attendance_url },
                    requireInteraction: stickyNotification.value,
                };

                showNativeNotification(title, options, item.attendance_url);
            }
        }
    }
}

// Send test notification to Windows status
export async function sendTestNotification() {
    testSent.value = true;

    if (permission.value !== 'granted') {
        const granted = await requestNotificationPermission();
        if (!granted) {
            testSent.value = false;
            return false;
        }
    }

    const title = 'ClassCheck Reminder Test 🔔';
    const body = `Your Windows status notification is active! Reminders will pop up ${leadTimeMinutes.value} minutes before class starts.`;

    const options: NotificationOptions = {
        body,
        icon: '/images/logo.png',
        badge: '/images/logo.png',
        tag: `cc-test-${Date.now()}`,
        data: { url: '/settings/notifications' },
        requireInteraction: stickyNotification.value,
    };

    await showNativeNotification(title, options, '/settings/notifications');
    setTimeout(() => {
        testSent.value = false;
    }, 4000);

    return true;
}

export function useClassReminders() {
    onMounted(() => {
        loadPreferences();
        initServiceWorker();

        if (typeof window !== 'undefined' && 'Notification' in window) {
            permission.value = Notification.permission;
        }

        fetchTodayReminders();

        // Check every 30 seconds
        if (!intervalId && typeof window !== 'undefined') {
            intervalId = setInterval(() => {
                checkAndAlertReminders();
            }, 30000);

            // Also check when window becomes visible
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    fetchTodayReminders();
                }
            });
        }
    });

    const setEnabled = (val: boolean) => {
        enabled.value = val;
        localStorage.setItem('cc_reminders_enabled', String(val));
        if (val && permission.value === 'default') {
            requestNotificationPermission();
        }
    };

    const setLeadTime = (mins: number) => {
        leadTimeMinutes.value = mins;
        localStorage.setItem('cc_reminders_lead_time', String(mins));
        checkAndAlertReminders();
    };

    const setSoundEnabled = (val: boolean) => {
        soundEnabled.value = val;
        localStorage.setItem('cc_reminders_sound', String(val));
    };

    const setStickyNotification = (val: boolean) => {
        stickyNotification.value = val;
        localStorage.setItem('cc_reminders_sticky', String(val));
    };

    const nextUpcomingClass = computed(() => {
        return todayClasses.value.find((c) => !c.is_conducted && c.minutes_until_start >= 0) || null;
    });

    const urgentClass = computed(() => {
        return (
            todayClasses.value.find((c) => !c.is_conducted && c.minutes_until_start <= leadTimeMinutes.value && c.minutes_until_start >= 0) || null
        );
    });

    return {
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
        nextUpcomingClass,
        urgentClass,
        setEnabled,
        setLeadTime,
        setSoundEnabled,
        setStickyNotification,
        requestNotificationPermission,
        playAlertChime,
        sendTestNotification,
        fetchTodayReminders,
        checkAndAlertReminders,
    };
}
