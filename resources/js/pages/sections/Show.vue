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
    Armchair,
    CalendarCheck2,
    Check,
    ClipboardList,
    Copy,
    Dices,
    DoorOpen,
    Download,
    Edit3,
    FileSpreadsheet,
    MapPin,
    MoreHorizontal,
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
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ section: any; join_url: string }>();
const page = usePage<any>();
const selectedStudent = ref<any>(null);
const selectedSeatId = ref<number | null>(null);
const showQr = ref(false);
const qrDataUrl = ref('');
const copied = ref(false);

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
        `<title>Enrollment QR</title><body style="font-family:Arial,sans-serif;text-align:center;padding:40px"><h1>${props.section.subject_code} - ${props.section.name}</h1><img src="${qrDataUrl.value}" style="width:480px;max-width:100%"><p style="font-family:monospace;font-size:14px;word-break:break-all;">${props.join_url}</p></body>`,
    );
    popup.document.close();
    popup.print();
};

const availableSeats = computed(() =>
    props.section.layout_blocks.flatMap((block: any) => block.seats).filter((seat: any) => !seat.is_disabled && !seat.student_id),
);
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
        },
    });
};

const removeStudent = (student: any) => {
    if (confirm(`Remove ${student.full_name} from this section?`))
        router.delete(`/sections/${props.section.id}/students/${student.id}`, { onSuccess: () => (selectedStudent.value = null) });
};

const moveStudent = (student: any, seatId: string) =>
    router.patch(
        `/sections/${props.section.id}/students/${student.id}/seat`,
        { seat_id: seatId ? Number(seatId) : null },
        { onSuccess: () => (selectedStudent.value = null) },
    );

const autoAssign = (mode: 'alphabetical' | 'random') => {
    if (confirm(`Automatically assign unseated students to available chairs (${mode})?`)) {
        router.post(`/sections/${props.section.id}/seats/auto-assign`, { mode }, { preserveScroll: true });
    }
};

const resetAllSeats = () => {
    if (confirm('Clear all chair assignments for this section? Students will remain enrolled but become unseated.')) {
        router.post(`/sections/${props.section.id}/seats/reset`, {}, { preserveScroll: true });
    }
};

const copyLink = async () => {
    await navigator.clipboard.writeText(props.join_url);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
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
                    class="mb-6 rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-semibold text-primary shadow-xs"
                >
                    {{ page.props.flash.success }}
                </div>

                <!-- Section Header Banner -->
                <header class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/5 p-6 sm:p-8 shadow-sm">
                    <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge-primary font-mono font-bold">{{ section.subject_code }}</span>
                                <span class="badge-muted">{{ section.academic_term.name }}</span>
                                <span v-if="section.room" class="badge-muted flex items-center gap-1">
                                    <MapPin class="size-3" /> {{ section.room }}
                                </span>
                            </div>
                            <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl md:text-5xl">{{ section.name }}</h1>
                            <p class="mt-2 text-sm sm:text-base text-muted-foreground">{{ section.subject_title }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <RandomStudentPicker :students="section.students" />
                            <Link
                                :href="`/sections/${section.id}/attendance`"
                                prefetch="hover"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-semibold text-foreground shadow-xs hover:bg-secondary hover:text-primary transition-colors"
                            >
                                <CalendarCheck2 class="size-4 text-primary" />
                                <span>Attendance</span>
                            </Link>
                            <Link
                                :href="`/sections/${section.id}/assessments`"
                                prefetch="hover"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-semibold text-foreground shadow-xs hover:bg-secondary hover:text-primary transition-colors"
                            >
                                <ClipboardList class="size-4 text-primary" />
                                <span>Scores</span>
                            </Link>
                            <Link
                                :href="`/sections/${section.id}/edit`"
                                prefetch="hover"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 text-sm font-semibold text-foreground shadow-xs hover:bg-secondary transition-colors"
                                title="Edit section details"
                            >
                                <Edit3 class="size-4 text-muted-foreground" />
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
                                <h2 class="mt-1 text-2xl font-bold tracking-tight">Live seating arrangement</h2>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                                <span class="badge-primary">
                                    <i class="size-2 rounded-full bg-primary" /> Assigned ({{ seatedStudents.length }})
                                </span>
                                <span class="badge-muted">
                                    <i class="size-2 rounded-full bg-border" /> Available ({{ availableSeats.length }})
                                </span>
                            </div>
                        </div>

                        <!-- Seating automation quick-actions toolbar -->
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/80 bg-secondary/40 px-4 py-2.5 text-xs">
                            <span class="font-bold text-foreground flex items-center gap-1.5">
                                <Shuffle class="size-3.5 text-primary" /> Auto-Assign Chairs:
                            </span>
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg bg-card px-2.5 py-1 font-semibold text-foreground border border-border hover:border-primary/40 hover:bg-secondary transition-all disabled:opacity-40"
                                    :disabled="!unseatedStudents.length || !availableSeats.length"
                                    @click="autoAssign('alphabetical')"
                                >
                                    <SortAsc class="size-3 text-primary" /> A–Z Order
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg bg-card px-2.5 py-1 font-semibold text-foreground border border-border hover:border-primary/40 hover:bg-secondary transition-all disabled:opacity-40"
                                    :disabled="!unseatedStudents.length || !availableSeats.length"
                                    @click="autoAssign('random')"
                                >
                                    <Dices class="size-3 text-primary" /> Random Shuffle
                                </button>
                                <button
                                    v-if="seatedStudents.length"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-500/10 transition-colors"
                                    @click="resetAllSeats"
                                >
                                    Clear all
                                </button>
                            </div>
                        </div>

                        <!-- Teaching Wall Bar -->
                        <div class="mb-8 rounded-xl bg-gradient-to-r from-zinc-900 via-zinc-800 to-zinc-900 py-3 text-center text-xs font-extrabold uppercase tracking-[0.25em] text-white shadow-sm">
                            Teaching wall / front board
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
                                    @select-seat="selectedSeatId = selectedSeatId === $event ? null : $event"
                                    @select-student="selectedStudent = $event"
                                />
                            </div>
                        </div>

                        <div v-else class="rounded-2xl border border-dashed border-border bg-secondary/30 p-12 text-center">
                            <h3 class="font-bold text-xl">Define classroom rows & columns</h3>
                            <p class="mt-2 text-sm text-muted-foreground max-w-md mx-auto">
                                Use the Floor Planner on the right to configure the room layout and aisles.
                            </p>
                        </div>
                    </section>

                    <!-- Sidebar Controls & Roster -->
                    <aside class="space-y-6">
                        <FloorPlanner :section-id="section.id" :initial-plan="initialFloorPlan" />

                        <!-- Manual Roster Entry Form -->
                        <form class="paper-card p-6" @submit.prevent="addStudent">
                            <span class="eyebrow">Quick enroll</span>
                            <h3 class="mt-1 text-lg font-bold">Add student to roster</h3>

                            <div
                                class="mt-3 rounded-xl border p-3 text-xs transition-colors"
                                :class="
                                    selectedSeat
                                        ? 'border-primary/40 bg-primary/10 text-foreground font-medium'
                                        : 'border-border bg-secondary/50 text-muted-foreground'
                                "
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <span v-if="selectedSeat">
                                        Chair <strong>{{ selectedSeat.label }}</strong> is selected.
                                    </span>
                                    <span v-else>No chair selected — student will be enrolled unseated.</span>
                                    <button
                                        v-if="selectedSeat"
                                        type="button"
                                        class="shrink-0 font-bold text-primary hover:underline"
                                        @click="selectedSeatId = null"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3">
                                <div>
                                    <Label for="student-number" class="text-xs font-semibold">Student number</Label>
                                    <Input id="student-number" v-model="studentForm.student_number" class="mt-1 h-9 text-sm" placeholder="e.g. 2026-001" autocomplete="off" />
                                    <InputError class="mt-1 text-xs" :message="studentForm.errors.student_number" />
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <Label for="student-first-name" class="text-xs font-semibold">First name</Label>
                                        <Input id="student-first-name" v-model="studentForm.first_name" class="mt-1 h-9 text-sm" autocomplete="given-name" />
                                        <InputError class="mt-1 text-xs" :message="studentForm.errors.first_name" />
                                    </div>
                                    <div>
                                        <Label for="student-last-name" class="text-xs font-semibold">Last name</Label>
                                        <Input id="student-last-name" v-model="studentForm.last_name" class="mt-1 h-9 text-sm" autocomplete="family-name" />
                                        <InputError class="mt-1 text-xs" :message="studentForm.errors.last_name" />
                                    </div>
                                </div>
                                <div>
                                    <Label for="student-middle-name" class="text-xs font-semibold">Middle name <span class="text-muted-foreground font-normal">(optional)</span></Label>
                                    <Input id="student-middle-name" v-model="studentForm.middle_name" class="mt-1 h-9 text-sm" autocomplete="additional-name" />
                                    <InputError class="mt-1 text-xs" :message="studentForm.errors.middle_name" />
                                </div>
                                <div>
                                    <Label for="student-photo" class="text-xs font-semibold block mb-1">Photo <span class="text-muted-foreground font-normal">(optional)</span></Label>
                                    <input
                                        id="student-photo"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="block w-full text-xs text-muted-foreground file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border file:border-border file:text-xs file:font-semibold file:bg-secondary file:text-foreground hover:file:bg-secondary/80"
                                        @change="studentForm.photo = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                    />
                                    <InputError class="mt-1 text-xs" :message="studentForm.errors.photo" />
                                </div>
                            </div>
                            <InputError class="mt-2 text-xs" :message="studentForm.errors.seat_id" />
                            <Button type="submit" class="ink-button !h-9 !w-full mt-4" :disabled="studentForm.processing">
                                <UserPlus class="size-4" />
                                <span>{{ studentForm.processing ? 'Adding...' : 'Add student' }}</span>
                            </Button>
                        </form>

                        <!-- Roster Section -->
                        <div class="paper-card p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Users class="size-4 text-primary" />
                                    <h3 class="text-base font-bold">Class roster</h3>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="`/sections/${section.id}/roster/print`"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline"
                                    >
                                        <Printer class="size-3" /> Print
                                    </Link>
                                    <span class="badge-primary font-bold">{{ section.students.length }} enrolled</span>
                                </div>
                            </div>

                            <form
                                class="mt-4 rounded-xl border border-dashed border-border/80 bg-secondary/30 p-3.5"
                                @submit.prevent="importForm.post(`/sections/${section.id}/students-import`, { forceFormData: true })"
                            >
                                <label class="block text-xs font-bold text-foreground">Import roster CSV</label>
                                <input
                                    type="file"
                                    accept=".csv,text/csv"
                                    class="mt-2 block w-full text-xs text-muted-foreground file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border file:border-border file:text-xs file:font-medium file:bg-secondary hover:file:bg-secondary/80"
                                    @change="importForm.roster = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                />
                                <InputError class="mt-1 text-xs" :message="importForm.errors.roster" />
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="mt-2.5 w-full text-xs font-semibold rounded-lg"
                                    :disabled="!importForm.roster || importForm.processing"
                                >
                                    <Upload class="size-3 mr-1.5" /> Upload CSV
                                </Button>
                            </form>

                            <div class="mt-4 max-h-80 overflow-y-auto pr-1">
                                <div class="space-y-1">
                                    <button
                                        v-for="student in seatedStudents"
                                        :key="student.id"
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm font-medium transition-colors hover:bg-secondary group"
                                        @click="selectedStudent = student"
                                    >
                                        <span class="truncate group-hover:text-primary transition-colors">{{ student.full_name }}</span>
                                        <span class="rounded-md bg-secondary px-2 py-0.5 font-mono text-[10px] font-bold text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary transition-colors">
                                            {{ student.seat.label }}
                                        </span>
                                    </button>
                                    <p v-if="!seatedStudents.length" class="px-3 py-3 text-xs text-muted-foreground text-center">
                                        No students have chosen a chair yet.
                                    </p>
                                </div>

                                <div v-if="unseatedStudents.length" class="mt-4 border-t border-dashed border-border/80 pt-3">
                                    <p class="mb-1.5 px-3 text-[11px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                                        Without chair ({{ unseatedStudents.length }})
                                    </p>
                                    <button
                                        v-for="student in unseatedStudents"
                                        :key="student.id"
                                        type="button"
                                        class="block w-full truncate rounded-xl px-3 py-1.5 text-left text-xs font-medium hover:bg-secondary transition-colors"
                                        @click="selectedStudent = student"
                                    >
                                        {{ student.full_name }}
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
                class="fixed inset-0 z-50 grid place-items-center bg-zinc-950/70 p-4 backdrop-blur-md"
                @click.self="showQr = false"
            >
                <section class="paper-card w-full max-w-md p-7 text-center shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                    <button
                        class="ml-auto grid size-8 place-items-center rounded-full text-muted-foreground hover:bg-secondary hover:text-foreground transition-colors"
                        @click="showQr = false"
                    >
                        <X class="size-4.5" />
                    </button>
                    <span class="badge-primary">Student enrollment</span>
                    <h2 class="mt-2 text-2xl font-extrabold tracking-tight">Scan & Claim Chair</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Students scan this QR code to view the live floor and pick their seat.</p>

                    <div class="my-5 rounded-2xl border border-border/80 bg-white p-4 shadow-sm inline-block mx-auto">
                        <img :src="qrDataUrl" alt="Enrollment QR code" class="size-60 rounded-xl" />
                    </div>

                    <p class="break-all rounded-xl bg-secondary/80 p-3 font-mono text-[11px] text-muted-foreground border border-border/50">
                        {{ join_url }}
                    </p>

                    <div class="mt-4 flex gap-2">
                        <Button variant="outline" class="flex-1 rounded-xl text-xs font-semibold" @click="copyLink">
                            <Check v-if="copied" class="size-3.5 mr-1.5 text-emerald-600" />
                            <Copy v-else class="size-3.5 mr-1.5" />
                            <span>{{ copied ? 'Copied link' : 'Copy link' }}</span>
                        </Button>
                        <Button
                            variant="outline"
                            class="flex-1 rounded-xl text-xs font-semibold"
                            @click="router.patch(`/sections/${section.id}/enrollment`)"
                        >
                            <DoorOpen class="size-3.5 mr-1.5" />
                            <span>{{ section.enrollment_open ? 'Close' : 'Open' }}</span>
                        </Button>
                    </div>

                    <div class="mt-2 flex justify-center gap-2">
                        <a
                            v-if="qrDataUrl"
                            :href="qrDataUrl"
                            :download="`${section.subject_code}-${section.name}-enrollment.png`"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold hover:bg-secondary transition-colors"
                        >
                            <Download class="size-3.5" /> Download PNG
                        </a>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold hover:bg-secondary transition-colors"
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
                        <RefreshCw class="size-3 mr-1.5" /> Invalidate & create new link
                    </Button>

                    <p class="mt-3 text-xs font-bold" :class="section.enrollment_open ? 'text-primary' : 'text-rose-600'">
                        Enrollment is {{ section.enrollment_open ? 'OPEN' : 'CLOSED' }}
                    </p>
                </section>
            </div>

            <!-- Student Detail Drawer -->
            <div
                v-if="selectedStudent"
                class="fixed inset-0 z-40 bg-zinc-950/50 backdrop-blur-xs"
                @click.self="selectedStudent = null"
            >
                <aside class="ml-auto flex h-full w-full max-w-md flex-col border-l border-border bg-card p-6 text-card-foreground shadow-2xl animate-in slide-in-from-right duration-200">
                    <button
                        class="ml-auto grid size-8 place-items-center rounded-full text-muted-foreground hover:bg-secondary hover:text-foreground transition-colors"
                        @click="selectedStudent = null"
                    >
                        <X class="size-4.5" />
                    </button>

                    <div class="mt-4 flex items-center gap-4">
                        <img
                            v-if="selectedStudent.photo_url"
                            :src="selectedStudent.photo_url"
                            alt=""
                            class="size-20 rounded-2xl object-cover border border-border shadow-sm"
                        />
                        <div
                            v-else
                            class="grid size-20 place-items-center rounded-2xl bg-primary/10 font-bold text-3xl text-primary border border-primary/20"
                        >
                            {{ selectedStudent.first_name[0] }}{{ selectedStudent.last_name[0] }}
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold tracking-tight">{{ selectedStudent.full_name }}</h2>
                            <p class="font-mono text-xs text-muted-foreground">{{ selectedStudent.student_number }}</p>
                        </div>
                    </div>

                    <dl class="mt-8 grid grid-cols-2 gap-4 border-y border-dashed border-border py-5">
                        <div>
                            <dt class="text-xs text-muted-foreground font-semibold">Current chair</dt>
                            <dd class="mt-1 font-mono text-sm font-bold text-primary">{{ selectedStudent.seat?.label || 'Unseated' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground font-semibold">Section</dt>
                            <dd class="mt-1 text-sm font-bold">{{ section.name }}</dd>
                        </div>
                    </dl>

                    <div class="mt-6">
                        <Label class="text-xs font-semibold">Move to another chair</Label>
                        <select
                            class="mt-2 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary"
                            :value="selectedStudent.seat?.id || ''"
                            @change="moveStudent(selectedStudent, ($event.target as HTMLSelectElement).value)"
                        >
                            <option value="">Leave unseated</option>
                            <option v-if="selectedStudent.seat" :value="selectedStudent.seat.id">{{ selectedStudent.seat.label }} (current)</option>
                            <option v-for="seat in availableSeats" :key="seat.id" :value="seat.id">{{ seat.label }}</option>
                        </select>
                    </div>

                    <Button
                        variant="outline"
                        class="mt-auto border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-900/50 dark:hover:bg-rose-950/30 rounded-xl"
                        @click="removeStudent(selectedStudent)"
                    >
                        <Trash2 class="size-4 mr-2" /> Remove from roster
                    </Button>
                </aside>
            </div>
        </main>
    </AppLayout>
</template>
