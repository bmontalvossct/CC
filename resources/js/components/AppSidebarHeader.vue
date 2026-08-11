<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';

defineProps<{ breadcrumbs?: BreadcrumbItemType[] }>();
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-[#d8d0bd]/70 bg-[#fbf7ec]/80 px-6 backdrop-blur-md transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 dark:bg-[#16231f]/80 md:px-5"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumb>
                    <BreadcrumbList>
                        <template v-for="(item, index) in breadcrumbs" :key="index">
                            <BreadcrumbItem>
                                <BreadcrumbPage v-if="index === breadcrumbs.length - 1">{{ item.title }}</BreadcrumbPage>
                                <BreadcrumbLink v-else :href="item.href">{{ item.title }}</BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </template>
        </div>
    </header>
</template>
