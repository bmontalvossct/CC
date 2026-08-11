<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{ items: NavItem[] }>();
const page = usePage<SharedData>();
</script>

<template>
    <SidebarGroup class="px-3 py-3">
        <SidebarGroupLabel class="mb-2 text-[10px] font-bold uppercase tracking-[.2em] text-[#7c776b]">Workspace</SidebarGroupLabel>
        <SidebarMenu class="gap-1.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="page.url === item.href || (item.href !== '/dashboard' && page.url.startsWith(item.href))"
                    class="h-10 rounded-xl text-[#47534e] data-[active=true]:bg-[#18352f] data-[active=true]:text-[#fff8e8]"
                >
                    <Link :href="item.href"
                        ><component :is="item.icon" class="size-4" /><span class="font-semibold">{{ item.title }}</span></Link
                    >
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
