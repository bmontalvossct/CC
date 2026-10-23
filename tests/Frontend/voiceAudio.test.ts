import assert from 'node:assert/strict';
import { test } from 'node:test';
import { joinAudio, resample, rms, SpeechSegmenter, wavBlob } from '../../resources/js/lib/voiceAudio.ts';

const frame = (value = 0) => new Float32Array(1024).fill(value);

test('silence never creates a phrase and short clicks are rejected', () => {
    const detector = new SpeechSegmenter();
    for (let i = 0; i < 50; i++) assert.deepEqual(detector.push(frame(), 16000), {});
    detector.push(frame(0.1), 16000);
    let result;
    for (let i = 0; i < 11; i++) result = detector.push(frame(), 16000);
    assert.equal(result?.audio, undefined);
});

test('speech is delivered once after a pause and a reset discards previous audio', () => {
    const detector = new SpeechSegmenter();
    for (let i = 0; i < 4; i++) detector.push(frame(0.1), 16000);
    let result;
    for (let i = 0; i < 11; i++) result = detector.push(frame(), 16000);
    assert.ok(result?.audio && result.audio.length > 0);
    for (let i = 0; i < 30; i++) assert.deepEqual(detector.push(frame(), 16000), {});
    detector.push(frame(0.1), 16000);
    detector.reset();
    for (let i = 0; i < 20; i++) assert.deepEqual(detector.push(frame(), 16000), {});
});

test('long uninterrupted speech is rejected instead of being split between students', () => {
    const detector = new SpeechSegmenter();
    let rejected = false;
    for (let i = 0; i < 130; i++) {
        const result = detector.push(frame(0.1), 16000);
        assert.equal(result.audio, undefined);
        rejected ||= !!result.tooLong;
    }
    assert.equal(rejected, true);
});

test('audio conversion preserves sample order, levels, duration, and WAV metadata', async () => {
    assert.deepEqual([...joinAudio([new Float32Array([1, 2]), new Float32Array([3])])], [1, 2, 3]);
    assert.equal(rms(frame()), 0);
    assert.ok(Math.abs(rms(frame(0.1)) - 0.1) < 0.00001);
    const audio = resample(new Float32Array(48000).fill(0.25), 48000);
    assert.equal(audio.length, 16000);
    assert.equal(audio[0], 0.25);
    const wav = wavBlob(audio, 16000);
    const view = new DataView(await wav.arrayBuffer());
    assert.equal(view.getUint32(24, true), 16000);
    assert.equal(view.getUint32(40, true), 32000);
    assert.equal(wav.size, 32044);
});
