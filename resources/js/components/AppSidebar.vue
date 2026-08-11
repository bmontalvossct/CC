<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { LayoutDashboard, School } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    { title: 'Overview', href: '/dashboard', icon: LayoutDashboard },
    { title: 'My sections', href: '/sections', icon: School },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-r-0">
        <SidebarHeader class="px-3 pt-4"
            ><SidebarMenu
                ><SidebarMenuItem
                    ><SidebarMenuButton size="lg" as-child class="h-12 rounded-xl"
                        ><Link href="/dashboard"><AppLogo /></Link></SidebarMenuButton></SidebarMenuItem></SidebarMenu
        ></SidebarHeader>
        <SidebarContent><NavMain :items="mainNavItems" /></SidebarContent>
        <SidebarFooter class="border-t border-[#d8d0bd]/70 p-3"><NavUser /></SidebarFooter>
    </Sidebar>
    <slot />
</template>
