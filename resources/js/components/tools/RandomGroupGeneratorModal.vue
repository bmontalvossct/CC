<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Check,
    Clipboard,
    Copy,
    Dices,
    Printer,
    RefreshCw,
    Shuffle,
    Sparkles,
    UserCheck,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    students: any[];
    sectionName: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

type SplitMode = 'by_group_count' | 'by_group_size';
const splitMode = ref<SplitMode>('by_group_count');
const targetGroupCount = ref(4);
const targetGroupSize = ref(4);
const copied = ref(false);

interface GroupItem {
    id: number;
    name: string;
    members: any[];
}

const groups = ref<GroupItem[]>([]);

const activeStudents = computed(() => {
    return (props.students || []).filter((s) => s.is_active !== false);
});

const generateGroups = () => {
    const pool = [...activeStudents.value];
    if (pool.length === 0) {
        groups.value = [];
        return;
    }

    // Fisher-Yates Shuffle
    for (let i = pool.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [pool[i], pool[j]] = [pool[j], pool[i]];
    }

    let numGroups = targetGroupCount.value;
    if (splitMode.value === 'by_group_size') {
        const size = Math.max(1, targetGroupSize.value);
        numGroups = Math.max(1, Math.ceil(pool.length / size));
    }

    numGroups = Math.max(1, Math.min(numGroups, pool.length));

    const result: GroupItem[] = Array.from({ length: numGroups }, (_, i) => ({
        id: i + 1,
        name: `Group ${i + 1}`,
        members: [],
    }));

    // Round-robin distribution for balanced team sizes
    pool.forEach((student, index) => {
        result[index % numGroups].members.push(student);
    });

    groups.value = result;
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            copied.value = false;
            generateGroups();
        }
    },
);

watch([splitMode, targetGroupCount, targetGroupSize], () => {
    if (props.open) {
        generateGroups();
    }
});

const copyGroupsToClipboard = async () => {
    if (groups.value.length === 0) return;

    let text = `📋 ${props.sectionName} — Random Group Rosters\n\n`;
    groups.value.forEach((group) => {
        text += `🔹 ${group.name} (${group.members.length} members):\n`;
        group.members.forEach((m, idx) => {
            text += `   ${idx + 1}. ${m.last_name}, ${m.first_name} (${m.student_number})\n`;
        });
        text += '\n';
    });

    try {
        await navigator.clipboard.writeText(text);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // fallback
    }
};

const printGroups = () => {
    const popup = window.open('', '_blank', 'width=800,height=900');
    if (!popup) return;

    let html = `<!DOCTYPE html>
<html>
<head>
    <title>${props.sectionName} - Group Rosters</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; padding: 24px; color: #0f172a; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .meta { color: #64748b; font-size: 12px; margin-bottom: 24px; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .group-card { border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; page-break-inside: avoid; }
        .group-title { font-weight: 700; font-size: 14px; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        ol { margin: 0; padding-left: 20px; font-size: 12px; line-height: 1.6; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>
    <h1>${props.sectionName} — Random Team Rosters</h1>
    <div class="meta">Generated: ${new Date().toLocaleDateString()} · Total Students: ${activeStudents.value.length}</div>
    <div class="grid">`;

    groups.value.forEach((group) => {
        html += `<div class="group-card">
            <div class="group-title">${group.name} (${group.members.length} members)</div>
            <ol>`;
        group.members.forEach((m) => {
            html += `<li><strong>${m.last_name}</strong>, ${m.first_name} <span style="color:#64748b; font-size:11px;">(${m.student_number})</span></li>`;
        });
        html += `</ol></div>`;
    });

    html += `</div></body></html>`;

    popup.document.write(html);
    popup.document.close();
    popup.focus();
    setTimeout(() => popup.print(), 300);
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 sm:p-6 backdrop-blur-xs duration-200 animate-in fade-in print:hidden"
    >
        <div
            class="paper-card relative flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden border border-border/80 bg-card p-6 shadow-2xl duration-200 animate-in zoom-in-95"
            role="dialog"
            aria-modal="true"
            aria-label="Random Group Generator"
        >
            <!-- Header Bar -->
            <div class="flex items-center justify-between border-b border-border/70 pb-4">
                <div class="flex items-center gap-2.5">
                    <span class="grid size-9 place-items-center rounded-xl bg-primary/10 text-primary">
                        <Dices class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold tracking-tight text-foreground">Random Group Generator</h2>
                        <p class="text-xs text-muted-foreground">
                            {{ sectionName }} · {{ activeStudents.length }} enrolled students
                        </p>
                    </div>
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8 rounded-lg text-muted-foreground hover:text-foreground"
                    title="Close"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </Button>
            </div>

            <!-- Configuration Strip -->
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/80 bg-secondary/30 p-3.5">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center rounded-lg bg-secondary/80 p-0.5 text-xs font-semibold">
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 transition-all"
                            :class="splitMode === 'by_group_count' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'"
                            @click="splitMode = 'by_group_count'"
                        >
                            By Number of Groups
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 transition-all"
                            :class="splitMode === 'by_group_size' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'"
                            @click="splitMode = 'by_group_size'"
                        >
                            By Group Size
                        </button>
                    </div>

                    <!-- Input Controls -->
                    <div v-if="splitMode === 'by_group_count'" class="flex items-center gap-2">
                        <Label class="text-xs font-medium text-muted-foreground">Groups:</Label>
                        <Input
                            v-model.number="targetGroupCount"
                            type="number"
                            min="2"
                            :max="Math.max(2, activeStudents.length)"
                            class="h-8 w-20 text-center font-mono text-xs font-bold"
                        />
                    </div>

                    <div v-else class="flex items-center gap-2">
                        <Label class="text-xs font-medium text-muted-foreground">Members per group:</Label>
                        <Input
                            v-model.number="targetGroupSize"
                            type="number"
                            min="2"
                            :max="Math.max(2, activeStudents.length)"
                            class="h-8 w-20 text-center font-mono text-xs font-bold"
                        />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        class="ink-button !h-8 !px-3 text-xs font-semibold"
                        title="Re-shuffle all groups"
                        @click="generateGroups"
                    >
                        <Shuffle class="mr-1.5 size-3.5" />
                        <span>Re-shuffle</span>
                    </Button>
                </div>
            </div>

            <!-- Group Grid Cards Area -->
            <div class="mt-4 flex-1 overflow-y-auto pr-1">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="group in groups"
                        :key="group.id"
                        class="rounded-xl border border-border/80 bg-card p-3.5 shadow-xs transition-all hover:border-primary/50"
                    >
                        <div class="flex items-center justify-between border-b border-border/70 pb-2">
                            <span class="font-bold text-foreground text-xs">{{ group.name }}</span>
                            <span class="rounded-md bg-secondary px-2 py-0.5 font-mono text-[10px] font-semibold text-muted-foreground">
                                {{ group.members.length }} members
                            </span>
                        </div>

                        <div class="mt-2.5 space-y-1.5">
                            <div
                                v-for="(student, sIdx) in group.members"
                                :key="student.id"
                                class="flex items-center gap-2 rounded-lg bg-secondary/30 px-2 py-1 text-xs"
                            >
                                <span class="grid size-5 shrink-0 place-items-center rounded-full bg-secondary font-mono text-[9px] font-bold text-muted-foreground">
                                    {{ sIdx + 1 }}
                                </span>
                                <div class="min-w-0 flex-1 truncate">
                                    <span class="font-semibold text-foreground">{{ student.last_name }}</span>,
                                    <span class="text-muted-foreground">{{ student.first_name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="mt-4 flex items-center justify-between border-t border-border/70 pt-4">
                <div class="text-xs text-muted-foreground">
                    {{ groups.length }} teams formed · Balanced by class size
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-9 rounded-xl px-3 text-xs font-medium"
                        @click="copyGroupsToClipboard"
                    >
                        <Check v-if="copied" class="mr-1.5 size-3.5 text-emerald-500" />
                        <Copy v-else class="mr-1.5 size-3.5" />
                        <span>{{ copied ? 'Copied to Clipboard!' : 'Copy Roster' }}</span>
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        class="h-9 rounded-xl px-3 text-xs font-medium"
                        @click="printGroups"
                    >
                        <Printer class="mr-1.5 size-3.5" />
                        <span>Print Sheet</span>
                    </Button>

                    <Button
                        type="button"
                        class="ink-button !h-9 !px-4 text-xs font-semibold"
                        @click="emit('close')"
                    >
                        <span>Done</span>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
