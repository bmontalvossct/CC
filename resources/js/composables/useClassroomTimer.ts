import { ref } from 'vue';

const isTimerOpen = ref(false);

export function useClassroomTimer() {
    const toggleTimer = () => {
        isTimerOpen.value = !isTimerOpen.value;
    };

    const openTimer = () => {
        isTimerOpen.value = true;
    };

    const closeTimer = () => {
        isTimerOpen.value = false;
    };

    return {
        isTimerOpen,
        toggleTimer,
        openTimer,
        closeTimer,
    };
}
