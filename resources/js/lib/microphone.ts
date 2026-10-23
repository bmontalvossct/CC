import { SPEECH_SAMPLE_RATE } from './voiceAudio';

export function microphoneError(error: unknown): string {
    const name = error instanceof DOMException ? error.name : '';
    if (name === 'NotAllowedError' || name === 'SecurityError')
        return 'Microphone access was blocked. Allow microphone access for this site in your browser and Windows privacy settings.';
    if (name === 'NotFoundError' || name === 'OverconstrainedError')
        return 'That microphone is unavailable. Select another microphone or connect one, then retry.';
    if (name === 'NotReadableError') return 'The microphone could not be opened. Check whether another app has exclusive access to it.';
    return error instanceof Error ? error.message : 'Microphone capture failed. Check your selected input and retry.';
}

export class Microphone {
    private stream?: MediaStream;
    private context?: AudioContext;
    private source?: MediaStreamAudioSourceNode;
    private processor?: AudioWorkletNode | ScriptProcessorNode;
    private mute?: GainNode;
    private closed = false;

    async open(deviceId: string, onAudio: (samples: Float32Array, rate: number) => void): Promise<void> {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia)
            throw new Error('Microphone access requires HTTPS or localhost. Open ClassCheck directly in a browser.');
        const stream = await navigator.mediaDevices.getUserMedia({
            audio: {
                deviceId: deviceId ? { exact: deviceId } : undefined,
                channelCount: 1,
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true,
            },
        });
        if (this.closed) {
            stream.getTracks().forEach((track) => track.stop());
            return;
        }
        this.stream = stream;
        stream.getAudioTracks().forEach((track) => track.addEventListener('ended', () => this.close(), { once: true }));
        this.context = new AudioContext({ sampleRate: SPEECH_SAMPLE_RATE });
        await this.context.resume();
        if (this.closed) return;
        if (this.context.state !== 'running') throw new Error('Audio capture is suspended. Click Retry microphone to start it.');
        this.source = this.context.createMediaStreamSource(stream);
        this.mute = this.context.createGain();
        this.mute.gain.value = 0;
        if (this.context.audioWorklet) {
            await this.context.audioWorklet.addModule(new URL('../workers/pcm-capture.worklet.js', import.meta.url).href);
            if (this.closed) return;
            const processor = new AudioWorkletNode(this.context, 'classcheck-pcm');
            processor.port.onmessage = ({ data }: MessageEvent<Float32Array>) => {
                if (!this.closed) onAudio(data, this.context!.sampleRate);
            };
            this.processor = processor;
        } else {
            // Older browsers without AudioWorklet still get an explicit microphone test.
            const processor = this.context.createScriptProcessor(2048, 1, 1);
            processor.onaudioprocess = (event) => {
                if (!this.closed) onAudio(new Float32Array(event.inputBuffer.getChannelData(0)), this.context!.sampleRate);
            };
            this.processor = processor;
        }
        this.source.connect(this.processor);
        this.processor.connect(this.mute);
        this.mute.connect(this.context.destination);
    }

    close(): void {
        this.closed = true;
        if (typeof AudioWorkletNode !== 'undefined' && this.processor instanceof AudioWorkletNode) this.processor.port.close();
        if (typeof ScriptProcessorNode !== 'undefined' && this.processor instanceof ScriptProcessorNode) this.processor.onaudioprocess = null;
        this.source?.disconnect();
        this.processor?.disconnect();
        this.mute?.disconnect();
        this.stream?.getTracks().forEach((track) => track.stop());
        if (this.context && this.context.state !== 'closed') void this.context.close().catch(() => {});
    }
}
