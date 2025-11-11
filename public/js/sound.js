export class SoundBoard {
    constructor() {
        this.enabled = true;
        this.context = null;
        this.tones = {
            insert: { frequency: 880, duration: 0.18 },
            update: { frequency: 660, duration: 0.28 },
            delete: { frequency: 220, duration: 0.2 },
        };
    }

    toggle() {
        this.enabled = !this.enabled;
        if (this.enabled && !this.context) {
            this.context = new AudioContext();
        }
        return this.enabled;
    }

    play(type) {
        if (!this.enabled) {
            return;
        }
        if (!this.context) {
            this.context = new AudioContext();
        }
        if (this.context.state === 'suspended') {
            this.context.resume();
        }

        const config = this.tones[type] || this.tones.update;
        const oscillator = this.context.createOscillator();
        const gain = this.context.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = config.frequency;
        gain.gain.setValueAtTime(0.001, this.context.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.2, this.context.currentTime + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, this.context.currentTime + config.duration);
        oscillator.connect(gain);
        gain.connect(this.context.destination);
        oscillator.start();
        oscillator.stop(this.context.currentTime + config.duration + 0.05);
    }
}
