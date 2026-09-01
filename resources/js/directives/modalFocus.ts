import type { Directive } from 'vue';

export const modalFocus: Directive = {
    mounted(el: HTMLElement) {
        const dialog = el.querySelector<HTMLElement>('[role="dialog"], section, .paper-card, [tabindex]') || el;
        if (!dialog.hasAttribute('tabindex')) {
            dialog.setAttribute('tabindex', '-1');
        }

        const focusTarget = () => {
            const firstInput = el.querySelector<HTMLElement>(
                'input:not([type=hidden]):not([disabled]):not([readonly]), textarea:not([disabled]):not([readonly]), select:not([disabled]), [autofocus]'
            );
            if (firstInput) {
                firstInput.focus();
            } else {
                dialog.focus();
            }
        };

        // Defer to next frame so the DOM is rendered
        requestAnimationFrame(() => {
            setTimeout(focusTarget, 60);
        });

        // Clicking the side / backdrop refocuses the modal without closing it
        el.addEventListener('click', (e: MouseEvent) => {
            if (e.target === el) {
                e.preventDefault();
                focusTarget();
            }
        });
    },
};
