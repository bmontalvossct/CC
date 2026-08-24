<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle2,
    Cloud,
    Database,
    Download,
    FileJson,
    FileSpreadsheet,
    HardDrive,
    Info,
    RefreshCw,
    ShieldCheck,
    Upload,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface LocalSnapshot {
    name: string;
    size: number;
    created_at: string;
}

interface Props {
    driver: string;
    isSqlite: boolean;
    sqliteSize?: number | null;
    stats: {
        terms_count: number;
        sections_count: number;
        students_count: number;
    };
    localSnapshots: LocalSnapshot[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Settings',
        href: '/settings/profile',
    },
    {
        title: 'Backup & Export',
        href: '/settings/backup',
    },
];

const page = usePage();
const flashSuccess = computed(() => (page.props.flash as any)?.success || null);
const flashError = computed(() => (page.props.flash as any)?.error || null);

// Restore Form
const restoreForm = useForm({
    backup_file: null as File | null,
    clean_replace: false,
});

const isRestoring = ref(false);
const showConfirmModal = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        restoreForm.backup_file = target.files[0];
    }
};

const triggerRestore = () => {
    if (!restoreForm.backup_file) return;
    showConfirmModal.value = true;
};

const confirmRestore = () => {
    showConfirmModal.value = false;
    isRestoring.value = true;

    restoreForm.post(route('backup.restore'), {
        preserveScroll: true,
        onSuccess: () => {
            isRestoring.value = false;
            restoreForm.reset();
            if (fileInputRef.value) fileInputRef.value.value = '';
        },
        onError: () => {
            isRestoring.value = false;
        },
    });
};

const isCreatingSnapshot = ref(false);
const createSnapshot = () => {
    isCreatingSnapshot.value = true;
    router.post(
        route('backup.create-local-snapshot'),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isCreatingSnapshot.value = false;
            },
        },
    );
};

const formatBytes = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Backup & Export Settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-8">
                <!-- Header -->
                <HeadingSmall
                    title="Backup, Export & Synchronization"
                    description="Safely export, backup, and transfer your classroom records between offline computers and online cloud instances."
                />

                <!-- Flash Notification Messages -->
                <div v-if="flashSuccess" class="flex items-start gap-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-900 dark:text-emerald-200">
                    <CheckCircle2 class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400 mt-0.5" />
                    <div class="text-sm font-medium">{{ flashSuccess }}</div>
                </div>

                <div v-if="flashError || restoreForm.errors.backup_file" class="flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/10 p-4 text-red-900 dark:text-red-200">
                    <AlertCircle class="h-5 w-5 shrink-0 text-red-600 dark:text-red-400 mt-0.5" />
                    <div class="text-sm font-medium">{{ flashError || restoreForm.errors.backup_file }}</div>
                </div>

                <!-- Database Status & Overview Badge -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="flex flex-col rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            <component :is="isSqlite ? HardDrive : Cloud" class="h-4 w-4 text-primary" />
                            Database Engine
                        </div>
                        <div class="mt-2 text-lg font-bold text-foreground capitalize">
                            {{ driver }} {{ isSqlite ? '(Offline / Standalone)' : '(Cloud / Online)' }}
                        </div>
                        <div v-if="sqliteSize" class="text-xs text-muted-foreground mt-1">
                            File Size: {{ formatBytes(sqliteSize) }}
                        </div>
                    </div>

                    <div class="flex flex-col rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            <Database class="h-4 w-4 text-primary" />
                            Classroom Sections
                        </div>
                        <div class="mt-2 text-lg font-bold text-foreground">
                            {{ stats.sections_count }} Active Sections
                        </div>
                        <div class="text-xs text-muted-foreground mt-1">
                            Across {{ stats.terms_count }} Academic Terms
                        </div>
                    </div>

                    <div class="flex flex-col rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            <ShieldCheck class="h-4 w-4 text-primary" />
                            Total Enrolled Students
                        </div>
                        <div class="mt-2 text-lg font-bold text-foreground">
                            {{ stats.students_count }} Students
                        </div>
                        <div class="text-xs text-muted-foreground mt-1">
                            With seating, attendance & grades
                        </div>
                    </div>
                </div>

                <!-- Section 1: Universal JSON Export & Restore (Online & Offline) -->
                <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
                    <div class="flex items-center justify-between pb-4 border-b border-border">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <FileJson class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-foreground">Universal System Backup (.JSON)</h3>
                                <p class="text-xs text-muted-foreground">
                                    Complete portable data archive compatible with both Offline Windows apps and Online Web instances.
                                </p>
                            </div>
                        </div>

                        <a :href="route('backup.export-json')" class="inline-flex">
                            <Button class="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm">
                                <Download class="h-4 w-4" />
                                Export JSON Backup
                            </Button>
                        </a>
                    </div>

                    <!-- Restore Area -->
                    <div class="mt-6 pt-2">
                        <h4 class="text-sm font-semibold text-foreground mb-1">Restore or Sync from Backup File</h4>
                        <p class="text-xs text-muted-foreground mb-4">
                            Upload an exported <code class="rounded bg-muted px-1.5 py-0.5 text-xs font-mono">classcheck_backup_*.json</code> file to restore terms, sections, rosters, attendance, recitations, and grades.
                        </p>

                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept=".json,application/json"
                                class="block w-full text-sm text-muted-foreground file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer"
                                @change="handleFileSelect"
                            />

                            <Button
                                :disabled="!restoreForm.backup_file || isRestoring"
                                class="gap-2 shrink-0"
                                variant="outline"
                                @click="triggerRestore"
                            >
                                <Upload class="h-4 w-4" />
                                {{ isRestoring ? 'Restoring...' : 'Restore Backup' }}
                            </Button>
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <input
                                id="clean_replace"
                                v-model="restoreForm.clean_replace"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            <label for="clean_replace" class="text-xs text-muted-foreground">
                                Clean Replace Mode (Wipe existing sections for this account before importing. Leave unchecked to safely merge).
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Direct SQLite Database Backup (Offline Mode) -->
                <div v-if="isSqlite" class="rounded-xl border border-border bg-card p-6 shadow-sm">
                    <div class="flex items-center justify-between pb-4 border-b border-border">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <Database class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-foreground">SQLite Database File (.sqlite)</h3>
                                <p class="text-xs text-muted-foreground">
                                    Direct access to the raw SQLite database for offline backup or transferring to another PC.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                class="gap-2"
                                :disabled="isCreatingSnapshot"
                                @click="createSnapshot"
                            >
                                <RefreshCw :class="['h-4 w-4', { 'animate-spin': isCreatingSnapshot }]" />
                                Save Local Snapshot
                            </Button>

                            <a :href="route('backup.download-sqlite')" class="inline-flex">
                                <Button class="gap-2 bg-blue-600 hover:bg-blue-700 text-white shadow-sm">
                                    <Download class="h-4 w-4" />
                                    Download SQLite DB
                                </Button>
                            </a>
                        </div>
                    </div>

                    <!-- Local Snapshots History -->
                    <div v-if="localSnapshots.length > 0" class="mt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                            Recent Local Snapshots (<code class="font-mono text-xs">database/backups/</code>)
                        </h4>
                        <div class="divide-y divide-border rounded-lg border border-border bg-muted/30">
                            <div
                                v-for="snap in localSnapshots"
                                :key="snap.name"
                                class="flex items-center justify-between px-4 py-2.5 text-xs"
                            >
                                <div class="flex items-center gap-2 font-mono text-foreground">
                                    <Database class="h-3.5 w-3.5 text-muted-foreground" />
                                    {{ snap.name }}
                                </div>
                                <div class="flex items-center gap-4 text-muted-foreground">
                                    <span>{{ formatBytes(snap.size) }}</span>
                                    <span>{{ snap.created_at }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: CSV Spreadsheet Reports (Excel / Google Sheets) -->
                <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
                    <div class="flex items-center gap-3 pb-4 border-b border-border">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <FileSpreadsheet class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-foreground">Spreadsheet Reports & Exports (.CSV)</h3>
                            <p class="text-xs text-muted-foreground">
                                Export structured tables for Microsoft Excel, Google Sheets, or school reporting.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <a
                            :href="route('backup.export-csv', { type: 'students' })"
                            class="flex items-center justify-between rounded-lg border border-border p-3 transition hover:bg-muted/50"
                        >
                            <div class="flex items-center gap-2.5">
                                <FileSpreadsheet class="h-4 w-4 text-emerald-600" />
                                <div>
                                    <div class="text-xs font-semibold text-foreground">Student Master Roster</div>
                                    <div class="text-[11px] text-muted-foreground">All sections, student IDs, names, status & seats</div>
                                </div>
                            </div>
                            <Download class="h-4 w-4 text-muted-foreground" />
                        </a>

                        <a
                            :href="route('backup.export-csv', { type: 'attendance' })"
                            class="flex items-center justify-between rounded-lg border border-border p-3 transition hover:bg-muted/50"
                        >
                            <div class="flex items-center gap-2.5">
                                <FileSpreadsheet class="h-4 w-4 text-emerald-600" />
                                <div>
                                    <div class="text-xs font-semibold text-foreground">Attendance Records Log</div>
                                    <div class="text-[11px] text-muted-foreground">Meeting dates, attended minutes & remarks</div>
                                </div>
                            </div>
                            <Download class="h-4 w-4 text-muted-foreground" />
                        </a>

                        <a
                            :href="route('backup.export-csv', { type: 'grades' })"
                            class="flex items-center justify-between rounded-lg border border-border p-3 transition hover:bg-muted/50"
                        >
                            <div class="flex items-center gap-2.5">
                                <FileSpreadsheet class="h-4 w-4 text-emerald-600" />
                                <div>
                                    <div class="text-xs font-semibold text-foreground">Assessment & Gradebook</div>
                                    <div class="text-[11px] text-muted-foreground">Quizzes, exams, student scores & percentages</div>
                                </div>
                            </div>
                            <Download class="h-4 w-4 text-muted-foreground" />
                        </a>

                        <a
                            :href="route('backup.export-csv', { type: 'recitations' })"
                            class="flex items-center justify-between rounded-lg border border-border p-3 transition hover:bg-muted/50"
                        >
                            <div class="flex items-center gap-2.5">
                                <FileSpreadsheet class="h-4 w-4 text-emerald-600" />
                                <div>
                                    <div class="text-xs font-semibold text-foreground">Oral Recitations Log</div>
                                    <div class="text-[11px] text-muted-foreground">Participation scores, dates & teacher notes</div>
                                </div>
                            </div>
                            <Download class="h-4 w-4 text-muted-foreground" />
                        </a>
                    </div>
                </div>

                <!-- Section 4: Synchronization Guide -->
                <div class="rounded-xl border border-border bg-muted/20 p-5">
                    <div class="flex items-start gap-3">
                        <Info class="h-5 w-5 text-primary shrink-0 mt-0.5" />
                        <div class="text-xs text-muted-foreground space-y-1.5">
                            <div class="font-semibold text-foreground text-sm">How to Sync Data Between Offline & Online</div>
                            <p>
                                <strong>From Offline PC to Online Web:</strong> Click <em>Export JSON Backup</em> on your offline laptop, then open the online school portal, navigate to <em>Settings → Backup & Export</em>, select the file and click <em>Restore Backup</em>.
                            </p>
                            <p>
                                <strong>From Online Web to Offline PC:</strong> Download the JSON backup from the web portal and restore it inside your offline ClassCheck app. All your seating plans, attendance records, and gradebooks will be synced seamlessly.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confirmation Modal for Restore -->
            <div
                v-if="showConfirmModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            >
                <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl border border-border">
                    <h3 class="text-lg font-bold text-foreground">Confirm Backup Restoration</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Are you sure you want to restore from <code class="font-mono text-xs font-semibold text-foreground">{{ restoreForm.backup_file?.name }}</code>?
                    </p>
                    <div v-if="restoreForm.clean_replace" class="mt-3 rounded-md bg-amber-500/10 border border-amber-500/30 p-3 text-xs text-amber-900 dark:text-amber-200">
                        <strong>Warning:</strong> Clean Replace Mode is selected. Existing sections for this user account will be overwritten.
                    </div>
                    <div v-else class="mt-3 rounded-md bg-emerald-500/10 border border-emerald-500/30 p-3 text-xs text-emerald-900 dark:text-emerald-200">
                        <strong>Safe Merge:</strong> New records will be safely merged with your current account data.
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <Button variant="outline" @click="showConfirmModal = false">
                            Cancel
                        </Button>
                        <Button class="bg-emerald-600 hover:bg-emerald-700 text-white" @click="confirmRestore">
                            Confirm & Restore
                        </Button>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
