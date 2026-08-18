<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, ArrowRight, BookOpen, ChevronLeft, ChevronRight, LayoutGrid, Plus, School, Users } from 'lucide-vue-next';
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
    archivedCount?: number;
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

const archivingId = ref<number | null>(null);
const toggleArchive = (id: number) => {
    archivingId.value = id;
    router.patch(
        `/sections/${id}/archive`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                archivingId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Sections - ClassCheck" />
    <AppLayout :breadcrumbs="[{ title: 'Sections', href: '/sections' }]">
        <main class="page-enter mx-auto w-full max-w-[1360px] px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <!-- Header Section -->
            <header class="flex flex-col justify-between gap-6 border-b border-border/80 pb-8 sm:flex-row sm:items-end">
                <div>
                    <span class="eyebrow">Classrooms & Courses</span>
                    <h1 class="mt-2 text-3xl font-medium tracking-tight sm:text-4xl">Your sections</h1>
                    <p class="mt-1.5 text-sm text-muted-foreground sm:text-base">
                        Manage classroom layouts, rosters, and academic records across your subjects.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        href="/sections/archived"
                        prefetch="hover"
                        class="shadow-xs group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-white px-4 text-sm font-medium text-foreground transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                    >
                        <Archive class="size-4 text-muted-foreground transition-colors group-hover:text-white" />
                        <span>Archived classes</span>
                        <span
                            v-if="archivedCount !== undefined && archivedCount > 0"
                            class="ml-1 rounded-full bg-secondary px-2 py-0.5 text-xs text-muted-foreground transition-colors group-hover:bg-white/20 group-hover:text-white"
                        >
                            {{ archivedCount }}
                        </span>
                    </Link>

                    <Button as-child class="ink-button !h-10 !rounded-xl">
                        <Link href="/sections/create" prefetch="hover">
                            <Plus class="size-4" />
                            <span>New section</span>
                        </Link>
                    </Button>
                </div>
            </header>

            <!-- Navigation Tabs / Switcher -->
            <div class="mt-6 flex items-center gap-2 border-b border-border/80 pb-3">
                <div
                    class="shadow-xs inline-flex items-center gap-2 rounded-xl border border-primary bg-primary px-4 py-2 text-sm font-medium text-white"
                >
                    <School class="size-4" />
                    <span>Active Classes</span>
                    <span class="text-xs text-white/90">({{ pagination?.total ?? sectionList.length }})</span>
                </div>

                <Link
                    href="/sections/archived"
                    prefetch="hover"
                    class="inline-flex items-center gap-2 rounded-xl border border-border bg-white px-4 py-2 text-sm font-medium text-foreground transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                >
                    <Archive class="size-4" />
                    <span>Archived Classes</span>
                    <span v-if="archivedCount !== undefined" class="text-xs text-muted-foreground">({{ archivedCount }})</span>
                </Link>
            </div>

            <!-- Active Sections Grid -->
            <template v-if="sectionList.length">
                <section class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="section in sectionList"
                        :key="section.id"
                        class="paper-card group flex min-h-[20rem] flex-col justify-between transition-all hover:border-primary/50 hover:shadow-lg"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <span
                                    class="inline-flex items-center rounded-md bg-primary/10 px-2.5 py-0.5 font-mono text-xs font-medium text-primary"
                                >
                                    {{ section.subject_code }}
                                </span>
                                <span
                                    v-if="section.archived_at"
                                    class="rounded-full border border-border bg-secondary px-2.5 py-0.5 text-[10px] font-medium uppercase tracking-wider text-muted-foreground"
                                >
                                    Archived
                                </span>
                            </div>
                            <h2 class="mt-4 text-xl font-medium tracking-tight transition-colors group-hover:text-primary">
                                {{ section.name }}
                            </h2>
                            <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                                {{ section.subject_title }}
                            </p>
                        </div>

                        <div>
                            <div class="mt-6 grid grid-cols-2 gap-3 border-t border-border/80 pt-4 text-xs">
                                <span class="flex items-center gap-2 font-medium text-foreground/90">
                                    <Users class="size-4 text-primary" /> {{ section.students_count }} students
                                </span>
                                <span class="flex items-center gap-2 font-medium text-foreground/90">
                                    <LayoutGrid class="size-4 text-primary" /> {{ section.layout_blocks_count }} blocks
                                </span>
                            </div>
                            <p class="mt-2.5 text-[11px] text-muted-foreground">
                                {{ section.academic_term.name }} · SY {{ section.academic_term.school_year }}
                            </p>

                            <div class="mt-5 flex items-center justify-between border-t border-border/60 pt-3">
                                <button
                                    type="button"
                                    class="group/btn shadow-xs inline-flex h-9 items-center gap-1.5 rounded-xl border border-border bg-white px-3 text-xs font-medium text-muted-foreground transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white disabled:opacity-50 dark:bg-card"
                                    :disabled="archivingId === section.id"
                                    title="Move section to archive"
                                    @click="toggleArchive(section.id)"
                                >
                                    <Archive class="size-3.5 text-muted-foreground transition-colors group-hover/btn:text-white" />
                                    <span>{{ archivingId === section.id ? 'Archiving...' : 'Archive' }}</span>
                                </button>

                                <Link
                                    :href="`/sections/${section.id}`"
                                    prefetch="hover"
                                    class="group/open shadow-xs inline-flex h-9 items-center gap-1.5 rounded-xl border border-primary bg-white px-3.5 text-xs font-medium text-primary transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                                >
                                    <span>Open classroom</span>
                                    <ArrowRight class="size-3.5 text-primary transition-colors group-hover/open:text-white" />
                                </Link>
                            </div>
                        </div>
                    </article>
                </section>

                <!-- Pagination Bar (max 6 per page) -->
                <nav
                    v-if="pagination && pagination.last_page > 1"
                    aria-label="Sections pagination"
                    class="mt-10 flex flex-col items-center justify-between gap-4 border-t border-border/80 pt-6 sm:flex-row"
                >
                    <p class="text-xs text-muted-foreground">
                        Showing
                        <span class="font-medium text-foreground">{{ pagination.from ?? 0 }}</span>
                        to
                        <span class="font-medium text-foreground">{{ pagination.to ?? 0 }}</span>
                        of
                        <span class="font-medium text-foreground">{{ pagination.total }}</span>
                        sections (max 6 per page)
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

            <section v-else class="mt-8 rounded-2xl border border-dashed border-border/80 bg-card p-12 text-center shadow-sm">
                <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-primary/10 text-primary">
                    <BookOpen class="size-7" />
                </span>
                <h2 class="mt-5 text-2xl font-medium">Create your first section</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                    Set up your class, customize the room grid, and invite students to sit with a QR code.
                </p>
                <Button as-child class="ink-button mt-6">
                    <Link href="/sections/create" prefetch="hover">
                        <Plus class="size-4" />
                        <span>Create section</span>
                    </Link>
                </Button>
            </section>
        </main>
    </AppLayout>
</template>
