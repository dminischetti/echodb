import { Visualizer } from './visualizer.js';
import { Timeline } from './timeline.js';
import { SoundBoard } from './sound.js';

const apiBase = document.body.dataset.apiBase || '/api';
const visualizer = new Visualizer(document.getElementById('visualizerCanvas'));
const timeline = new Timeline(document.getElementById('timeline'));
const soundBoard = new SoundBoard();
const toast = document.getElementById('toast');
const streamingChip = document.querySelector('.chip.live');
const THEME_STORAGE_KEY = 'echodb-theme';

const stats = {
    insert: 0,
    update: 0,
    delete: 0,
    rpm: 0,
};
let sparklineData = new Array(15).fill(0);
let reconnectDelay = 1000;
let eventSource = null;
let isStreamPaused = false;

const payloadInput = document.getElementById('payloadInput');
const sendButton = document.getElementById('sendPayload');
const randomButton = document.getElementById('randomPayload');
const formatButton = document.getElementById('formatPayload');
const resetButton = document.getElementById('resetPayload');
const pauseButton = document.getElementById('pauseStream');
const themeToggleButtons = Array.from(document.querySelectorAll('[data-theme-toggle]'));
const soundToggleButtons = Array.from(document.querySelectorAll('[data-sound-toggle]'));
const tabButtons = document.querySelectorAll('[data-tab-target]');
const tabPanels = document.querySelectorAll('.tab-panel');
const copyButtons = document.querySelectorAll('.copy-button');

const SEEDED_ORDER_IDS = [1, 2, 3];
const KNOWN_USER_IDS = [1, 2, 3];
const knownOrderIds = new Set(SEEDED_ORDER_IDS);

init();

function getRandomKnownOrderId() {
    const ids = Array.from(knownOrderIds);
    if (ids.length === 0) {
        return SEEDED_ORDER_IDS[0];
    }

    return ids[Math.floor(Math.random() * ids.length)];
}

function getRandomKnownUserId() {
    return KNOWN_USER_IDS[Math.floor(Math.random() * KNOWN_USER_IDS.length)];
}

function registerKnownOrderId(id) {
    const numericId = Number(id);
    if (Number.isInteger(numericId) && numericId > 0) {
        knownOrderIds.add(numericId);
    }
}

function forgetKnownOrderId(id) {
    const numericId = Number(id);
    if (!Number.isInteger(numericId) || numericId <= 0) {
        return;
    }

    knownOrderIds.delete(numericId);
}

function trackKnownOrderIds(event) {
    if (!event || event.table !== 'orders') {
        return;
    }

    if (event.type === 'delete') {
        forgetKnownOrderId(event.row_id);
        return;
    }

    registerKnownOrderId(event.row_id);
}

function init() {
    setInitialPayload();
    preload();
    bindDemoControls();
    bindThemeToggles();
    bindSoundToggles();
    bindTabs();
    bindCopyButtons();
    observeAnimations();
    openStream();
    setInterval(fetchStats, 15000);
}

function setInitialPayload() {
    if (!payloadInput) {
        return;
    }
    const sample = {
        table: 'orders',
        row_id: 1,
        type: 'update',
        actor: 'demo-user',
        changes: {
            status: 'processing',
            amount: 72.45,
        },
    };
    payloadInput.value = JSON.stringify(sample, null, 2);
}

async function preload() {
    await Promise.all([fetchInitialEvents(), fetchStats()]);
}

async function fetchInitialEvents() {
    try {
        const response = await fetch(`${apiBase}/events?limit=20`);
        const payload = await response.json();
        const events = (payload.data || []).map(normalizeEvent).filter(Boolean);
        if (events.length > 0) {
            events.forEach(trackKnownOrderIds);
            timeline.preload(events);
            stats.insert += events.filter((event) => event.type === 'insert').length;
            stats.update += events.filter((event) => event.type === 'update').length;
            stats.delete += events.filter((event) => event.type === 'delete').length;
            renderStats();
        }
    } catch (error) {
        console.error('Failed to load events', error);
        showToast('Unable to load events. Check API connectivity.', true);
    }
}

function openStream() {
    if (isStreamPaused) {
        return;
    }
    if (eventSource) {
        eventSource.close();
    }
    const url = `${apiBase}/stream`;
    eventSource = new EventSource(url, { withCredentials: true });
    updateStreamingChip('Streaming');

    ['insert', 'update', 'delete'].forEach((type) => {
        eventSource.addEventListener(type, (event) => {
            reconnectDelay = 1000;
            handleIncomingEvent(event);
        });
    });

    eventSource.onopen = () => {
        reconnectDelay = 1000;
        updateStreamingChip('Streaming');
    };

    eventSource.onerror = () => {
        if (isStreamPaused) {
            updateStreamingChip('Paused');
            return;
        }
        updateStreamingChip('Reconnecting…');
        eventSource?.close();
        setTimeout(() => {
            reconnectDelay = Math.min(reconnectDelay * 1.8, 5000);
            openStream();
        }, reconnectDelay);
    };
}

function handleIncomingEvent(event) {
    try {
        const payload = normalizeEvent(JSON.parse(event.data));
        trackKnownOrderIds(payload);
        timeline.append(payload);
        visualizer.trigger(payload.type);
        soundBoard.play(payload.type);
        incrementStats(payload.type);
        renderStats();
    } catch (error) {
        console.error('Malformed SSE payload', error);
    }
}

async function fetchStats() {
    try {
        const response = await fetch(`${apiBase}/stats`);
        const payload = await response.json();
        const data = payload.data || {};
        const counts = data.counts || {};
        stats.insert = counts.orders?.insert || 0;
        stats.update = counts.orders?.update || 0;
        stats.delete = counts.orders?.delete || 0;
        stats.rpm = data.rpm || 0;
        sparklineData = Object.values(data.events_per_minute || {}).map((value) => Number(value));
        renderStats();
    } catch (error) {
        console.error('Failed to fetch stats', error);
    }
}

function incrementStats(type) {
    if (type in stats) {
        stats[type] += 1;
    }
}

function renderStats() {
    const insertEl = document.getElementById('stat-insert');
    const updateEl = document.getElementById('stat-update');
    const deleteEl = document.getElementById('stat-delete');
    const rpmEl = document.getElementById('stat-rpm');

    if (insertEl) insertEl.textContent = stats.insert.toString();
    if (updateEl) updateEl.textContent = stats.update.toString();
    if (deleteEl) deleteEl.textContent = stats.delete.toString();
    if (rpmEl) rpmEl.textContent = Number(stats.rpm || 0).toFixed(2);

    drawSparkline();
}

function drawSparkline() {
    const svg = document.getElementById('sparkline');
    if (!svg) {
        return;
    }
    const data = sparklineData.length ? sparklineData : [0];
    const max = Math.max(...data, 1);
    const width = 220;
    const height = 60;
    const points = data.map((value, index) => {
        const x = (index / (data.length - 1 || 1)) * width;
        const y = height - (value / max) * height;
        return `${x},${y}`;
    });
    const path = `M0,${height} L${points.join(' ')} L${width},${height} Z`;
    svg.innerHTML = `<path d="${path}" fill="rgba(79, 195, 247, 0.18)" stroke="none"></path>` +
        `<polyline points="${points.join(' ')}" stroke="var(--accent-cyan)" stroke-width="2" fill="none"></polyline>`;
}

function bindDemoControls() {
    updatePauseButton();
    sendButton?.addEventListener('click', async () => {
        const payload = readPayload();
        if (!payload) {
            return;
        }
        await sendEvent(payload, 'Mutation sent');
    });

    formatButton?.addEventListener('click', () => {
        const payload = readPayload();
        if (!payload) {
            return;
        }
        payloadInput.value = JSON.stringify(payload, null, 2);
    });

    resetButton?.addEventListener('click', () => {
        setInitialPayload();
        showToast('Sample payload restored');
    });

    randomButton?.addEventListener('click', async () => {
        const payload = generateRandomPayload();
        if (payloadInput) {
            payloadInput.value = JSON.stringify(payload, null, 2);
        }
        await sendEvent(payload, 'Random event dispatched');
    });

    pauseButton?.addEventListener('click', () => {
        isStreamPaused = !isStreamPaused;
        if (isStreamPaused) {
            pauseStream();
        } else {
            resumeStream();
        }
    });
}

function readPayload() {
    if (!payloadInput) {
        return null;
    }
    try {
        const parsed = JSON.parse(payloadInput.value);
        if (!parsed || typeof parsed !== 'object') {
            throw new Error('Payload must be a JSON object');
        }
        if (!parsed.table || !parsed.type) {
            throw new Error('Payload requires "table" and "type" fields');
        }
        return parsed;
    } catch (error) {
        showToast(error.message || 'Invalid JSON payload', true);
        return null;
    }
}

async function sendEvent(payload, successMessage) {
    try {
        const response = await fetch(`${apiBase}/events`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const result = await response.json();
        if (!response.ok) {
            throw new Error(result.error || 'Request failed');
        }
        const createdEvent = normalizeEvent(result.data);
        trackKnownOrderIds(createdEvent);
        showToast(successMessage);
    } catch (error) {
        console.error('Failed to send event', error);
        showToast(error.message || 'Failed to send event', true);
    }
}

function pauseStream() {
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }
    updateStreamingChip('Paused');
    updatePauseButton();
    showToast('Stream paused');
}

function resumeStream() {
    updatePauseButton();
    showToast('Stream resumed');
    openStream();
}

function updatePauseButton() {
    if (!pauseButton) {
        return;
    }
    if (isStreamPaused) {
        pauseButton.textContent = 'Resume Stream';
        pauseButton.dataset.icon = 'play';
    } else {
        pauseButton.textContent = 'Pause Stream';
        pauseButton.dataset.icon = 'pause';
    }
}

function updateStreamingChip(label) {
    if (streamingChip) {
        streamingChip.textContent = label;
    }
}

function bindThemeToggles() {
    const storedTheme = readStoredThemePreference();
    setTheme(storedTheme === 'light' ? 'light' : 'dark', false);

    themeToggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const nextTheme = document.body.classList.contains('theme-light') ? 'dark' : 'light';
            setTheme(nextTheme);
        });
    });
}

function setTheme(theme, persist = true) {
    const useLight = theme === 'light';
    document.body.classList.toggle('theme-light', useLight);
    document.body.classList.toggle('theme-dark', !useLight);
    if (persist) {
        rememberThemePreference(theme);
    }
    updateThemeButtons();
}

function updateThemeButtons() {
    const isLight = document.body.classList.contains('theme-light');
    const label = isLight ? 'Dark Mode' : 'Light Mode';
    themeToggleButtons.forEach((button) => {
        button.textContent = label;
        button.dataset.icon = 'theme';
    });
}

function rememberThemePreference(theme) {
    try {
        localStorage.setItem(THEME_STORAGE_KEY, theme);
    } catch (error) {
        console.warn('Unable to persist theme preference', error);
    }
}

function readStoredThemePreference() {
    try {
        return localStorage.getItem(THEME_STORAGE_KEY);
    } catch (error) {
        console.warn('Unable to read theme preference', error);
        return null;
    }
}

function bindSoundToggles() {
    updateSoundButtons(soundBoard.enabled);
    soundToggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const enabled = soundBoard.toggle();
            updateSoundButtons(enabled);
        });
    });
}

function updateSoundButtons(enabled) {
    const label = enabled ? 'Sound On' : 'Sound Off';
    soundToggleButtons.forEach((button) => {
        button.textContent = label;
        button.dataset.icon = 'sound';
    });
}

function bindTabs() {
    const initialTarget = document.querySelector('.tab-button.active')?.dataset.tabTarget || 'overview';
    activateTab(initialTarget);
    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.tabTarget;
            activateTab(target || 'overview');
        });
    });
}

function activateTab(target) {
    tabButtons.forEach((button) => {
        const isActive = button.dataset.tabTarget === target;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    tabPanels.forEach((panel) => {
        const isActive = panel.id === `tab-${target}`;
        panel.classList.toggle('active', isActive);
        if (isActive) {
            panel.removeAttribute('hidden');
        } else {
            panel.setAttribute('hidden', 'true');
        }
    });
}

function bindCopyButtons() {
    copyButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const targetId = button.dataset.copyTarget;
            if (!targetId) {
                return;
            }
            const element = document.getElementById(targetId);
            if (!element) {
                return;
            }
            try {
                await navigator.clipboard.writeText(element.textContent || '');
                button.textContent = 'Copied';
                setTimeout(() => {
                    button.textContent = 'Copy';
                }, 1500);
            } catch (error) {
                showToast('Copy failed', true);
            }
        });
    });
}

function observeAnimations() {
    const animated = document.querySelectorAll('[data-animate]');
    if (!animated.length) {
        return;
    }
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.2,
    });

    animated.forEach((element) => observer.observe(element));
}

function normalizeEvent(raw) {
    if (!raw) {
        return null;
    }
    const diff = typeof raw.diff === 'string' ? safeJson(raw.diff) : raw.diff;
    return {
        id: Number(raw.id),
        type: raw.type,
        table: raw.table || raw.table_name,
        row_id: Number(raw.row_id),
        actor: raw.actor,
        created_at: raw.created_at,
        diff: diff || {},
    };
}

function safeJson(value) {
    try {
        return JSON.parse(value);
    } catch (error) {
        return null;
    }
}

function generateRandomPayload() {
    const types = ['insert', 'update', 'delete'];
    const requestedType = types[Math.floor(Math.random() * types.length)];
    const actors = ['demo-bot', 'ops-lead', 'scheduler', 'qa-user'];
    const statuses = ['pending', 'processing', 'shipped', 'cancelled'];
    const effectiveType = knownOrderIds.size === 0 ? 'insert' : requestedType;
    const payload = {
        table: 'orders',
        row_id: effectiveType === 'insert' ? 0 : getRandomKnownOrderId(),
        type: effectiveType,
        actor: actors[Math.floor(Math.random() * actors.length)],
        changes: {},
    };

    if (payload.type === 'insert') {
        let candidate;
        do {
            candidate = Math.floor(Math.random() * 500) + 200;
        } while (knownOrderIds.has(candidate));
        payload.row_id = candidate;
        payload.changes = {
            user_id: getRandomKnownUserId(),
            status: statuses[Math.floor(Math.random() * statuses.length)],
            amount: Number((Math.random() * 250 + 25).toFixed(2)),
        };
    }

    if (payload.type === 'update') {
        payload.changes = {
            status: statuses[Math.floor(Math.random() * statuses.length)],
            amount: Number((Math.random() * 180 + 10).toFixed(2)),
        };
    }

    if (payload.type === 'delete') {
        payload.changes = {};
    }

    return payload;
}

function showToast(message, isError = false) {
    if (!toast) {
        return;
    }
    toast.textContent = message;
    toast.classList.toggle('error', Boolean(isError));
    toast.classList.remove('hidden');
    toast.classList.add('show');
    clearTimeout(showToast.timeoutId);
    showToast.timeoutId = setTimeout(() => {
        toast.classList.remove('show');
        toast.classList.add('hidden');
    }, 2600);
}
