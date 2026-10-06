<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

defineProps<{ reason: string }>();
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Tooltip :disabled="!reason">
            <TooltipTrigger as-child>
                <span class="inline-flex [&>button:disabled]:pointer-events-none" :tabindex="reason ? 0 : undefined" :aria-label="reason || undefined"
                    ><slot
                /></span>
            </TooltipTrigger>
            <TooltipContent v-if="reason" class="max-w-72 text-sm leading-relaxed">
                {{ reason }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
