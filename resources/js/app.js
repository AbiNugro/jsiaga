import Chart from 'chart.js/auto';
import { cn } from './cn';

const page = document.querySelector('[data-page]')?.dataset.page;
const chartColor = '#087c74';
const gridColor = 'rgba(16, 42, 67, 0.07)';

Chart.defaults.font.family = "'Segoe UI Variable', 'Aptos', 'Segoe UI', sans-serif";
Chart.defaults.color = '#62788c';

function jsonData(id) {
    const node = document.getElementById(id);
    if (!node) return [];
    try { return JSON.parse(node.textContent || '[]'); } catch { return []; }
}

const ui = jsonData('ui-translations');
const browserLocale = { id: 'id-ID', en: 'en-US', ko: 'ko-KR' }[ui.locale] || 'id-ID';

function formatTime(value, withDate = false) {
    return new Intl.DateTimeFormat(browserLocale, {
        timeZone: 'Asia/Jakarta',
        ...(withDate ? { day: '2-digit', month: 'short' } : {}),
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function createLineChart(canvas, readings, detailed = false) {
    if (!canvas) return null;
    return new Chart(canvas, {
        type: 'line',
        data: {
            labels: readings.map(item => formatTime(item.recorded_at, detailed)),
            datasets: [{
                data: readings.map(item => Number(item.water_level)),
                borderColor: chartColor,
                backgroundColor: 'rgba(8, 124, 116, 0.08)',
                borderWidth: 2.5,
                pointRadius: readings.length > 30 ? 0 : 2,
                pointHoverRadius: 5,
                pointBackgroundColor: chartColor,
                fill: true,
                tension: 0.32,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            animation: { duration: 180 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    displayColors: false,
                    callbacks: {
                        title(items) {
                            const item = readings[items[0]?.dataIndex];
                            return item ? formatTime(item.recorded_at, true) + ' WIB' : '';
                        },
                        label(context) {
                            const item = readings[context.dataIndex];
                            const details = [`${ui.chart?.level || 'Level air'}: ${context.parsed.y}%`];
                            if (detailed && item) details.push(`${ui.chart?.status || 'Status'}: ${item.status}`);
                            return details;
                        },
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: window.innerWidth < 640 ? 4 : 8, maxRotation: 0 } },
                y: { min: 0, max: 100, grid: { color: gridColor }, ticks: { stepSize: 25, callback: value => `${value}%` } },
            },
        },
    });
}

function updateConnection(recordedAt) {
    const indicator = document.querySelector('[data-connection-indicator]');
    const ageSeconds = recordedAt ? (Date.now() - new Date(recordedAt).getTime()) / 1000 : Infinity;
    const state = !recordedAt ? 'empty' : ageSeconds <= Number(ui.offlineSeconds || 15) ? 'online' : 'offline';
    if (indicator) {
        indicator.className = cn('connection-pill', `connection-${state}`);
        indicator.querySelector('[data-connection-label]').textContent = state === 'online'
            ? ui.common?.dataOnline
            : state === 'offline' ? ui.common?.dataOffline : ui.common?.noData;
    }
    return state;
}

const recommendations = ui.recommendations || {};

function numberValue(value) {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat(browserLocale, { maximumFractionDigits: 1 }).format(value);
}

function lightCondition(value) {
    if (value === null || value === undefined || !Number.isFinite(Number(value))) return '—';
    const numericValue = Number(value);
    const key = numericValue <= 199 ? 'bright' : numericValue <= 449 ? 'cloudy' : numericValue <= 699 ? 'dim' : 'dark';
    return ui.lightConditions?.[key] || numberValue(numericValue);
}

function updateHome(reading) {
    if (!reading) return;
    const status = reading.effective_status || (reading.is_stale ? 'OFFLINE' : reading.status);
    const isOffline = status === 'OFFLINE';
    const card = document.querySelector('[data-status-card]');
    if (!card) { window.location.reload(); return; }
    card.dataset.status = status;
    card.className = cn('status-card', {
        'status-card-safe': status === 'SAFE',
        'status-card-warning': status === 'WARNING',
        'status-card-danger': status === 'DANGER',
        'status-card-flood': status === 'FLOOD',
        'status-card-empty': status === 'OFFLINE',
    });
    card.querySelector('[data-status-label]').textContent = status === 'OFFLINE' ? (ui.status?.OFFLINE || 'OFFLINE') : status;
    card.querySelector('[data-recorded-at]').textContent = ui.common?.secondsAgo || 'beberapa detik lalu';
    document.querySelectorAll('[data-sensor-value]').forEach(node => {
        const key = node.dataset.sensorValue;
        const value = reading[key];
        node.textContent = isOffline ? '-' : (key === 'light' ? lightCondition(value) : numberValue(value));
        const unit = node.parentElement?.querySelector('[data-sensor-unit]');
        if (unit) unit.classList.toggle('hidden', isOffline);
        if (key === 'light' && !isOffline && value !== null && value !== undefined) {
            node.dataset.rawValue = String(value);
        } else {
            delete node.dataset.rawValue;
        }
    });
    const gauge = document.querySelector('[data-gauge]');
    if (gauge) {
        const level = isOffline ? 0 : Math.max(0, Math.min(100, Number(reading.water_level)));
        gauge.dataset.status = status;
        gauge.dataset.value = level;
        gauge.querySelector('.gauge-progress').style.strokeDashoffset = String(100 - level);
        gauge.querySelector('[data-gauge-value]').textContent = isOffline ? '-' : String(Math.round(level));
        gauge.querySelector('[data-gauge-unit]')?.classList.toggle('hidden', isOffline);
        const currentWaterLevel = document.querySelector('[data-current-water-level]');
        if (currentWaterLevel) currentWaterLevel.textContent = isOffline ? '-' : String(Math.round(level));
        document.querySelector('[data-current-water-level-unit]')?.classList.toggle('hidden', isOffline);
    }
    const copy = recommendations[status];
    if (copy) {
        document.querySelector('[data-recommendation-title]').textContent = copy[0];
        document.querySelector('[data-recommendation-summary]').textContent = copy[1];
    }
    updateConnection(reading.recorded_at);
    document.dispatchEvent(new CustomEvent('jsiaga:status', { detail: { status } }));
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, { headers: { Accept: 'application/json', ...options.headers }, ...options });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'Permintaan gagal.');
    return payload;
}

const alertButtons = [...document.querySelectorAll('[data-enable-alerts]')];
const safetyAlert = document.querySelector('[data-safety-alert]');
const alertFeedback = document.querySelector('[data-alert-feedback]');
const chatConnectionStatus = document.querySelector('[data-chat-connection-status]');
let lastAlertState = ui.initialStatus || 'OFFLINE';
let alertFeedbackTimer = null;

const updateChatConnectionStatus = (status) => {
    if (!chatConnectionStatus) return;
    chatConnectionStatus.textContent = status === 'OFFLINE'
        ? (ui.chat?.offline || 'Offline')
        : (ui.chat?.online || 'Online');
};

    const alertsEnabled = () => {
        try { return window.localStorage.getItem('jsiaga-alerts-enabled') === '1'; } catch { return false; }
    };

    const setAlertsEnabled = (enabled) => {
        try { window.localStorage.setItem('jsiaga-alerts-enabled', enabled ? '1' : '0'); } catch { /* visual alerts remain available */ }
    };

    const updateAlertButtons = (label, enabled) => {
        alertButtons.forEach(button => {
            button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            const text = button.querySelector('[data-alert-button-label]');
            if (text) text.textContent = label;
        });
    };

    const showAlertFeedback = (message) => {
        if (!alertFeedback) return;
        const text = alertFeedback.querySelector('[data-alert-feedback-text]');
        if (text) text.textContent = message;
        alertFeedback.classList.remove('hidden');
        window.clearTimeout(alertFeedbackTimer);
        alertFeedbackTimer = window.setTimeout(() => alertFeedback.classList.add('hidden'), 3000);
    };

    const renderSafetyAlert = (status) => {
        if (!safetyAlert) return;
        const copy = ui.alerts?.[status];
        const visible = ['WARNING', 'DANGER', 'FLOOD', 'OFFLINE'].includes(status) && copy;
        safetyAlert.className = cn('safety-alert', {
            'safety-alert-warning': status === 'WARNING',
            'safety-alert-danger': status === 'DANGER',
            'safety-alert-flood': status === 'FLOOD',
            'safety-alert-offline': status === 'OFFLINE',
            hidden: !visible,
        });
        safetyAlert.dataset.state = status;
        if (visible) {
            safetyAlert.querySelector('[data-safety-alert-title]').textContent = copy[0];
            safetyAlert.querySelector('[data-safety-alert-body]').textContent = copy[1];
        }
    };

    const speakAlert = (status) => {
        if (!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) return;
        const message = ui.alerts?.voice?.[status];
        if (!message) return;
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(message);
        utterance.lang = { id: 'id-ID', en: 'en-US', ko: 'ko-KR' }[ui.locale] || 'id-ID';
        const voices = window.speechSynthesis.getVoices();
        const preferredVoice = voices.find(voice =>
            voice.lang.toLowerCase() === utterance.lang.toLowerCase()
            && /natural|online/i.test(voice.name)
        ) || voices.find(voice => voice.lang.toLowerCase() === utterance.lang.toLowerCase())
          || voices.find(voice => voice.lang.toLowerCase().startsWith(utterance.lang.slice(0, 2).toLowerCase()));
        if (preferredVoice) utterance.voice = preferredVoice;
        utterance.rate = 0.92;
        utterance.pitch = 1;
        utterance.volume = 1;
        window.speechSynthesis.speak(utterance);
    };

    const notifyStatus = (status) => {
        if (!ui.alerts?.voice?.[status] || !alertsEnabled()) return;
        speakAlert(status);
    };

    const handleStatus = (status, force = false) => {
        updateChatConnectionStatus(status);
        renderSafetyAlert(status);
        if (!force && status === lastAlertState) return;
        const previous = lastAlertState;
        lastAlertState = status;
        if (['WARNING', 'DANGER', 'FLOOD', 'OFFLINE'].includes(status) || (status === 'SAFE' && previous && previous !== 'SAFE')) {
            notifyStatus(status);
        }
    };

    document.addEventListener('jsiaga:status', event => handleStatus(event.detail.status));

    if (alertsEnabled()) {
        updateAlertButtons(ui.alerts?.soundOnly || 'Peringatan suara aktif', true);
    }
    alertButtons.forEach(alertButton => alertButton.addEventListener('click', () => {
        if (alertsEnabled()) {
            setAlertsEnabled(false);
            if ('speechSynthesis' in window) window.speechSynthesis.cancel();
            updateAlertButtons(ui.alerts?.enable || 'Aktifkan peringatan', false);
            showAlertFeedback(ui.alerts?.infoDisabled || 'Peringatan dimatikan.');
            return;
        }

        setAlertsEnabled(true);
        updateAlertButtons(ui.alerts?.soundOnly || 'Peringatan suara aktif', true);
        showAlertFeedback(ui.alerts?.infoEnabled || 'Peringatan aktif untuk semua status.');
        handleStatus(lastAlertState || ui.initialStatus || 'OFFLINE', true);
    }));

    handleStatus(ui.initialStatus || 'OFFLINE');

let globalAlertPending = false;
const refreshGlobalAlertStatus = async () => {
    if (globalAlertPending || document.hidden) return;
    globalAlertPending = true;
    try {
        const payload = await fetchJson('/api/v1/sensor-readings/latest');
        const reading = payload.data;
        const status = reading ? (reading.effective_status || (reading.is_stale ? 'OFFLINE' : reading.status)) : 'OFFLINE';
        handleStatus(status);
    } catch {
        handleStatus('OFFLINE');
    } finally {
        globalAlertPending = false;
    }
};

if (!['home', 'recommendations'].includes(page)) {
    window.setInterval(refreshGlobalAlertStatus, 2000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshGlobalAlertStatus();
    });
}

if (page === 'home') {
    const homeReadings = jsonData('home-chart-data');
    const chart = createLineChart(document.getElementById('homeChart'), homeReadings);
    let latestPending = false;
    let historyPending = false;

    const markHomeOffline = () => {
        const card = document.querySelector('[data-status-card]');
        if (card) {
            card.dataset.status = 'OFFLINE';
            card.className = 'status-card status-card-empty';
            card.querySelector('[data-status-label]').textContent = ui.status?.OFFLINE || 'OFFLINE';
        }
        const copy = recommendations.OFFLINE;
        if (copy) {
            document.querySelector('[data-recommendation-title]').textContent = copy[0];
            document.querySelector('[data-recommendation-summary]').textContent = copy[1];
        }
        document.querySelectorAll('[data-sensor-value]').forEach(node => {
            node.textContent = '-';
            node.parentElement?.querySelector('[data-sensor-unit]')?.classList.add('hidden');
            delete node.dataset.rawValue;
        });
        const gauge = document.querySelector('[data-gauge]');
        if (gauge) {
            gauge.dataset.status = 'OFFLINE';
            gauge.dataset.value = '0';
            gauge.querySelector('.gauge-progress').style.strokeDashoffset = '100';
            gauge.querySelector('[data-gauge-value]').textContent = '-';
            gauge.querySelector('[data-gauge-unit]')?.classList.add('hidden');
        }
        const currentWaterLevel = document.querySelector('[data-current-water-level]');
        if (currentWaterLevel) currentWaterLevel.textContent = '-';
        document.querySelector('[data-current-water-level-unit]')?.classList.add('hidden');
        handleStatus('OFFLINE');
    };

    const refreshLatest = async () => {
        if (latestPending || document.hidden) return;
        latestPending = true;
        try {
            const latest = await fetchJson('/api/v1/sensor-readings/latest');
            latest.data ? updateHome(latest.data) : markHomeOffline();
        } catch {
            updateConnection(null);
            markHomeOffline();
        } finally {
            latestPending = false;
        }
    };

    const refreshHistory = async () => {
        if (historyPending || document.hidden || !chart) return;
        historyPending = true;
        try {
            const history = await fetchJson('/api/v1/sensor-readings/history?range=1h');
            if (chart && history.data) {
                const readings = history.data.slice(-20);
                chart.data.labels = readings.map(item => formatTime(item.recorded_at));
                chart.data.datasets[0].data = readings.map(item => Number(item.water_level));
                chart.update('none');
            }
        } catch {
            // Kegagalan grafik tidak membuat indikator sensor terlihat offline.
        } finally {
            historyPending = false;
        }
    };

    window.setInterval(refreshLatest, 1000);
    window.setInterval(refreshHistory, 10000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshLatest();
            refreshHistory();
        }
    });
}

if (page === 'history') {
    let readings = jsonData('history-chart-data');
    const root = document.querySelector('[data-page="history"]');
    const range = root?.dataset.range || '1h';
    const chart = createLineChart(document.getElementById('historyChart'), readings, true);
    const button = document.querySelector('[data-refresh-history]');
    const error = document.querySelector('[data-history-error]');
    const state = document.querySelector('[data-history-state]');
    const refresh = async () => {
        if (!chart) return window.location.reload();
        button?.setAttribute('disabled', 'disabled');
        if (state) state.textContent = ui.common?.loading || 'Memuat…';
        error?.classList.add('hidden');
        try {
            const payload = await fetchJson(`/api/v1/sensor-readings/history?range=${range}`);
            readings = payload.data;
            chart.data.labels = readings.map(item => formatTime(item.recorded_at, true));
            chart.data.datasets[0].data = readings.map(item => Number(item.water_level));
            chart.update();
            if (state) state.textContent = (ui.common?.readings || ':count pembacaan').replace(':count', readings.length);
        } catch (exception) {
            error?.classList.remove('hidden');
            if (state) state.textContent = ui.common?.failed || 'Gagal diperbarui';
        } finally { button?.removeAttribute('disabled'); }
    };
    button?.addEventListener('click', refresh);
}

if (page === 'recommendations') {
    const button = document.querySelector('[data-ai-explain]');
    const result = document.querySelector('[data-ai-result]');
    const statusBadge = document.querySelector('[data-recommendation-status]');
    const waterLevel = document.querySelector('[data-recommendation-water-level]');
    const light = document.querySelector('[data-recommendation-light]');
    const updated = document.querySelector('[data-recommendation-updated]');
    let latestPending = false;
    let aiPending = false;
    let latestOffline = button?.disabled ?? true;

    const updateRecommendationLatest = (reading) => {
        if (!reading) return;
        const status = reading.effective_status || (reading.is_stale ? 'OFFLINE' : reading.status);
        latestOffline = status === 'OFFLINE';
        if (statusBadge) {
            statusBadge.className = cn('status-badge', {
                'status-safe': status === 'SAFE',
                'status-warning': status === 'WARNING',
                'status-danger': status === 'DANGER',
                'status-flood': status === 'FLOOD',
                'status-empty': status === 'OFFLINE',
            });
            const labelNode = statusBadge.querySelector('[data-status-badge-label]');
            if (labelNode) labelNode.textContent = status === 'OFFLINE' ? (ui.status?.OFFLINE || 'OFFLINE') : status;
        }
        if (waterLevel) waterLevel.textContent = latestOffline ? '-' : `${numberValue(reading.water_level)}%`;
        if (light) light.textContent = latestOffline ? '-' : lightCondition(reading.light);
        if (updated && reading.recorded_at) {
            updated.textContent = new Intl.DateTimeFormat(browserLocale, {
                timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
            }).format(new Date(reading.recorded_at)) + ' WIB';
        }
        if (button) button.disabled = aiPending || latestOffline;
        if (latestOffline && result && !result.classList.contains('hidden')) {
            result.textContent = recommendations.OFFLINE?.[1] || ui.alerts?.OFFLINE?.[1] || 'Data sensor tidak diperbarui.';
        }
        handleStatus(status);
    };

    const refreshRecommendationLatest = async () => {
        if (latestPending || document.hidden) return;
        latestPending = true;
        try {
            const payload = await fetchJson('/api/v1/sensor-readings/latest');
            updateRecommendationLatest(payload.data);
        } catch {
            latestOffline = true;
            if (waterLevel) waterLevel.textContent = '-';
            if (light) light.textContent = '-';
            if (button) button.disabled = true;
            handleStatus('OFFLINE');
        } finally {
            latestPending = false;
        }
    };

    button?.addEventListener('click', async () => {
        if (latestOffline) return;
        aiPending = true;
        button.disabled = true;
        result.classList.remove('hidden');
        result.textContent = ui.common?.loading || 'Memuat…';
        try {
            const payload = await fetchJson('/api/v1/recommendations/explain', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ locale: ui.locale || 'id' }),
            });
            result.textContent = payload.data.answer;
        } catch (exception) {
            result.textContent = exception.message || 'Penjelasan AI tidak tersedia. Rekomendasi keselamatan lokal tetap dapat digunakan.';
        } finally {
            aiPending = false;
            button.disabled = latestOffline;
        }
    });

    window.setInterval(refreshRecommendationLatest, 1000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshRecommendationLatest();
    });
    refreshRecommendationLatest();
}

if (page === 'chat') {
    const form = document.getElementById('chatForm');
    const input = document.getElementById('chatInput');
    const messages = document.getElementById('chatMessages');
    const send = form?.querySelector('button[type="submit"]');
    const error = document.getElementById('chatError');

    const addBubble = (text, role, typing = false) => {
        const row = document.createElement('div');
        row.className = cn('flex', role === 'user' ? 'justify-end' : 'justify-start');
        const bubble = document.createElement('div');
        bubble.className = cn('chat-bubble', role === 'user' ? 'chat-bubble-user' : 'chat-bubble-assistant');
        bubble.textContent = text;
        if (typing) bubble.dataset.typing = 'true';
        row.appendChild(bubble);
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
        return row;
    };

    const submit = async (message) => {
        const text = message.trim();
        if (!text || send.disabled) return;
        error.classList.add('hidden');
        addBubble(text, 'user');
        input.value = '';
        input.style.height = 'auto';
        send.disabled = true;
        input.disabled = true;
        const typing = addBubble(ui.chat?.typing || 'Sedang mengetik…', 'assistant', true);
        try {
            const payload = await fetchJson('/api/v1/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text, locale: ui.locale || 'id' }),
            });
            typing.remove();
            addBubble(payload.data.answer, 'assistant');
        } catch (exception) {
            typing.remove();
            error.textContent = exception.message || ui.chat?.sendError || 'Pesan belum dapat dikirim. Coba lagi.';
            error.classList.remove('hidden');
        } finally {
            send.disabled = false;
            input.disabled = false;
            input.focus();
        }
    };

    form?.addEventListener('submit', event => { event.preventDefault(); submit(input.value); });
    input?.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); submit(input.value); }
    });
    input?.addEventListener('input', () => { input.style.height = 'auto'; input.style.height = `${Math.min(input.scrollHeight, 128)}px`; });
    document.querySelectorAll('[data-quick-question]').forEach(button => button.addEventListener('click', () => submit(button.dataset.quickQuestion)));
}
