import type { ObjectDirective } from 'vue';

const cleanups = new WeakMap<HTMLElement, () => void>();

/** Reveal once, then remove all observers so long pages stay inexpensive. */
export const reveal: ObjectDirective<HTMLElement, number | undefined> = {
    mounted(el, { value }) {
        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        if (motion.matches || !('IntersectionObserver' in window)) return;

        el.style.setProperty('--reveal-delay', `${Math.min(Math.max(value ?? 0, 0), 300)}ms`);
        el.dataset.reveal = 'pending';

        const finish = () => {
            observer.disconnect();
            motion.removeEventListener('change', onPreferenceChange);
            cleanups.delete(el);
        };
        const onPreferenceChange = () => {
            if (motion.matches) {
                delete el.dataset.reveal;
                finish();
            }
        };
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    el.dataset.reveal = 'visible';
                    finish();
                }
            },
            { threshold: 0.08 },
        );

        motion.addEventListener('change', onPreferenceChange);
        observer.observe(el);
        cleanups.set(el, finish);
    },
    beforeUnmount(el) {
        cleanups.get(el)?.();
    },
};
