export class SoundBoard {
    constructor() {
        this.enabled = true;
        this.context = null;
        this.unlocked = false;
        this.AudioContext = window.AudioContext || window.webkitAudioContext;
        this.tones = {
            insert: { frequency: 880, duration: 0.18 },
            update: { frequency: 660, duration: 0.28 },
            delete: { frequency: 220, duration: 0.2 },
        };
    }

    toggle() {
        this.enabled = !this.enabled;
        if (this.enabled) {
            this.unlock();
        }
        return this.enabled;
    }

    ensureContext() {
        if (this.context || !this.AudioContext) {
            return this.context;
        }
        try {
            this.context = new this.AudioContext();
        } catch (error) {
            console.warn('Unable to create audio context', error);
        }
        return this.context;
    }

    async unlock() {
        if (!this.enabled) {
            return false;
        }

        const context = this.ensureContext();
        if (!context) {
            return false;
        }

        if (context.state === 'suspended') {
            try {
                await context.resume();
            } catch (error) {
                console.warn('Unable to resume audio context', error);
                return false;
            }
        }

        if (this.unlocked || context.state !== 'running') {
            return this.unlocked;
        }

        try {
            const buffer = context.createBuffer(1, 1, 22050);
            const source = context.createBufferSource();
            const gain = context.createGain();
            gain.gain.value = 0;
            source.buffer = buffer;
            source.connect(gain);
            gain.connect(context.destination);
            source.start(0);
            source.stop(0);
            source.disconnect();
            gain.disconnect();
            this.unlocked = true;
        } catch (error) {
            console.warn('Failed to prime audio context', error);
        }

        return this.unlocked;
    }

    play(type) {
        if (!this.enabled) {
            return;
        }
        const context = this.ensureContext();
        if (!context) {
            return;
        }
        if (context.state === 'suspended') {
            context.resume();
        }

        const config = this.tones[type] || this.tones.update;
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = config.frequency;
        gain.gain.setValueAtTime(0.001, context.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.2, context.currentTime + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + config.duration);
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start();
        oscillator.stop(context.currentTime + config.duration + 0.05);
    }
}
