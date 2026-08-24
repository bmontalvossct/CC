<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { Armchair, Dices, Mic, RotateCcw, Sparkles, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    sectionId?: number;
    students: Array<{
        id: number;
        student_number: string;
        first_name: string;
        last_name: string;
        full_name?: string;
        photo_url?: string | null;
        seat?: { label: string } | null;
    }>;
    calledTodayIds?: number[];
}>();

const open = ref(false);
const isRolling = ref(false);
const selectedStudent = ref<(typeof props.students)[0] | null>(null);
const currentDisplayName = ref('');

const eligibleStudents = computed(() => {
    const excluded = props.calledTodayIds ?? [];
    return props.students.filter((student) => !excluded.includes(student.id));
});

const initials = (name?: string) => {
    if (!name) return '';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();
};

const pickRandom = () => {
    if (!eligibleStudents.value.length) return;
    open.value = true;
    isRolling.value = true;
    selectedStudent.value = null;

    let iterations = 0;
    const maxIterations = 20;
    const intervalTime = 60;

    const timer = setInterval(() => {
        const randomIndex = Math.floor(Math.random() * eligibleStudents.value.length);
        const candidate = eligibleStudents.value[randomIndex];
        currentDisplayName.value = candidate.full_name || `${candidate.last_name}, ${candidate.first_name}`;
        iterations++;

        if (iterations >= maxIterations) {
            clearInterval(timer);
            selectedStudent.value = candidate;
            isRolling.value = false;
        }
    }, intervalTime);
};

const close = () => {
    open.value = false;
    selectedStudent.value = null;
    isRolling.value = false;
};
</script>

<template>
    <div>
        <button
            type="button"
            class="shadow-xs group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-4 text-sm font-medium text-primary transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white disabled:opacity-50 dark:bg-card"
            :disabled="!eligibleStudents.length"
            @click="pickRandom"
        >
            <Dices class="size-4 text-primary transition-colors group-hover:text-white" />
            <span>Pick student</span>
        </button>

        <!-- Random Picker Modal -->
        <div
            v-if="open"
            class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
            @click.self="close"
        >
            <div
                class="paper-card relative w-full max-w-md overflow-hidden border-border/90 p-8 text-center shadow-2xl duration-200 animate-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                aria-label="Random student selector"
            >
                <button
                    type="button"
                    class="absolute right-4 top-4 grid size-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                    @click="close"
                >
                    <X class="size-4" />
                </button>

                <div class="inline-flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <Sparkles class="size-7" />
                </div>

                <h3 class="mt-4 text-xl font-medium tracking-tight text-foreground">Random student caller</h3>
                <p class="mt-1 text-xs font-normal text-muted-foreground">
                    Selecting from <span class="font-bold text-foreground">{{ eligibleStudents.length }}</span> eligible present students
                </p>

                <!-- Rolling State -->
                <div v-if="isRolling" class="my-8">
                    <div class="mx-auto size-28 animate-bounce rounded-3xl bg-secondary/80 p-3">
                        <div class="grid size-full place-items-center rounded-2xl bg-primary text-3xl font-medium text-white shadow-inner">?</div>
                    </div>
                    <p class="mt-6 truncate text-xl font-medium tracking-tight text-foreground">
                        {{ currentDisplayName }}
                    </p>
                    <p class="mt-1 text-xs font-normal text-muted-foreground">Selecting a student...</p>
                </div>

                <!-- Landed Result State -->
                <div v-else-if="selectedStudent" class="my-6 duration-300 animate-in zoom-in-90">
                    <div class="relative mx-auto size-28">
                        <img
                            v-if="selectedStudent.photo_url"
                            :src="selectedStudent.photo_url"
                            alt=""
                            class="size-28 rounded-3xl border-4 border-primary/40 object-cover shadow-xl"
                        />
                        <div
                            v-else
                            class="grid size-28 place-items-center rounded-3xl border-4 border-white/20 bg-primary text-3xl font-medium text-white shadow-xl shadow-primary/25"
                        >
                            {{ initials(selectedStudent.full_name || `${selectedStudent.last_name}, ${selectedStudent.first_name}`) }}
                        </div>
                    </div>

                    <h4 class="mt-5 text-2xl font-medium tracking-tight text-foreground">
                        {{ selectedStudent.full_name || `${selectedStudent.last_name}, ${selectedStudent.first_name}` }}
                    </h4>
                    <p class="mt-0.5 font-mono text-xs text-muted-foreground">{{ selectedStudent.student_number }}</p>

                    <!-- Location chip -->
                    <div class="mt-4 inline-flex items-center gap-2 rounded-xl border border-border bg-secondary/80 px-4 py-2 text-xs font-medium">
                        <Armchair class="size-4 text-primary" />
                        <span
                            >Seated at: <span class="font-mono font-medium text-primary">{{ selectedStudent.seat?.label || 'Unseated' }}</span></span
                        >
                    </div>

                    <div class="mt-7 flex flex-wrap justify-center gap-2.5">
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-xl px-3.5 text-xs font-medium"
                            :disabled="!eligibleStudents.length"
                            @click="pickRandom"
                        >
                            <RotateCcw class="mr-1.5 size-3.5" /> Pick another
                        </Button>
                        <Link
                            v-if="sectionId"
                            :href="`/sections/${sectionId}/recitation`"
                            class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl border border-primary/20 bg-primary/10 px-4 text-xs font-bold text-primary transition-all hover:bg-primary/20"
                            @click="close"
                        >
                            <Mic class="size-3.5" />
                            <span>Score Oral Participation</span>
                        </Link>
                        <Button type="button" class="ink-button !h-10 !rounded-xl !px-5 text-xs font-medium" @click="close"> Done </Button>
                    </div>
                </div>

                <div v-else class="my-8 text-center">
                    <p v-if="!eligibleStudents.length" class="text-xs font-medium text-muted-foreground">All students have been called today!</p>
                    <p v-else class="text-xs text-muted-foreground">Click below to pick a student randomly.</p>
                </div>
            </div>
        </div>
    </div>
</template>
