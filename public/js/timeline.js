export class Timeline {
    constructor(root) {
        this.root = root;
    }

    preload(events) {
        this.root.innerHTML = '';
        events.slice().reverse().forEach((event) => this.append(event, false));
    }

    append(event, animate = true) {
        const item = document.createElement('li');
        item.dataset.type = event.type;
        item.innerHTML = `
            <div class="meta">
                <span>#${event.id} · ${event.table}</span>
                <span>${new Date(event.created_at).toLocaleTimeString()}</span>
            </div>
            <div class="diff">${this.renderDiff(event.diff)}</div>
            <div class="meta">
                <span>${event.actor || 'system'}</span>
                <span>${event.type.toUpperCase()}</span>
            </div>
        `;
        if (animate) {
            item.style.opacity = '0';
            item.style.transform = 'translateY(-6px)';
            requestAnimationFrame(() => {
                item.style.transition = 'all 0.4s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            });
        }
        this.root.prepend(item);
        while (this.root.children.length > 20) {
            this.root.lastElementChild?.remove();
        }
    }

    renderDiff(diff) {
        if (!diff || typeof diff !== 'object') {
            return '<em>No diff</em>';
        }

        return Object.entries(diff)
            .map(([field, values]) => {
                const oldVal = this.formatValue(values.old);
                const newVal = this.formatValue(values.new);
                return `<span><strong>${field}</strong>: ${oldVal} → ${newVal}</span>`;
            })
            .join('');
    }

    formatValue(value) {
        if (value === null || value === undefined) {
            return '<span class="muted">∅</span>';
        }
        if (typeof value === 'number') {
            return value.toLocaleString();
        }
        return String(value);
    }
}
