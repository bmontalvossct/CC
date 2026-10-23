import { env, pipeline, type AutomaticSpeechRecognitionPipeline } from '@huggingface/transformers';

env.allowLocalModels = false;
if (env.backends.onnx.wasm) env.backends.onnx.wasm.numThreads = 1;
let model: Promise<AutomaticSpeechRecognitionPipeline> | undefined;

function load(): Promise<AutomaticSpeechRecognitionPipeline> {
    model ??= pipeline('automatic-speech-recognition', 'onnx-community/whisper-base', {
        device: 'wasm',
        dtype: 'q8',
        progress_callback: (progress) => self.postMessage({ type: 'progress', progress }),
    }).catch((error) => {
        model = undefined;
        throw error;
    });
    return model;
}

// One inference at a time; the caller pauses listening while a phrase is processed.
let queue = Promise.resolve();
self.onmessage = ({ data }: MessageEvent<{ id: number; type: 'load' | 'transcribe'; audio?: Float32Array; language?: string }>) => {
    queue = queue.then(async () => {
        try {
            const transcriber = await load();
            if (data.type === 'load') {
                self.postMessage({ id: data.id, type: 'ready' });
                return;
            }
            if (!data.audio || data.audio.length < 1600 || data.audio.length > 16000 * 10)
                throw new Error('No usable speech was recorded. Test your microphone and retry.');
            const result = await transcriber(data.audio, {
                language: data.language === 'auto' ? undefined : 'english',
                task: 'transcribe',
                max_new_tokens: 64,
                temperature: 0,
            });
            const text = (Array.isArray(result) ? result[0] : result).text.trim();
            self.postMessage({ id: data.id, type: 'result', text });
        } catch (error) {
            self.postMessage({ id: data.id, type: 'error', message: error instanceof Error ? error.message : 'Local speech recognition failed.' });
        }
    });
};
