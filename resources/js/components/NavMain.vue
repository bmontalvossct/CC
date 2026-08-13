<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{ items: NavItem[] }>();
const page = usePage<SharedData>();
</script>

<template>
    <SidebarGroup class="px-3 py-4">
        <SidebarGroupLabel class="mb-2 px-2 text-[11px] font-medium tracking-normal text-[#86868b]">Workspace</SidebarGroupLabel>
        <SidebarMenu class="gap-1">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="page.url === item.href || (item.href !== '/dashboard' && page.url.startsWith(item.href))"
                    class="h-10 rounded-md px-3 text-[#515154] transition-colors hover:bg-white hover:text-[#1d1d1f] data-[active=true]:bg-white data-[active=true]:font-medium data-[active=true]:text-[#0071e3] dark:hover:bg-secondary dark:data-[active=true]:bg-secondary"
                >
                    <Link :href="item.href"
                        ><component :is="item.icon" class="size-4" /><span>{{ item.title }}</span></Link
                    >
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
