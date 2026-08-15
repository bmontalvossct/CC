<script setup lang="ts">
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';

defineProps<{ breadcrumbs?: BreadcrumbItemType[] }>();
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center justify-between border-b border-border/80 bg-background/80 px-4 backdrop-blur-xl transition-[width,height] ease-linear md:px-8"
    >
        <div class="flex items-center gap-3">
            <SidebarTrigger class="-ml-2 size-9 rounded-xl" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumb class="hidden sm:block">
                    <BreadcrumbList>
                        <template v-for="(item, index) in breadcrumbs" :key="index">
                            <BreadcrumbItem>
                                <BreadcrumbPage v-if="index === breadcrumbs.length - 1" class="text-xs font-medium">{{ item.title }}</BreadcrumbPage>
                                <BreadcrumbLink v-else :href="item.href" class="text-xs font-medium text-muted-foreground hover:text-foreground">{{ item.title }}</BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </template>
        </div>

        <div class="flex items-center gap-2.5">
            <ThemeToggle />
        </div>
    </header>
</template>

