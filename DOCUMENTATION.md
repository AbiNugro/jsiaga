# Dokumentasi Operasional J-SIAGA

Dokumen ini berisi panduan menjalankan, menghentikan, melakukan maintenance, memperbarui, dan memeriksa aplikasi J-SIAGA di VPS.

## Informasi layanan

- Direktori aplikasi: `/var/www/jsiaga`
- Website produksi: `https://jsiaga.me`
- Nama proses Supervisor: `jsiaga-mqtt`
- Nama proses scheduler Supervisor: `jsiaga-scheduler`
- Konfigurasi Supervisor: `/etc/supervisor/conf.d/jsiaga-mqtt.conf`
- Log MQTT: `/var/log/jsiaga-mqtt.log`
- Log error MQTT: `/var/log/jsiaga-mqtt-error.log`
- Log Laravel: `/var/www/jsiaga/storage/logs/laravel.log`

J-SIAGA memiliki dua bagian yang perlu diperhatikan saat maintenance:

1. Website dan API Laravel.
2. MQTT bridge yang menerima data ESP dari HiveMQ lalu mengirimkannya ke Laravel.

Perintah `php artisan down` hanya menonaktifkan website Laravel. Perintah tersebut tidak menghentikan MQTT bridge.

## Masuk ke direktori aplikasi

Jalankan perintah berikut setelah masuk ke VPS melalui SSH:

```bash
cd /var/www/jsiaga
```

## Memeriksa status layanan

Periksa status MQTT bridge:

```bash
sudo supervisorctl status
```

Kondisi normal akan menampilkan status seperti berikut:

```text
jsiaga-mqtt    RUNNING
```

Periksa informasi aplikasi Laravel:

```bash
cd /var/www/jsiaga
php artisan about
```

## Maintenance penuh

Gunakan prosedur ini apabila website, API, dan pengiriman data sensor harus dihentikan sementara.

### Mengaktifkan mode maintenance

Hentikan MQTT bridge terlebih dahulu:

```bash
sudo supervisorctl stop jsiaga-mqtt
```

Kemudian aktifkan mode maintenance Laravel:

```bash
cd /var/www/jsiaga
php artisan down
```

Periksa hasilnya:

```bash
sudo supervisorctl status
```

Status MQTT bridge seharusnya menjadi `STOPPED`.

### Menonaktifkan mode maintenance

Aktifkan kembali website Laravel:

```bash
cd /var/www/jsiaga
php artisan up
```

Kemudian jalankan kembali MQTT bridge:

```bash
sudo supervisorctl start jsiaga-mqtt
sudo supervisorctl status
```

Status akhirnya harus menjadi `RUNNING`.

## Mengelola MQTT bridge

### Menjalankan bridge

```bash
sudo supervisorctl start jsiaga-mqtt
```

### Menghentikan bridge

```bash
sudo supervisorctl stop jsiaga-mqtt
```

Penghentian ini bersifat sementara. Karena konfigurasi menggunakan `autostart=true`, bridge akan dijalankan kembali ketika Supervisor atau VPS dinyalakan ulang.

### Memulai ulang bridge

Gunakan setelah mengubah `.env` atau `scripts/mqtt-bridge.mjs`:

```bash
sudo supervisorctl restart jsiaga-mqtt
sudo supervisorctl status
```

### Membaca ulang konfigurasi Supervisor

Gunakan setelah mengubah `/etc/supervisor/conf.d/jsiaga-mqtt.conf`:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart jsiaga-mqtt
sudo supervisorctl status
```

### Menonaktifkan autostart

Buka konfigurasi Supervisor:

```bash
sudo nano /etc/supervisor/conf.d/jsiaga-mqtt.conf
```

Ubah:

```ini
autostart=true
```

menjadi:

```ini
autostart=false
```

Simpan perubahan, kemudian jalankan:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl stop jsiaga-mqtt
```

### Mengaktifkan kembali autostart

Ubah kembali konfigurasi menjadi:

```ini
autostart=true
```

Kemudian jalankan:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start jsiaga-mqtt
sudo supervisorctl status
```

## Perubahan konfigurasi `.env`

Setelah mengubah `.env`, bersihkan dan buat ulang cache Laravel, lalu restart MQTT bridge:

```bash
cd /var/www/jsiaga
php artisan optimize:clear
php artisan optimize
sudo supervisorctl restart jsiaga-mqtt
sudo supervisorctl status
```

Jangan menampilkan isi lengkap `.env` di terminal publik, tangkapan layar, repositori Git, atau percakapan karena file tersebut mengandung kredensial.

## Notifikasi Telegram

J-SIAGA dapat mengirim notifikasi Telegram kepada seluruh pengguna yang berlangganan saat status sensor berubah menjadi `WARNING`, `DANGER`, atau `FLOOD`. Ketika kondisi berubah dari status tersebut kembali menjadi `SAFE`, sistem juga mengirimkan notifikasi pemulihan. Pembacaan berulang dengan status yang sama tidak menghasilkan pesan baru.

Kegagalan Telegram tidak menghentikan penyimpanan data sensor. Detail kegagalan dicatat di log Laravel tanpa mencatat token bot.

Ketika data sensor berhenti melewati batas `JSIAGA_OFFLINE_SECONDS`, sistem mengirim satu notifikasi `OFFLINE` lalu tidak mengulangnya. Ketika data kembali masuk, sistem mengirim notifikasi pemulihan dan melanjutkan peringatan status. Fitur ini membutuhkan Laravel scheduler yang aktif.

### Membuat bot Telegram

1. Buka akun resmi `@BotFather` di Telegram.
2. Kirim perintah `/newbot` dan ikuti petunjuknya.
3. Simpan bot token yang diberikan.
4. Simpan username bot agar tautannya dapat dibagikan kepada pengguna.

Bot tidak dapat memulai percakapan secara sepihak. Setiap pengguna harus membuka bot dan menekan **Start** atau mengirim `/start` satu kali sebelum dapat menerima notifikasi.

### Mengaktifkan webhook dan database pelanggan

Pastikan perubahan kode terbaru sudah berada di VPS, lalu buat tabel pelanggan:

```bash
cd /var/www/jsiaga
php artisan migrate --force
```

Buka `.env` produksi:

```bash
cd /var/www/jsiaga
nano .env
```

Isi konfigurasi berikut:

```dotenv
TELEGRAM_NOTIFICATIONS_ENABLED=true
TELEGRAM_BOT_TOKEN=isi_token_dari_botfather
TELEGRAM_WEBHOOK_SECRET=buat_teks_acak_minimal_32_karakter
TELEGRAM_NOTIFY_STATUSES=WARNING,DANGER,FLOOD,SAFE
TELEGRAM_TIMEOUT=5
```

Webhook secret hanya boleh berisi huruf, angka, garis bawah, dan tanda minus. Buat secret acak dengan:

```bash
openssl rand -hex 32
```

Jangan mengirim bot token atau webhook secret melalui tangkapan layar dan jangan menyimpannya di Git.

Terapkan konfigurasi Laravel:

```bash
php artisan optimize:clear
php artisan optimize
```

Daftarkan webhook Telegram:

```bash
php artisan jsiaga:telegram-set-webhook
```

Webhook yang didaftarkan adalah:

```text
https://jsiaga.me/api/telegram/webhook
```

### Cara pengguna berlangganan

Bagikan tautan bot kepada semua pengguna:

```text
https://t.me/USERNAME_BOT
```

Setiap pengguna kemudian:

1. Membuka tautan bot.
2. Menekan **Start** atau mengirim `/start`.
3. Menerima konfirmasi bahwa notifikasi J-SIAGA sudah aktif.

Perintah yang tersedia:

- `/start` untuk mengaktifkan atau mengaktifkan kembali notifikasi.
- `/stop` untuk berhenti menerima notifikasi.
- `/bahasa` atau `/language` untuk memilih Bahasa Indonesia, English, atau 한국어.
- `/help` untuk melihat bantuan.

Pilihan bahasa disimpan untuk setiap pengguna. Pesan bot dan peringatan status sensor berikutnya akan menggunakan bahasa yang dipilih pengguna tersebut. Pengguna lama menggunakan Bahasa Indonesia sampai memilih bahasa lain.

Chat ID masing-masing pengguna disimpan otomatis di database. Tidak ada `TELEGRAM_CHAT_ID` tunggal di `.env`.

Periksa jumlah pelanggan:

```bash
php artisan jsiaga:telegram-subscribers
```

### Menguji broadcast

Setelah sedikitnya satu pengguna mengirim `/start`, jalankan:

```bash
php artisan jsiaga:test-telegram
```

Setelah menambahkan atau mengubah menu bahasa, jalankan kembali perintah berikut agar webhook menerima klik tombol dan menu perintah bot diperbarui:

```bash
php artisan jsiaga:telegram-set-webhook
```

Hasil yang benar:

```text
Pesan tes Telegram berhasil dikirim.
```

### Mengubah status yang mengirim notifikasi

Nilai bawaan:

```dotenv
TELEGRAM_NOTIFY_STATUSES=WARNING,DANGER,FLOOD,SAFE
```

Hapus `SAFE` apabila notifikasi pemulihan tidak diperlukan. Setelah mengubahnya, jalankan kembali:

```bash
php artisan optimize:clear
php artisan optimize
```

### Menonaktifkan notifikasi Telegram

Ubah `.env` menjadi:

```dotenv
TELEGRAM_NOTIFICATIONS_ENABLED=false
```

Kemudian jalankan:

```bash
php artisan optimize:clear
php artisan optimize
```

### Memeriksa kegagalan notifikasi

```bash
tail -n 100 /var/www/jsiaga/storage/logs/laravel.log
```

Penyebab yang umum adalah token salah, webhook secret tidak cocok, belum ada pengguna yang mengirim `/start`, bot diblokir pengguna, atau koneksi keluar HTTPS dari VPS bermasalah.

### Menjalankan pemantauan OFFLINE otomatis

Buat konfigurasi Supervisor:

```bash
sudo nano /etc/supervisor/conf.d/jsiaga-scheduler.conf
```

Isi:

```ini
[program:jsiaga-scheduler]
directory=/var/www/jsiaga
command=/usr/bin/php /var/www/jsiaga/artisan schedule:work
user=justdeano
autostart=true
autorestart=true
startsecs=5
stopasgroup=true
killasgroup=true
stdout_logfile=/var/log/jsiaga-scheduler.log
stderr_logfile=/var/log/jsiaga-scheduler-error.log
environment=APP_ENV="production"
```

Aktifkan prosesnya:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start jsiaga-scheduler
sudo supervisorctl status
```

Status normal:

```text
jsiaga-mqtt        RUNNING
jsiaga-scheduler   RUNNING
```

Uji pemantauan secara manual:

```bash
php artisan jsiaga:monitor-sensor
```

Perintah manual hanya mengirim apabila data terbaru memang sudah kedaluwarsa dan notifikasi OFFLINE belum pernah dikirim untuk kejadian tersebut.

Log scheduler:

```bash
sudo tail -f /var/log/jsiaga-scheduler.log
sudo tail -f /var/log/jsiaga-scheduler-error.log
```

## Prosedur deployment

### 1. Aktifkan maintenance

```bash
cd /var/www/jsiaga
sudo supervisorctl stop jsiaga-mqtt
php artisan down
```

### 2. Perbarui kode

Jika deployment menggunakan Git:

```bash
git pull
```

### 3. Perbarui dependency dan aset

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### 4. Perbarui database dan cache

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

### 5. Aktifkan kembali layanan

```bash
php artisan up
sudo supervisorctl start jsiaga-mqtt
sudo supervisorctl status
```

### 6. Periksa log

```bash
sudo tail -n 50 /var/log/jsiaga-mqtt.log
sudo tail -n 50 /var/log/jsiaga-mqtt-error.log
tail -n 50 /var/www/jsiaga/storage/logs/laravel.log
```

Pastikan `jsiaga-mqtt` berstatus `RUNNING` dan tidak terdapat error baru.

## Melihat log secara langsung

### Aktivitas MQTT bridge

```bash
sudo tail -f /var/log/jsiaga-mqtt.log
```

Log normal antara lain berisi:

```text
[HiveMQ] Terhubung
[ESP1 -> Laravel] level ...
[ESP2] Light ...
```

### Error MQTT bridge

```bash
sudo tail -f /var/log/jsiaga-mqtt-error.log
```

### Log Laravel

```bash
cd /var/www/jsiaga
tail -f storage/logs/laravel.log
```

Tekan `Ctrl+C` untuk berhenti melihat log. Tindakan ini hanya menutup tampilan log dan tidak menghentikan MQTT bridge.

## Pengujian manual MQTT bridge

Pengujian manual hanya digunakan untuk diagnosis ketika proses Supervisor sedang dihentikan:

```bash
sudo supervisorctl stop jsiaga-mqtt
cd /var/www/jsiaga
timeout 20s /usr/bin/node --env-file=/var/www/jsiaga/.env scripts/mqtt-bridge.mjs
```

Setelah pengujian selesai, aktifkan kembali proses Supervisor:

```bash
sudo supervisorctl start jsiaga-mqtt
sudo supervisorctl status
```

Jangan menjalankan bridge manual bersamaan dengan bridge Supervisor atau bridge dari komputer lokal karena data dapat terkirim lebih dari sekali.

## Pemecahan masalah

### Status `BACKOFF`, `FATAL`, atau `EXITED`

```bash
sudo supervisorctl status
sudo tail -n 100 /var/log/jsiaga-mqtt-error.log
sudo tail -n 100 /var/log/jsiaga-mqtt.log
```

Setelah masalah diperbaiki:

```bash
sudo supervisorctl restart jsiaga-mqtt
sudo supervisorctl status
```

### Package Node.js tidak ditemukan

Jika log menampilkan bahwa package `mqtt` tidak ditemukan:

```bash
cd /var/www/jsiaga
npm ci --omit=dev
sudo supervisorctl restart jsiaga-mqtt
```

### Respons Laravel `401 Unauthorized`

Pastikan token perangkat di `.env` digunakan oleh Laravel dan MQTT bridge yang sama. Kemudian jalankan:

```bash
cd /var/www/jsiaga
php artisan optimize:clear
php artisan optimize
sudo supervisorctl restart jsiaga-mqtt
```

### Bridge terhubung tetapi data tidak masuk

Periksa konfigurasi non-rahasia berikut:

```bash
cd /var/www/jsiaga
grep -E '^(APP_URL|JSIAGA_API_URL|MQTT_HOST|MQTT_PORT)=' .env
```

Kemudian periksa log bridge dan Laravel:

```bash
sudo tail -n 100 /var/log/jsiaga-mqtt.log
sudo tail -n 100 /var/log/jsiaga-mqtt-error.log
tail -n 100 storage/logs/laravel.log
```

### Memeriksa Supervisor setelah VPS restart

```bash
sudo systemctl status supervisor
sudo supervisorctl status
```

Jika Supervisor tidak aktif:

```bash
sudo systemctl enable --now supervisor
sudo supervisorctl status
```

## Ringkasan perintah harian

Maintenance aktif:

```bash
cd /var/www/jsiaga
sudo supervisorctl stop jsiaga-mqtt
php artisan down
```

Maintenance selesai:

```bash
cd /var/www/jsiaga
php artisan up
sudo supervisorctl start jsiaga-mqtt
sudo supervisorctl status
```

Restart setelah perubahan `.env`:

```bash
cd /var/www/jsiaga
php artisan optimize:clear
php artisan optimize
sudo supervisorctl restart jsiaga-mqtt
sudo supervisorctl status
```

Keluar dari SSH tidak menghentikan bridge. Selama Supervisor berstatus aktif dan `jsiaga-mqtt` berstatus `RUNNING`, bridge akan tetap berjalan di latar belakang.
