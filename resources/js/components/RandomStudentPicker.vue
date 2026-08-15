<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Armchair, Dices, RotateCcw, Sparkles, User, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    students: Array<{
        id: number;
        student_number: string;
        first_name: string;
        last_name: string;
        full_name?: string;
        photo_url?: string | null;
        seat?: { label: string } | null;
    }>;
}>();

const open = ref(false);
const isRolling = ref(false);
const selectedStudent = ref<(typeof props.students)[0] | null>(null);
const currentDisplayName = ref('');

const eligibleStudents = computed(() => props.students);

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
        currentDisplayName.value = candidate.full_name || `${candidate.first_name} ${candidate.last_name}`;
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
        <Button
            type="button"
            variant="outline"
            class="h-10 rounded-xl text-xs font-semibold hover:bg-secondary hover:text-primary transition-colors gap-2"
            :disabled="!students.length"
            @click="pickRandom"
        >
            <Dices class="size-4 text-primary" />
            <span>Pick student</span>
        </Button>

        <!-- Random Picker Modal -->
        <div
            v-if="open"
            class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 backdrop-blur-md animate-in fade-in duration-200"
            @click.self="close"
        >
            <div
                class="paper-card relative w-full max-w-md overflow-hidden p-8 text-center shadow-2xl animate-in zoom-in-95 duration-200 border-border/90"
                role="dialog"
                aria-modal="true"
                aria-label="Random student selector"
            >
                <button
                    type="button"
                    class="absolute right-4 top-4 grid size-8 place-items-center rounded-full text-muted-foreground hover:bg-secondary hover:text-foreground transition-colors"
                    @click="close"
                >
                    <X class="size-4.5" />
                </button>

                <div class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 font-mono text-[11px] font-bold uppercase tracking-wider text-primary border border-primary/20">
                    <Sparkles class="size-3.5" /> Random recitation
                </div>

                <h3 class="mt-3 text-2xl font-extrabold tracking-tight">Who's Next?</h3>

                <!-- Rolling State -->
                <div v-if="isRolling" class="my-8 py-6">
                    <div class="mx-auto flex size-24 items-center justify-center rounded-full bg-primary/15 border-2 border-primary animate-pulse shadow-lg">
                        <Dices class="size-10 text-primary animate-spin" />
                    </div>
                    <p class="mt-6 text-xl font-extrabold tracking-tight text-foreground truncate">
                        {{ currentDisplayName }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground font-medium">Selecting a student...</p>
                </div>

                <!-- Landed Result State -->
                <div v-else-if="selectedStudent" class="my-6 animate-in zoom-in-90 duration-300">
                    <div class="relative mx-auto size-28">
                        <img
                            v-if="selectedStudent.photo_url"
                            :src="selectedStudent.photo_url"
                            alt=""
                            class="size-28 rounded-3xl object-cover border-4 border-primary/40 shadow-xl"
                        />
                        <div
                            v-else
                            class="grid size-28 place-items-center rounded-3xl bg-primary text-3xl font-extrabold text-white shadow-xl shadow-primary/25 border-4 border-white/20"
                        >
                            {{ initials(selectedStudent.full_name || `${selectedStudent.first_name} ${selectedStudent.last_name}`) }}
                        </div>
                    </div>

                    <h4 class="mt-5 text-2xl font-extrabold tracking-tight text-foreground">
                        {{ selectedStudent.full_name || `${selectedStudent.first_name} ${selectedStudent.last_name}` }}
                    </h4>
                    <p class="mt-0.5 font-mono text-xs text-muted-foreground">{{ selectedStudent.student_number }}</p>

                    <!-- Location chip -->
                    <div class="mt-4 inline-flex items-center gap-2 rounded-xl bg-secondary/80 px-4 py-2 text-xs font-bold border border-border">
                        <Armchair class="size-4 text-primary" />
                        <span>Seated at: <strong class="font-mono text-primary">{{ selectedStudent.seat?.label || 'Unseated' }}</strong></span>
                    </div>

                    <div class="mt-7 flex justify-center gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-xl text-xs font-semibold px-4"
                            @click="pickRandom"
                        >
                            <RotateCcw class="size-3.5 mr-1.5" /> Pick another
                        </Button>
                        <Button
                            type="button"
                            class="ink-button !h-10 !rounded-xl !px-5 text-xs font-semibold"
                            @click="close"
                        >
                            Done
                        </Button>
                    </div>
                </div>

                <p v-else class="my-8 text-xs text-muted-foreground">Click below to pick a student randomly.</p>
            </div>
        </div>
    </div>
</template>
