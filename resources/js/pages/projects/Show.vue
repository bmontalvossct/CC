<script setup lang="ts">
import FilePreviewModal from '@/components/FilePreviewModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    BookOpen,
    CalendarDays,
    Check,
    CheckCircle2,
    Dices,
    Download,
    Edit3,
    FileCheck,
    FileText,
    FolderKanban,
    LoaderCircle,
    Paperclip,
    Plus,
    Printer,
    Save,
    Search,
    Sparkles,
    Trash2,
    User,
    UserCheck,
    UserMinus,
    UserPlus,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

type Member = {
    id: number;
    student_id: number;
    role?: string;
    score?: number;
    notes?: string;
    student_number?: string;
    first_name?: string;
    last_name?: string;
    middle_name?: string;
    full_name: string;
    photo_path?: string;
    seat_label?: string;
    absent_count?: number;
};

type Group = {
    id: number;
    project_id: number;
    group_number: number;
    name: string;
    topic: string | null;
    description: string | null;
    score: number | null;
    notes: string | null;
    order_column: number;
    members: Member[];
};

type Project = {
    id: number;
    section_id: number;
    type: 'project' | 'reporting' | 'group_activity';
    format?: 'group' | 'individual';
    title: string;
    description: string | null;
    conducted_on: string | null;
    max_points: string | number | null;
    attachment_path?: string;
    attachment_name?: string;
    groups: Group[];
};

type Student = {
    id: number;
    student_number: string;
    first_name: string;
    last_name: string;
    middle_name?: string;
    full_name: string;
    photo_path?: string;
    seat_label?: string;
    absent_count?: number;
};

const props = defineProps<{
    section: { id: number; name: string; subject_code?: string; subject_title: string };
    project: Project;
    totalStudentsCount: number;
    unassignedStudents: Student[];
}>();

// Modals
const showRandomizeModal = ref(false);
const showEditModal = ref(false);
const showAddGroupModal = ref(false);
const showPreviewModal = ref(false);
const showDeleteModal = ref(false);
const isDeleting = ref(false);
const activeGroupForMember = ref<Group | null>(null);
const memberSearchQuery = ref('');

// Filter & Search for individual reporting
const presenterSearchQuery = ref('');
const presenterFilterTab = ref<'all' | 'has_topic' | 'needs_topic' | 'scored' | 'pending_score'>('all');

const confirmDeleteProject = () => {
    isDeleting.value = true;
    router.delete(`/sections/${props.section.id}/projects/${props.project.id}`, {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};

// Randomize form
const randomizeMode = ref<'count' | 'size'>('count');
const targetGroupCount = ref(props.project.groups.length > 0 ? props.project.groups.length : 4);
const targetGroupSize = ref(5);
const preserveTopics = ref(true);
const randomizing = ref(false);

// Edit project form
const editForm = useForm({
    type: props.project.type,
    format: props.project.format || 'group',
    title: props.project.title,
    description: props.project.description || '',
    conducted_on: props.project.conducted_on || '',
    max_points: props.project.max_points || '',
    attachment: null as File | null,
    _method: 'PUT',
});

// Add group form
const addGroupForm = useForm({
    name: '',
    topic: '',
    description: '',
});

// Group topics, descriptions, scores & notes local state
const topicSaving = ref<Record<number, boolean>>({});
const topicSaved = ref<Record<number, boolean>>({});
const groupTopics = ref<Record<number, string>>({});
const groupDescriptions = ref<Record<number, string>>({});
const groupNotes = ref<Record<number, string>>({});

const groupScores = ref<Record<number, string | number>>({});
const memberScores = ref<Record<number, string | number>>({});
const memberNotes = ref<Record<number, string>>({});
const scoreSaving = ref<Record<number, boolean>>({});
const scoreSaved = ref<Record<number, boolean>>({});
const memberSaving = ref<Record<number, boolean>>({});
const memberSaved = ref<Record<number, boolean>>({});

// Bulk Save All state
const savingAll = ref(false);
const savedAllSuccess = ref(false);

props.project.groups.forEach((g) => {
    groupTopics.value[g.id] = g.topic || '';
    groupDescriptions.value[g.id] = g.description || '';
    groupNotes.value[g.id] = g.notes || '';
    groupScores.value[g.id] = g.score !== null && g.score !== undefined ? g.score : '';
    g.members.forEach((m) => {
        memberScores.value[m.id] = m.score !== null && m.score !== undefined ? m.score : '';
        memberNotes.value[m.id] = m.notes || '';
    });
});

// Save All Topics & Scores in one unified request
const saveAll = async () => {
    if (savingAll.value) return;
    savingAll.value = true;
    savedAllSuccess.value = false;

    const payload = {
        groups: props.project.groups.map((g) => ({
            id: g.id,
            topic: groupTopics.value[g.id] ?? '',
            description: groupDescriptions.value[g.id] || null,
            score: groupScores.value[g.id] === '' || groupScores.value[g.id] === null || groupScores.value[g.id] === undefined ? null : Number(groupScores.value[g.id]),
            notes: groupNotes.value[g.id] || null,
        })),
        members: props.project.groups.flatMap((g) =>
            g.members.map((m) => ({
                id: m.id,
                score: memberScores.value[m.id] === '' || memberScores.value[m.id] === null || memberScores.value[m.id] === undefined ? null : Number(memberScores.value[m.id]),
                notes: memberNotes.value[m.id] || null,
            }))
        ),
    };

    try {
        const res = await fetch(`/sections/${props.section.id}/projects/${props.project.id}/save-all`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify(payload),
        });

        if (res.ok) {
            savedAllSuccess.value = true;
            props.project.groups.forEach((g) => {
                topicSaved.value[g.id] = true;
                scoreSaved.value[g.id] = true;
                g.members.forEach((m) => {
                    memberSaved.value[m.id] = true;
                });
            });

            setTimeout(() => {
                savedAllSuccess.value = false;
                props.project.groups.forEach((g) => {
                    topicSaved.value[g.id] = false;
                    scoreSaved.value[g.id] = false;
                    g.members.forEach((m) => {
                        memberSaved.value[m.id] = false;
                    });
                });
            }, 3000);
        }
    } catch (e) {
        console.error('Error saving all topics and scores:', e);
    } finally {
        savingAll.value = false;
    }
};

// Keyboard shortcut (Ctrl+S / Cmd+S)
const handleGlobalKeydown = (e: KeyboardEvent) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        void saveAll();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});

// Date formatting
const formatDate = (val: string | null) => {
    if (!val) return 'No date set';
    return new Intl.DateTimeFormat('en-PH', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' }).format(new Date(val));
};

// Computed stats for individual reporting
const topicsAssignedCount = computed(() => {
    return props.project.groups.filter((g) => (groupTopics.value[g.id] || '').trim().length > 0).length;
});

const scoredPresentersCount = computed(() => {
    return props.project.groups.filter((g) => {
        const val = groupScores.value[g.id];
        return val !== '' && val !== null && val !== undefined;
    }).length;
});

const averagePresenterScore = computed(() => {
    const scored = props.project.groups
        .map((g) => groupScores.value[g.id])
        .filter((val) => val !== '' && val !== null && val !== undefined)
        .map((val) => Number(val));
    if (!scored.length) return null;
    const sum = scored.reduce((a, b) => a + b, 0);
    return (sum / scored.length).toFixed(1);
});

// Filtered presenters for individual mode
const filteredPresenters = computed(() => {
    if (props.project.format !== 'individual') return props.project.groups;

    return props.project.groups.filter((g) => {
        const student = g.members[0];
        const studentName = student?.full_name?.toLowerCase() || g.name.toLowerCase();
        const studentNum = student?.student_number?.toLowerCase() || '';
        const topicText = (groupTopics.value[g.id] || '').toLowerCase();
        const descText = (groupDescriptions.value[g.id] || '').toLowerCase();
        const seatText = student?.seat_label?.toLowerCase() || '';
        const q = presenterSearchQuery.value.toLowerCase().trim();

        const matchesQuery = !q || studentName.includes(q) || studentNum.includes(q) || topicText.includes(q) || descText.includes(q) || seatText.includes(q);
        if (!matchesQuery) return false;

        const hasTopic = (groupTopics.value[g.id] || '').trim().length > 0;
        const scoreVal = groupScores.value[g.id];
        const isScored = scoreVal !== '' && scoreVal !== null && scoreVal !== undefined;

        if (presenterFilterTab.value === 'has_topic') return hasTopic;
        if (presenterFilterTab.value === 'needs_topic') return !hasTopic;
        if (presenterFilterTab.value === 'scored') return isScored;
        if (presenterFilterTab.value === 'pending_score') return !isScored;

        return true;
    });
});

// Compute distribution preview for randomization
const distributionPreview = computed(() => {
    const n = props.totalStudentsCount;
    if (n === 0) return { groups: 0, preview: 'No active students found in this section.' };

    let k = targetGroupCount.value;
    if (randomizeMode.value === 'size') {
        const size = Math.max(1, targetGroupSize.value);
        k = Math.max(1, Math.floor(n / size));
    }
    k = Math.max(1, Math.min(k, n));

    const base = Math.floor(n / k);
    const remainder = n % k;

    if (remainder === 0) {
        return {
            groups: k,
            preview: `${n} students will be evenly split into ${k} groups of ${base} members each.`,
        };
    }

    if (remainder === 1) {
        return {
            groups: k,
            preview: `${n} students into ${k} groups: Group 1 receives 1 additional member (${base + 1} members), and Groups 2–${k} will have ${base} members.`,
        };
    }

    return {
        groups: k,
        preview: `${n} students into ${k} groups: Groups 1–${remainder} will each have ${base + 1} members (+1 extra), and Groups ${remainder + 1}–${k} will have ${base} members.`,
    };
});

// Save group topic and description
const saveGroupTopic = async (group: Group) => {
    const newTopic = groupTopics.value[group.id];
    const newDesc = groupDescriptions.value[group.id];
    topicSaving.value[group.id] = true;

    try {
        const res = await fetch(`/sections/${props.section.id}/projects/${props.project.id}/groups/${group.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                topic: newTopic,
                description: newDesc || null,
                notes: groupNotes.value[group.id] || null,
            }),
        });

        if (res.ok) {
            topicSaved.value[group.id] = true;
            setTimeout(() => {
                topicSaved.value[group.id] = false;
            }, 2500);
        }
    } catch (e) {
        console.error('Error saving group topic & description:', e);
    } finally {
        topicSaving.value[group.id] = false;
    }
};

// Save group score
const saveGroupScore = async (group: Group) => {
    const rawVal = groupScores.value[group.id];
    const scoreVal = rawVal === '' || rawVal === null || rawVal === undefined ? null : Number(rawVal);
    scoreSaving.value[group.id] = true;

    try {
        const res = await fetch(`/sections/${props.section.id}/projects/${props.project.id}/groups/${group.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                score: scoreVal,
                notes: groupNotes.value[group.id] || null,
            }),
        });

        if (res.ok) {
            scoreSaved.value[group.id] = true;
            setTimeout(() => {
                scoreSaved.value[group.id] = false;
            }, 2500);
        }
    } catch (e) {
        console.error('Error saving group score:', e);
    } finally {
        scoreSaving.value[group.id] = false;
    }
};

// Save individual member score override
const saveMemberScore = async (group: Group, member: Member) => {
    const rawVal = memberScores.value[member.id];
    const scoreVal = rawVal === '' || rawVal === null || rawVal === undefined ? null : Number(rawVal);
    memberSaving.value[member.id] = true;

    try {
        const res = await fetch(`/sections/${props.section.id}/projects/${props.project.id}/groups/${group.id}/members/${member.student_id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                score: scoreVal,
                notes: memberNotes.value[member.id] || null,
            }),
        });

        if (res.ok) {
            memberSaved.value[member.id] = true;
            setTimeout(() => {
                memberSaved.value[member.id] = false;
            }, 2500);
        }
    } catch (e) {
        console.error('Error saving member score:', e);
    } finally {
        memberSaving.value[member.id] = false;
    }
};

const groupScoreInputs = new Map<number, HTMLInputElement>();
const memberScoreInputs = new Map<number, HTMLInputElement>();

// Move to next/prev group score input
const moveGroupScore = (currentGroup: Group, direction: number) => {
    void saveGroupScore(currentGroup);
    const groups = filteredPresenters.value;
    const idx = groups.findIndex((g) => g.id === currentGroup.id);
    if (idx === -1) return;
    const target = groups[idx + direction];
    if (target) {
        const el = groupScoreInputs.get(target.id);
        if (el) {
            el.focus();
            el.select();
        }
    }
};

const handleGroupScoreKey = (event: KeyboardEvent, group: Group) => {
    if (event.key === 'Enter' || event.key === 'Tab') {
        event.preventDefault();
        moveGroupScore(group, event.shiftKey ? -1 : 1);
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveGroupScore(group, 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveGroupScore(group, -1);
    }
};

// Flattened member list across all groups for smooth sequential navigation
const allGroupMembers = computed(() => props.project.groups.flatMap((g) => g.members.map((m) => ({ group: g, member: m }))));

// Move to next/prev member score input across groups
const moveMemberScore = (currentMember: Member, currentGroup: Group, direction: number) => {
    void saveMemberScore(currentGroup, currentMember);
    const members = allGroupMembers.value;
    const idx = members.findIndex((item) => item.member.id === currentMember.id);
    if (idx === -1) return;
    const target = members[idx + direction];
    if (target) {
        const el = memberScoreInputs.get(target.member.id);
        if (el) {
            el.focus();
            el.select();
        }
    }
};

const handleMemberScoreKey = (event: KeyboardEvent, member: Member, group: Group) => {
    if (event.key === 'Enter' || event.key === 'Tab') {
        event.preventDefault();
        moveMemberScore(member, group, event.shiftKey ? -1 : 1);
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveMemberScore(member, group, 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveMemberScore(member, group, -1);
    }
};

// Execute randomization
const submitRandomize = () => {
    randomizing.value = true;
    router.post(
        `/sections/${props.section.id}/projects/${props.project.id}/randomize`,
        {
            group_count: randomizeMode.value === 'count' ? targetGroupCount.value : undefined,
            group_size: randomizeMode.value === 'size' ? targetGroupSize.value : undefined,
            preserve_topics: preserveTopics.value,
        },
        {
            onSuccess: () => {
                showRandomizeModal.value = false;
                randomizing.value = false;
                // Re-sync topics
                props.project.groups.forEach((g) => {
                    groupTopics.value[g.id] = g.topic || '';
                });
            },
            onError: () => {
                randomizing.value = false;
            },
        },
    );
};

// Add group
const submitAddGroup = () => {
    addGroupForm.post(`/sections/${props.section.id}/projects/${props.project.id}/groups`, {
        onSuccess: () => {
            showAddGroupModal.value = false;
            addGroupForm.reset();
        },
    });
};

// Update project
const submitEditProject = () => {
    editForm.post(`/sections/${props.section.id}/projects/${props.project.id}`, {
        forceFormData: true,
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset('attachment');
        },
    });
};

// Delete project
const deleteProject = () => {
    if (confirm(`Are you sure you want to delete "${props.project.title}" and all its records?`)) {
        router.delete(`/sections/${props.section.id}/projects/${props.project.id}`);
    }
};

// Delete group
const deleteGroup = (group: Group) => {
    if (confirm(`Remove ${group.name}?`)) {
        router.delete(`/sections/${props.section.id}/projects/${props.project.id}/groups/${group.id}`);
    }
};

// Add student to group
const addStudentToGroup = (studentId: number, group: Group) => {
    router.post(
        `/sections/${props.section.id}/projects/${props.project.id}/groups/${group.id}/members`,
        {
            student_id: studentId,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                activeGroupForMember.value = null;
                memberSearchQuery.value = '';
            },
        },
    );
};

// Remove student from group
const removeStudentFromGroup = (member: Member, group: Group) => {
    if (confirm(`Remove ${member.full_name} from ${group.name}?`)) {
        router.delete(`/sections/${props.section.id}/projects/${props.project.id}/groups/${group.id}/members/${member.student_id}`, {
            preserveScroll: true,
        });
    }
};

// Move student to another group
const moveStudent = (studentId: number, targetGroupId: number) => {
    router.post(
        `/sections/${props.section.id}/projects/${props.project.id}/move-member`,
        {
            student_id: studentId,
            target_group_id: targetGroupId,
        },
        { preserveScroll: true },
    );
};

// Filter unassigned students by search query
const filteredUnassigned = computed(() => {
    if (!memberSearchQuery.value.trim()) return props.unassignedStudents;
    const q = memberSearchQuery.value.toLowerCase();
    return props.unassignedStudents.filter(
        (s) => s.full_name.toLowerCase().includes(q) || s.student_number.toLowerCase().includes(q),
    );
});
</script>

<template>
    <Head :title="`${project.title} · ${section.name} - ClassCheck`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
            { title: 'Assessments', href: `/sections/${section.id}/assessments` },
            { title: 'Projects & Reporting', href: `/sections/${section.id}/projects` },
            { title: project.title, href: `/sections/${section.id}/projects/${project.id}` },
        ]"
    >
        <main class="page-enter mx-auto flex w-full max-w-[1360px] flex-1 flex-col gap-6 px-5 pb-16 pt-8 md:px-10 md:pt-10">
            <!-- Back Button & Breadcrumb Shortcut -->
            <div class="flex items-center justify-between">
                <Link
                    :href="`/sections/${section.id}/assessments?type=project`"
                    prefetch="hover"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft class="size-3.5" /> Back to activities & projects
                </Link>

                <div class="flex items-center gap-2">
                    <span
                        class="rounded-full px-3 py-1 font-mono text-xs font-bold uppercase tracking-wider text-white"
                        :class="
                            project.type === 'group_activity'
                                ? 'bg-emerald-800'
                                : project.type === 'project'
                                  ? 'bg-emerald-800'
                                  : project.format === 'individual'
                                    ? 'bg-indigo-800'
                                    : 'bg-amber-800'
                        "
                    >
                        {{
                            project.type === 'group_activity'
                                ? 'Group Activity'
                                : project.type === 'project'
                                  ? 'Project'
                                  : project.format === 'individual'
                                    ? 'Individual Reporting'
                                    : 'Group Reporting'
                        }}
                    </span>
                    <span v-if="project.max_points" class="rounded-full bg-secondary px-3 py-1 font-mono text-xs font-semibold text-foreground">
                        {{ project.max_points }} pts max
                    </span>
                </div>
            </div>

            <!-- Main Project Header Card -->
            <header
                class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 shadow-sm sm:p-8 space-y-5"
            >
                <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge-primary font-mono font-bold">{{ section.subject_code || 'Activity' }}</span>
                            <span class="badge-muted">{{ section.name }}</span>
                            <span class="badge-muted flex items-center gap-1">
                                <CalendarDays class="size-3" /> {{ formatDate(project.conducted_on) }}
                            </span>
                        </div>

                        <h1 class="text-3xl font-extrabold tracking-tight text-foreground sm:text-4xl">
                            {{ project.title }}
                        </h1>
                    </div>

                    <!-- Action Buttons Toolbar -->
                    <div class="flex shrink-0 flex-wrap items-center gap-2.5">
                        <!-- PROMINENT SAVE ALL BUTTON -->
                        <button
                            type="button"
                            class="ink-button !h-10 !rounded-xl gap-2 shadow-sm transition-all"
                            :class="{ '!bg-emerald-600 !text-white': savedAllSuccess }"
                            :disabled="savingAll"
                            title="Save all topics and scores across all presenters (Ctrl+S)"
                            @click="saveAll"
                        >
                            <LoaderCircle v-if="savingAll" class="size-4 animate-spin" />
                            <Check v-else-if="savedAllSuccess" class="size-4" />
                            <Save v-else class="size-4" />
                            <span class="font-bold">{{ savingAll ? 'Saving All…' : savedAllSuccess ? 'All Saved!' : 'Save All Topics & Scores' }}</span>
                            <span class="hidden rounded bg-black/20 px-1.5 py-0.5 font-mono text-[10px] sm:inline-block">Ctrl+S</span>
                        </button>

                        <button
                            v-if="project.format !== 'individual'"
                            class="shadow-xs group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-4 text-sm font-medium text-primary transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            @click="showRandomizeModal = true"
                        >
                            <Dices class="size-4 text-primary transition-colors group-hover:text-white" />
                            <span>Randomize members</span>
                        </button>

                        <button
                            v-if="project.format !== 'individual'"
                            class="shadow-xs group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-3.5 text-sm font-medium text-primary transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            @click="showAddGroupModal = true"
                        >
                            <Plus class="size-4 text-primary transition-colors group-hover:text-white" />
                            <span>Add group</span>
                        </button>

                        <a
                            :href="`/sections/${section.id}/projects/${project.id}/export`"
                            class="shadow-xs group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-3.5 text-sm font-medium text-primary transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            title="Export topics, members, and scores to CSV"
                        >
                            <Download class="size-4 text-primary transition-colors group-hover:text-white" />
                            <span>Export CSV</span>
                        </a>

                        <a
                            :href="`/sections/${section.id}/projects/${project.id}/print`"
                            target="_blank"
                            class="shadow-xs group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-3.5 text-sm font-medium text-primary transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            title="Print presentation roster and grading sheet"
                        >
                            <Printer class="size-4 text-primary transition-colors group-hover:text-white" />
                            <span>Print</span>
                        </a>

                        <button
                            class="shadow-xs group inline-flex h-10 items-center justify-center rounded-xl border border-primary bg-white px-3 text-sm font-medium text-primary transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            title="Edit project details"
                            @click="showEditModal = true"
                        >
                            <Edit3 class="size-4 text-primary transition-colors group-hover:text-white" />
                        </button>

                        <button
                            class="shadow-xs group inline-flex h-10 items-center justify-center rounded-xl border border-rose-600 bg-white px-3 text-sm font-medium text-rose-700 transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            :title="`Delete ${project.type}`"
                            @click="deleteProject"
                        >
                            <Trash2 class="size-4 text-rose-700 transition-colors group-hover:text-white" />
                        </button>
                    </div>
                </div>

                <!-- Full-width Mode Description / Instructions -->
                <div v-if="project.type === 'group_activity'" class="w-full rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-sm">
                    <div class="flex items-start gap-2.5">
                        <Users class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-bold text-foreground">Group Activity (Recorded in Activities)</h2>
                                <span class="rounded bg-emerald-500/10 px-2 py-0.5 font-mono text-[10px] font-bold text-emerald-600 dark:text-emerald-400">Activity Category</span>
                            </div>
                            <p class="mt-1 text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                {{ project.description || 'All group and individual scores entered here will be recorded and computed directly under the Activities category in the Gradebook.' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else-if="project.type === 'project'" class="w-full rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm">
                    <div class="flex items-start gap-2.5">
                        <FolderKanban class="mt-0.5 size-5 shrink-0 text-primary" />
                        <div class="flex-1">
                            <h2 class="text-sm font-bold text-foreground">Project Title & Description (Unified Scope)</h2>
                            <p class="mt-1 text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                {{ project.description || 'All groups share this project title and assignment description.' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else-if="project.format === 'individual'" class="w-full rounded-xl border border-indigo-500/20 bg-indigo-500/5 p-4 text-sm">
                    <div class="flex items-start gap-2.5">
                        <BookOpen class="mt-0.5 size-5 shrink-0 text-indigo-600 dark:text-indigo-400" />
                        <div class="flex-1">
                            <h2 class="text-sm font-bold text-foreground">Individual Student Reporting</h2>
                            <p class="mt-1 text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                {{
                                    project.description ||
                                    'Each student presents their own topic and receives an individual score. Enter all topics and scores below, then click "Save All Topics & Scores".'
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="w-full rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 text-sm">
                    <div class="flex items-start gap-2.5">
                        <BookOpen class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div class="flex-1">
                            <h2 class="text-sm font-bold text-foreground">Group Reporting Activity</h2>
                            <p class="mt-1 text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                {{
                                    project.description ||
                                    'Each group presents their assigned topic. Enter topics and scores below, then click "Save All Topics & Scores" to save all at once.'
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Attachment button with preview modal -->
                <div v-if="project.attachment_path" class="pt-0.5">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-primary/40 bg-primary/10 px-3.5 py-2 text-xs font-bold text-primary transition-all hover:bg-primary hover:text-white"
                        title="Preview attached file with download option"
                        @click="showPreviewModal = true"
                    >
                        <Paperclip class="size-4" />
                        <span>Preview: {{ project.attachment_name || 'Guidelines / Assignment File' }}</span>
                    </button>
                </div>

                <!-- Stats summary bar -->
                <div class="mt-6 flex flex-wrap items-center gap-6 border-t border-border/80 pt-4 text-xs">
                    <div class="flex items-center gap-2">
                        <Users class="size-4 text-primary" />
                        <span class="font-medium text-muted-foreground">{{ project.format === 'individual' ? 'Total Presenters:' : 'Total Groups:' }}</span>
                        <span class="font-mono font-bold text-foreground">{{ project.groups.length }}</span>
                    </div>

                    <div v-if="project.format === 'individual'" class="flex items-center gap-2">
                        <FileCheck class="size-4 text-indigo-600 dark:text-indigo-400" />
                        <span class="font-medium text-muted-foreground">Topics Assigned:</span>
                        <span class="font-mono font-bold text-foreground">
                            {{ topicsAssignedCount }} / {{ project.groups.length }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <UserCheck class="size-4 text-emerald-600 dark:text-emerald-400" />
                        <span class="font-medium text-muted-foreground">{{ project.format === 'individual' ? 'Scored Presenters:' : 'Assigned Students:' }}</span>
                        <span class="font-mono font-bold text-foreground">
                            {{ project.format === 'individual' ? `${scoredPresentersCount} / ${project.groups.length}` : `${totalStudentsCount - unassignedStudents.length} / ${totalStudentsCount}` }}
                        </span>
                    </div>

                    <div v-if="project.format === 'individual' && averagePresenterScore !== null" class="flex items-center gap-2">
                        <Sparkles class="size-4 text-amber-500" />
                        <span class="font-medium text-muted-foreground">Average Score:</span>
                        <span class="font-mono font-bold text-foreground">{{ averagePresenterScore }} / {{ project.max_points || 100 }}</span>
                    </div>

                    <div v-if="project.format !== 'individual' && unassignedStudents.length > 0" class="flex items-center gap-2 font-semibold text-amber-600 dark:text-amber-400">
                        <span class="inline-block size-2 animate-pulse rounded-full bg-amber-500" />
                        <span>{{ unassignedStudents.length }} unassigned student{{ unassignedStudents.length > 1 ? 's' : '' }}</span>
                    </div>
                </div>
            </header>

            <!-- Unassigned Students Alert (For Group Mode) -->
            <div
                v-if="project.format !== 'individual' && unassignedStudents.length > 0"
                class="paper-card border-amber-500/30 bg-amber-500/5 p-4 duration-200 animate-in fade-in sm:p-5"
            >
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400">
                            <Users class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-foreground">
                                {{ unassignedStudents.length }} Student{{ unassignedStudents.length > 1 ? 's' : '' }} Not in Any Group
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Click "Randomize members" to assign all students automatically, or assign them to groups individually below.
                            </p>
                        </div>
                    </div>

                    <button
                        class="shadow-xs inline-flex items-center gap-2 rounded-xl bg-amber-600 px-3.5 py-2 text-xs font-bold text-white transition-colors hover:bg-amber-700"
                        @click="showRandomizeModal = true"
                    >
                        <Dices class="size-3.5" />
                        <span>Auto-distribute unassigned</span>
                    </button>
                </div>

                <!-- Unassigned chips -->
                <div class="mt-3 flex flex-wrap gap-2 border-t border-amber-500/20 pt-2">
                    <span
                        v-for="student in unassignedStudents"
                        :key="student.id"
                        class="shadow-xs inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-2.5 py-1 text-xs font-medium text-foreground"
                    >
                        <span class="font-bold">{{ student.full_name }}</span>
                        <span v-if="student.seat_label" class="font-mono text-[10px] text-muted-foreground">({{ student.seat_label }})</span>
                    </span>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- INDIVIDUAL REPORTING PRESENTATION ROSTER -->
            <!-- ========================================== -->
            <section v-if="project.format === 'individual'" class="space-y-6">
                <!-- Search and Filter Bar -->
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="relative flex-1 max-w-md">
                        <Search class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="presenterSearchQuery"
                            type="text"
                            placeholder="Search presenter by student name, ID, chair, or topic..."
                            class="w-full rounded-xl border border-input bg-card pl-10 pr-4 py-2 text-xs font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                        <button
                            v-if="presenterSearchQuery"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            @click="presenterSearchQuery = ''"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>

                    <!-- Filter Tabs -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-bold transition-colors"
                            :class="presenterFilterTab === 'all' ? 'bg-primary text-primary-foreground' : 'border border-border bg-card text-muted-foreground hover:bg-secondary'"
                            @click="presenterFilterTab = 'all'"
                        >
                            All ({{ project.groups.length }})
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-bold transition-colors"
                            :class="presenterFilterTab === 'has_topic' ? 'bg-primary text-primary-foreground' : 'border border-border bg-card text-muted-foreground hover:bg-secondary'"
                            @click="presenterFilterTab = 'has_topic'"
                        >
                            With Topic ({{ topicsAssignedCount }})
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-bold transition-colors"
                            :class="presenterFilterTab === 'needs_topic' ? 'bg-primary text-primary-foreground' : 'border border-border bg-card text-muted-foreground hover:bg-secondary'"
                            @click="presenterFilterTab = 'needs_topic'"
                        >
                            Needs Topic ({{ project.groups.length - topicsAssignedCount }})
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-bold transition-colors"
                            :class="presenterFilterTab === 'scored' ? 'bg-primary text-primary-foreground' : 'border border-border bg-card text-muted-foreground hover:bg-secondary'"
                            @click="presenterFilterTab = 'scored'"
                        >
                            Scored ({{ scoredPresentersCount }})
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-bold transition-colors"
                            :class="presenterFilterTab === 'pending_score' ? 'bg-primary text-primary-foreground' : 'border border-border bg-card text-muted-foreground hover:bg-secondary'"
                            @click="presenterFilterTab = 'pending_score'"
                        >
                            Pending Score ({{ project.groups.length - scoredPresentersCount }})
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!project.groups.length" class="paper-card rounded-2xl border-2 border-dashed p-14 text-center">
                    <User class="mx-auto size-12 text-muted-foreground/60" />
                    <h3 class="mt-4 text-xl font-bold text-foreground">No students in this section</h3>
                    <p class="mx-auto mt-1.5 max-w-md text-sm text-muted-foreground">
                        Enroll active students in this section to start assigning individual presentation topics.
                    </p>
                </div>

                <!-- Presenter Cards List -->
                <div v-else class="space-y-4">
                    <div
                        v-for="(group, idx) in filteredPresenters"
                        :key="group.id"
                        class="paper-card overflow-hidden rounded-2xl border border-border/80 p-0 shadow-sm transition-all hover:border-primary/40"
                    >
                        <div class="grid gap-4 p-5 lg:grid-cols-12 lg:items-center">
                            <!-- Presenter Identity -->
                            <div class="flex items-center gap-3 lg:col-span-4">
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-indigo-600/15 font-mono text-xs font-bold text-indigo-700 dark:text-indigo-400">
                                    #{{ group.group_number }}
                                </span>

                                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold uppercase text-primary">
                                    {{ group.members[0]?.first_name?.[0] || 'S' }}{{ group.members[0]?.last_name?.[0] || '' }}
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="truncate font-bold text-foreground">
                                            {{ group.members[0]?.full_name || group.name }}
                                        </h3>
                                        <span
                                            v-if="group.members[0]?.absent_count && group.members[0].absent_count >= 3"
                                            class="inline-flex items-center gap-1 rounded bg-rose-600/15 px-1.5 py-0.5 font-mono text-[10px] font-bold text-rose-700 dark:text-rose-400"
                                            title="Student has accumulated 3 or more absences"
                                        >
                                            <AlertCircle class="size-3" /> 3+ ABS
                                        </span>
                                    </div>
                                    <p class="flex items-center gap-2 font-mono text-[11px] text-muted-foreground">
                                        <span>{{ group.members[0]?.student_number || '—' }}</span>
                                        <span v-if="group.members[0]?.seat_label" class="font-medium text-primary">· Chair {{ group.members[0].seat_label }}</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Presentation Topic & Optional Description -->
                            <div class="space-y-1.5 lg:col-span-5">
                                <div class="relative">
                                    <input
                                        v-model="groupTopics[group.id]"
                                        type="text"
                                        placeholder="Assigned presentation topic..."
                                        class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs font-bold transition-all placeholder:font-normal placeholder:text-muted-foreground/60 focus-visible:ring-2 focus-visible:ring-primary"
                                        @blur="saveGroupTopic(group)"
                                    />
                                    <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center gap-1 text-[10px]">
                                        <span v-if="topicSaving[group.id]" class="animate-pulse text-muted-foreground">Saving…</span>
                                        <span v-else-if="topicSaved[group.id]" class="flex items-center gap-0.5 font-bold text-emerald-600 dark:text-emerald-400">
                                            <Check class="size-3" /> Saved
                                        </span>
                                    </div>
                                </div>

                                <div>
                                    <input
                                        v-model="groupDescriptions[group.id]"
                                        type="text"
                                        placeholder="Topic description / scope / guidelines (optional)..."
                                        class="w-full rounded-lg border border-border/80 bg-secondary/30 px-3 py-1.5 text-[11px] font-medium text-foreground transition-all placeholder:text-muted-foreground/50 focus-visible:ring-2 focus-visible:ring-primary"
                                        @blur="saveGroupTopic(group)"
                                    />
                                </div>
                            </div>

                            <!-- Presentation Score & Quick Actions -->
                            <div class="flex items-center justify-end gap-3 lg:col-span-3">
                                <div class="flex items-center gap-2 rounded-xl border border-border/80 bg-secondary/40 px-3 py-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Score:</span>
                                    <input
                                        :ref="(el) => { if (el) groupScoreInputs.set(group.id, el as HTMLInputElement); }"
                                        v-model="groupScores[group.id]"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        :max="project.max_points || 1000"
                                        placeholder="—"
                                        class="w-16 rounded-lg border px-2 py-1 text-center font-mono text-xs font-bold transition-all focus:outline-none"
                                        :class="[
                                            groupScores[group.id] !== '' &&
                                            groupScores[group.id] !== null &&
                                            groupScores[group.id] !== undefined &&
                                            (Number(groupScores[group.id]) < 0 || Number(groupScores[group.id]) > Number(project.max_points || 100))
                                                ? '!border-rose-500 !bg-rose-500/10 !text-rose-600 !ring-2 !ring-rose-500 dark:!text-rose-400'
                                                : 'border-input bg-background text-foreground focus:ring-1 focus:ring-primary',
                                        ]"
                                        @focus="($event.target as HTMLInputElement)?.select()"
                                        @blur="saveGroupScore(group)"
                                        @keydown="handleGroupScoreKey($event, group)"
                                    />
                                    <span class="font-mono text-[11px] text-muted-foreground">/ {{ project.max_points || '100' }}</span>
                                </div>

                                <button
                                    type="button"
                                    :disabled="topicSaving[group.id] || scoreSaving[group.id]"
                                    class="inline-flex size-9 items-center justify-center rounded-xl border border-primary/40 bg-primary/10 text-primary transition-all hover:bg-primary hover:text-white"
                                    title="Save this presenter's topic & score"
                                    @click="
                                        saveGroupTopic(group);
                                        saveGroupScore(group);
                                    "
                                >
                                    <Check v-if="scoreSaved[group.id] || topicSaved[group.id]" class="size-4" />
                                    <Save v-else class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ========================================== -->
            <!-- GROUP REPORTING & PROJECT GROUPS VIEW     -->
            <!-- ========================================== -->
            <section v-else class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold tracking-tight text-foreground">Groups & Topic Assignments</h2>
                        <p class="text-xs text-muted-foreground">
                            {{
                                project.type === 'reporting'
                                    ? 'Members listed on left, presentation topics entered on right.'
                                    : 'Members listed on left, unified project details applied to all groups.'
                            }}
                        </p>
                    </div>

                    <div class="font-mono text-xs text-muted-foreground">
                        {{ project.groups.length }} group{{ project.groups.length !== 1 ? 's' : '' }}
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!project.groups.length" class="paper-card rounded-2xl border-2 border-dashed p-14 text-center">
                    <FolderKanban class="mx-auto size-12 text-muted-foreground/60" />
                    <h3 class="mt-4 text-xl font-bold text-foreground">No groups created yet</h3>
                    <p class="mx-auto mt-1.5 max-w-md text-sm text-muted-foreground">
                        Get started quickly by randomizing section students into groups, or manually create your first group.
                    </p>
                    <div class="mt-6 flex justify-center gap-3">
                        <button class="ink-button !h-10 !rounded-xl" @click="showRandomizeModal = true">
                            <Dices class="size-4" />
                            <span>Randomize students into groups</span>
                        </button>
                        <button
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-semibold hover:bg-secondary"
                            @click="showAddGroupModal = true"
                        >
                            <Plus class="size-4" />
                            <span>Add Group 1</span>
                        </button>
                    </div>
                </div>

                <!-- Groups Grid / List -->
                <div v-else class="space-y-5">
                    <div
                        v-for="group in project.groups"
                        :key="group.id"
                        class="paper-card overflow-hidden rounded-2xl border border-border/80 p-0 shadow-sm transition-all hover:border-primary/40"
                    >
                        <!-- Group Header Strip -->
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/80 bg-secondary/40 px-6 py-3.5">
                            <div class="flex flex-wrap items-center gap-3">
                                <span
                                    class="flex size-7 items-center justify-center rounded-lg bg-primary font-mono text-xs font-bold text-primary-foreground"
                                >
                                    {{ group.group_number }}
                                </span>
                                <h3 class="text-base font-bold text-foreground">{{ group.name }}</h3>
                                <span
                                    class="rounded-full border border-border bg-background px-2.5 py-0.5 font-mono text-[11px] font-semibold text-muted-foreground"
                                >
                                    {{ group.members.length }} member{{ group.members.length !== 1 ? 's' : '' }}
                                </span>

                                <!-- Group Score Input -->
                                <div class="shadow-2xs flex items-center gap-1.5 rounded-xl border border-border/80 bg-card px-2.5 py-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Group Score:</span>
                                    <input
                                        :ref="
                                            (el) => {
                                                if (el) groupScoreInputs.set(group.id, el as HTMLInputElement);
                                            }
                                        "
                                        v-model="groupScores[group.id]"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        :max="project.max_points || 1000"
                                        placeholder="—"
                                        class="w-16 rounded-lg border px-2 py-0.5 text-center font-mono text-xs font-bold transition-all focus:outline-none"
                                        :class="[
                                            groupScores[group.id] !== '' &&
                                            groupScores[group.id] !== null &&
                                            groupScores[group.id] !== undefined &&
                                            (Number(groupScores[group.id]) < 0 || Number(groupScores[group.id]) > Number(project.max_points || 100))
                                                ? '!border-rose-500 !bg-rose-500/10 !text-rose-600 !ring-2 !ring-rose-500 dark:!text-rose-400'
                                                : 'border-input bg-background text-foreground focus:ring-1 focus:ring-primary',
                                        ]"
                                        @focus="($event.target as HTMLInputElement)?.select()"
                                        @blur="saveGroupScore(group)"
                                        @keydown="handleGroupScoreKey($event, group)"
                                    />
                                    <span class="font-mono text-[10px] text-muted-foreground">/ {{ project.max_points || '100' }}</span>
                                    <span v-if="scoreSaving[group.id]" class="animate-pulse text-[9px] text-muted-foreground">Saving…</span>
                                    <span v-else-if="scoreSaved[group.id]" class="text-[9px] font-semibold text-emerald-600 dark:text-emerald-400"
                                        >✓ Saved</span
                                    >
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-2.5 py-1 text-xs font-semibold text-foreground transition-colors hover:bg-secondary"
                                    @click="activeGroupForMember = group"
                                >
                                    <UserPlus class="size-3.5 text-primary" />
                                    <span>Add member</span>
                                </button>
                                <button
                                    class="inline-flex items-center justify-center rounded-lg p-1 text-muted-foreground transition-colors hover:bg-rose-500/10 hover:text-rose-600"
                                    title="Delete group"
                                    @click="deleteGroup(group)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>

                        <!-- 2-Column Split: Members on Left, Topic on Right -->
                        <div class="grid gap-6 p-6 lg:grid-cols-12">
                            <!-- LEFT COLUMN: Members List -->
                            <div class="space-y-3 lg:col-span-6">
                                <div class="flex items-center justify-between pb-1">
                                    <span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                        <Users class="size-3.5 text-primary" /> Group Members
                                    </span>
                                    <span class="font-mono text-[11px] text-muted-foreground">
                                        {{ group.members.length }} student{{ group.members.length !== 1 ? 's' : '' }}
                                    </span>
                                </div>

                                <div
                                    v-if="!group.members.length"
                                    class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground"
                                >
                                    No members in this group yet. Click "+ Add member" or randomize.
                                </div>

                                <div v-else class="space-y-2">
                                    <div
                                        v-for="(member, idx) in group.members"
                                        :key="member.id"
                                        class="group/member flex items-center justify-between rounded-xl border border-border/70 bg-background/60 p-2.5 text-xs transition-colors hover:bg-secondary/40"
                                    >
                                        <div class="flex min-w-0 items-center gap-2.5">
                                            <span class="w-4 text-right font-mono text-[10px] font-bold text-muted-foreground/70">
                                                {{ idx + 1 }}.
                                            </span>
                                            <div
                                                class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold uppercase text-primary"
                                            >
                                                {{ member.first_name?.[0] || 'S' }}{{ member.last_name?.[0] || '' }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-foreground">
                                                    {{ member.full_name }}
                                                </p>
                                                <p class="flex items-center gap-2 font-mono text-[10px] text-muted-foreground">
                                                    <span>{{ member.student_number }}</span>
                                                    <span v-if="member.seat_label" class="font-medium text-primary">· {{ member.seat_label }}</span>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-1.5 opacity-80 group-hover/member:opacity-100">
                                            <!-- Individual Student Score Override -->
                                            <div
                                                class="flex items-center gap-1"
                                                title="Individual student score override (defaults to group score if left empty)"
                                            >
                                                <span class="font-mono text-[9px] text-muted-foreground">Ind:</span>
                                                <input
                                                    :ref="
                                                        (el) => {
                                                            if (el) memberScoreInputs.set(member.id, el as HTMLInputElement);
                                                        }
                                                    "
                                                    v-model="memberScores[member.id]"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    :max="project.max_points || 1000"
                                                    :placeholder="
                                                        groupScores[group.id] !== '' && groupScores[group.id] !== undefined
                                                            ? String(groupScores[group.id])
                                                            : '—'
                                                    "
                                                    class="w-14 rounded-lg border px-1.5 py-0.5 text-center font-mono text-[11px] font-medium transition-all focus:outline-none"
                                                    :class="[
                                                        memberScores[member.id] !== '' &&
                                                        memberScores[member.id] !== null &&
                                                        memberScores[member.id] !== undefined &&
                                                        (Number(memberScores[member.id]) < 0 ||
                                                            Number(memberScores[member.id]) > Number(project.max_points || 100))
                                                            ? '!border-rose-500 !bg-rose-500/10 !text-rose-600 !ring-2 !ring-rose-500 dark:!text-rose-400'
                                                            : 'border-input bg-card text-foreground focus:ring-1 focus:ring-primary',
                                                    ]"
                                                    @focus="($event.target as HTMLInputElement)?.select()"
                                                    @blur="saveMemberScore(group, member)"
                                                    @keydown="handleMemberScoreKey($event, member, group)"
                                                />
                                                <span
                                                    v-if="memberSaved[member.id]"
                                                    class="text-[8px] font-semibold text-emerald-600 dark:text-emerald-400"
                                                    >✓</span
                                                >
                                            </div>

                                            <!-- Quick Move to Another Group Selector -->
                                            <select
                                                v-if="project.groups.length > 1"
                                                class="h-7 rounded-lg border border-input bg-card px-2 text-[11px] font-medium text-muted-foreground focus:ring-1 focus:ring-primary"
                                                title="Move to another group"
                                                @change="moveStudent(member.student_id, Number(($event.target as HTMLSelectElement).value))"
                                            >
                                                <option value="" disabled selected>Move to…</option>
                                                <option
                                                    v-for="otherGroup in project.groups.filter((g) => g.id !== group.id)"
                                                    :key="otherGroup.id"
                                                    :value="otherGroup.id"
                                                >
                                                    {{ otherGroup.name }}
                                                </option>
                                            </select>

                                            <!-- Remove Member -->
                                            <button
                                                class="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-rose-500/10 hover:text-rose-600"
                                                title="Remove from group"
                                                @click="removeStudentFromGroup(member, group)"
                                            >
                                                <UserMinus class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN: Topic Assignment -->
                            <div
                                class="flex flex-col justify-between border-t border-border/60 pt-4 lg:col-span-6 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0"
                            >
                                <div>
                                    <div class="mb-2 flex items-center justify-between">
                                        <span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                            <FileText class="size-3.5 text-primary" />
                                            {{ project.type === 'reporting' ? 'Group Topic (Reporting)' : 'Project Scope & Instructions' }}
                                        </span>

                                        <div v-if="project.type === 'reporting'" class="flex items-center gap-1.5 text-xs font-medium">
                                            <span v-if="topicSaving[group.id]" class="animate-pulse text-[11px] text-muted-foreground">Saving…</span>
                                            <span
                                                v-else-if="topicSaved[group.id]"
                                                class="flex items-center gap-1 text-[11px] text-emerald-600 dark:text-emerald-400"
                                            >
                                                <Check class="size-3" /> Saved
                                            </span>
                                        </div>
                                    </div>

                                    <!-- If REPORTING: Teacher manually enters topic on right side -->
                                    <div v-if="project.type === 'reporting'" class="space-y-3">
                                        <label class="block">
                                            <span class="text-[11px] font-semibold text-muted-foreground">Assigned Presentation Topic:</span>
                                            <textarea
                                                v-model="groupTopics[group.id]"
                                                rows="2"
                                                class="mt-1 w-full rounded-xl border border-input bg-background p-3 text-xs font-semibold leading-relaxed transition-all placeholder:font-normal placeholder:text-muted-foreground/60 focus-visible:ring-2 focus-visible:ring-primary"
                                                :placeholder="`e.g. Chapter ${group.group_number}: Architecture & Implementation...`"
                                                @blur="saveGroupTopic(group)"
                                            />
                                        </label>

                                        <label class="block">
                                            <span class="text-[11px] font-medium text-muted-foreground">Topic Description / Scope <em class="font-normal text-[10px]">(optional)</em>:</span>
                                            <textarea
                                                v-model="groupDescriptions[group.id]"
                                                rows="2"
                                                class="mt-1 w-full rounded-xl border border-border/80 bg-secondary/30 p-2.5 text-xs font-normal leading-relaxed transition-all placeholder:text-muted-foreground/50 focus-visible:ring-2 focus-visible:ring-primary"
                                                placeholder="e.g. Key subtopics, deliverables, or presentation instructions (optional)..."
                                                @blur="saveGroupTopic(group)"
                                            />
                                        </label>

                                        <div class="flex items-center justify-between">
                                            <span class="text-[10px] text-muted-foreground">Auto-saves on blur or click Save All</span>
                                            <button
                                                type="button"
                                                :disabled="topicSaving[group.id]"
                                                class="inline-flex items-center gap-1 rounded-lg bg-primary/10 px-3 py-1 text-xs font-bold text-primary transition-colors hover:bg-primary/20"
                                                @click="saveGroupTopic(group)"
                                            >
                                                <Save class="size-3" /> Save Topic & Description
                                            </button>
                                        </div>
                                    </div>

                                    <!-- If PROJECT: Unified project title and description applied to all groups -->
                                    <div v-else class="space-y-3 rounded-xl border border-border/80 bg-secondary/30 p-4 text-xs">
                                        <div>
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground"
                                                >Unified Project Title:</span
                                            >
                                            <p class="mt-0.5 text-sm font-bold text-foreground">{{ project.title }}</p>
                                        </div>

                                        <div>
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground"
                                                >Scope & Deliverables:</span
                                            >
                                            <p class="mt-0.5 leading-relaxed text-muted-foreground">
                                                {{ project.description || 'Applies to all groups in this section.' }}
                                            </p>
                                        </div>

                                        <div class="space-y-2 border-t border-border/60 pt-2.5">
                                            <label class="block">
                                                <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                                    Group-Specific Focus / Notes <em class="font-normal normal-case">(optional)</em>:
                                                </span>
                                                <input
                                                    v-model="groupTopics[group.id]"
                                                    type="text"
                                                    placeholder="e.g. Frontend Module focus..."
                                                    class="mt-1 w-full rounded-lg border border-input bg-background px-3 py-1.5 text-xs font-medium"
                                                    @blur="saveGroupTopic(group)"
                                                />
                                            </label>

                                            <label class="block">
                                                <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                                    Description / Deliverables <em class="font-normal normal-case">(optional)</em>:
                                                </span>
                                                <input
                                                    v-model="groupDescriptions[group.id]"
                                                    type="text"
                                                    placeholder="e.g. Specific requirements for this group (optional)..."
                                                    class="mt-1 w-full rounded-lg border border-border/80 bg-secondary/30 px-3 py-1.5 text-xs font-medium"
                                                    @blur="saveGroupTopic(group)"
                                                />
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- RANDOMIZE MEMBERS MODAL -->
        <div
            v-if="showRandomizeModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm duration-150 animate-in fade-in"
        >
            <div class="paper-card w-full max-w-lg space-y-6 border-border/90 p-6 shadow-2xl sm:p-8">
                <div class="flex items-center justify-between border-b border-border/80 pb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Dices class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-foreground">Randomize Group Members</h3>
                            <p class="text-xs text-muted-foreground">Fair distribution of {{ totalStudentsCount }} active students</p>
                        </div>
                    </div>
                    <button class="text-muted-foreground hover:text-foreground" @click="showRandomizeModal = false">
                        <X class="size-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Choose Randomization Mode -->
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            class="rounded-xl border p-3.5 text-left transition-all"
                            :class="
                                randomizeMode === 'count'
                                    ? 'border-primary bg-primary/5 font-bold text-primary ring-1 ring-primary'
                                    : 'border-border bg-card text-muted-foreground hover:bg-secondary'
                            "
                            @click="randomizeMode = 'count'"
                        >
                            <span class="block text-xs font-bold">By Number of Groups</span>
                            <span class="mt-0.5 block text-[11px] font-normal text-muted-foreground">e.g. 4 or 5 groups</span>
                        </button>

                        <button
                            type="button"
                            class="rounded-xl border p-3.5 text-left transition-all"
                            :class="
                                randomizeMode === 'size'
                                    ? 'border-primary bg-primary/5 font-bold text-primary ring-1 ring-primary'
                                    : 'border-border bg-card text-muted-foreground hover:bg-secondary'
                            "
                            @click="randomizeMode = 'size'"
                        >
                            <span class="block text-xs font-bold">By Group Size</span>
                            <span class="mt-0.5 block text-[11px] font-normal text-muted-foreground">e.g. 5 students per group</span>
                        </button>
                    </div>

                    <!-- Input according to mode -->
                    <div v-if="randomizeMode === 'count'">
                        <label class="block">
                            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Number of Groups</span>
                            <input
                                v-model.number="targetGroupCount"
                                type="number"
                                min="1"
                                :max="Math.max(1, totalStudentsCount)"
                                class="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-bold focus-visible:ring-2 focus-visible:ring-primary"
                            />
                        </label>
                    </div>

                    <div v-else>
                        <label class="block">
                            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Target Students Per Group</span>
                            <input
                                v-model.number="targetGroupSize"
                                type="number"
                                min="1"
                                :max="Math.max(1, totalStudentsCount)"
                                class="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-bold focus-visible:ring-2 focus-visible:ring-primary"
                            />
                        </label>
                    </div>

                    <!-- Distribution Rule Explainer & Live Preview -->
                    <div class="space-y-2 rounded-xl border border-primary/20 bg-primary/5 p-4 text-xs">
                        <div class="flex items-center gap-2 font-bold text-primary">
                            <Sparkles class="size-4 shrink-0" />
                            <span>Uneven Member Handling:</span>
                        </div>
                        <p class="leading-relaxed text-foreground">
                            {{ distributionPreview.preview }}
                        </p>
                        <p class="border-t border-primary/10 pt-2 text-[11px] text-muted-foreground">
                            ✓ If numbers are uneven, the extra member is automatically assigned to Group 1 (the first group on the list).
                        </p>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2.5 text-xs font-medium text-foreground">
                        <input v-model="preserveTopics" type="checkbox" class="size-4 rounded border-border text-primary focus:ring-primary" />
                        <span>Preserve existing group topics (if re-randomizing)</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-border/80 pt-4">
                    <button
                        type="button"
                        class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground"
                        @click="showRandomizeModal = false"
                    >
                        Cancel
                    </button>
                    <button :disabled="randomizing" class="ink-button !h-10 !rounded-xl text-xs font-bold" @click="submitRandomize">
                        <Dices class="size-4" />
                        <span>{{ randomizing ? 'Shuffling…' : 'Shuffle & Assign Groups' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ADD GROUP MODAL -->
        <div
            v-if="showAddGroupModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm duration-150 animate-in fade-in"
        >
            <div class="paper-card w-full max-w-md space-y-5 p-6 shadow-2xl">
                <div class="flex items-center justify-between border-b border-border/80 pb-3">
                    <h3 class="text-lg font-bold text-foreground">Add New Group</h3>
                    <button class="text-muted-foreground hover:text-foreground" @click="showAddGroupModal = false">
                        <X class="size-5" />
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submitAddGroup">
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Group Name</span>
                        <input
                            v-model="addGroupForm.name"
                            type="text"
                            :placeholder="`Group ${project.groups.length + 1}`"
                            class="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <label v-if="project.type === 'reporting'" class="block">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground"
                            >Topic <em class="font-normal normal-case">(optional)</em></span
                        >
                        <textarea
                            v-model="addGroupForm.topic"
                            rows="2"
                            placeholder="Enter presentation topic..."
                            class="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-xs font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <label v-if="project.type === 'reporting'" class="block">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground"
                            >Topic Description / Scope <em class="font-normal normal-case">(optional)</em></span
                        >
                        <textarea
                            v-model="addGroupForm.description"
                            rows="2"
                            placeholder="Enter topic details, requirements, or instructions (optional)..."
                            class="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-xs font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground"
                            @click="showAddGroupModal = false"
                        >
                            Cancel
                        </button>
                        <button :disabled="addGroupForm.processing" class="ink-button !h-9 !rounded-xl text-xs font-bold">
                            {{ addGroupForm.processing ? 'Adding…' : 'Add Group' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ADD MEMBER TO GROUP MODAL -->
        <div
            v-if="activeGroupForMember"
            class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm duration-150 animate-in fade-in"
        >
            <div class="paper-card w-full max-w-md space-y-4 p-6 shadow-2xl">
                <div class="flex items-center justify-between border-b border-border/80 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-foreground">Add Student to {{ activeGroupForMember.name }}</h3>
                        <p class="text-xs text-muted-foreground">Select a student from the section roster</p>
                    </div>
                    <button class="text-muted-foreground hover:text-foreground" @click="activeGroupForMember = null">
                        <X class="size-5" />
                    </button>
                </div>

                <div class="space-y-3">
                    <input
                        v-model="memberSearchQuery"
                        type="text"
                        placeholder="Search unassigned student by name or ID..."
                        class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs font-medium focus-visible:ring-2 focus-visible:ring-primary"
                    />

                    <div class="max-h-60 space-y-1.5 overflow-y-auto pr-1">
                        <div v-if="!filteredUnassigned.length" class="py-8 text-center text-xs text-muted-foreground">
                            {{
                                unassignedStudents.length
                                    ? 'No matching unassigned students.'
                                    : 'All students in this section are currently assigned to a group.'
                            }}
                        </div>

                        <button
                            v-for="student in filteredUnassigned"
                            :key="student.id"
                            type="button"
                            class="flex w-full items-center justify-between rounded-xl border border-border/70 bg-card p-2.5 text-left text-xs transition-colors hover:border-primary/50 hover:bg-secondary"
                            @click="addStudentToGroup(student.id, activeGroupForMember!)"
                        >
                            <div>
                                <p class="font-bold text-foreground">{{ student.full_name }}</p>
                                <p class="font-mono text-[10px] text-muted-foreground">
                                    {{ student.student_number }}
                                    <span v-if="student.seat_label">· Chair {{ student.seat_label }}</span>
                                </p>
                            </div>
                            <Plus class="size-4 text-primary" />
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-end border-t border-border/80 pt-3">
                    <button
                        type="button"
                        class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground"
                        @click="activeGroupForMember = null"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- EDIT PROJECT MODAL -->
        <div
            v-if="showEditModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm duration-150 animate-in fade-in"
        >
            <div class="paper-card w-full max-w-lg space-y-5 p-6 shadow-2xl">
                <div class="flex items-center justify-between border-b border-border/80 pb-3">
                    <h3 class="text-lg font-bold text-foreground">Edit Project / Reporting Details</h3>
                    <button class="text-muted-foreground hover:text-foreground" @click="showEditModal = false">
                        <X class="size-5" />
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submitEditProject">
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Activity Type</span>
                            <select
                                v-model="editForm.type"
                                class="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                            >
                                <option value="group_activity">Group Activity</option>
                                <option value="reporting">Reporting</option>
                                <option value="project">Project</option>
                            </select>
                        </label>

                        <label v-if="editForm.type === 'reporting'" class="block">
                            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Reporting Format</span>
                            <select
                                v-model="editForm.format"
                                class="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                            >
                                <option value="group">Group Reporting</option>
                                <option value="individual">Individual Reporting</option>
                            </select>
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Title</span>
                        <input
                            v-model="editForm.title"
                            required
                            class="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            {{
                                editForm.type === 'group_activity'
                                    ? 'Group Activity Instructions / Objectives'
                                    : editForm.type === 'project'
                                      ? 'Project Description / Scope'
                                      : (editForm.format === 'individual' ? 'Individual Presentation Guidelines / Instructions' : 'Group Reporting Instructions')
                            }}
                        </span>
                        <textarea
                            v-model="editForm.description"
                            rows="3"
                            class="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-primary"
                        />
                    </label>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Conducted / Due Date</span>
                            <input
                                v-model="editForm.conducted_on"
                                type="date"
                                class="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                            />
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Max Points</span>
                            <input
                                v-model="editForm.max_points"
                                type="number"
                                step="0.01"
                                class="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                            />
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground"
                            >Attachment / Reference Guidelines
                            <em class="font-normal normal-case text-muted-foreground">(optional, max 50MB)</em></span
                        >
                        <div
                            v-if="project.attachment_name && !editForm.attachment"
                            class="mb-2 flex items-center justify-between rounded-xl border border-border/80 bg-muted/40 p-2.5 text-xs"
                        >
                            <span class="flex items-center gap-1.5 font-medium text-foreground">
                                <Paperclip class="size-3.5 text-primary" />
                                Current: {{ project.attachment_name }}
                            </span>
                            <span class="text-[10px] text-muted-foreground">Upload new file below to replace</span>
                        </div>
                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z,.rtf,.odt,.ods,.odp,.svg"
                            class="mt-1 block w-full text-xs text-muted-foreground file:mr-2 file:rounded-lg file:border file:border-border file:bg-secondary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-foreground hover:file:bg-secondary/80"
                            @change="editForm.attachment = ($event.target as HTMLInputElement).files?.[0] || null"
                        />
                        <span v-if="editForm.attachment" class="mt-1 block font-mono text-[10px] text-primary">
                            Selected: {{ editForm.attachment.name }} ({{ (editForm.attachment.size / 1024 / 1024).toFixed(2) }} MB)
                        </span>
                        <small v-if="editForm.errors.attachment" class="mt-1 block text-xs font-semibold text-rose-600">{{
                            editForm.errors.attachment
                        }}</small>
                    </label>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border/80 pt-3">
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-3.5 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-600 hover:text-white dark:text-rose-400 dark:hover:text-white"
                            @click="
                                showEditModal = false;
                                showDeleteModal = true;
                            "
                        >
                            <Trash2 class="size-3.5" />
                            <span class="capitalize">Delete {{ project.type }}</span>
                        </button>

                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground"
                                @click="showEditModal = false"
                            >
                                Cancel
                            </button>
                            <button :disabled="editForm.processing" class="ink-button !h-9 !rounded-xl text-xs font-bold">
                                {{ editForm.processing ? 'Saving…' : 'Save Changes' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Project Confirmation Modal -->
        <div
            v-if="showDeleteModal"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-zinc-950/70 p-4 backdrop-blur-md duration-200 animate-in fade-in"
            @click.self="showDeleteModal = false"
        >
            <div
                class="paper-card relative w-full max-w-lg border-border/90 p-6 shadow-2xl duration-200 animate-in zoom-in-95"
                role="dialog"
                aria-modal="true"
                :aria-label="`Delete ${project.title}`"
            >
                <div class="flex items-start gap-4">
                    <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-rose-500/15 text-rose-700 dark:text-rose-400">
                        <Trash2 class="size-6" />
                    </div>
                    <div class="flex-1">
                        <span class="eyebrow text-rose-700 dark:text-rose-400">Permanent Deletion</span>
                        <h3 class="mt-1 text-xl font-bold text-foreground">Delete {{ project.title }}?</h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ project.type === 'group_activity' ? 'GROUP ACTIVITY' : project.type === 'project' ? 'PROJECT' : 'REPORTING' }} · {{ project.groups.length }} groups
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-rose-500/20 bg-rose-500/5 p-4 text-xs leading-relaxed text-foreground">
                    <p class="font-bold text-rose-700 dark:text-rose-400">
                        Warning: This action will permanently remove this {{ project.type }} activity:
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-4 text-muted-foreground">
                        <li>All {{ project.groups.length }} groups/presenters, student assignments, and roles</li>
                        <li>Assigned presentation topics, notes, and individual/group scores</li>
                        <li v-if="project.attachment_name">Attached guidelines file: {{ project.attachment_name }}</li>
                    </ul>
                    <p class="mt-2 font-bold text-rose-700 dark:text-rose-400">This action cannot be undone.</p>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-border/80 pt-4">
                    <button
                        type="button"
                        class="shadow-xs inline-flex h-10 items-center justify-center rounded-xl border border-border bg-card px-5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                        :disabled="isDeleting"
                        @click="showDeleteModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-5 text-xs font-bold text-white shadow-sm transition-all hover:bg-rose-700 disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="confirmDeleteProject"
                    >
                        <LoaderCircle v-if="isDeleting" class="size-4 animate-spin" />
                        <Trash2 v-else class="size-4" />
                        <span>{{ isDeleting ? 'Deleting…' : `Yes, Delete ${project.type}` }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Attachment Preview Modal -->
        <FilePreviewModal
            v-if="project.attachment_path"
            :show="showPreviewModal"
            :title="project.title"
            :file-name="project.attachment_name"
            :file-url="`/sections/${section.id}/projects/${project.id}/attachment`"
            :download-url="`/sections/${section.id}/projects/${project.id}/attachment?download=1`"
            @close="showPreviewModal = false"
        />
    </AppLayout>
</template>
