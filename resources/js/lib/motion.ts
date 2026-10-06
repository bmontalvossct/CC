/**
 * motion.dev animation presets and spring physics helpers for ClassCheck
 */

export const springDefault = {
    type: 'spring',
    stiffness: 400,
    damping: 30,
} as const;

export const springGentle = {
    type: 'spring',
    stiffness: 280,
    damping: 28,
} as const;

export const springSnappy = {
    type: 'spring',
    stiffness: 450,
    damping: 32,
} as const;

export const springBouncy = {
    type: 'spring',
    stiffness: 450,
    damping: 22,
} as const;

export const modalBackdropVariants = {
    initial: { opacity: 0 },
    animate: { opacity: 1 },
    exit: { opacity: 0 },
    transition: { duration: 0.18 },
};

export const modalContentVariants = {
    initial: { opacity: 0, scale: 0.95, y: 10 },
    animate: { opacity: 1, scale: 1, y: 0 },
    exit: { opacity: 0, scale: 0.95, y: 10 },
    transition: { type: 'spring', stiffness: 380, damping: 28 },
};

export const tabIndicatorTransition = {
    type: 'spring',
    stiffness: 450,
    damping: 32,
} as const;

export const cardHover = {
    y: -3,
    transition: { duration: 0.15, ease: 'easeOut' },
} as const;

export const buttonPress = {
    scale: 0.98,
} as const;

export function staggerItem(index: number, baseDelay: number = 0.035) {
    return {
        delay: Math.min(index * baseDelay, 0.4),
        duration: 0.26,
        ease: [0.22, 1, 0.36, 1],
    };
}
