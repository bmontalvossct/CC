export type ModelProgress = { status: string; file?: string; progress?: number; loaded?: number; total?: number };
type Waiting = { resolve: (text: string) => void; reject: (error: Error) => void; timer: ReturnType<typeof setTimeout> };
let worker: Worker | undefined;
let nextId = 0;
let ready = false;
const waiting = new Map<number, Waiting>();
const listeners = new Set<(progress: ModelProgress) => void>();

function stopWorker(message: string) {
    worker?.terminate();
    worker = undefined;
    ready = false;
    waiting.forEach((request) => {
        clearTimeout(request.timer);
        request.reject(new Error(message));
    });
    waiting.clear();
}

function request(type: 'load' | 'transcribe', audio?: Float32Array, language = 'english'): Promise<string> {
    if (!worker) {
        worker = new Worker(new URL('../workers/whisper.worker.ts', import.meta.url), { type: 'module' });
        worker.onmessage = ({ data }) => {
            if (data.type === 'progress') {
                listeners.forEach((listener) => listener(data.progress));
                return;
            }
            const entry = waiting.get(data.id);
            if (!entry) return;
            waiting.delete(data.id);
            clearTimeout(entry.timer);
            if (data.type === 'error') entry.reject(new Error(data.message));
            else {
                if (data.type === 'ready') ready = true;
                entry.resolve(data.text ?? '');
            }
        };
        worker.onerror = () => stopWorker('Whisper could not start. Check that the speech model can download, then retry.');
    }
    const id = ++nextId;
    return new Promise((resolve, reject) => {
        const timer = setTimeout(
            () => stopWorker('Whisper took too long. Check your connection for the first model download, then retry.'),
            type === 'load' ? 300000 : 90000,
        );
        waiting.set(id, { resolve, reject, timer });
        worker!.postMessage({ id, type, audio, language });
    });
}

export async function prepareWhisper(onProgress: (progress: ModelProgress) => void): Promise<void> {
    if (ready) return;
    listeners.add(onProgress);
    try {
        await request('load');
    } finally {
        listeners.delete(onProgress);
    }
}

export function transcribeAudio(audio: Float32Array, language: string): Promise<string> {
    return request('transcribe', audio, language);
}

export function releaseWhisper(): void {
    stopWorker('Voice entry closed.');
}
