export const SPEECH_SAMPLE_RATE = 16000;

export function joinAudio(chunks: Float32Array[]): Float32Array {
    const output = new Float32Array(chunks.reduce((sum, chunk) => sum + chunk.length, 0));
    let offset = 0;
    for (const chunk of chunks) {
        output.set(chunk, offset);
        offset += chunk.length;
    }
    return output;
}

export function rms(samples: Float32Array): number {
    return Math.sqrt(samples.reduce((sum, value) => sum + value * value, 0) / Math.max(1, samples.length));
}

export function resample(samples: Float32Array, fromRate: number, toRate = SPEECH_SAMPLE_RATE): Float32Array {
    if (fromRate === toRate) return samples;
    const ratio = fromRate / toRate;
    const output = new Float32Array(Math.floor(samples.length / ratio));
    // Average each source interval when downsampling instead of discarding samples.
    for (let i = 0; i < output.length; i++) {
        const start = Math.floor(i * ratio);
        const end = Math.min(samples.length, Math.max(start + 1, Math.floor((i + 1) * ratio)));
        let sum = 0;
        for (let j = start; j < end; j++) sum += samples[j];
        output[i] = sum / (end - start);
    }
    return output;
}

/** A phrase ends after a pause. Long phrases are rejected rather than split into different students. */
export class SpeechSegmenter {
    private lead: Float32Array[] = [];
    private chunks: Float32Array[] = [];
    private voicedMs = 0;
    private silenceMs = 0;
    private totalMs = 0;

    reset(): void {
        this.lead = [];
        this.chunks = [];
        this.voicedMs = this.silenceMs = this.totalMs = 0;
    }

    push(samples: Float32Array, rate: number): { audio?: Float32Array; tooLong?: boolean } {
        const ms = (samples.length / rate) * 1000;
        const voiced = rms(samples) >= 0.008;
        if (!this.chunks.length && !voiced) {
            this.lead.push(samples);
            while (this.lead.reduce((sum, chunk) => sum + chunk.length, 0) > rate * 0.2) this.lead.shift();
            return {};
        }
        if (!this.chunks.length) this.chunks.push(...this.lead);
        this.chunks.push(samples);
        this.totalMs += ms;
        this.voicedMs += voiced ? ms : 0;
        this.silenceMs = voiced ? 0 : this.silenceMs + ms;
        if (this.totalMs > 8000) {
            this.reset();
            return { tooLong: true };
        }
        if (this.silenceMs < 700) return {};
        const audio = this.voicedMs >= 160 ? resample(joinAudio(this.chunks), rate) : undefined;
        this.reset();
        return { audio };
    }
}

export function wavBlob(audio: Float32Array, rate: number): Blob {
    const buffer = new ArrayBuffer(44 + audio.length * 2);
    const view = new DataView(buffer);
    const write = (offset: number, text: string) => [...text].forEach((char, i) => view.setUint8(offset + i, char.charCodeAt(0)));
    write(0, 'RIFF');
    view.setUint32(4, 36 + audio.length * 2, true);
    write(8, 'WAVE');
    write(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, rate, true);
    view.setUint32(28, rate * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    write(36, 'data');
    view.setUint32(40, audio.length * 2, true);
    audio.forEach((sample, i) => view.setInt16(44 + i * 2, Math.max(-1, Math.min(1, sample)) * 32767, true));
    return new Blob([buffer], { type: 'audio/wav' });
}
