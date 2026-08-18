<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Clock,
    Home,
    RotateCcw,
    SearchX,
    ServerCrash,
    ShieldAlert,
    Wrench,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    status: number;
    message?: string;
}>();

const title = computed(() => {
    return {
        404: 'Page Not Found',
        403: 'Access Forbidden',
        500: 'Internal Server Error',
        503: 'Service Unavailable',
        419: 'Session Expired',
    }[props.status] || 'An Error Occurred';
});

const description = computed(() => {
    if (props.message) return props.message;
    return {
        404: 'The page or resource you are looking for does not exist, has been moved, or is temporarily unavailable.',
        403: 'You do not have permission to view or modify this resource. Please ensure you are logged into the correct teacher account.',
        500: 'The application encountered an unexpected issue while processing your request. Our team has been notified.',
        503: 'We are performing brief system maintenance or updates. Please try again in a few moments.',
        419: 'Your security session has expired due to inactivity. Please refresh the page or log in again to continue.',
    }[props.status] || 'An unexpected error occurred while loading this page.';
});

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = '/dashboard';
    }
};

const reloadPage = () => {
    window.location.reload();
};
</script>

<template>
    <Head :title="`${status}: ${title}`" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-background p-6 selection:bg-primary/20 selection:text-primary">
        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-border/80 bg-card p-8 shadow-xl text-center">
            <!-- Decorative Accent Line -->
            <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-primary via-emerald-600 to-primary" />

            <!-- Error Code & Icon Badge -->
            <div class="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl border border-border/80 bg-secondary/50">
                <SearchX v-if="status === 404" class="size-8 text-muted-foreground" />
                <ShieldAlert v-else-if="status === 403" class="size-8 text-amber-600 dark:text-amber-400" />
                <ServerCrash v-else-if="status === 500" class="size-8 text-rose-600 dark:text-rose-400" />
                <Wrench v-else-if="status === 503" class="size-8 text-blue-600 dark:text-blue-400" />
                <Clock v-else-if="status === 419" class="size-8 text-amber-600 dark:text-amber-400" />
                <RotateCcw v-else class="size-8 text-primary" />
            </div>

            <!-- Error Badge -->
            <div class="inline-flex items-center gap-1.5 rounded-full bg-primary px-3 py-1 font-mono text-xs font-medium text-white shadow-sm">
                <span>Error {{ status }}</span>
            </div>

            <!-- Title & Description -->
            <h1 class="mt-4 text-2xl font-medium tracking-tight text-foreground sm:text-3xl">
                {{ title }}
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                {{ description }}
            </p>

            <!-- Action Buttons -->
            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <Link
                    href="/dashboard"
                    prefetch="hover"
                    class="group inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-primary bg-white px-5 text-sm font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white sm:w-auto dark:bg-card"
                >
                    <Home class="size-4 text-primary transition-colors group-hover:text-white" />
                    <span>Go to Dashboard</span>
                </Link>

                <button
                    type="button"
                    class="group inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-primary bg-white px-5 text-sm font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white sm:w-auto dark:bg-card"
                    @click="goBack"
                >
                    <ArrowLeft class="size-4 text-primary transition-colors group-hover:text-white" />
                    <span>Go Back</span>
                </button>

                <Button
                    v-if="status === 419 || status === 500 || status === 503"
                    type="button"
                    variant="ghost"
                    class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-border px-4 text-sm font-medium text-foreground hover:bg-secondary sm:w-auto"
                    @click="reloadPage"
                >
                    <RotateCcw class="size-4" />
                    <span>Reload</span>
                </Button>
            </div>

            <!-- Footer Meta -->
            <div class="mt-8 border-t border-border/60 pt-4 text-xs text-muted-foreground">
                <span>ClassCheck · Classroom & Attendance Management</span>
            </div>
        </div>
    </div>
</template>
