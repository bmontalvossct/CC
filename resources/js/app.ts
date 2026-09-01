import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { SpeedInsights } from '@vercel/speed-insights/vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, Fragment, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import { modalFocus } from './directives/modalFocus';

const appName = import.meta.env.VITE_APP_NAME || 'ClassCheck';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(Fragment, [h(App, props), h(SpeedInsights)]) });
        app.directive('modal-focus', modalFocus);
        app.use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
