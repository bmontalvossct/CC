<script setup lang="ts">
import { useDocumentVisibility, usePreferredReducedMotion } from '@vueuse/core';
import { computed, onUnmounted, ref } from 'vue';

type MascotState = 'normal' | 'blink' | 'wink' | 'happy' | 'thinking';
const props = withDefaults(
    defineProps<{
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'custom';
        customClass?: string;
        interactive?: boolean;
        showBubble?: boolean;
        bubbleText?: string;
        showStatus?: boolean;
        isOnline?: boolean;
        forcedState?: MascotState | null;
        isThinking?: boolean;
        isStreaming?: boolean;
        alt?: string;
    }>(),
    {
        size: 'md',
        customClass: '',
        interactive: true,
        showBubble: false,
        bubbleText: 'Ask me anything! (Ctrl+J)',
        showStatus: false,
        isOnline: false,
        forcedState: null,
        isThinking: false,
        isStreaming: false,
        alt: 'Octo, your AI teaching companion',
    },
);
const emit = defineEmits<{ (e: 'click', event: MouseEvent): void }>();
const sizes = { xs: 'size-5', sm: 'size-7', md: 'size-10', lg: 'size-14', xl: 'size-16', '2xl': 'size-20' };
const sizeClasses = computed(() => (props.size === 'custom' ? props.customClass : sizes[props.size]));
const reducedMotion = usePreferredReducedMotion();
const visibility = useDocumentVisibility();
const canAnimate = computed(() => reducedMotion.value !== 'reduce' && visibility.value === 'visible');
const isBusy = computed(() => props.isThinking || props.isStreaming);
const isHovered = ref(false);
const isHappy = ref(false);
let greetingTimer: ReturnType<typeof setTimeout> | undefined;
const dynamicBubbleText = computed(() =>
    props.isThinking ? 'Thinking it through…' : props.isStreaming ? 'Putting your answer together…' : props.bubbleText,
);
function handleClick(event: MouseEvent) {
    if (props.interactive && !isBusy.value && canAnimate.value) {
        clearTimeout(greetingTimer);
        isHappy.value = true;
        greetingTimer = setTimeout(() => {
            isHappy.value = false;
        }, 650);
    }
    emit('click', event);
}
onUnmounted(() => clearTimeout(greetingTimer));
</script>

<template>
    <div
        class="group/octo relative inline-flex select-none items-center justify-center"
        @mouseenter="isHovered = interactive"
        @mouseleave="isHovered = false"
        @click="handleClick"
    >
        <div
            v-if="showBubble && (isHovered || isBusy)"
            class="pointer-events-none absolute -top-12 right-0 z-50 flex items-center gap-1.5 whitespace-nowrap rounded-xl border border-border bg-card px-3 py-2 text-xs text-foreground shadow-lg"
        >
            <span class="text-primary">Octo</span><span>{{ dynamicBubbleText }}</span>
        </div>
        <div
            class="octo-frame relative"
            :class="[
                sizeClasses,
                {
                    'octo-animated': canAnimate && (interactive || isBusy),
                    'octo-busy': isBusy || forcedState === 'thinking',
                    'octo-happy': isHappy || forcedState === 'happy',
                    'octo-interactive': interactive && canAnimate,
                },
            ]"
            role="img"
            :aria-label="alt"
        >
            <img
                src="/images/octo-scholar.jpg"
                alt=""
                aria-hidden="true"
                width="1024"
                height="1024"
                class="octo-portrait size-full rounded-full object-contain"
                decoding="async"
                draggable="false"
            />
            <span
                v-if="showStatus"
                class="absolute bottom-0 right-0 size-3 rounded-full border-2 border-background shadow-sm"
                :class="isBusy ? 'bg-primary' : isOnline ? 'bg-emerald-500' : 'bg-amber-500'"
                :title="isBusy ? 'Preparing a response' : isOnline ? 'AI connected' : 'AI offline'"
            />
        </div>
    </div>
</template>

<style scoped>
.octo-frame {
    transition: transform 300ms var(--ease-out);
}
.octo-animated .octo-portrait {
    animation: octo-float 5s ease-in-out infinite;
}
.octo-busy.octo-animated .octo-portrait {
    animation-duration: 2.4s;
}
.octo-happy.octo-animated .octo-portrait {
    animation: octo-wave 700ms ease-in-out infinite alternate;
}
.group\/octo:hover .octo-interactive,
:global(button:focus-visible) .octo-interactive {
    transform: rotate(-5deg) scale(1.03);
}
.group\/octo:active .octo-interactive {
    transform: scale(0.96);
}
@keyframes octo-float {
    0%,
    100% {
        transform: translateY(0) rotate(-1deg);
    }
    50% {
        transform: translateY(-2.5%) rotate(1.5deg);
    }
}
@keyframes octo-wave {
    from {
        transform: rotate(-4deg);
    }
    to {
        transform: translateY(-2%) rotate(4deg);
    }
}
@media (prefers-reduced-motion: reduce) {
    .octo-frame {
        transition: none;
    }
    .octo-portrait {
        animation: none !important;
    }
}
</style>
