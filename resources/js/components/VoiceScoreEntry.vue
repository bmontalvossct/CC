<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Microphone, microphoneError } from '@/lib/microphone';
import { joinAudio, resample, rms, SpeechSegmenter, wavBlob } from '@/lib/voiceAudio';
import { matchStudents, parseScoreCommand, type ScoreAction, type VoiceStudent } from '@/lib/voiceScores';
import { prepareWhisper, releaseWhisper, transcribeAudio, type ModelProgress } from '@/lib/whisperClient';
import { Check, Keyboard, Mic, MicOff, RotateCcw, SkipForward, TriangleAlert } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    students: VoiceStudent[];
    record: (id: number, action: Exclude<ScoreAction, { type: 'pass' }>) => Promise<boolean>;
    maxPoints: number;
    disabled?: boolean;
    assessmentTitle?: string;
    saveError?: string;
}>();
const emit = defineEmits<{ current: [id: number | null]; active: [value: boolean] }>();
const supported = typeof window !== 'undefined' && window.isSecureContext && !!navigator.mediaDevices?.getUserMedia;
const mode = ref('ordered');
const startId = ref<number | null>(null);
const active = ref(false);
const listening = ref(false);
const busy = ref(false);
const currentId = ref<number | null>(null);
const heard = ref('');
const message = ref('Choose a mode, then start the microphone.');
const failed = ref(false);
const focusOpen = ref(false);
const manualOpen = ref(false);
const manualScore = ref<string | number>('');
const manualInput = ref<HTMLInputElement>();
const candidates = ref<VoiceStudent[]>([]);
const lastSaved = ref('');
const finished = ref(false);
const ordered = computed(() =>
    [...props.students].sort(
        (a, b) =>
            a.last_name.localeCompare(b.last_name, 'en', { sensitivity: 'base' }) ||
            a.first_name.localeCompare(b.first_name, 'en', { sensitivity: 'base' }) ||
            a.id - b.id,
    ),
);
const current = computed(() => props.students.find((student) => student.id === currentId.value));
const microphoneId = ref('');
const devices = ref<MediaDeviceInfo[]>([]);
const language = ref('english');
const level = ref(0);
const preparing = ref(false);
const transcribing = ref(false);
const modelPercent = ref(0);
const testing = ref(false);
const testingTranscript = ref(false);
const testMessage = ref('');
const testTranscript = ref('');
const playbackUrl = ref('');
let microphone: Microphone | undefined;
let testMicrophoneInput: Microphone | undefined;
let testAudio: Float32Array | undefined;
let testPeak = 0;
let testRun = 0;
let testTimer: ReturnType<typeof setTimeout> | undefined;
let idleTimer: ReturnType<typeof setTimeout> | undefined;
let generation = 0;
const segmenter = new SpeechSegmenter();
const progressFiles = new Map<string, { loaded: number; total: number }>();

async function refreshDevices() {
    try {
        devices.value = (await navigator.mediaDevices.enumerateDevices()).filter(
            (device) => device.kind === 'audioinput' && device.deviceId && !['default', 'communications'].includes(device.deviceId),
        );
    } catch {
        /* Permission errors are shown when opening the microphone. */
    }
}
function onModelProgress(progress: ModelProgress) {
    if (progress.file && progress.total) progressFiles.set(progress.file, { loaded: progress.loaded ?? 0, total: progress.total });
    const files = [...progressFiles.values()];
    modelPercent.value = files.length
        ? Math.min(99, Math.round((files.reduce((sum, file) => sum + file.loaded, 0) / files.reduce((sum, file) => sum + file.total, 0)) * 100))
        : 0;
}
function stopTest() {
    ++testRun;
    clearTimeout(testTimer);
    testMicrophoneInput?.close();
    testMicrophoneInput = undefined;
    testing.value = false;
    if (!active.value) level.value = 0;
}
async function testMicrophone() {
    if (active.value || testing.value || testingTranscript.value) return;
    stopTest();
    const run = testRun;
    testMessage.value = 'Recording a five-second microphone test. Say ?eighteen?, ?pass?, or ?absent?. No scores will be saved.';
    testTranscript.value = '';
    testAudio = undefined;
    testPeak = 0;
    if (playbackUrl.value) URL.revokeObjectURL(playbackUrl.value);
    playbackUrl.value = '';
    testing.value = true;
    const chunks: Float32Array[] = [];
    let sampleRate = 16000;
    testMicrophoneInput = new Microphone();
    try {
        await testMicrophoneInput.open(microphoneId.value, (audio, rate) => {
            if (run !== testRun) return;
            chunks.push(audio);
            sampleRate = rate;
            const volume = rms(audio);
            testPeak = Math.max(testPeak, volume);
            level.value = Math.min(100, Math.round(volume * 800));
        });
        if (run !== testRun) return;
        void refreshDevices();
        testTimer = setTimeout(() => {
            if (run !== testRun) return;
            stopTest();
            testAudio = resample(joinAudio(chunks), sampleRate);
            if (testAudio.length) playbackUrl.value = URL.createObjectURL(wavBlob(testAudio, 16000));
            testMessage.value =
                testPeak >= 0.008
                    ? 'Sound detected. Play the recording to check clarity, then transcribe the test. Test recordings never save a score.'
                    : 'No usable sound detected. Select the correct microphone and check its volume, mute switch, and browser permission.';
        }, 5000);
    } catch (error) {
        if (run === testRun) {
            stopTest();
            testMessage.value = microphoneError(error);
        }
    }
}
async function transcribeTest() {
    if (!testAudio || testPeak < 0.008 || testing.value || testingTranscript.value || active.value) return;
    testingTranscript.value = true;
    testTranscript.value = '';
    const run = testRun;
    testMessage.value = 'Preparing Whisper. First use downloads the speech model; your recording stays on this device.';
    try {
        await prepareWhisper(onModelProgress);
        if (run !== testRun) return;
        testMessage.value = 'Transcribing the test on this device?';
        const text = await transcribeAudio(testAudio, language.value);
        if (run !== testRun) return;
        testTranscript.value = text || 'No clear words were recognized.';
        testMessage.value = 'Test finished. No scores were changed.';
    } catch (error) {
        if (run === testRun) testMessage.value = error instanceof Error ? error.message : 'Local transcription failed. Retry the model download.';
    } finally {
        testingTranscript.value = false;
    }
}

function selectCurrent(id: number | null) {
    currentId.value = id;
    if (mode.value === 'ordered') startId.value = id;
    emit('current', id);
}
function stop(text = 'Microphone stopped.') {
    active.value = false;
    listening.value = false;
    preparing.value = false;
    transcribing.value = false;
    generation++;
    clearTimeout(idleTimer);
    segmenter.reset();
    microphone?.close();
    microphone = undefined;
    level.value = 0;
    emit('active', false);
    message.value = text;
}
function fail(text: string) {
    failed.value = true;
    // Discard queued phrases after an uncertain or failed entry so scores cannot shift students.
    stop(text);
}
function setFocusOpen(open: boolean) {
    focusOpen.value = open;
    if (!open) stop();
}
function chooseStudent(event: Event) {
    const id = Number((event.target as HTMLSelectElement).value);
    selectCurrent(props.students.some((student) => student.id === id) ? id : null);
    finished.value = false;
    manualScore.value = '';
    message.value = current.value
        ? `Selected ${current.value.full_name}. Retry the microphone or type a score.`
        : 'Select the student for this entry.';
}
async function showManual() {
    if (active.value) stop('Microphone paused. Type the score for the selected student.');
    manualOpen.value = true;
    await nextTick();
    manualInput.value?.focus();
}
function skipCurrent() {
    if (!current.value || busy.value || active.value) return;
    failed.value = false;
    candidates.value = [];
    manualScore.value = '';
    message.value = `Skipped ${current.value.full_name}; their entry is unchanged.`;
    advance(current.value);
}
async function submitManual() {
    if (busy.value || active.value) return;
    if (!current.value) return fail('Select a student before saving a score.');
    const raw = String(manualScore.value).trim();
    const score = Number(raw);
    if (raw === '' || !Number.isSafeInteger(score) || score < 0 || score > props.maxPoints) {
        return fail(`Enter a whole-number score from 0 to ${props.maxPoints}. This entry has not been saved.`);
    }
    failed.value = false;
    candidates.value = [];
    if (await persist(current.value, { type: 'score', score }, generation)) manualScore.value = '';
}
function advance(student: VoiceStudent) {
    if (mode.value === 'named') {
        selectCurrent(null);
        return;
    }
    const next = ordered.value[ordered.value.findIndex((item) => item.id === student.id) + 1];
    if (next) selectCurrent(next.id);
    else {
        selectCurrent(null);
        finished.value = true;
        stop('List finished. All students in this run have been processed.');
    }
}
async function process(transcript: string, run: number) {
    if (!active.value || run !== generation) return;
    heard.value = transcript;
    failed.value = false;
    candidates.value = [];
    if (!transcript.trim()) return fail('No clear speech was detected. This entry has not been saved. Retry or type the score.');
    if (/^(stop|stop recording)$/i.test(transcript.trim().replace(/[.!?]+$/, ''))) return stop();
    let { name, action } = parseScoreCommand(transcript);
    // A full name may itself end with a number word (for example, June Ten).
    const nameOnly = matchStudents(transcript, props.students);
    if (nameOnly.length && (!action || name)) {
        name = transcript;
        action = null;
    }
    let student = current.value;
    if (name) {
        const matches = matchStudents(name, props.students);
        if (matches.length !== 1) {
            candidates.value = matches;
            return fail(
                matches.length
                    ? 'More than one student matches. Choose the student below, then retry or type their score.'
                    : 'Name or score not recognized. This entry has not been saved. Retry, select a student, or type the score.',
            );
        }
        if (mode.value === 'ordered' && student && student.id !== matches[0].id)
            return fail(`Expected ${student.full_name}. Choose a different starting student to continue.`);
        student = matches[0];
        selectCurrent(student.id);
    }
    if (!student) return fail('Say the student’s last and first name before the score.');
    if (!action) {
        if (nameOnly.length === 1) message.value = `Selected ${student.full_name}. Say the score, “pass”, or “absent”.`;
        else fail('Score not recognized. This entry has not been saved. Retry or type a whole-number score.');
        return;
    }
    if (action.type === 'score' && (!Number.isSafeInteger(action.score) || action.score < 0 || action.score > props.maxPoints)) {
        return fail(`Use a whole-number score from 0 to ${props.maxPoints}. No score was changed.`);
    }
    if (action.type === 'pass') {
        message.value = `Skipped ${student.full_name}; their entry is unchanged.`;
        advance(student);
        return;
    }
    await persist(student, action, run);
}
async function persist(student: VoiceStudent, action: Exclude<ScoreAction, { type: 'pass' }>, run: number) {
    busy.value = true;
    message.value = `Saving ${student.full_name}…`;
    try {
        const saved = await props.record(student.id, action);
        if (!focusOpen.value || generation !== run) return false;
        if (!saved) {
            fail(`Could not save ${student.full_name}. Check the error below, then retry this student.`);
            return false;
        }
        lastSaved.value = `${student.full_name}: ${action.type === 'absent' ? 'Absent' : action.score}`;
        message.value = `Saved ${lastSaved.value}.`;
        advance(student);
        return true;
    } catch {
        if (focusOpen.value && generation === run) fail('Could not save. Check your connection and retry this student.');
        return false;
    } finally {
        busy.value = false;
    }
}
function armIdleTimer(run: number) {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => {
        if (active.value && generation === run && !transcribing.value)
            fail('No usable speech was captured. Open Microphone & speech setup and test your selected microphone. No score was saved.');
    }, 15000);
}
async function recognizePhrase(audio: Float32Array, run: number) {
    transcribing.value = true;
    listening.value = false;
    clearTimeout(idleTimer);
    message.value = 'Transcribing this phrase on your device. Wait for the result before speaking again.';
    try {
        const transcript = await transcribeAudio(audio, language.value);
        if (!active.value || run !== generation) return;
        await process(transcript, run);
        if (active.value && run === generation) {
            transcribing.value = false;
            listening.value = true;
            segmenter.reset();
            armIdleTimer(run);
        }
    } catch (error) {
        if (active.value && run === generation) fail(error instanceof Error ? error.message : 'Local transcription failed. Retry this student.');
    }
}
async function start(resume = false) {
    if (busy.value || active.value || testingTranscript.value || props.disabled || !props.students.length) return;
    stopTest();
    focusOpen.value = true;
    if (!resume) selectCurrent(mode.value === 'ordered' ? (startId.value ?? ordered.value[0]?.id ?? null) : null);
    finished.value = false;
    manualOpen.value = false;
    candidates.value = [];
    if (!supported) return fail('Microphone capture requires HTTPS or localhost in a browser. You can still select a student and type a score.');
    failed.value = false;
    if (!resume) heard.value = '';
    const run = ++generation;
    active.value = true;
    preparing.value = true;
    emit('active', true);
    message.value = 'Opening your selected microphone.';
    segmenter.reset();
    microphone = new Microphone();
    try {
        await microphone.open(microphoneId.value, (audio, rate) => {
            if (!active.value || run !== generation) return;
            const volume = rms(audio);
            level.value = Math.min(100, Math.round(volume * 800));
            if (preparing.value || transcribing.value || busy.value) return;
            if (volume >= 0.008) armIdleTimer(run);
            const result = segmenter.push(audio, rate);
            if (result.tooLong) return fail('The phrase was too long. Say one student and one score, then pause. This entry has not been saved.');
            if (result.audio) void recognizePhrase(result.audio, run);
        });
        if (!active.value || run !== generation) return;
        void refreshDevices();
        message.value = 'Microphone connected. Preparing Whisper; the first use downloads the speech model. Wait for Listening.';
        await prepareWhisper(onModelProgress);
        if (!active.value || run !== generation) return;
        preparing.value = false;
        listening.value = true;
        message.value = current.value
            ? `Ready for ${current.value.full_name}. Say the score, then pause.`
            : 'Say the student?s last and first name, then their score. Pause after the phrase.';
        armIdleTimer(run);
    } catch (error) {
        if (run === generation && active.value) fail(microphoneError(error));
    }
}
onMounted(() => {
    void refreshDevices();
    navigator.mediaDevices?.addEventListener('devicechange', refreshDevices);
});
defineExpose({ start });
onBeforeUnmount(() => {
    stop();
    stopTest();
    releaseWhisper();
    navigator.mediaDevices?.removeEventListener('devicechange', refreshDevices);
    if (playbackUrl.value) URL.revokeObjectURL(playbackUrl.value);
});
</script>

<template>
    <Dialog :open="focusOpen" @update:open="setFocusOpen">
        <section aria-labelledby="voice-title" class="h-full min-w-0 rounded-2xl border border-border bg-card p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="voice-title" class="flex items-center gap-2 text-lg font-semibold"><Mic class="size-5" /> Voice score entry</h2>
                    <p class="mt-1 text-[11px] leading-4 text-muted-foreground">One student per phrase. Wait for “Saved” before continuing.</p>
                </div>
                <DialogTrigger as-child
                    ><button
                        type="button"
                        :disabled="disabled || busy || testingTranscript || !students.length"
                        class="inline-flex min-h-9 items-center gap-2 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50"
                        @click="start()"
                    >
                        <Mic class="size-4" /> Start voice entry
                    </button></DialogTrigger
                >
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <label class="text-xs font-bold uppercase tracking-wider"
                    >Entry mode
                    <select
                        v-model="mode"
                        :disabled="active || busy"
                        class="mt-1.5 min-h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-xs font-normal normal-case tracking-normal focus:border-ring focus:outline-none focus:ring-1 focus:ring-ring"
                    >
                        <option value="ordered">In last-name order</option>
                        <option value="named">Say a student’s name</option>
                    </select>
                </label>
                <label v-if="mode === 'ordered'" class="text-xs font-bold uppercase tracking-wider"
                    >Start with
                    <select
                        v-model="startId"
                        :disabled="active || busy"
                        class="mt-1.5 min-h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-xs font-normal normal-case tracking-normal focus:border-ring focus:outline-none focus:ring-1 focus:ring-ring"
                    >
                        <option :value="null">First student</option>
                        <option v-for="student in ordered" :key="student.id" :value="student.id">{{ student.full_name }}</option>
                    </select>
                </label>
            </div>
            <p class="mt-3 text-xs italic leading-5 text-muted-foreground">
                {{
                    mode === 'ordered'
                        ? 'Say “18”, “pass”, or “absent” for the highlighted student. Each entry moves to the next last name.'
                        : 'Say “Dela Cruz, Ana, score 18”, or say the name first and the score in the next phrase. A unique last name also works.'
                }}
                “Pass” skips without changing the entry. “Absent” records absence for this activity only. Say “stop” to finish.
            </p>
            <details class="mt-3 border-t border-border pt-3">
                <summary class="cursor-pointer text-xs font-semibold text-foreground">Microphone &amp; speech setup</summary>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="text-xs font-medium"
                        >Input microphone
                        <select
                            v-model="microphoneId"
                            :disabled="active || testing || testingTranscript"
                            class="mt-1.5 min-h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-xs"
                        >
                            <option value="">System default microphone</option>
                            <option v-for="(device, index) in devices" :key="device.deviceId" :value="device.deviceId">
                                {{ device.label || `Microphone ${index + 1}` }}
                            </option>
                        </select>
                    </label>
                    <label class="text-xs font-medium"
                        >Recognition language
                        <select
                            v-model="language"
                            :disabled="active || testingTranscript"
                            class="mt-1.5 min-h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-xs"
                        >
                            <option value="english">English (recommended for scores)</option>
                            <option value="auto">Detect language automatically</option>
                        </select>
                    </label>
                </div>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Say score numbers and commands in English. First allow microphone access so device names can appear.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        :disabled="active || testing || testingTranscript"
                        class="rounded-lg border border-input bg-background px-3 py-2 text-xs font-semibold disabled:opacity-50"
                        @click="testMicrophone"
                    >
                        {{ testing ? 'Recording test…' : 'Test microphone (5 seconds)' }}
                    </button>
                    <button
                        v-if="playbackUrl"
                        type="button"
                        :disabled="active || testing || testingTranscript"
                        class="rounded-lg border border-input bg-background px-3 py-2 text-xs font-semibold disabled:opacity-50"
                        @click="transcribeTest"
                    >
                        {{ testingTranscript ? 'Preparing / transcribing…' : 'Transcribe test (no scores saved)' }}
                    </button>
                </div>
                <meter :value="level" min="0" max="100" aria-label="Microphone test level" class="mt-3 block h-3 w-full"></meter>
                <p v-if="testMessage" role="status" class="mt-2 text-xs leading-5 text-muted-foreground">{{ testMessage }}</p>
                <p v-if="testingTranscript && modelPercent" class="mt-1 text-xs text-muted-foreground">Preparing speech model: {{ modelPercent }}%</p>
                <audio v-if="playbackUrl" :src="playbackUrl" controls class="mt-3 h-9 w-full" aria-label="Play microphone test recording"></audio>
                <p v-if="testTranscript" class="mt-3 text-sm"><strong>Test transcript:</strong> {{ testTranscript }}</p>
            </details>
            <p v-if="lastSaved" class="mt-2 text-xs font-semibold text-foreground">Last saved: {{ lastSaved }}</p>
            <p v-if="!supported" class="mt-2 text-xs text-muted-foreground">
                Microphone capture is unavailable here. Open ClassCheck over HTTPS or localhost in a browser. You can still type scores below.
            </p>
            <p class="mt-2 text-[10px] leading-4 text-muted-foreground">
                Whisper runs on this device. First use downloads and caches the speech model. Audio is not uploaded; microphone tests never save
                scores.
            </p>
        </section>
        <DialogContent
            class="meta-theme max-h-[90dvh] w-[calc(100%-2rem)] max-w-2xl gap-0 overflow-y-auto rounded-2xl border-border bg-card p-0 text-foreground motion-reduce:animate-none"
        >
            <div class="border-b border-border p-6 pr-12 sm:p-8 sm:pr-12">
                <p class="mb-2 font-mono text-[10px] font-bold uppercase tracking-[.2em] text-foreground">
                    Focused recording · {{ mode === 'ordered' ? 'Last-name order' : 'By student name' }}
                </p>
                <DialogTitle class="text-2xl font-semibold sm:text-3xl">{{ assessmentTitle || 'Record scores' }}</DialogTitle>
                <DialogDescription class="mt-2 text-sm text-foreground"
                    >One student at a time. Wait for the saved confirmation before the next phrase.</DialogDescription
                >
            </div>
            <div class="p-6 sm:p-8">
                <div role="status" aria-live="polite" aria-atomic="true" class="rounded-2xl bg-primary p-6 text-primary-foreground sm:p-8">
                    <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-foreground">
                        <TriangleAlert v-if="failed" class="size-4" /><Mic v-else-if="listening" class="size-4" /><MicOff v-else class="size-4" />
                        {{
                            busy
                                ? 'Saving score'
                                : preparing
                                  ? 'Preparing speech model'
                                  : transcribing
                                    ? 'Transcribing speech'
                                    : failed
                                      ? 'Paused — check this entry'
                                      : listening
                                        ? 'Listening'
                                        : active
                                          ? 'Connecting microphone'
                                          : finished
                                            ? 'List finished'
                                            : 'Microphone paused'
                        }}
                    </p>
                    <p v-if="current" class="mt-5 break-words text-4xl font-semibold leading-tight sm:text-5xl">{{ current.full_name }}</p>
                    <p v-else class="mt-5 text-3xl font-semibold">{{ finished ? 'You have reached the end.' : 'Who is this score for?' }}</p>
                    <p v-if="current" class="mt-3 text-sm text-foreground">
                        Student {{ ordered.findIndex((student) => student.id === currentId) + 1 }} of {{ students.length }} · Maximum
                        {{ maxPoints }} points
                    </p>
                    <p class="mt-5 border-t border-white/15 pt-4 text-sm leading-6">{{ message }}</p>
                </div>
                <div class="mt-4 rounded-xl border border-border p-4">
                    <div class="flex items-center justify-between gap-2 text-xs font-semibold">
                        <span>Microphone level</span><span>{{ level > 1 ? 'Sound detected' : 'No sound signal' }}</span>
                    </div>
                    <meter :value="level" min="0" max="100" aria-label="Microphone level" class="mt-2 block h-3 w-full"></meter>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{
                            preparing
                                ? `Preparing local Whisper${modelPercent ? `: ${modelPercent}%` : '…'}. Speak after “Listening”.`
                                : transcribing
                                  ? 'Processing this phrase. Wait for the result.'
                                  : 'Say one entry, then pause. Whisper transcribes on this device.'
                        }}
                    </p>
                    <progress
                        v-if="preparing"
                        :value="modelPercent || undefined"
                        max="100"
                        aria-label="Speech model preparation"
                        class="mt-2 h-2 w-full"
                    ></progress>
                </div>
                <div class="mt-4 rounded-xl border border-border p-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-foreground">What was heard</p>
                    <p class="mt-1 break-words text-base">
                        {{ heard ? `“${heard}”` : failed ? 'No clear transcript was received.' : 'Waiting for a clear phrase…' }}
                    </p>
                </div>
                <p v-if="failed && saveError" role="alert" class="mt-4 text-sm font-semibold text-red-700 dark:text-red-300">{{ saveError }}</p>
                <p v-if="lastSaved" class="mt-4 flex items-start gap-2 text-sm font-semibold text-foreground">
                    <Check class="mt-0.5 size-4 shrink-0" />Last saved: {{ lastSaved }}
                </p>
                <div v-if="!active && !busy && !finished" class="mt-5">
                    <label class="text-xs font-bold uppercase tracking-wider"
                        >{{ candidates.length ? 'Choose the matching student' : 'Student for this entry' }}
                        <select
                            :value="currentId ?? ''"
                            class="mt-1.5 min-h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-xs font-normal normal-case tracking-normal focus:border-ring focus:outline-none focus:ring-1 focus:ring-ring"
                            @change="chooseStudent"
                        >
                            <option value="" disabled>Select a student</option>
                            <option v-for="student in candidates.length ? candidates : ordered" :key="student.id" :value="student.id">
                                {{ student.full_name }}
                            </option>
                        </select>
                    </label>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button
                        v-if="active"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white"
                        @click="stop('Microphone paused. This student stays selected until you continue or skip.')"
                    >
                        <MicOff class="size-4" />Pause microphone
                    </button>
                    <button
                        v-else
                        type="button"
                        :disabled="!supported || busy || disabled"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                        @click="start(!finished)"
                    >
                        <RotateCcw class="size-4" />{{ finished ? 'Start another run' : 'Retry microphone' }}
                    </button>
                    <button
                        v-if="!finished"
                        type="button"
                        :disabled="busy"
                        class="inline-flex items-center gap-2 rounded-xl border border-border px-4 py-3 text-sm font-semibold disabled:opacity-50"
                        @click="showManual"
                    >
                        <Keyboard class="size-4" />Type score
                    </button>
                    <button
                        v-if="!active && !finished"
                        type="button"
                        :disabled="!current || busy"
                        class="inline-flex items-center gap-2 rounded-xl border border-border px-4 py-3 text-sm font-semibold disabled:opacity-50"
                        @click="skipCurrent"
                    >
                        <SkipForward class="size-4" />Skip student
                    </button>
                </div>
                <form v-if="manualOpen && !finished" class="mt-5 rounded-xl bg-card p-4 dark:bg-white/10" @submit.prevent="submitManual">
                    <label class="text-xs font-bold uppercase tracking-wider"
                        >Score for {{ current?.full_name || 'the selected student' }}
                        <input
                            ref="manualInput"
                            v-model="manualScore"
                            type="number"
                            min="0"
                            :max="maxPoints"
                            step="1"
                            inputmode="numeric"
                            :disabled="busy || !current"
                            required
                            class="mt-2 w-full rounded-xl border-border bg-card text-2xl font-bold"
                        />
                    </label>
                    <button
                        type="submit"
                        :disabled="busy || !current"
                        class="mt-3 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {{ busy ? 'Saving…' : 'Save score' }}
                    </button>
                </form>
                <p class="mt-5 text-xs leading-5 text-foreground">
                    “Pass” skips without changing the entry. “Absent” records absence for this activity. Unrecognized audio pauses this screen. Close
                    or press Escape to release the microphone.
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
