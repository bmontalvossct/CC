<script setup lang="ts">
import { useDocumentVisibility, usePreferredReducedMotion } from '@vueuse/core';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

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
        alt: 'Octo AI Mascot',
    },
);

const emit = defineEmits<{ (e: 'click', event: MouseEvent): void }>();
const images: Record<MascotState, string> = {
    normal: '/images/octo.png',
    blink: '/images/octo-blink.png',
    wink: '/images/octo-wink.png',
    happy: '/images/octo-happy.png',
    thinking: '/images/octo-thinking.png',
};
const sizes = { xs: 'size-5', sm: 'size-7', md: 'size-10', lg: 'size-14', xl: 'size-16', '2xl': 'size-20' };
const sizeClasses = computed(() => (props.size === 'custom' ? props.customClass : sizes[props.size]));
const internalState = ref<MascotState>('normal');
const isHovered = ref(false);
const isHappy = ref(false);
const readyStates = ref(new Set<MascotState>());
const mounted = ref(false);
const reducedMotion = usePreferredReducedMotion();
const visibility = useDocumentVisibility();
const canAnimate = computed(() => mounted.value && reducedMotion.value !== 'reduce' && visibility.value === 'visible');
const isBusy = computed(() => props.isThinking || props.isStreaming);
const timers = new Set<ReturnType<typeof setTimeout>>();

const activeState = computed<MascotState>(() => {
    if (props.forcedState) return props.forcedState;
    if (props.isThinking) return 'thinking';
    if (props.isStreaming) return canAnimate.value ? internalState.value : 'thinking';
    if (isHappy.value) return 'happy';
    if (isHovered.value) return 'wink';
    return internalState.value;
});
const visibleState = computed(() => (readyStates.value.has(activeState.value) ? activeState.value : 'normal'));
const dynamicBubbleText = computed(() =>
    props.isThinking ? 'Thinking it through?' : props.isStreaming ? 'Putting your answer together?' : props.bubbleText,
);

function later(callback: () => void, delay: number) {
    const timer = setTimeout(() => {
        timers.delete(timer);
        callback();
    }, delay);
    timers.add(timer);
}
function clearTimers() {
    timers.forEach(clearTimeout);
    timers.clear();
}
function scheduleBlink() {
    later(
        () => {
            if (!isHovered.value && !isHappy.value) internalState.value = 'blink';
            later(() => {
                internalState.value = 'normal';
                scheduleBlink();
            }, 160);
        },
        3800 + Math.random() * 2600,
    );
}
function cycleThinking() {
    later(() => {
        internalState.value = internalState.value === 'thinking' ? 'happy' : 'thinking';
        cycleThinking();
    }, 1800);
}
watch(
    [canAnimate, isBusy, () => props.interactive, () => props.forcedState],
    () => {
        clearTimers();
        isHappy.value = false;
        internalState.value = isBusy.value ? 'thinking' : 'normal';
        if (!canAnimate.value || props.forcedState) return;
        if (props.isStreaming) cycleThinking();
        else if (props.interactive && !isBusy.value) scheduleBlink();
    },
    { immediate: true },
);

function handleClick(event: MouseEvent) {
    if (props.interactive && !isBusy.value && canAnimate.value) {
        isHappy.value = true;
        later(() => {
            isHappy.value = false;
        }, 650);
    }
    emit('click', event);
}
onMounted(() => {
    mounted.value = true;
});
onUnmounted(clearTimers);
</script>

<template>
    <div
        class="group/octo relative inline-flex select-none items-center justify-center"
        @mouseenter="isHovered = interactive"
        @mouseleave="isHovered = false"
        @click="handleClick"
    >
        <Transition name="octo-bubble">
            <div
                v-if="showBubble && (isHovered || isBusy)"
                class="pointer-events-none absolute -top-12 right-0 z-50 flex items-center gap-1.5 whitespace-nowrap rounded-xl border border-border bg-card px-3 py-2 text-xs text-foreground shadow-lg"
            >
                <span class="font-bold text-primary">Octo</span>
                <span>{{ dynamicBubbleText }}</span>
                <span class="absolute -bottom-1 right-5 size-2 rotate-45 border-b border-r border-border bg-card" />
            </div>
        </Transition>
        <div
            class="octo-frame relative"
            :class="[
                sizeClasses,
                { 'octo-busy': isBusy && canAnimate, 'octo-interactive': interactive && canAnimate, 'octo-blink': activeState === 'blink' },
            ]"
            role="img"
            :aria-label="alt"
        >
            <img
                v-for="(src, state) in images"
                :key="state"
                :src="src"
                alt=""
                aria-hidden="true"
                width="1024"
                height="1024"
                class="octo-state absolute inset-0 size-full object-contain"
                :class="{ 'octo-visible': visibleState === state }"
                decoding="async"
                draggable="false"
                @load="readyStates.add(state)"
            />
            <span v-if="isBusy" class="pointer-events-none absolute -inset-1 rounded-full border border-primary/20" aria-hidden="true" />
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
.octo-state {
    opacity: 0;
    transition: opacity 140ms ease;
}
.octo-visible {
    opacity: 1;
}
.octo-blink .octo-state {
    transition-duration: 55ms;
}
.group\/octo:hover .octo-interactive {
    transform: translateY(-2px) rotate(-3deg) scale(1.04);
}
.group\/octo:active .octo-interactive {
    transform: scale(0.96);
}
.octo-busy {
    animation: octo-float 2.8s ease-in-out infinite;
}
.octo-bubble-enter-active,
.octo-bubble-leave-active {
    transition:
        opacity 180ms,
        transform 220ms var(--ease-out);
}
.octo-bubble-enter-from,
.octo-bubble-leave-to {
    opacity: 0;
    transform: translateY(4px);
}
@keyframes octo-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-3px) rotate(-1deg);
    }
}
</style>
