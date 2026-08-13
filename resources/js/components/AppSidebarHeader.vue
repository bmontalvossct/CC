<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';

defineProps<{ breadcrumbs?: BreadcrumbItemType[] }>();
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center border-b border-border bg-background/90 px-5 backdrop-blur-xl transition-[width,height] ease-linear md:px-8"
    >
        <div class="flex items-center gap-3">
            <SidebarTrigger class="-ml-2 size-9 rounded-full" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumb>
                    <BreadcrumbList>
                        <template v-for="(item, index) in breadcrumbs" :key="index">
                            <BreadcrumbItem>
                                <BreadcrumbPage v-if="index === breadcrumbs.length - 1" class="text-sm font-medium">{{ item.title }}</BreadcrumbPage>
                                <BreadcrumbLink v-else :href="item.href" class="text-sm">{{ item.title }}</BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </template>
        </div>
    </header>
</template>
