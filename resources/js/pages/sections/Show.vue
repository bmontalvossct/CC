<script setup lang="ts">
import ClassroomSeatGrid from '@/components/ClassroomSeatGrid.vue';
import FloorPlanner from '@/components/FloorPlanner.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Copy, DoorOpen, Edit3, QrCode, RefreshCw, Trash2, UserPlus, Users, X } from 'lucide-vue-next';
import QRCode from 'qrcode';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ section: any; join_url: string }>();
const page = usePage<any>();
const selectedStudent = ref<any>(null);
const selectedSeatId = ref<number | null>(null);
const showQr = ref(false);
const qrDataUrl = ref('');
onMounted(async () => {
    qrDataUrl.value = await QRCode.toDataURL(props.join_url, { width: 640, margin: 2, color: { dark: '#1d1d1f', light: '#ffffff' } });
});
const printQr = () => {
    const popup = window.open('', '_blank', 'width=700,height=800');
    if (!popup) return;
    popup.document.write(
        `<title>Enrollment QR</title><body style="font-family:Arial,sans-serif;text-align:center;padding:40px"><h1>${props.section.subject_code} - ${props.section.name}</h1><img src="${qrDataUrl.value}" style="width:480px;max-width:100%"><p>${props.join_url}</p></body>`,
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
const copyLink = () => navigator.clipboard.writeText(props.join_url);
</script>

<template>
    <Head :title="`${section.subject_code} - ${section.name}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sections', href: '/sections' },
            { title: section.name, href: `/sections/${section.id}` },
        ]"
    >
        <main class="min-h-full bg-[#f5f5f7] p-4 md:p-8">
            <div class="mx-auto max-w-7xl">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-5 rounded-xl border border-[#bfdbfe] bg-[#f5f9ff] px-4 py-3 text-sm font-medium text-[#0066cc]"
                >
                    {{ page.props.flash.success }}
                </div>
                <header class="relative overflow-hidden rounded-3xl bg-[#1d1d1f] p-6 text-white shadow-2xl md:p-8">
                    <div
                        class="absolute inset-0 opacity-20 [background-image:linear-gradient(rgba(255,255,255,.12)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.12)_1px,transparent_1px)] [background-size:32px_32px]"
                    />
                    <div class="relative flex flex-col justify-between gap-7 lg:flex-row lg:items-end">
                        <div>
                            <p class="font-mono text-xs font-bold uppercase tracking-[.28em] text-[#2997ff]">
                                {{ section.subject_code }} / {{ section.academic_term.name }}
                            </p>
                            <h1 class="mt-2 font-serif text-4xl font-black md:text-5xl">{{ section.name }}</h1>
                            <p class="mt-2 text-[#e5e7eb]">
                                {{ section.subject_title }} <span v-if="section.room"> - {{ section.room }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button as-child variant="outline" class="border-white/20 bg-white/5 text-white hover:bg-white/10"
                                ><Link :href="`/sections/${section.id}/attendance`">Attendance</Link></Button
                            ><Button as-child variant="outline" class="border-white/20 bg-white/5 text-white hover:bg-white/10"
                                ><Link :href="`/sections/${section.id}/assessments`">Activities & scores</Link></Button
                            ><Button as-child variant="outline" class="border-white/20 bg-white/5 text-white hover:bg-white/10"
                                ><Link :href="`/sections/${section.id}/edit`"><Edit3 class="mr-2 size-4" /> Details</Link></Button
                            ><Button class="bg-[#0071e3] text-[#1d1d1f] hover:bg-[#2997ff]" @click="showQr = true"
                                ><QrCode class="mr-2 size-4" /> Enrollment QR</Button
                            >
                        </div>
                    </div>
                </header>

                <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_330px]">
                    <section class="min-w-0 rounded-3xl border border-[#e5e7eb] bg-[#ffffff] p-5 shadow-[0_25px_80px_-60px_rgba(28,25,23,.8)] md:p-7">
                        <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p class="font-mono text-xs font-bold uppercase tracking-[.2em] text-[#0071e3]">Live seating chart</p>
                                <h2 class="mt-1 font-serif text-3xl font-bold">The classroom floor</h2>
                            </div>
                            <div class="flex items-center gap-4 text-xs text-[#86868b]">
                                <span class="flex items-center gap-1"><i class="size-3 rounded-sm bg-[#0071e3]" /> Assigned</span
                                ><span class="flex items-center gap-1"><i class="size-3 rounded-sm border border-[#86868b] bg-white" /> Free</span>
                            </div>
                        </div>
                        <div
                            class="mb-8 rounded-xl border-2 border-[#1d1d1f] bg-[#1d1d1f] py-3 text-center font-mono text-xs font-bold uppercase tracking-[.35em] text-[#f5f5f7]"
                        >
                            Teaching wall / board
                        </div>
                        <div
                            v-if="section.layout_blocks.length"
                            class="grid min-w-[620px] gap-8 overflow-x-auto pb-4"
                            :style="{
                                gridTemplateColumns: `repeat(${Math.max(...section.layout_blocks.map((block: any) => block.block_column))}, minmax(210px, 1fr))`,
                            }"
                        >
                            <ClassroomSeatGrid
                                v-for="block in section.layout_blocks"
                                :key="block.id"
                                :block="block"
                                :selected-seat-id="selectedSeatId"
                                @select-seat="selectedSeatId = selectedSeatId === $event ? null : $event"
                                @select-student="selectedStudent = $event"
                            />
                        </div>
                        <div v-else class="rounded-2xl border border-dashed border-[#e5e7eb] p-10 text-center">
                            <h3 class="mt-3 font-serif text-2xl font-bold">Set the room rows and columns</h3>
                            <p class="mt-2 text-sm text-[#86868b]">Use Room setup to create the chair grid, then drag aisles into the preview.</p>
                        </div>
                    </section>

                    <aside class="space-y-5">
                        <FloorPlanner :section-id="section.id" :initial-plan="initialFloorPlan" />

                        <form class="rounded-2xl border border-[#e5e7eb] bg-[#ffffff] p-5" @submit.prevent="addStudent">
                            <p class="text-xs font-bold uppercase tracking-wider text-[#0071e3]">Manual roster entry</p>
                            <div
                                class="mt-3 rounded-xl border px-3 py-2.5 text-sm"
                                :class="
                                    selectedSeat ? 'border-[#2997ff] bg-[#eaf4ff] text-[#1d1d1f]' : 'border-[#e5e7eb] bg-[#f5f5f7] text-[#6e6e73]'
                                "
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <span v-if="selectedSeat"
                                        ><strong>{{ selectedSeat.label }}</strong> is selected for this student.</span
                                    >
                                    <span v-else>No chair selected - this student will be added without a chair.</span>
                                    <button
                                        v-if="selectedSeat"
                                        type="button"
                                        class="shrink-0 text-xs font-bold text-[#0066cc] underline"
                                        @click="selectedSeatId = null"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-[#86868b]">Click any empty chair on the classroom floor to select it.</p>
                            <div class="mt-4 grid gap-3">
                                <div>
                                    <Label for="student-number">Student number</Label>
                                    <Input id="student-number" v-model="studentForm.student_number" class="mt-1" autocomplete="off" />
                                    <InputError class="mt-1" :message="studentForm.errors.student_number" />
                                </div>
                                <div>
                                    <Label for="student-first-name">First name</Label>
                                    <Input id="student-first-name" v-model="studentForm.first_name" class="mt-1" autocomplete="given-name" />
                                    <InputError class="mt-1" :message="studentForm.errors.first_name" />
                                </div>
                                <div>
                                    <Label for="student-middle-name">Middle name <span class="text-[#86868b]">(optional)</span></Label>
                                    <Input id="student-middle-name" v-model="studentForm.middle_name" class="mt-1" autocomplete="additional-name" />
                                    <InputError class="mt-1" :message="studentForm.errors.middle_name" />
                                </div>
                                <div>
                                    <Label for="student-last-name">Last name</Label>
                                    <Input id="student-last-name" v-model="studentForm.last_name" class="mt-1" autocomplete="family-name" />
                                    <InputError class="mt-1" :message="studentForm.errors.last_name" />
                                </div>
                                <div>
                                    <Label for="student-photo" class="mb-1 block">Photo <span class="text-[#86868b]">(optional)</span></Label>
                                    <input
                                        id="student-photo"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="block w-full text-xs"
                                        @change="studentForm.photo = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                    />
                                    <InputError class="mt-1" :message="studentForm.errors.photo" />
                                </div>
                            </div>
                            <InputError class="mt-2" :message="studentForm.errors.seat_id" />
                            <Button type="submit" class="mt-4 w-full bg-[#0071e3] text-white" :disabled="studentForm.processing">
                                <UserPlus class="mr-2 size-4" /> {{ studentForm.processing ? 'Adding student...' : 'Add student' }}
                            </Button>
                        </form>
                        <div class="rounded-2xl border border-[#e5e7eb] bg-[#ffffff] p-5">
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 font-semibold"><Users class="size-4 text-[#0071e3]" /> Roster</span
                                ><strong class="font-mono text-xl">{{ section.students.length }}</strong>
                            </div>
                            <form
                                class="mt-4 rounded-xl border border-dashed border-[#e5e7eb] p-3"
                                @submit.prevent="importForm.post(`/sections/${section.id}/students-import`, { forceFormData: true })"
                            >
                                <label class="block text-xs font-semibold">Import roster CSV</label
                                ><input
                                    type="file"
                                    accept=".csv,text/csv"
                                    class="mt-2 block w-full text-xs"
                                    @change="importForm.roster = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                /><InputError class="mt-1" :message="importForm.errors.roster" /><Button
                                    size="sm"
                                    variant="outline"
                                    class="mt-2 w-full"
                                    :disabled="!importForm.roster || importForm.processing"
                                    >Upload CSV</Button
                                >
                            </form>
                            <div class="mt-4 max-h-80 overflow-auto">
                                <div class="space-y-1">
                                    <button
                                        v-for="student in seatedStudents"
                                        :key="student.id"
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-[#f5f5f7]"
                                        @click="selectedStudent = student"
                                    >
                                        <span class="truncate">{{ student.full_name }}</span>
                                        <span class="text-[10px] text-[#86868b]">{{ student.seat.label }}</span>
                                    </button>
                                    <p v-if="!seatedStudents.length" class="px-3 py-2 text-xs text-[#86868b]">No students have a chair yet.</p>
                                </div>
                                <div v-if="unseatedStudents.length" class="mt-4 border-t border-dashed border-[#e5e7eb] pt-3">
                                    <p class="mb-1 px-3 text-xs font-bold uppercase tracking-wider text-[#86868b]">Without chair</p>
                                    <button
                                        v-for="student in unseatedStudents"
                                        :key="student.id"
                                        type="button"
                                        class="block w-full truncate rounded-lg px-3 py-2 text-left text-sm hover:bg-[#f5f5f7]"
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

            <div v-if="showQr" class="fixed inset-0 z-50 grid place-items-center bg-[#1d1d1f]/70 p-4 backdrop-blur-sm" @click.self="showQr = false">
                <section class="w-full max-w-md rounded-3xl bg-[#ffffff] p-6 text-center shadow-2xl">
                    <button class="ml-auto block rounded-full p-2 hover:bg-[#f5f5f7]" @click="showQr = false"><X class="size-5" /></button>
                    <p class="font-mono text-xs font-bold uppercase tracking-[.2em] text-[#0071e3]">Student enrollment</p>
                    <h2 class="mt-2 font-serif text-3xl font-bold">Scan. Pick a chair. Sit.</h2>
                    <img :src="qrDataUrl" alt="Enrollment QR code" class="mx-auto my-5 size-64 rounded-xl border bg-white p-3" />
                    <p class="break-all rounded-lg bg-[#f5f5f7] p-3 font-mono text-[10px]">{{ join_url }}</p>
                    <div class="mt-4 flex gap-2">
                        <Button variant="outline" class="flex-1" @click="copyLink"><Copy class="mr-2 size-4" /> Copy link</Button
                        ><Button variant="outline" class="flex-1" @click="router.patch(`/sections/${section.id}/enrollment`)"
                            ><DoorOpen class="mr-2 size-4" /> {{ section.enrollment_open ? 'Close' : 'Open' }}</Button
                        >
                    </div>
                    <div class="mt-2 flex justify-center gap-2">
                        <a
                            v-if="qrDataUrl"
                            :href="qrDataUrl"
                            :download="`${section.subject_code}-${section.name}-enrollment.png`"
                            class="rounded-md px-3 py-2 text-xs font-semibold hover:bg-[#f5f5f7]"
                            >Download PNG</a
                        ><button type="button" class="rounded-md px-3 py-2 text-xs font-semibold hover:bg-[#f5f5f7]" @click="printQr">
                            Print QR
                        </button>
                    </div>
                    <Button variant="ghost" class="mt-2 text-xs" @click="router.post(`/sections/${section.id}/enrollment-token`)"
                        ><RefreshCw class="mr-2 size-3" /> Invalidate and create new link</Button
                    >
                    <p class="mt-2 text-xs font-semibold" :class="section.enrollment_open ? 'text-[#0071e3]' : 'text-red-700'">
                        Enrollment is {{ section.enrollment_open ? 'OPEN' : 'CLOSED' }}
                    </p>
                </section>
            </div>

            <div v-if="selectedStudent" class="fixed inset-0 z-40 bg-[#1d1d1f]/40" @click.self="selectedStudent = null">
                <aside class="ml-auto flex h-full w-full max-w-md flex-col bg-[#ffffff] p-6 shadow-2xl">
                    <button class="ml-auto rounded-full p-2 hover:bg-[#f5f5f7]" @click="selectedStudent = null"><X class="size-5" /></button>
                    <div class="mt-4 flex items-center gap-4">
                        <img v-if="selectedStudent.photo_url" :src="selectedStudent.photo_url" alt="" class="size-20 rounded-2xl object-cover" />
                        <div v-else class="grid size-20 place-items-center rounded-2xl bg-[#eaf4ff] font-serif text-3xl font-bold text-[#0066cc]">
                            {{ selectedStudent.first_name[0] }}{{ selectedStudent.last_name[0] }}
                        </div>
                        <div>
                            <h2 class="font-serif text-2xl font-bold">{{ selectedStudent.full_name }}</h2>
                            <p class="font-mono text-xs text-[#86868b]">{{ selectedStudent.student_number }}</p>
                        </div>
                    </div>
                    <dl class="mt-8 grid grid-cols-2 gap-4 border-y border-dashed border-[#e5e7eb] py-5">
                        <div>
                            <dt class="text-xs text-[#86868b]">Current chair</dt>
                            <dd class="mt-1 font-semibold">{{ selectedStudent.seat?.label || 'Unseated' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#86868b]">Section</dt>
                            <dd class="mt-1 font-semibold">{{ section.name }}</dd>
                        </div>
                    </dl>
                    <div class="mt-6">
                        <Label>Move to another chair</Label
                        ><select
                            class="mt-2 w-full rounded-md border-[#e5e7eb]"
                            :value="selectedStudent.seat?.id || ''"
                            @change="moveStudent(selectedStudent, ($event.target as HTMLSelectElement).value)"
                        >
                            <option value="">Leave unseated</option>
                            <option v-if="selectedStudent.seat" :value="selectedStudent.seat.id">{{ selectedStudent.seat.label }} (current)</option>
                            <option v-for="seat in availableSeats" :key="seat.id" :value="seat.id">{{ seat.label }}</option>
                        </select>
                    </div>
                    <Button variant="outline" class="mt-auto border-red-200 text-red-700 hover:bg-red-50" @click="removeStudent(selectedStudent)"
                        ><Trash2 class="mr-2 size-4" /> Remove from roster</Button
                    >
                </aside>
            </div>
        </main>
    </AppLayout>
</template>
