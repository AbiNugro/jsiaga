Tes otomatisasi GitHub Actions ke VPS

# J-SIAGA

J-SIAGA adalah aplikasi monitoring banjir berbasis Laravel yang menerima pembacaan ESP melalui HiveMQ, menghitung ulang status banjir di server, menyimpan riwayat sensor, dan menyajikan dashboard, rekomendasi keselamatan, serta chatbot hibrida lokal/Groq.

Antarmuka mendukung Bahasa Indonesia, English, dan 한국어 melalui pemilih bahasa pada header mobile atau sidebar desktop. Pilihan disimpan di session dan juga diterapkan pada jawaban chatbot lokal serta bahasa respons Groq.

## Arsitektur

```text
ESP 1 + ESP 2 → HiveMQ → MQTT bridge → Laravel API → SQLite → Dashboard Laravel
                              └──── status jembatan → ESP 2
```

Untuk pengujian lokal, proses `scripts/mqtt-bridge.mjs` menggantikan peran penerima data pada contoh `arman tes`: bridge berlangganan topik ESP1 dan ESP2, meneruskan telemetri ke Laravel, lalu mengirim status hasil perhitungan Laravel kembali ke servo ESP2. Kredensial HiveMQ hanya dibaca dari `.env` dan tidak pernah dikirim ke browser.

Node-RED tetap dapat dipakai sebagai dashboard/otomasi tambahan, tetapi tidak wajib untuk membuat website lokal menerima data HiveMQ.

## Requirement

- PHP 8.3 atau lebih baru
- Composer 2
- Node.js 20 atau lebih baru (dikembangkan dengan Node.js 22)
- npm 10 atau lebih baru
- Ekstensi PHP SQLite untuk pengembangan
- Akun HiveMQ Cloud dan credential MQTT perangkat

Versi utama proyek saat ini: Laravel 13, Tailwind CSS 4, Vite 8, dan Chart.js 4.

## Instalasi

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Pada Windows PowerShell gunakan:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Pastikan file SQLite tersedia, lalu jalankan migration:

```bash
php artisan migrate
```

Skeleton proyek sudah menyertakan `database/database.sqlite`. Migration tetap menggunakan tipe kolom portabel agar kompatibel dengan MySQL dan PostgreSQL/Supabase PostgreSQL.

## Konfigurasi `.env`

```dotenv
APP_NAME=J-SIAGA
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=sqlite

JSIAGA_DEVICE_TOKEN=ganti-dengan-token-panjang-dan-acak
JSIAGA_OFFLINE_SECONDS=15

MQTT_HOST=cluster-anda.s1.eu.hivemq.cloud
MQTT_PORT=8883
MQTT_USERNAME=jsiaga_device
MQTT_PASSWORD=password-hivemq-anda

GROQ_API_KEY=
GROQ_MODEL=llama-3.1-8b-instant
GROQ_TIMEOUT=10
```

`JSIAGA_DEVICE_TOKEN` wajib diisi dan digunakan oleh bridge lokal saat mengirim data ke Laravel. Isi `MQTT_HOST`, `MQTT_USERNAME`, dan `MQTT_PASSWORD` dengan credential HiveMQ yang sama dengan ESP. `GROQ_API_KEY` opsional; status, rekomendasi lokal, dan pertanyaan sensor tetap bekerja tanpa Groq. Jangan commit `.env` atau menaruh secret di JavaScript.

Jika konfigurasi baru belum terbaca:

```bash
php artisan config:clear
```

## Data demo

Command berikut membuat rangkaian SAFE, WARNING, DANGER, dan FLOOD dengan waktu pencatatan yang berbeda:

```bash
php artisan jsiaga:seed-demo
```

Command ini hanya dijalankan dari CLI dan tidak diekspos sebagai tombol publik.

## Menjalankan aplikasi secara lokal dengan HiveMQ

Cara paling mudah adalah menjalankan Laravel, Vite, dan bridge MQTT sekaligus:

```bash
npm run local:mqtt
```

Perintah tersebut menjalankan Laravel pada `0.0.0.0:8000`. Saat berhasil, terminal menampilkan `[HiveMQ] Terhubung`. Ketika ESP1 mengirim data, terminal menampilkan level air dan status hasil Laravel. Di laptop yang sama buka `http://127.0.0.1:8000`; dari perangkat lain dalam Wi-Fi yang sama gunakan `http://IP_LAPTOP:8000`. Data akan dianggap online selama ESP terus mengirim.

Jika ingin menjalankan tiap proses secara terpisah, gunakan tiga terminal.

Terminal pertama:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Terminal kedua untuk pengembangan frontend:

```bash
npm run dev
```

Terminal ketiga untuk penerima HiveMQ:

```bash
npm run mqtt:bridge
```

Atau jalankan seluruh proses pengembangan—server, queue, log, Vite, dan scheduler—dengan satu command:

```bash
composer run dev
```

Production build:

```bash
npm run build
```

Halaman web:

- Beranda: `http://127.0.0.1:8000/`
- Riwayat: `http://127.0.0.1:8000/riwayat`
- Rekomendasi: `http://127.0.0.1:8000/rekomendasi`
- Chatbot: `http://127.0.0.1:8000/chatbot`

## API

| Method | Endpoint                                   | Keterangan                                  |
| ------ | ------------------------------------------ | ------------------------------------------- |
| POST   | `/api/v1/sensor-readings`                  | Simpan sensor, wajib `X-Device-Token`       |
| GET    | `/api/v1/sensor-readings/latest`           | Pembacaan terbaru                           |
| GET    | `/api/v1/sensor-readings/history?range=1h` | Riwayat; range `1h`, `6h`, `24h`, atau `7d` |
| POST   | `/api/v1/chat`                             | Chat lokal/Groq                             |
| POST   | `/api/v1/recommendations/explain`          | Penjelasan Groq opsional                    |

Contoh pengiriman sensor:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/sensor-readings \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Device-Token: TOKEN_ANDA" \
  -d '{
    "distance": 15,
    "temperature": 29,
    "humidity": 72,
    "light": 430
  }'
```

Laravel mengabaikan `status` dan `water_level` dari client lalu menghitung ulang:

- `distance > 8.5 cm` → SAFE
- `distance > 6.8 cm` dan `distance <= 8.5 cm` → WARNING
- `distance >= 6.5 cm` dan `distance <= 6.8 cm` → DANGER
- `distance < 6.5 cm` → FLOOD
- `water_level = clamp(round(((12 - distance) / (12 - 6)) × 100), 0, 100)`
- Jarak `12 cm` berarti level `0%`, sedangkan jarak `6 cm` atau kurang berarti level `100%`.

## Node-RED opsional

Jika tetap ingin memakai Node-RED, flow lama masih tersedia. Jangan jalankan bridge Node-RED dan `npm run mqtt:bridge` bersamaan karena keduanya akan menyimpan telemetri yang sama.

1. Jalankan Laravel dengan `--host=0.0.0.0` agar dapat diakses dari perangkat jaringan.
2. Import `node-red-laravel-bridge.json` melalui menu **Import** Node-RED.
3. Atur environment Node-RED:
    - `JSIAGA_DEVICE_TOKEN` sama dengan `.env` Laravel.
    - `JSIAGA_LARAVEL_URL`, misalnya `http://192.168.1.10:8000`.
4. Pada flow asli, cari node **Process Sensor + Auto Servo IP203**.
5. Tambahkan cabang wire baru dari **output pertama (status)** menuju **Prepare Laravel Payload** di flow bridge.
6. Jangan melepas wire lama dan jangan mengubah output servo ke ESP2.
7. Deploy, lalu periksa node debug **Laravel Bridge Result**.

Bridge dipicu setelah node Process Sensor. Ia membaca `flow.jsiaga_latest`, membuat payload Laravel, memberi timeout HTTP 10 detik, dan menangani error di cabang sendiri sehingga servo tetap berjalan.

### Mengetahui IP laptop

Windows:

```powershell
ipconfig
```

Cari **IPv4 Address** pada adaptor Wi-Fi/Ethernet aktif. Ganti `IP_LAPTOP` atau isi `JSIAGA_LARAVEL_URL` dengan IP tersebut. Pastikan firewall mengizinkan port 8000 pada jaringan privat.

Linux/macOS:

```bash
ip addr
```

## Chatbot dan Groq

Chatbot menjawab sapaan, status, ringkasan sensor, jarak, level air, suhu, kelembapan, LDR, batas status, tindakan, dan waktu pembaruan langsung dari database. Pertanyaan bebas diteruskan ke Groq dari backend bila API key tersedia.

Groq tidak pernah menentukan status. Prompt selalu menerima status hasil Laravel dan larangan mengarang data. Bila Groq tidak dikonfigurasi, timeout, 401, 429, 5xx, atau mengirim respons tidak valid, API mengembalikan fallback lokal tanpa membocorkan key.

## Testing dan bukti UI

```bash
php artisan test
npm run build
```

Konfigurasi `ui-evidence` berada di `ui-evidence/config.yaml` dan mencakup 360×800, 390×844, 768×1024, 1366×768, serta 1440×900.

```bash
npx ui-evidence doctor --config ui-evidence/config.yaml
npx ui-evidence snapshot --config ui-evidence/config.yaml --stage aplikasi-jsiaga
npx ui-evidence review --config ui-evidence/config.yaml --stage aplikasi-jsiaga
```

## Troubleshooting

### Token sensor ditolak

- Pastikan header bernama persis `X-Device-Token`.
- Pastikan nilai pada Node-RED sama dengan `JSIAGA_DEVICE_TOKEN` Laravel.
- Jalankan `php artisan config:clear` setelah mengubah `.env`.
- Respons 503 berarti token belum dikonfigurasi di server; 401 berarti token salah.

### Node-RED gagal mengirim

- Pastikan Laravel berjalan pada `0.0.0.0:8000`, bukan hanya loopback.
- Uji `http://IP_LAPTOP:8000/up` dari perangkat lain.
- Periksa `JSIAGA_LARAVEL_URL`, firewall, dan debug node bridge.
- Pastikan wire bridge berasal dari output pertama Process Sensor, bukan menggantikan wire servo.

### Data tidak muncul

- Buka debug **Laravel Bridge Result** dan cari status HTTP 201.
- Jalankan `php artisan migrate:status`.
- Periksa endpoint `/api/v1/sensor-readings/latest`.
- Gunakan `php artisan jsiaga:seed-demo` untuk memastikan dashboard dan database bekerja tanpa perangkat.

### Groq tidak tersedia

- Pastikan `GROQ_API_KEY` dan `GROQ_MODEL` di `.env` benar.
- Jalankan `php artisan config:clear`.
- Periksa koneksi internet serta kuota/rate limit Groq.
- Rekomendasi sistem dan jawaban sensor lokal tetap dapat digunakan saat Groq gagal.

## File penting

- `app/Services/FloodStatusService.php` — aturan status dan water level.
- `app/Http/Controllers/Api/SensorReadingController.php` — endpoint sensor.
- `app/Services/LocalChatbotService.php` — jawaban lokal berbasis database.
- `app/Services/GroqService.php` — akses Groq backend dengan timeout/fallback.
- `resources/views/pages/` — Beranda, Riwayat, Rekomendasi, dan Chatbot.
- `resources/js/app.js` — polling 5 detik, Chart.js, dan interaksi chatbot.
- `node-red-laravel-bridge.json` — flow tambahan yang aman untuk Laravel.
