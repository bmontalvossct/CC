<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{ items: NavItem[] }>();
const page = usePage<SharedData>();
</script>

<template>
    <SidebarGroup class="px-3 py-4">
        <SidebarGroupLabel class="mb-2 px-2 text-[11px] font-bold uppercase tracking-[0.14em] text-muted-foreground/80">Workspace</SidebarGroupLabel>
        <SidebarMenu class="gap-1.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="page.url === item.href || (item.href !== '/dashboard' && page.url.startsWith(item.href))"
                    class="h-10 rounded-xl px-3.5 text-sidebar-foreground/80 font-medium transition-all duration-150 hover:bg-sidebar-accent hover:text-sidebar-foreground data-[active=true]:bg-primary/10 data-[active=true]:font-semibold data-[active=true]:text-primary"
                >
                    <Link :href="item.href" prefetch="hover" class="flex items-center gap-3">
                        <component :is="item.icon" class="size-4 shrink-0 transition-transform group-hover:scale-110" />
                        <span class="text-sm">{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
