import type { SharedData, UserSectionItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const activeSection = ref<UserSectionItem | null>(null);

export function useActiveSection() {
    const page = usePage<SharedData>();

    const currentSectionIdFromUrl = computed<number | null>(() => {
        const match = page.url.match(/^\/sections\/(\d+)/);
        if (match && match[1]) {
            return parseInt(match[1], 10);
        }
        return null;
    });

    const userSectionsList = computed<UserSectionItem[]>(() => {
        if (Array.isArray(page.props.sections)) {
            const list = (
                page.props.sections as Array<{
                    id: number;
                    name: string;
                    subject_code?: string;
                    subject_title?: string;
                }>
            ).map((s) => ({
                id: s.id,
                name: s.name,
                subject_code: s.subject_code || '',
                subject_title: s.subject_title || '',
            }));
            try {
                localStorage.setItem('classcheck_known_sections', JSON.stringify(list));
            } catch {
                // ignore
            }
            return list;
        }

        try {
            const saved = localStorage.getItem('classcheck_known_sections');
            if (saved) {
                return JSON.parse(saved);
            }
        } catch {
            // ignore
        }
        return [];
    });

    const resolveActiveSection = () => {
        // 1. If current page has direct section prop
        const pageSection = page.props.section as
            | { id: number; name: string; subject_code?: string; subject_title?: string }
            | undefined;
        if (pageSection && pageSection.id) {
            const item: UserSectionItem = {
                id: pageSection.id,
                name: pageSection.name,
                subject_code: pageSection.subject_code || '',
                subject_title: pageSection.subject_title || '',
            };
            activeSection.value = item;
            try {
                localStorage.setItem('classcheck_active_section', JSON.stringify(item));
            } catch {
                // ignore
            }
            return;
        }

        // 2. If URL contains /sections/{id}, match with userSectionsList
        const idFromUrl = currentSectionIdFromUrl.value;
        if (idFromUrl) {
            const matched = userSectionsList.value.find((s) => s.id === idFromUrl);
            if (matched) {
                activeSection.value = matched;
                try {
                    localStorage.setItem('classcheck_active_section', JSON.stringify(matched));
                } catch {
                    // ignore
                }
                return;
            } else if (!activeSection.value || activeSection.value.id !== idFromUrl) {
                activeSection.value = {
                    id: idFromUrl,
                    name: `Section #${idFromUrl}`,
                    subject_code: '',
                    subject_title: '',
                };
            }
            return;
        }

        // 3. Fallback: retrieve last active section from localStorage
        if (!activeSection.value) {
            try {
                const saved = localStorage.getItem('classcheck_active_section');
                if (saved) {
                    const parsed = JSON.parse(saved);
                    if (parsed && parsed.id) {
                        activeSection.value = parsed;
                    }
                }
            } catch {
                // ignore
            }
        }
    };

    onMounted(() => {
        resolveActiveSection();
    });

    watch(
        () => [page.url, page.props.section, page.props.userSections],
        () => {
            resolveActiveSection();
        },
        { deep: true },
    );

    const sectionName = computed(() => {
        return activeSection.value?.name || 'ClassCheck';
    });

    const courseName = computed(() => {
        if (!activeSection.value) return 'Classroom workspace';
        if (activeSection.value.subject_title) {
            return activeSection.value.subject_title;
        }
        if (activeSection.value.subject_code) {
            return activeSection.value.subject_code;
        }
        return 'Classroom workspace';
    });

    const selectSection = (sec: UserSectionItem) => {
        activeSection.value = {
            id: sec.id,
            name: sec.name,
            subject_code: sec.subject_code || '',
            subject_title: sec.subject_title || '',
        };
        try {
            localStorage.setItem('classcheck_active_section', JSON.stringify(activeSection.value));
        } catch {
            // ignore
        }
    };

    return {
        activeSection,
        userSectionsList,
        sectionName,
        courseName,
        selectSection,
        resolveActiveSection,
    };
}
