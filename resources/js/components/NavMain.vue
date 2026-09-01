<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useActiveSection } from '@/composables/useActiveSection';
import type { NavItem, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Armchair,
    BarChart3,
    CalendarCheck2,
    ChevronDown,
    ClipboardList,
    FolderKanban,
    GraduationCap,
    Layers,
    MessageSquare,
    Settings,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{ items: NavItem[] }>();
const page = usePage<SharedData>();

const { activeSection, userSectionsList, selectSection } = useActiveSection();
const showAllSections = ref(false);

// Active section sub-pages navigation list
const sectionNavItems = computed(() => {
    if (!activeSection.value) return [];
    const id = activeSection.value.id;

    return [
        {
            title: 'Classroom & Seats',
            href: `/sections/${id}`,
            icon: Armchair,
            exact: true,
        },
        {
            title: 'Attendance Register',
            href: `/sections/${id}/attendance`,
            icon: CalendarCheck2,
            exact: false,
        },
        {
            title: 'Course Modules',
            href: `/sections/${id}/modules`,
            icon: Layers,
            exact: false,
        },
        {
            title: 'Scores & Assessments',
            href: `/sections/${id}/assessments`,
            icon: ClipboardList,
            exact: false,
        },
        {
            title: 'Projects & Reports',
            href: `/sections/${id}/projects`,
            icon: FolderKanban,
            exact: false,
        },
        {
            title: 'Oral Participation',
            href: `/sections/${id}/recitation`,
            icon: MessageSquare,
            exact: false,
        },
        {
            title: 'Gradebook & Analytics',
            href: `/sections/${id}/reports/gradebook`,
            icon: BarChart3,
            exact: false,
        },
        {
            title: 'Section Settings',
            href: `/sections/${id}/edit`,
            icon: Settings,
            exact: false,
        },
    ];
});

// Check if a sub-nav link is currently active
const isSubNavActive = (itemHref: string, exact: boolean) => {
    if (exact) {
        return page.url === itemHref;
    }
    return page.url === itemHref || page.url.startsWith(itemHref + '/') || page.url.startsWith(itemHref + '?');
};

const handleSelectSection = (sec: any) => {
    selectSection(sec);
    showAllSections.value = false;
};
</script>

<template>
    <div class="space-y-4 py-2">
        <!-- Main Workspace Navigation -->
        <SidebarGroup class="px-3 py-2">
            <SidebarGroupLabel class="mb-2 px-2 text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground/80">
                Workspace
            </SidebarGroupLabel>
            <SidebarMenu class="gap-1">
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="page.url === item.href || (item.href === '/sections' && page.url === '/sections')"
                        class="data-[active=true]:shadow-xs h-10 rounded-xl px-3.5 font-medium text-sidebar-foreground/80 transition-all duration-150 hover:bg-sidebar-accent hover:text-sidebar-foreground data-[active=true]:bg-primary data-[active=true]:text-white [&[data-active=true]_svg]:text-white"
                    >
                        <Link :href="item.href" prefetch="hover" class="flex items-center gap-3">
                            <component :is="item.icon" class="size-4 shrink-0 transition-transform group-hover:scale-110" />
                            <span class="text-sm">{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>

        <!-- Active Section Context Navigation -->
        <SidebarGroup v-if="activeSection" class="border-t border-border/70 px-3 pt-4">
            <div class="mb-2 flex items-center justify-between px-2">
                <div class="flex flex-col min-w-0 flex-1">
                    <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Active Section</span>
                    <div class="flex items-center gap-1.5 truncate text-xs font-semibold text-foreground" :title="activeSection.name">
                        <span class="truncate">{{ activeSection.name }}</span>
                        <span v-if="activeSection.subject_code" class="shrink-0 rounded bg-primary/15 px-1.5 py-0.2 text-[10px] font-bold text-primary dark:bg-primary/25">
                            {{ activeSection.subject_code }}
                        </span>
                    </div>
                    <span v-if="activeSection.subject_title" class="truncate text-[11px] text-muted-foreground" :title="activeSection.subject_title">
                        {{ activeSection.subject_title }}
                    </span>
                </div>

                <button
                    v-if="userSectionsList.length > 1"
                    type="button"
                    class="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground"
                    title="Switch section"
                    @click="showAllSections = !showAllSections"
                >
                    <ChevronDown class="size-3.5 transition-transform" :class="{ 'rotate-180': showAllSections }" />
                </button>
            </div>

            <!-- Quick Section Switcher Dropdown (if toggled) -->
            <div v-if="showAllSections && userSectionsList.length > 1" class="mb-3 space-y-1 rounded-xl border border-border/80 bg-card/60 p-1.5">
                <div class="px-2 py-1 text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Switch Section</div>
                <Link
                    v-for="sec in userSectionsList"
                    :key="sec.id"
                    :href="`/sections/${sec.id}`"
                    prefetch="hover"
                    class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        sec.id === activeSection.id
                            ? 'bg-primary text-white'
                            : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-foreground'
                    "
                    @click="handleSelectSection(sec)"
                >
                    <span class="truncate">{{ sec.name }}</span>
                    <span v-if="sec.subject_code" class="font-mono text-[10px] opacity-80">{{ sec.subject_code }}</span>
                </Link>
            </div>

            <!-- Section Sub-Pages Menu -->
            <SidebarMenu class="gap-1">
                <SidebarMenuItem v-for="nav in sectionNavItems" :key="nav.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="isSubNavActive(nav.href, nav.exact)"
                        class="data-[active=true]:shadow-xs h-9 rounded-xl px-3 text-xs font-medium text-sidebar-foreground/80 transition-all duration-150 hover:bg-sidebar-accent hover:text-sidebar-foreground data-[active=true]:bg-primary data-[active=true]:text-white [&[data-active=true]_svg]:text-white"
                    >
                        <Link :href="nav.href" prefetch="hover" class="flex items-center gap-2.5">
                            <component :is="nav.icon" class="size-3.5 shrink-0" />
                            <span class="truncate">{{ nav.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>

        <!-- All Sections List (if no active section yet) -->
        <SidebarGroup v-else-if="userSectionsList.length > 0" class="border-t border-border/70 px-3 pt-4">
            <SidebarGroupLabel class="mb-2 px-2 text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground/80">
                Sections
            </SidebarGroupLabel>
            <SidebarMenu class="gap-1">
                <SidebarMenuItem v-for="sec in userSectionsList" :key="sec.id">
                    <SidebarMenuButton
                        as-child
                        class="h-9 rounded-xl px-3 text-xs font-medium text-sidebar-foreground/80 transition-all duration-150 hover:bg-sidebar-accent hover:text-sidebar-foreground"
                    >
                        <Link :href="`/sections/${sec.id}`" prefetch="hover" class="flex items-center justify-between" @click="handleSelectSection(sec)">
                            <div class="flex items-center gap-2 truncate">
                                <GraduationCap class="size-3.5 shrink-0 text-primary" />
                                <span class="truncate">{{ sec.name }}</span>
                            </div>
                            <span
                                v-if="sec.subject_code"
                                class="shadow-xs rounded bg-primary px-1.5 py-0.5 font-mono text-[9px] font-medium text-white"
                            >
                                {{ sec.subject_code }}
                            </span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </div>
</template>
