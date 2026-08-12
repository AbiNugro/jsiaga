import mqtt from 'mqtt';

const requiredEnvironment = [
    'MQTT_HOST',
    'MQTT_USERNAME',
    'MQTT_PASSWORD',
    'JSIAGA_DEVICE_TOKEN',
];

const missingEnvironment = requiredEnvironment.filter((key) => !process.env[key]?.trim());

if (missingEnvironment.length > 0) {
    console.error(`Konfigurasi belum lengkap: ${missingEnvironment.join(', ')}`);
    console.error('Isi variabel tersebut di file .env, lalu jalankan ulang npm run local:mqtt.');
    process.exit(1);
}

const topics = {
    telemetry: process.env.MQTT_TOPIC_TELEMETRY || 'jsiaga/v1/esp1/telemetry',
    light: process.env.MQTT_TOPIC_LIGHT || 'jsiaga/v1/esp2/light',
    statusSet: process.env.MQTT_TOPIC_STATUS_SET || 'jsiaga/v1/esp2/status/set',
    statusState: process.env.MQTT_TOPIC_STATUS_STATE || 'jsiaga/v1/esp2/status/state',
};

const appUrl = (process.env.APP_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const apiUrl = process.env.JSIAGA_API_URL || `${appUrl}/api/v1/sensor-readings`;
const mqttPort = Number(process.env.MQTT_PORT || 8883);
const clientId = `jsiaga-web-${process.pid}-${Math.random().toString(16).slice(2, 10)}`;

let latestLight = null;
let ingestQueue = Promise.resolve();

const client = mqtt.connect(`mqtts://${process.env.MQTT_HOST}:${mqttPort}`, {
    clientId,
    username: process.env.MQTT_USERNAME,
    password: process.env.MQTT_PASSWORD,
    clean: true,
    connectTimeout: 10_000,
    reconnectPeriod: 3_000,
    rejectUnauthorized: true,
});

function parseObject(message) {
    const text = message.toString('utf8').trim();

    try {
        const parsed = JSON.parse(text);
        return typeof parsed === 'object' && parsed !== null ? parsed : { value: parsed };
    } catch {
        return { value: text };
    }
}

function finiteNumber(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}

async function forwardTelemetry(message) {
    const telemetry = parseObject(message);
    const distance = finiteNumber(telemetry.distance ?? telemetry.value);

    if (distance === null) {
        console.warn('[ESP1] Data diabaikan karena distance tidak valid.');
        return;
    }

    const payload = {
        distance,
        temperature: finiteNumber(telemetry.temperature),
        humidity: finiteNumber(telemetry.humidity),
        light: finiteNumber(telemetry.light) ?? latestLight,
    };

    const response = await fetch(apiUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Device-Token': process.env.JSIAGA_DEVICE_TOKEN,
        },
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(10_000),
    });

    if (!response.ok) {
        const body = await response.text();
        throw new Error(`Laravel HTTP ${response.status}: ${body.slice(0, 300)}`);
    }

    const result = await response.json();
    const status = result?.data?.status;

    if (!['SAFE', 'WARNING', 'DANGER', 'FLOOD'].includes(status)) {
        throw new Error('Laravel tidak mengembalikan status sensor yang valid.');
    }

    // ESP2 menggunakan posisi jembatan DANGER juga ketika status sudah FLOOD.
    const servoStatus = status === 'FLOOD' ? 'DANGER' : status;
    client.publish(topics.statusSet, servoStatus, { qos: 1, retain: true });

    console.log(
        `[ESP1 -> Laravel] level ${result.data.water_level}% | ${status} | servo ${servoStatus}`,
    );
}

client.on('connect', () => {
    console.log(`[HiveMQ] Terhubung ke ${process.env.MQTT_HOST}:${mqttPort}`);

    client.subscribe(
        [topics.telemetry, topics.light, topics.statusState],
        { qos: 1 },
        (error) => {
            if (error) {
                console.error(`[HiveMQ] Gagal subscribe: ${error.message}`);
                return;
            }

            console.log(`[HiveMQ] Menunggu ESP1 pada ${topics.telemetry}`);
            console.log(`[HiveMQ] Menunggu ESP2 pada ${topics.light}`);
        },
    );
});

client.on('message', (topic, message) => {
    if (topic === topics.light) {
        const payload = parseObject(message);
        const value = finiteNumber(payload.light ?? payload.value);

        if (value === null) {
            console.warn('[ESP2] Data light diabaikan karena tidak valid.');
            return;
        }

        latestLight = Math.round(value);
        console.log(`[ESP2] Light ${latestLight}`);
        return;
    }

    if (topic === topics.statusState) {
        console.log(`[ESP2] Konfirmasi servo: ${message.toString('utf8').trim()}`);
        return;
    }

    if (topic === topics.telemetry) {
        ingestQueue = ingestQueue
            .then(() => forwardTelemetry(message))
            .catch((error) => console.error(`[Bridge] ${error.message}`));
    }
});

client.on('reconnect', () => console.warn('[HiveMQ] Mencoba menyambung kembali...'));
client.on('offline', () => console.warn('[HiveMQ] Koneksi sedang offline.'));
client.on('error', (error) => console.error(`[HiveMQ] ${error.message}`));

function shutdown() {
    console.log('\n[Bridge] Menghentikan koneksi MQTT...');
    client.end(false, () => process.exit(0));
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
