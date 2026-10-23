import type { Page } from '@inertiajs/core';
import type { SharedData } from './index';

declare module '@vue/runtime-core' {
    interface ComponentCustomProperties {
        $page: Page<SharedData>;
    }
}
