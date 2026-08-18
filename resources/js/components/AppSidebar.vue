<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Archive, Calendar, LayoutDashboard, School } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    { title: 'Overview', href: '/dashboard', icon: LayoutDashboard },
    { title: 'Schedule & Calendar', href: '/schedule', icon: Calendar },
    { title: 'My sections', href: '/sections', icon: School },
    { title: 'Archived classes', href: '/sections/archived', icon: Archive },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-r border-border/80 bg-sidebar/95 backdrop-blur-md">
        <SidebarHeader class="px-3 pt-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="h-12 rounded-xl transition-colors hover:bg-sidebar-accent">
                        <Link href="/dashboard" prefetch="hover"><AppLogo /></Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>
        <SidebarContent><NavMain :items="mainNavItems" /></SidebarContent>
        <SidebarFooter class="border-t border-border/80 p-3"><NavUser /></SidebarFooter>
    </Sidebar>
    <slot />
</template>
