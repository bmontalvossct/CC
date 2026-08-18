<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Archive,
    ArrowLeft,
    Calendar,
    ChevronLeft,
    ChevronRight,
    LayoutGrid,
    RotateCcw,
    School,
    Trash2,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface SectionItem {
    id: number;
    name: string;
    subject_code: string;
    subject_title: string;
    archived_at: string | null;
    students_count: number;
    layout_blocks_count: number;
    academic_term: {
        id: number;
        name: string;
        school_year: string;
    };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedSections {
    data: SectionItem[];
    current_page: number;
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: PaginationLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    sections: PaginatedSections | SectionItem[];
    activeCount?: number;
}>();

const sectionList = computed<SectionItem[]>(() => {
    if (Array.isArray(props.sections)) {
        return props.sections;
    }
    return props.sections?.data ?? [];
});

const pagination = computed<PaginatedSections | null>(() => {
    if (props.sections && !Array.isArray(props.sections) && 'data' in props.sections) {
        return props.sections;
    }
    return null;
});

const pageLinks = computed(() => {
    if (!pagination.value) return [];
    return pagination.value.links.filter(
        (link) =>
            !link.label.includes('Previous') && !link.label.includes('Next') && !link.label.includes('&laquo;') && !link.label.includes('&raquo;'),
    );
});

// Restore action
const restoringId = ref<number | null>(null);
const restoreSection = (id: number) => {
    restoringId.value = id;
    router.patch(
        `/sections/${id}/archive`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                restoringId.value = null;
            },
        },
    );
};

// Delete confirmation modal state
const sectionToDelete = ref<SectionItem | null>(null);
const isDeleting = ref(false);

const confirmDelete = (section: SectionItem) => {
    sectionToDelete.value = section;
};

const cancelDelete = () => {
    if (!isDeleting.value) {
        sectionToDelete.value = null;
    }
};

const executeDelete = () => {
    if (!sectionToDelete.value || isDeleting.value) return;

    isDeleting.value = true;
    router.delete(`/sections/${sectionToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            sectionToDelete.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

const formatDate = (dateStr: string | null) => {
    if (!dateStr) return 'Archived';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return dateStr;
    }
};
</script>

<template>
    <Head title="Archived Classes - ClassCheck" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: 'Archived Classes', href: '/sections/archived' },
        ]"
    >
        <main class="page-enter mx-auto w-full max-w-[1360px] px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <!-- Header Section -->
            <header class="flex flex-col justify-between gap-6 border-b border-border/80 pb-8 sm:flex-row sm:items-end">
                <div>
                    <span class="eyebrow">Classroom Archives</span>
                    <h1 class="mt-2 text-3xl font-medium tracking-tight sm:text-4xl">Archived classes</h1>
                    <p class="mt-1.5 text-sm text-muted-foreground sm:text-base">
                        Review past terms and archived sections. Restore a class to make it active, or permanently delete a section and all its
                        records.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        href="/sections"
                        prefetch="hover"
                        class="shadow-xs group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-4 text-sm font-medium text-primary transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                    >
                        <School class="size-4 text-primary transition-colors group-hover:text-white" />
                        <span>Active sections</span>
                        <span
                            v-if="activeCount !== undefined"
                            class="ml-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary transition-colors group-hover:bg-white/20 group-hover:text-white"
                        >
                            {{ activeCount }}
                        </span>
                    </Link>
                </div>
            </header>

            <!-- Navigation Tabs / Switcher -->
            <div class="mt-6 flex items-center gap-2 border-b border-border/80 pb-3">
                <Link
                    href="/sections"
                    prefetch="hover"
                    class="inline-flex items-center gap-2 rounded-xl border border-border bg-white px-4 py-2 text-sm font-medium text-foreground transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                >
                    <School class="size-4" />
                    <span>Active Classes</span>
                    <span v-if="activeCount !== undefined" class="text-xs text-muted-foreground">({{ activeCount }})</span>
                </Link>

                <div
                    class="shadow-xs inline-flex items-center gap-2 rounded-xl border border-primary bg-primary px-4 py-2 text-sm font-medium text-white"
                >
                    <Archive class="size-4" />
                    <span>Archived Classes</span>
                    <span class="text-xs text-white/90">({{ pagination?.total ?? sectionList.length }})</span>
                </div>
            </div>

            <!-- Card Grid -->
            <template v-if="sectionList.length">
                <section class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="section in sectionList"
                        :key="section.id"
                        class="paper-card group flex min-h-[20rem] flex-col justify-between border-border/90 bg-card/80 transition-all hover:border-primary/50 hover:shadow-lg"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <span
                                    class="inline-flex items-center rounded-md bg-primary/10 px-2.5 py-0.5 font-mono text-xs font-medium text-primary"
                                >
                                    {{ section.subject_code }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border border-border/80 bg-secondary/80 px-2.5 py-0.5 font-mono text-[10px] font-medium text-muted-foreground"
                                >
                                    <Archive class="size-3" />
                                    <span>Archived {{ formatDate(section.archived_at) }}</span>
                                </span>
                            </div>

                            <h2 class="mt-4 text-xl font-medium tracking-tight text-foreground transition-colors group-hover:text-primary">
                                {{ section.name }}
                            </h2>
                            <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                                {{ section.subject_title }}
                            </p>
                        </div>

                        <div>
                            <!-- Statistics -->
                            <div class="mt-6 grid grid-cols-2 gap-3 border-t border-border/80 pt-4 text-xs">
                                <span class="flex items-center gap-2 font-medium text-foreground/90">
                                    <Users class="size-4 text-primary" /> {{ section.students_count }} enrolled
                                </span>
                                <span class="flex items-center gap-2 font-medium text-foreground/90">
                                    <LayoutGrid class="size-4 text-primary" /> {{ section.layout_blocks_count }} blocks
                                </span>
                            </div>
                            <p class="mt-2.5 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                <Calendar class="size-3 text-muted-foreground" />
                                <span>{{ section.academic_term.name }} · SY {{ section.academic_term.school_year }}</span>
                            </p>

                            <!-- Actions Toolbar inside card -->
                            <div class="mt-5 flex items-center justify-between border-t border-border/60 pt-3">
                                <button
                                    type="button"
                                    class="group/btn shadow-xs inline-flex h-9 items-center gap-1.5 rounded-xl border border-primary bg-white px-3 text-xs font-medium text-primary transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white disabled:opacity-50 dark:bg-card"
                                    :disabled="restoringId === section.id"
                                    title="Restore this section back to active classes"
                                    @click="restoreSection(section.id)"
                                >
                                    <RotateCcw class="size-3.5 text-primary transition-colors group-hover/btn:text-white" />
                                    <span>{{ restoringId === section.id ? 'Restoring...' : 'Restore' }}</span>
                                </button>

                                <button
                                    type="button"
                                    class="group/btn shadow-xs inline-flex h-9 items-center gap-1.5 rounded-xl border border-rose-600 bg-white px-3 text-xs font-medium text-rose-700 transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                                    title="Permanently delete this section and all associated data"
                                    @click="confirmDelete(section)"
                                >
                                    <Trash2 class="size-3.5 text-rose-700 transition-colors group-hover/btn:text-white" />
                                    <span>Delete</span>
                                </button>
                            </div>
                        </div>
                    </article>
                </section>

                <!-- Pagination Bar (max 6 per page) -->
                <nav
                    v-if="pagination && pagination.last_page > 1"
                    aria-label="Archived sections pagination"
                    class="mt-10 flex flex-col items-center justify-between gap-4 border-t border-border/80 pt-6 sm:flex-row"
                >
                    <p class="text-xs text-muted-foreground">
                        Showing
                        <span class="font-medium text-foreground">{{ pagination.from ?? 0 }}</span>
                        to
                        <span class="font-medium text-foreground">{{ pagination.to ?? 0 }}</span>
                        of
                        <span class="font-medium text-foreground">{{ pagination.total }}</span>
                        archived classes (max 6 per page)
                    </p>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <!-- Previous Page Button -->
                        <Link
                            v-if="pagination.prev_page_url"
                            :href="pagination.prev_page_url"
                            prefetch="hover"
                            class="shadow-xs inline-flex h-9 items-center gap-1 rounded-xl border border-border bg-white px-3 text-xs font-medium text-foreground transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                        >
                            <ChevronLeft class="size-3.5" />
                            <span>Previous</span>
                        </Link>
                        <span
                            v-else
                            class="inline-flex h-9 items-center gap-1 rounded-xl border border-border/50 bg-secondary/40 px-3 text-xs font-medium text-muted-foreground/60"
                        >
                            <ChevronLeft class="size-3.5" />
                            <span>Previous</span>
                        </span>

                        <!-- Page Number Buttons -->
                        <template v-for="link in pageLinks" :key="link.label">
                            <span v-if="link.label === '...'" class="px-2 text-xs font-medium text-muted-foreground"> ... </span>
                            <Link
                                v-else-if="link.url && !link.active"
                                :href="link.url"
                                prefetch="hover"
                                class="shadow-xs inline-flex size-9 items-center justify-center rounded-xl border border-border bg-white text-xs font-medium text-foreground transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            >
                                {{ link.label }}
                            </Link>
                            <span
                                v-else-if="link.active"
                                class="shadow-xs inline-flex size-9 items-center justify-center rounded-xl border border-primary bg-primary text-xs font-medium text-white"
                                aria-current="page"
                            >
                                {{ link.label }}
                            </span>
                        </template>

                        <!-- Next Page Button -->
                        <Link
                            v-if="pagination.next_page_url"
                            :href="pagination.next_page_url"
                            prefetch="hover"
                            class="shadow-xs inline-flex h-9 items-center gap-1 rounded-xl border border-border bg-white px-3 text-xs font-medium text-foreground transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                        >
                            <span>Next</span>
                            <ChevronRight class="size-3.5" />
                        </Link>
                        <span
                            v-else
                            class="inline-flex h-9 items-center gap-1 rounded-xl border border-border/50 bg-secondary/40 px-3 text-xs font-medium text-muted-foreground/60"
                        >
                            <span>Next</span>
                            <ChevronRight class="size-3.5" />
                        </span>
                    </div>
                </nav>
            </template>

            <!-- Empty State -->
            <section v-else class="mt-8 rounded-2xl border border-dashed border-border/80 bg-card p-12 text-center shadow-sm">
                <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-primary/10 text-primary">
                    <Archive class="size-7" />
                </span>
                <h2 class="mt-5 text-2xl font-medium text-foreground">No archived classes</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                    Sections that you archive will be stored here safely without cluttering your active workspace.
                </p>
                <div class="mt-6">
                    <Link
                        href="/sections"
                        prefetch="hover"
                        class="shadow-xs group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-4 text-sm font-medium text-primary transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                    >
                        <ArrowLeft class="size-4 text-primary transition-colors group-hover:text-white" />
                        <span>Go to active sections</span>
                    </Link>
                </div>
            </section>
        </main>

        <!-- Permanent Delete Confirmation Modal -->
        <div
            v-if="sectionToDelete"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
            @click.self="cancelDelete"
        >
            <div
                class="paper-card relative w-full max-w-lg border-border/90 p-6 shadow-2xl duration-200 animate-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                :aria-label="`Delete ${sectionToDelete.name}`"
            >
                <div class="flex items-start gap-4">
                    <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-rose-500/15 text-rose-700 dark:text-rose-400">
                        <AlertTriangle class="size-6" />
                    </div>
                    <div class="flex-1">
                        <span class="eyebrow text-rose-700 dark:text-rose-400">Permanent Deletion</span>
                        <h3 class="mt-1 text-xl font-medium text-foreground">Delete {{ sectionToDelete.name }}?</h3>
                        <p class="mt-1 text-xs text-muted-foreground">{{ sectionToDelete.subject_code }} · {{ sectionToDelete.subject_title }}</p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-rose-500/20 bg-rose-500/5 p-4 text-xs leading-relaxed text-foreground">
                    <p class="font-medium text-rose-700 dark:text-rose-400">
                        Warning: This action permanently deletes the section and all its stored data:
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-4 text-muted-foreground">
                        <li>All enrolled student profiles, seat assignments, and student photos</li>
                        <li>Room layouts, seating arrangements, and floor plans</li>
                        <li>All attendance sessions and historical roll-call logs</li>
                        <li>Oral recitation participation scores and grading records</li>
                        <li>Project assignments, groupings, and presentation scores</li>
                    </ul>
                    <p class="mt-2 font-medium text-rose-700 dark:text-rose-400">This action cannot be undone.</p>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-border/80 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-xl border border-border bg-white text-xs font-medium text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                        :disabled="isDeleting"
                        @click="cancelDelete"
                    >
                        Cancel
                    </Button>

                    <button
                        type="button"
                        class="shadow-xs inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-rose-700 bg-rose-700 px-4 text-xs font-medium text-white transition-all hover:border-rose-800 hover:bg-rose-800 disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="executeDelete"
                    >
                        <Trash2 class="size-4" />
                        <span>{{ isDeleting ? 'Deleting section & data...' : 'Permanently delete section' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
