<script setup lang="ts">
import ClassroomSeatGrid from '@/components/ClassroomSeatGrid.vue';
import FloorPlanner from '@/components/FloorPlanner.vue';
import InputError from '@/components/InputError.vue';
import RandomStudentPicker from '@/components/RandomStudentPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    Armchair,
    CalendarCheck2,
    Check,
    CheckCircle2,
    ClipboardList,
    Copy,
    Dices,
    DoorOpen,
    Download,
    Edit3,
    FileDown,
    FileSpreadsheet,
    FolderKanban,
    MapPin,
    MessageSquare,
    Printer,
    QrCode,
    RefreshCw,
    Shuffle,
    SortAsc,
    Trash2,
    Upload,
    UserPlus,
    Users,
    X,
} from 'lucide-vue-next';
import QRCode from 'qrcode';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{ section: any; join_url: string; called_today_ids?: number[] }>();
const page = usePage<any>();
const selectedStudent = ref<any>(null);
const selectedSeatId = ref<number | null>(null);
const showQr = ref(false);
const showEnrollModal = ref(false);
const showImportModal = ref(false);
const showImportResults = ref(false);
const importActiveTab = ref<'success' | 'failed'>('success');
const showRosterModal = ref(false);
const rosterSearchQuery = ref('');
const qrDataUrl = ref('');
const copied = ref(false);

watch(
    () => page.props.flash?.import_results,
    (results) => {
        if (results) {
            showImportModal.value = false;
            showImportResults.value = true;
            importActiveTab.value = results.failed_count > 0 && results.success_count === 0 ? 'failed' : 'success';
        }
    },
    { immediate: true },
);

const filteredRoster = computed(() => {
    const q = rosterSearchQuery.value.toLowerCase().trim();
    if (!q) return props.section.students;
    return props.section.students.filter(
        (student: any) => student.full_name.toLowerCase().includes(q) || student.student_number.toLowerCase().includes(q),
    );
});

watch(selectedSeatId, (newVal) => {
    if (newVal !== null) {
        showEnrollModal.value = true;
    }
});

onMounted(async () => {
    qrDataUrl.value = await QRCode.toDataURL(props.join_url, {
        width: 640,
        margin: 2,
        color: { dark: '#0f172a', light: '#ffffff' },
    });
});

const printQr = () => {
    const popup = window.open('', '_blank', 'width=700,height=800');
    if (!popup) return;
    popup.document.write(
        `<title>Enrollment QR</title><body style="font-family:Tahoma,Arial,sans-serif;text-align:center;padding:40px"><h1>${props.section.subject_code} - ${props.section.name}</h1><img src="${qrDataUrl.value}" style="width:480px;max-width:100%"><p style="font-family:monospace;font-size:14px;word-break:break-all;">${props.join_url}</p></body>`,
    );
    popup.document.close();
    popup.print();
};

const availableSeats = computed(() =>
    props.section.layout_blocks.flatMap((block: any) => block.seats).filter((seat: any) => !seat.is_disabled && !seat.student_id),
);
const totalUsableSeats = computed(() => props.section.layout_blocks.flatMap((block: any) => block.seats).filter((seat: any) => !seat.is_disabled));
const selectedSeat = computed(() => availableSeats.value.find((seat: any) => seat.id === selectedSeatId.value) ?? null);
const seatedStudents = computed(() => props.section.students.filter((student: any) => student.seat));
const unseatedStudents = computed(() => props.section.students.filter((student: any) => !student.seat));

const initialFloorPlan = computed(() => {
    const blocks = [...props.section.layout_blocks].sort((a: any, b: any) => a.block_row - b.block_row || a.block_column - b.block_column);
    if (!blocks.length) return { rows: 5, columns: 6, aisle_after_rows: [], aisle_after_columns: [] };
    if (blocks.length === 1) {
        const block = blocks[0];
        return {
            rows: block.internal_rows,
            columns: block.internal_columns,
            aisle_after_rows: block.aisle_after_rows ?? [],
            aisle_after_columns: block.aisle_after_columns ?? [],
        };
    }

    const firstBlockRow = blocks[0].block_row;
    const rowBlocks = blocks.filter((block: any) => block.block_row === firstBlockRow);
    const aisleAfterColumns: number[] = [];
    let columns = 0;
    rowBlocks.forEach((block: any, index: number) => {
        columns += block.internal_columns;
        if (index < rowBlocks.length - 1) aisleAfterColumns.push(columns);
    });

    return {
        rows: Math.max(...rowBlocks.map((block: any) => block.internal_rows)),
        columns,
        aisle_after_rows: [],
        aisle_after_columns: aisleAfterColumns,
    };
});

const studentForm = useForm<{
    student_number: string;
    first_name: string;
    middle_name: string;
    last_name: string;
    seat_id: number | null;
    photo: File | null;
}>({ student_number: '', first_name: '', middle_name: '', last_name: '', seat_id: null, photo: null });

const importForm = useForm<{ roster: File | null }>({ roster: null });

const addStudent = () => {
    studentForm.seat_id = selectedSeatId.value;
    studentForm.post(`/sections/${props.section.id}/students`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            studentForm.reset();
            selectedSeatId.value = null;
            showEnrollModal.value = false;
        },
    });
};

const removeStudent = (student: any) => {
    if (confirm(`Remove ${student.full_name} from this section roster?`))
        router.delete(`/sections/${props.section.id}/students/${student.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedStudent.value?.id === student.id) {
                    selectedStudent.value = null;
                }
            },
        });
};

const unseatStudent = (student: any) => {
    router.patch(
        `/sections/${props.section.id}/students/${student.id}/seat`,
        { seat_id: null },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedStudent.value?.id === student.id) {
                    selectedStudent.value = null;
                }
            },
        },
    );
};

const moveStudent = (student: any, seatId: string) =>
    router.patch(
        `/sections/${props.section.id}/students/${student.id}/seat`,
        { seat_id: seatId ? Number(seatId) : null },
        { preserveScroll: true, onSuccess: () => (selectedStudent.value = null) },
    );

const autoAssign = (mode: 'alphabetical' | 'random') => {
    const modeName = mode === 'alphabetical' ? 'last name' : 'random';
    if (confirm(`Automatically assign chairs for students by ${modeName}?`)) {
        router.post(`/sections/${props.section.id}/seats/auto-assign`, { mode }, { preserveScroll: true });
    }
};

const resetAllSeats = () => {
    if (confirm('Clear all chair assignments for this section? Students will remain enrolled but become unseated.')) {
        router.post(`/sections/${props.section.id}/seats/reset`, {}, { preserveScroll: true });
    }
};

const saveAisles = ({ axis, values }: { axis: 'row' | 'column'; values: number[] }) => {
    const block = props.section.layout_blocks[0];
    const newRows = block.internal_rows;
    const newColumns = block.internal_columns;
    const newAisleRows = axis === 'row' ? values : (block.aisle_after_rows ?? []);
    const newAisleColumns = axis === 'column' ? values : (block.aisle_after_columns ?? []);

    router.put(
        `/sections/${props.section.id}/floor-plan`,
        {
            rows: newRows,
            columns: newColumns,
            aisle_after_rows: newAisleRows,
            aisle_after_columns: newAisleColumns,
        },
        { preserveScroll: true },
    );
};

const copyLink = async () => {
    await navigator.clipboard.writeText(props.join_url);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};
const dragStartUnseated = (event: DragEvent, student: any) => {
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', JSON.stringify({ studentId: student.id }));
    }
};

const handleDragMoveStudent = ({ studentId, targetSeatId }: { studentId: number; targetSeatId: number }) => {
    const student = props.section.students.find((s: any) => s.id === studentId);
    if (student) {
        moveStudent(student, targetSeatId);
    }
};
</script>

<template>
    <Head :title="`${section.subject_code} - ${section.name}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
        ]"
    >
        <main class="page-enter min-h-full bg-background px-5 pb-16 pt-8 text-foreground md:px-10 md:pt-10">
            <div class="mx-auto max-w-[1360px]">
                <div
                    v-if="page.props.flash?.success"
                    class="shadow-xs mb-6 rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-medium text-primary"
                >
                    {{ page.props.flash.success }}
                </div>

                <!-- Section Header Banner -->
                <header
                    class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 shadow-sm sm:p-8"
                >
                    <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge-primary font-mono font-medium">{{ section.subject_code }}</span>
                                <span class="badge-muted">{{ section.academic_term.name }}</span>
                                <span v-if="section.room" class="badge-muted flex items-center gap-1">
                                    <MapPin class="size-3" /> {{ section.room }}
                                </span>
                            </div>
                            <h1 class="mt-3 text-3xl font-medium tracking-tight sm:text-4xl md:text-5xl">{{ section.name }}</h1>
                            <p class="mt-2 text-sm text-muted-foreground sm:text-base">{{ section.subject_title }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <RandomStudentPicker :section-id="section.id" :students="section.students" :called-today-ids="called_today_ids" />
                            <Link
                                :href="`/sections/${section.id}/attendance`"
                                prefetch="hover"
                                class="group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-4 text-sm font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            >
                                <CalendarCheck2 class="size-4 text-primary transition-colors group-hover:text-white" />
                                <span>Attendance</span>
                            </Link>
                            <Link
                                :href="`/sections/${section.id}/assessments`"
                                prefetch="hover"
                                class="group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-4 text-sm font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            >
                                <ClipboardList class="size-4 text-primary transition-colors group-hover:text-white" />
                                <span>Scores</span>
                            </Link>
                            <Link
                                :href="`/sections/${section.id}/projects`"
                                prefetch="hover"
                                class="group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-4 text-sm font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            >
                                <FolderKanban class="size-4 text-primary transition-colors group-hover:text-white" />
                                <span>Projects & Groups</span>
                            </Link>
                            <Link
                                :href="`/sections/${section.id}/recitation`"
                                prefetch="hover"
                                class="group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-4 text-sm font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            >
                                <MessageSquare class="size-4 text-primary transition-colors group-hover:text-white" />
                                <span>Oral Participation</span>
                            </Link>
                            <Link
                                :href="`/sections/${section.id}/edit`"
                                prefetch="hover"
                                class="group inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-primary bg-white px-3 text-sm font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                                title="Edit section details"
                            >
                                <Edit3 class="size-4 text-primary transition-colors group-hover:text-white" />
                            </Link>
                            <Button class="ink-button !h-10 !rounded-xl" @click="showQr = true">
                                <QrCode class="size-4" />
                                <span>Enrollment QR</span>
                            </Button>
                        </div>
                    </div>
                </header>

                <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <!-- Live Classroom Seating Section -->
                    <section class="paper-card min-w-0 p-6 md:p-8">
                        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <span class="eyebrow">Classroom floor</span>
                                <h2 class="mt-1 text-2xl font-medium tracking-tight">Live seating arrangement</h2>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs font-medium">
                                <span class="badge-primary">
                                    <i class="size-2 rounded-full bg-primary" /> Assigned ({{ seatedStudents.length }})
                                </span>
                                <span class="badge-muted"> <i class="size-2 rounded-full bg-border" /> Available ({{ availableSeats.length }}) </span>
                            </div>
                        </div>

                        <!-- Seating automation quick-actions toolbar -->
                        <div
                            class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/80 bg-secondary/40 px-4 py-2.5 text-xs"
                        >
                            <span class="flex items-center gap-1.5 font-medium text-foreground">
                                <Shuffle class="size-3.5 text-primary" /> Auto-Assign Chairs:
                            </span>
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-2.5 py-1 font-medium text-foreground transition-all hover:border-primary/40 hover:bg-secondary disabled:opacity-40"
                                    :disabled="!section.students.length || !totalUsableSeats.length"
                                    @click="autoAssign('alphabetical')"
                                >
                                    <SortAsc class="size-3 text-primary" /> Last Name
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-2.5 py-1 font-medium text-foreground transition-all hover:border-primary/40 hover:bg-secondary disabled:opacity-40"
                                    :disabled="!section.students.length || !totalUsableSeats.length"
                                    @click="autoAssign('random')"
                                >
                                    <Dices class="size-3 text-primary" /> Random Shuffle
                                </button>
                                <button
                                    v-if="seatedStudents.length"
                                    type="button"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 font-medium text-rose-600 transition-colors hover:bg-rose-500/10 dark:text-rose-400"
                                    @click="resetAllSeats"
                                >
                                    Clear all
                                </button>
                            </div>
                        </div>

                        <!-- Teaching Wall Bar -->
                        <div
                            class="mb-6 flex items-center justify-center rounded-2xl bg-[#164e3f] py-3.5 px-6 text-center text-xs md:text-sm font-bold uppercase tracking-[0.25em] text-white shadow-xs dark:bg-[#134e48]"
                        >
                            Teaching Wall / Front Board
                        </div>

                        <div
                            v-if="section.layout_blocks.length"
                            class="w-full max-w-full overflow-x-auto overscroll-x-contain pb-4 [-webkit-overflow-scrolling:touch] [scrollbar-gutter:stable]"
                            role="region"
                            aria-label="Scrollable classroom seating chart"
                            tabindex="0"
                        >
                            <div
                                class="grid min-w-[620px] gap-8"
                                :style="{
                                    gridTemplateColumns: `repeat(${Math.max(...section.layout_blocks.map((block: any) => block.block_column))}, minmax(220px, 1fr))`,
                                }"
                            >
                                <ClassroomSeatGrid
                                    v-for="block in section.layout_blocks"
                                    :key="block.id"
                                    :block="block"
                                    :section-name="section.name"
                                    :selected-seat-id="selectedSeatId"
                                    :unseated-students="unseatedStudents"
                                    @select-seat="selectedSeatId = selectedSeatId === $event ? null : $event"
                                    @select-student="selectedStudent = $event"
                                    @assign-student="moveStudent($event.student, $event.seatId)"
                                    @drag-move-student="handleDragMoveStudent"
                                    @update-aisles="saveAisles"
                                />
                            </div>
                        </div>

                        <div v-else class="rounded-2xl border border-dashed border-border bg-secondary/30 p-12 text-center">
                            <h3 class="text-xl font-medium">Define classroom rows & columns</h3>
                            <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                                Use the Floor Planner on the right to configure the room layout and aisles.
                            </p>
                        </div>
                    </section>

                    <!-- Sidebar Controls & Roster -->
                    <aside class="space-y-6">
                        <FloorPlanner :section-id="section.id" :initial-plan="initialFloorPlan" />

                        <!-- Roster Section -->
                        <div class="paper-card p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Users class="size-4 text-primary" />
                                    <h3 class="text-base font-medium">Class roster</h3>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="`/sections/${section.id}/roster/print`"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 text-[11px] font-medium text-primary hover:underline"
                                    >
                                        <Printer class="size-3" /> Print
                                    </Link>
                                    <span class="badge-primary font-medium">{{ section.students.length }} enrolled</span>
                                </div>
                            </div>

                            <!-- Manage Roster Actions Buttons -->
                            <div class="mt-4 flex flex-col gap-2">
                                <button
                                    type="button"
                                    class="group inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-primary bg-white px-3 text-xs font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                                    @click="showRosterModal = true"
                                >
                                    <Users class="size-4 text-primary transition-colors group-hover:text-white" />
                                    <span>View Class Roster ({{ section.students.length }} Enrolled)</span>
                                </button>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        class="group inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-primary bg-white px-3 text-xs font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                                        @click="showEnrollModal = true"
                                    >
                                        <UserPlus class="size-3.5 text-primary transition-colors group-hover:text-white" />
                                        <span>Quick Enroll</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="group inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-primary bg-white px-3 text-xs font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                                        @click="showImportModal = true"
                                    >
                                        <Upload class="size-3.5 text-primary transition-colors group-hover:text-white" />
                                        <span>Import CSV</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

            <!-- Enrollment QR Modal -->
            <div
                v-if="showQr"
                class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-zinc-950/70 p-4 backdrop-blur-md"
                @click.self="showQr = false"
            >
                <section class="paper-card mt-0 w-full max-w-md p-7 text-center shadow-2xl duration-200 animate-in fade-in zoom-in-95">
                    <button
                        class="ml-auto grid size-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        @click="showQr = false"
                    >
                        <X class="size-4.5" />
                    </button>
                    <span class="badge-primary">Student enrollment</span>
                    <h2 class="mt-2 text-2xl font-medium tracking-tight">Scan & Claim Chair</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Students scan this QR code to view the live floor and pick their seat.</p>

                    <div class="mx-auto my-5 inline-block rounded-2xl border border-border/80 bg-white p-4 shadow-sm">
                        <img :src="qrDataUrl" alt="Enrollment QR code" class="size-60 rounded-xl" />
                    </div>

                    <p class="break-all rounded-xl border border-border/50 bg-secondary/80 p-3 font-mono text-[11px] text-muted-foreground">
                        {{ join_url }}
                    </p>

                    <div class="mt-4 flex gap-2">
                        <Button variant="outline" class="flex-1 rounded-xl text-xs font-medium" @click="copyLink">
                            <Check v-if="copied" class="mr-1.5 size-3.5 text-emerald-600" />
                            <Copy v-else class="mr-1.5 size-3.5" />
                            <span>{{ copied ? 'Copied link' : 'Copy link' }}</span>
                        </Button>
                        <Button
                            variant="outline"
                            class="flex-1 rounded-xl text-xs font-medium"
                            @click="router.patch(`/sections/${section.id}/enrollment`)"
                        >
                            <DoorOpen class="mr-1.5 size-3.5" />
                            <span>{{ section.enrollment_open ? 'Close' : 'Open' }}</span>
                        </Button>
                    </div>

                    <div class="mt-2 flex justify-center gap-2">
                        <a
                            v-if="qrDataUrl"
                            :href="qrDataUrl"
                            :download="`${section.subject_code}-${section.name}-enrollment.png`"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors hover:bg-secondary"
                        >
                            <Download class="size-3.5" /> Download PNG
                        </a>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors hover:bg-secondary"
                            @click="printQr"
                        >
                            <Printer class="size-3.5" /> Print QR
                        </button>
                    </div>

                    <Button
                        variant="ghost"
                        class="mt-2 text-xs text-muted-foreground hover:text-foreground"
                        @click="router.post(`/sections/${section.id}/enrollment-token`)"
                    >
                        <RefreshCw class="mr-1.5 size-3" /> Invalidate & create new link
                    </Button>

                    <p class="mt-3 text-xs font-medium" :class="section.enrollment_open ? 'text-primary' : 'text-rose-600'">
                        Enrollment is {{ section.enrollment_open ? 'OPEN' : 'CLOSED' }}
                    </p>
                </section>
            </div>

            <!-- Student Detail Drawer -->
            <div v-if="selectedStudent" class="backdrop-blur-xs fixed inset-0 z-40 bg-zinc-950/50" @click.self="selectedStudent = null">
                <aside
                    class="ml-auto flex h-full w-full max-w-md flex-col border-l border-border bg-card p-6 text-card-foreground shadow-2xl duration-200 animate-in slide-in-from-right"
                >
                    <button
                        class="ml-auto grid size-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        @click="selectedStudent = null"
                    >
                        <X class="size-4.5" />
                    </button>

                    <div class="mt-4 flex items-center gap-4">
                        <img
                            v-if="selectedStudent.photo_url"
                            :src="selectedStudent.photo_url"
                            alt=""
                            class="size-20 rounded-2xl border border-border object-cover shadow-sm"
                        />
                        <div
                            v-else
                            class="grid size-20 place-items-center rounded-2xl border border-primary/20 bg-primary/10 text-3xl font-medium text-primary"
                        >
                            {{ selectedStudent.first_name[0] }}{{ selectedStudent.last_name[0] }}
                        </div>
                        <div>
                            <h2 class="text-2xl font-medium tracking-tight">{{ selectedStudent.full_name }}</h2>
                            <p class="font-mono text-xs text-muted-foreground">{{ selectedStudent.student_number }}</p>
                        </div>
                    </div>

                    <dl class="mt-8 grid grid-cols-2 gap-4 border-y border-dashed border-border py-5">
                        <div>
                            <dt class="text-xs font-medium text-muted-foreground">Current chair</dt>
                            <dd class="mt-1 font-mono text-sm font-medium text-primary">{{ selectedStudent.seat?.label || 'Unseated' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-muted-foreground">Section</dt>
                            <dd class="mt-1 text-sm font-medium">{{ section.name }}</dd>
                        </div>
                    </dl>

                    <div class="mt-6 space-y-3">
                        <Label class="text-xs font-semibold">Chair Assignment</Label>
                        <select
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                            :value="selectedStudent.seat?.id || ''"
                            @change="moveStudent(selectedStudent, ($event.target as HTMLSelectElement).value)"
                        >
                            <option value="">Leave unseated</option>
                            <option v-if="selectedStudent.seat" :value="selectedStudent.seat.id">{{ selectedStudent.seat.label }} (current)</option>
                            <option v-for="seat in availableSeats" :key="seat.id" :value="seat.id">{{ seat.label }}</option>
                        </select>

                        <!-- Direct Unseat Button if currently seated -->
                        <Button
                            v-if="selectedStudent.seat"
                            type="button"
                            variant="outline"
                            class="h-9.5 w-full gap-2 rounded-xl border-amber-500/30 bg-amber-500/5 text-xs font-semibold text-amber-600 hover:bg-amber-500/15 dark:text-amber-400"
                            @click="unseatStudent(selectedStudent)"
                        >
                            <Armchair class="size-4" />
                            <span>Unseat Student (Clear {{ selectedStudent.seat.label }})</span>
                        </Button>
                    </div>

                    <div class="mt-auto border-t border-border/60 pt-6">
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 w-full gap-2 rounded-xl border-rose-300 bg-rose-50/50 text-xs font-semibold text-rose-600 hover:bg-rose-100/80 dark:border-rose-900/50 dark:bg-rose-950/20 dark:hover:bg-rose-950/40"
                            @click="removeStudent(selectedStudent)"
                        >
                            <Trash2 class="size-4" />
                            <span>Remove from Class Roster</span>
                        </Button>
                    </div>
                </aside>
            </div>

            <!-- Quick Enroll Modal -->
            <div
                v-if="showEnrollModal"
                class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-zinc-950/70 p-4 backdrop-blur-md"
                @click.self="
                    showEnrollModal = false;
                    selectedSeatId = null;
                "
            >
                <section class="paper-card mt-0 w-full max-w-md p-7 shadow-2xl duration-200 animate-in fade-in zoom-in-95">
                    <div class="flex items-center justify-between border-b border-border/60 pb-3">
                        <div>
                            <span class="eyebrow">Quick enroll</span>
                            <h2 class="text-xl font-medium">Add student to roster</h2>
                        </div>
                        <button
                            type="button"
                            class="grid size-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            @click="
                                showEnrollModal = false;
                                selectedSeatId = null;
                            "
                        >
                            <X class="size-4.5" />
                        </button>
                    </div>

                    <form class="mt-4" @submit.prevent="addStudent">
                        <div
                            class="mb-4 rounded-xl border p-3 text-xs transition-colors"
                            :class="
                                selectedSeat
                                    ? 'border-primary/40 bg-primary/10 font-medium text-foreground'
                                    : 'border-border bg-secondary/50 text-muted-foreground'
                            "
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span v-if="selectedSeat">
                                    Chair <span class="font-medium">{{ selectedSeat.label }}</span> is selected.
                                </span>
                                <span v-else>No chair selected — student will be enrolled unseated.</span>
                                <button
                                    v-if="selectedSeat"
                                    type="button"
                                    class="shrink-0 font-medium text-primary hover:underline"
                                    @click="selectedSeatId = null"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <div>
                                <Label for="student-number" class="text-xs font-medium"
                                    >ID / Student number <span class="font-normal text-muted-foreground">(optional)</span></Label
                                >
                                <Input
                                    id="student-number"
                                    v-model="studentForm.student_number"
                                    class="mt-1 h-9 text-sm"
                                    placeholder="e.g. 2026-001 (optional)"
                                    autocomplete="off"
                                />
                                <InputError class="mt-1 text-xs" :message="studentForm.errors.student_number" />
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for="student-first-name" class="text-xs font-medium">First name</Label>
                                    <Input
                                        id="student-first-name"
                                        v-model="studentForm.first_name"
                                        class="mt-1 h-9 text-sm"
                                        autocomplete="given-name"
                                    />
                                    <InputError class="mt-1 text-xs" :message="studentForm.errors.first_name" />
                                </div>
                                <div>
                                    <Label for="student-last-name" class="text-xs font-medium">Last name</Label>
                                    <Input
                                        id="student-last-name"
                                        v-model="studentForm.last_name"
                                        class="mt-1 h-9 text-sm"
                                        autocomplete="family-name"
                                    />
                                    <InputError class="mt-1 text-xs" :message="studentForm.errors.last_name" />
                                </div>
                            </div>
                            <div>
                                <Label for="student-middle-name" class="text-xs font-medium"
                                    >Middle name <span class="font-normal text-muted-foreground">(optional)</span></Label
                                >
                                <Input
                                    id="student-middle-name"
                                    v-model="studentForm.middle_name"
                                    class="mt-1 h-9 text-sm"
                                    autocomplete="additional-name"
                                />
                                <InputError class="mt-1 text-xs" :message="studentForm.errors.middle_name" />
                            </div>
                            <div>
                                <Label for="student-photo" class="mb-1 block text-xs font-medium"
                                    >Photo <span class="font-normal text-muted-foreground">(optional)</span></Label
                                >
                                <input
                                    id="student-photo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="block w-full text-xs text-muted-foreground file:mr-2 file:rounded-lg file:border file:border-border file:bg-secondary file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-foreground hover:file:bg-secondary/80"
                                    @change="studentForm.photo = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                />
                                <InputError class="mt-1 text-xs" :message="studentForm.errors.photo" />
                            </div>
                        </div>
                        <InputError class="mt-2 text-xs" :message="studentForm.errors.seat_id" />
                        <Button type="submit" class="ink-button mt-5 !h-10 !w-full" :disabled="studentForm.processing">
                            <UserPlus class="size-4" />
                            <span>{{ studentForm.processing ? 'Adding...' : 'Add student' }}</span>
                        </Button>
                    </form>
                </section>
            </div>

            <!-- Import Roster Modal -->
            <div
                v-if="showImportModal"
                class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-zinc-950/70 p-4 backdrop-blur-md"
                @click.self="showImportModal = false"
            >
                <section class="paper-card mt-0 w-full max-w-lg p-7 shadow-2xl duration-200 animate-in fade-in zoom-in-95">
                    <div class="flex items-center justify-between border-b border-border/60 pb-3">
                        <div>
                            <span class="eyebrow">Roster Import</span>
                            <h2 class="text-xl font-medium text-foreground">Import CSV file</h2>
                        </div>
                        <button
                            type="button"
                            class="grid size-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            @click="showImportModal = false"
                        >
                            <X class="size-4.5" />
                        </button>
                    </div>

                    <!-- Step 1: Download Template Card -->
                    <div class="mt-4 rounded-xl border border-primary/20 bg-primary/5 p-4">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div>
                                <div class="flex items-center gap-1.5 text-xs font-medium text-primary">
                                    <FileSpreadsheet class="size-4" />
                                    <span>Step 1: Download Format Template</span>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Download the CSV template, fill in your student list, and upload below.
                                </p>
                            </div>
                            <a
                                :href="`/sections/${section.id}/roster/template`"
                                class="group inline-flex items-center justify-center gap-1.5 rounded-xl border border-primary bg-white px-3.5 py-2 text-xs font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            >
                                <FileDown class="size-3.5 text-primary transition-colors group-hover:text-white" />
                                <span>Download Template</span>
                            </a>
                        </div>

                        <!-- Template Column Reference Guide -->
                        <div class="mt-3 grid grid-cols-2 gap-2 border-t border-primary/10 pt-2.5 text-[11px] sm:grid-cols-4">
                            <div>
                                <span class="font-mono font-medium text-foreground">student_number</span>
                                <p class="text-muted-foreground">Optional ID</p>
                            </div>
                            <div>
                                <span class="font-mono font-medium text-foreground">first_name</span>
                                <p class="text-muted-foreground">Required</p>
                            </div>
                            <div>
                                <span class="font-mono font-medium text-foreground">last_name</span>
                                <p class="text-muted-foreground">Required</p>
                            </div>
                            <div>
                                <span class="font-mono font-medium text-foreground">middle_name</span>
                                <p class="text-muted-foreground">Optional</p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Upload CSV File -->
                    <form
                        class="mt-4"
                        @submit.prevent="
                            importForm.post(`/sections/${section.id}/students-import`, {
                                forceFormData: true,
                            })
                        "
                    >
                        <div class="rounded-xl border border-dashed border-border/80 bg-secondary/30 p-5 text-center">
                            <label class="block text-xs font-medium text-foreground">Step 2: Select completed CSV file</label>
                            <input
                                type="file"
                                accept=".csv,text/csv"
                                class="mt-3 block w-full cursor-pointer text-xs text-muted-foreground file:mr-2 file:rounded-lg file:border file:border-border file:bg-secondary file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-foreground hover:file:bg-secondary/80"
                                @change="importForm.roster = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            />
                            <p class="mt-2 text-[11px] text-muted-foreground">
                                Uploaded students will be placed on the active roster as unseated chairs.
                            </p>
                            <InputError class="mt-2 text-xs" :message="importForm.errors.roster" />
                        </div>

                        <Button type="submit" class="ink-button mt-5 !h-10 !w-full" :disabled="!importForm.roster || importForm.processing">
                            <Upload class="size-4" />
                            <span>{{ importForm.processing ? 'Importing roster...' : 'Upload & Import' }}</span>
                        </Button>
                    </form>
                </section>
            </div>

            <!-- Import Results Summary Modal -->
            <div
                v-if="showImportResults && page.props.flash?.import_results"
                class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-zinc-950/70 p-4 backdrop-blur-md"
                @click.self="showImportResults = false"
            >
                <section class="paper-card mt-0 w-full max-w-2xl p-7 shadow-2xl duration-200 animate-in fade-in zoom-in-95">
                    <div class="flex items-center justify-between border-b border-border/60 pb-3">
                        <div>
                            <span class="eyebrow">Import Report</span>
                            <h2 class="text-xl font-medium text-foreground">Roster Upload Results</h2>
                        </div>
                        <button
                            type="button"
                            class="grid size-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            @click="showImportResults = false"
                        >
                            <X class="size-4.5" />
                        </button>
                    </div>

                    <!-- Summary KPI Badges -->
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <div class="rounded-xl border border-border/80 bg-secondary/30 p-3 text-center">
                            <span class="text-xs text-muted-foreground">Total Rows</span>
                            <p class="mt-0.5 text-lg font-medium text-foreground">{{ page.props.flash.import_results.total }}</p>
                        </div>
                        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-center">
                            <span class="text-xs text-emerald-700 dark:text-emerald-400">Successfully Uploaded</span>
                            <p class="mt-0.5 text-lg font-medium text-emerald-700 dark:text-emerald-400">
                                {{ page.props.flash.import_results.success_count }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl p-3 text-center"
                            :class="
                                page.props.flash.import_results.failed_count > 0
                                    ? 'border border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-400'
                                    : 'border border-border/80 bg-secondary/30 text-muted-foreground'
                            "
                        >
                            <span class="text-xs">Failed / Skipped</span>
                            <p class="mt-0.5 text-lg font-medium">
                                {{ page.props.flash.import_results.failed_count }}
                            </p>
                        </div>
                    </div>

                    <!-- Tabs Switcher -->
                    <div class="mt-5 flex items-center gap-2 border-b border-border/80 pb-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                            :class="
                                importActiveTab === 'success'
                                    ? 'bg-primary text-white'
                                    : 'text-muted-foreground hover:bg-amber-400 hover:text-white'
                            "
                            @click="importActiveTab = 'success'"
                        >
                            <CheckCircle2 class="size-3.5 text-emerald-400" />
                            <span>Successfully Uploaded ({{ page.props.flash.import_results.success_count }})</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                            :class="
                                importActiveTab === 'failed'
                                    ? 'bg-rose-700 text-white'
                                    : 'text-muted-foreground hover:bg-amber-400 hover:text-white'
                            "
                            @click="importActiveTab = 'failed'"
                        >
                            <AlertCircle class="size-3.5" />
                            <span>Failed / Skipped ({{ page.props.flash.import_results.failed_count }})</span>
                        </button>
                    </div>

                    <!-- TAB 1: Successful Students List -->
                    <div v-if="importActiveTab === 'success'" class="mt-3 max-h-64 overflow-y-auto">
                        <div v-if="page.props.flash.import_results.successful.length > 0" class="space-y-1.5">
                            <div
                                v-for="item in page.props.flash.import_results.successful"
                                :key="item.student_number"
                                class="flex items-center justify-between rounded-lg border border-border/60 bg-card px-3 py-2 text-xs"
                            >
                                <div class="flex items-center gap-2">
                                    <CheckCircle2 class="size-4 text-emerald-600 dark:text-emerald-400" />
                                    <div>
                                        <span class="font-medium text-foreground">{{ item.name }}</span>
                                        <span class="ml-2 font-mono text-muted-foreground">{{ item.student_number }}</span>
                                    </div>
                                </div>
                                <span class="rounded bg-emerald-500/10 px-2 py-0.5 font-medium text-emerald-700 dark:text-emerald-400">
                                    {{ item.action }} (Row {{ item.row }})
                                </span>
                            </div>
                        </div>
                        <p v-else class="py-8 text-center text-xs text-muted-foreground">No students were uploaded.</p>
                    </div>

                    <!-- TAB 2: Failed Rows List -->
                    <div v-if="importActiveTab === 'failed'" class="mt-3 max-h-64 overflow-y-auto">
                        <div v-if="page.props.flash.import_results.failed.length > 0" class="space-y-1.5">
                            <div
                                v-for="fail in page.props.flash.import_results.failed"
                                :key="fail.row"
                                class="rounded-lg border border-rose-500/30 bg-rose-500/5 p-3 text-xs"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-mono font-medium text-rose-700 dark:text-rose-400">Row {{ fail.row }}</span>
                                    <span class="rounded bg-rose-500/20 px-2 py-0.5 font-medium text-rose-700 dark:text-rose-300">
                                        {{ fail.reason }}
                                    </span>
                                </div>
                                <p v-if="fail.raw" class="mt-1 font-mono text-[11px] text-muted-foreground truncate">
                                    Data: {{ fail.raw }}
                                </p>
                            </div>
                        </div>
                        <div v-else class="py-8 text-center text-xs text-muted-foreground">
                            <CheckCircle2 class="mx-auto mb-1 size-5 text-emerald-600 dark:text-emerald-400" />
                            <span>All rows were uploaded cleanly with zero errors!</span>
                        </div>
                    </div>

                    <!-- Actions Footer -->
                    <div class="mt-6 flex items-center justify-end gap-2.5 border-t border-border/60 pt-4">
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-border px-4 text-xs font-medium text-foreground hover:bg-secondary"
                            @click="
                                showImportResults = false;
                                showImportModal = true;
                            "
                        >
                            <Upload class="size-3.5" />
                            <span>Upload Another CSV</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center rounded-xl border border-primary bg-white px-5 text-xs font-medium text-primary shadow-xs transition-all hover:border-amber-400 hover:bg-amber-400 hover:text-white dark:bg-card"
                            @click="showImportResults = false"
                        >
                            <span>Done</span>
                        </button>
                    </div>
                </section>
            </div>

            <!-- View Class Roster Modal -->
            <div
                v-if="showRosterModal"
                class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-zinc-950/70 p-4 backdrop-blur-md"
                @click.self="showRosterModal = false"
            >
                <section class="paper-card mt-0 w-full max-w-lg p-7 shadow-2xl duration-200 animate-in fade-in zoom-in-95">
                    <div class="flex items-center justify-between border-b border-border/60 pb-3">
                        <div>
                            <span class="eyebrow">Roster Management</span>
                            <h2 class="text-xl font-medium">Class Roster ({{ section.students.length }})</h2>
                        </div>
                        <button
                            type="button"
                            class="grid size-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            @click="showRosterModal = false"
                        >
                            <X class="size-4.5" />
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="mt-4">
                        <Input
                            v-slot="{ value }"
                            v-model="rosterSearchQuery"
                            placeholder="Search students by name or student number..."
                            class="h-10 rounded-xl text-xs"
                            autocomplete="off"
                        />
                    </div>

                    <!-- Students List -->
                    <div class="mt-5 max-h-96 space-y-1.5 overflow-y-auto pr-1">
                        <div
                            v-for="student in filteredRoster"
                            :key="student.id"
                            class="group flex cursor-pointer items-center justify-between rounded-xl border border-border/50 bg-card p-2.5 transition-all hover:bg-secondary"
                            @click="
                                selectedStudent = student;
                                showRosterModal = false;
                            "
                        >
                            <div class="flex min-w-0 items-center gap-2.5">
                                <div class="relative shrink-0">
                                    <img
                                        v-if="student.photo_url"
                                        :src="student.photo_url"
                                        alt=""
                                        class="size-9 rounded-xl border border-border object-cover"
                                    />
                                    <div
                                        v-else
                                        class="grid size-9 place-items-center rounded-xl border border-primary/20 bg-primary/10 text-xs font-medium text-primary"
                                    >
                                        {{ student.first_name[0] }}{{ student.last_name[0] }}
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-medium text-foreground transition-colors group-hover:text-primary">
                                        {{ student.full_name }}
                                    </p>
                                    <p class="font-mono text-[10px] text-muted-foreground">{{ student.student_number }}</p>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-1.5" @click.stop>
                                <span
                                    v-if="student.seat"
                                    class="rounded-lg border border-primary/20 bg-primary/10 px-2.5 py-1 font-mono text-[10px] font-medium text-primary"
                                >
                                    {{ student.seat.label }}
                                </span>
                                <span
                                    v-else
                                    class="rounded-lg border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 font-mono text-[10px] font-medium text-amber-600 dark:text-amber-400"
                                >
                                    Unseated
                                </span>

                                <button
                                    v-if="student.seat"
                                    type="button"
                                    class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-amber-500/15 hover:text-amber-600 dark:hover:text-amber-400"
                                    title="Unseat student from chair"
                                    @click.stop="unseatStudent(student)"
                                >
                                    <Armchair class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-rose-500/15 hover:text-rose-600 dark:hover:text-rose-400"
                                    title="Remove student from roster"
                                    @click.stop="removeStudent(student)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <div v-if="!filteredRoster.length" class="py-12 text-center text-xs text-muted-foreground">
                            {{ rosterSearchQuery ? 'No students match your search query.' : 'No students enrolled in this section.' }}
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </AppLayout>
</template>
