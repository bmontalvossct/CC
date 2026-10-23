/* global AudioWorkletProcessor, registerProcessor */
class PcmCapture extends AudioWorkletProcessor {
    constructor() {
        super();
        this.buffer = new Float32Array(1024);
        this.offset = 0;
    }
    process(inputs) {
        const channel = inputs[0]?.[0];
        if (channel) {
            for (const sample of channel) {
                this.buffer[this.offset++] = sample;
                if (this.offset === this.buffer.length) {
                    this.port.postMessage(this.buffer);
                    this.offset = 0;
                }
            }
        }
        return true;
    }
}
registerProcessor('classcheck-pcm', PcmCapture);
