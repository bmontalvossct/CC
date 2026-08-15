<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    BookOpen,
    CalendarCheck2,
    ClipboardList,
    GraduationCap,
    LayoutDashboard,
    Plus,
    Search,
    Settings,
    Sparkles,
    User,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const open = ref(false);
const query = ref('');
const activeIndex = ref(0);
const searchInput = ref<HTMLInputElement | null>(null);

const items = [
    { title: 'Overview Dashboard', subtitle: 'View stats and active classrooms', href: '/dashboard', icon: LayoutDashboard, category: 'Navigation' },
    { title: 'My Sections', subtitle: 'List all active and archived classes', href: '/sections', icon: GraduationCap, category: 'Navigation' },
    { title: 'Create New Section', subtitle: 'Set up a new course and seating chart', href: '/sections/create', icon: Plus, category: 'Quick Actions' },
    { title: 'Profile Settings', subtitle: 'Manage your name and email', href: '/settings/profile', icon: User, category: 'Settings' },
    { title: 'Appearance & Theme', subtitle: 'Customize light, dark or system mode', href: '/settings/appearance', icon: Settings, category: 'Settings' },
    { title: 'Security & Password', subtitle: 'Update your account password', href: '/settings/password', icon: Settings, category: 'Settings' },
];

const filteredItems = computed(() => {
    const q = query.value.toLowerCase().trim();
    if (!q) return items;
    return items.filter(
        (item) =>
            item.title.toLowerCase().includes(q) ||
            item.subtitle.toLowerCase().includes(q) ||
            item.category.toLowerCase().includes(q),
    );
});

const openPalette = () => {
    open.value = true;
    query.value = '';
    activeIndex.value = 0;
    nextTick(() => {
        searchInput.value?.focus();
    });
};

const closePalette = () => {
    open.value = false;
};

const navigateTo = (href: string) => {
    closePalette();
    router.visit(href);
};

const onKeydown = (event: KeyboardEvent) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        if (open.value) closePalette();
        else openPalette();
    }

    if (!open.value) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        closePalette();
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % Math.max(1, filteredItems.value.length);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value - 1 + filteredItems.value.length) % Math.max(1, filteredItems.value.length);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const selected = filteredItems.value[activeIndex.value];
        if (selected) navigateTo(selected.href);
    }
};

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
});

defineExpose({ openPalette });
</script>

<template>
    <div>
        <!-- Trigger button for headers / toolbars -->
        <button
            type="button"
            class="flex h-9 w-44 sm:w-60 items-center justify-between rounded-xl border border-border/80 bg-card/70 px-3 text-xs text-muted-foreground shadow-xs transition-all hover:bg-secondary hover:border-primary/40 hover:text-foreground focus-visible:ring-2 focus-visible:ring-primary"
            @click="openPalette"
        >
            <span class="flex items-center gap-2">
                <Search class="size-3.5 text-primary" />
                <span class="truncate">Quick search...</span>
            </span>
            <kbd class="hidden sm:inline-flex h-5 items-center rounded-md border border-border bg-secondary px-1.5 font-mono text-[10px] font-bold text-muted-foreground">
                ⌘K
            </kbd>
        </button>

        <!-- Command Palette Modal -->
        <div
            v-if="open"
            class="fixed inset-0 z-50 grid place-items-start justify-center bg-zinc-950/60 p-4 pt-16 sm:pt-24 backdrop-blur-md animate-in fade-in duration-150"
            @click.self="closePalette"
        >
            <div
                class="paper-card w-full max-w-xl overflow-hidden p-0 shadow-2xl animate-in zoom-in-95 duration-150 border-border/90"
                role="dialog"
                aria-modal="true"
                aria-label="Command palette"
            >
                <!-- Search Input Bar -->
                <div class="flex items-center border-b border-border/80 px-4 py-3.5 bg-card">
                    <Search class="size-4.5 text-primary shrink-0 mr-3" />
                    <input
                        ref="searchInput"
                        v-model="query"
                        type="text"
                        placeholder="Search commands, pages, and actions..."
                        class="w-full bg-transparent text-sm font-medium text-foreground placeholder:text-muted-foreground focus:outline-none"
                    />
                    <button
                        type="button"
                        class="grid size-6 place-items-center rounded-lg text-muted-foreground hover:bg-secondary hover:text-foreground"
                        @click="closePalette"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <!-- Results List -->
                <div class="max-h-80 overflow-y-auto p-2">
                    <div v-if="filteredItems.length" class="space-y-1">
                        <button
                            v-for="(item, index) in filteredItems"
                            :key="item.href"
                            type="button"
                            class="flex w-full items-center justify-between rounded-xl px-3.5 py-2.5 text-left transition-colors"
                            :class="
                                index === activeIndex
                                    ? 'bg-primary text-primary-foreground shadow-xs font-semibold'
                                    : 'hover:bg-secondary text-foreground'
                            "
                            @click="navigateTo(item.href)"
                            @mouseenter="activeIndex = index"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <component
                                    :is="item.icon"
                                    class="size-4 shrink-0"
                                    :class="index === activeIndex ? 'text-white' : 'text-primary'"
                                />
                                <div class="min-w-0">
                                    <p class="text-xs font-bold truncate leading-tight">{{ item.title }}</p>
                                    <p
                                        class="text-[11px] truncate leading-none mt-0.5"
                                        :class="index === activeIndex ? 'text-white/80' : 'text-muted-foreground'"
                                    >
                                        {{ item.subtitle }}
                                    </p>
                                </div>
                            </div>
                            <span
                                class="rounded-md px-2 py-0.5 font-mono text-[9px] font-bold uppercase tracking-wider shrink-0 ml-2"
                                :class="
                                    index === activeIndex
                                        ? 'bg-white/20 text-white'
                                        : 'bg-secondary text-muted-foreground'
                                "
                            >
                                {{ item.category }}
                            </span>
                        </button>
                    </div>

                    <div v-else class="py-10 text-center text-xs text-muted-foreground">
                        No actions or pages found for "{{ query }}".
                    </div>
                </div>

                <!-- Footer Shortcut Help -->
                <div class="flex items-center justify-between border-t border-border/80 bg-secondary/40 px-4 py-2 text-[10px] text-muted-foreground font-medium">
                    <div class="flex items-center gap-3">
                        <span><kbd class="font-mono font-bold">↑↓</kbd> to navigate</span>
                        <span><kbd class="font-mono font-bold">↵</kbd> to select</span>
                        <span><kbd class="font-mono font-bold">esc</kbd> to close</span>
                    </div>
                    <span class="font-semibold text-primary">ClassCheck Quick Jump</span>
                </div>
            </div>
        </div>
    </div>
</template>
