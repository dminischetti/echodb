const SVG_NS = 'http://www.w3.org/2000/svg';

export class Visualizer {
    constructor(svg) {
        this.svg = svg;
        this.colors = {
            insert: '#34d399',
            update: '#38bdf8',
            delete: '#f87171',
        };
    }

    trigger(type) {
        const color = this.colors[type] || this.colors.update;
        this.flashNodes(type);
        this.spawnPulse('arc-top', color);
        setTimeout(() => this.spawnPulse('arc-bottom', color), 400);
    }

    spawnPulse(pathClass, color) {
        const path = this.svg.querySelector(`.${pathClass}`);
        if (!path) {
            return;
        }

        const pulse = document.createElementNS(SVG_NS, 'circle');
        pulse.setAttribute('r', '7');
        pulse.setAttribute('fill', color);
        pulse.setAttribute('opacity', '0.85');

        const motion = document.createElementNS(SVG_NS, 'animateMotion');
        motion.setAttribute('dur', '1.2s');
        motion.setAttribute('path', path.getAttribute('d'));
        motion.setAttribute('fill', 'freeze');
        pulse.appendChild(motion);
        this.svg.appendChild(pulse);
        requestAnimationFrame(() => motion.beginElement());
        setTimeout(() => {
            pulse.remove();
        }, 1400);

        path.classList.remove('pulse');
        // Force reflow for animation restart.
        void path.getBoundingClientRect();
        path.classList.add('pulse');
    }

    flashNodes(type) {
        const sequence = ['.node-db', '.node-echo', '.node-ui'];
        sequence.forEach((selector, index) => {
            const node = this.svg.querySelector(selector);
            if (!node) {
                return;
            }
            setTimeout(() => {
                node.classList.add('active');
                node.setAttribute('stroke', this.colors[type] || this.colors.update);
                setTimeout(() => {
                    node.classList.remove('active');
                    node.removeAttribute('stroke');
                }, 700);
            }, index * 220);
        });
    }
}
