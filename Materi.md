# Materi Proyek J-SIAGA

## 1. Gambaran Umum

J-SIAGA adalah sistem pemantauan dan peringatan dini banjir berbasis Internet of Things (IoT). Sistem menerima pembacaan sensor dari perangkat ESP melalui HiveMQ, menghitung tingkat risiko di server Laravel, menyimpan riwayat pengukuran, menampilkan kondisi melalui website, mengendalikan servo pada perangkat ESP2, dan mengirimkan peringatan kepada masyarakat melalui Telegram.

Website produksi dapat diakses melalui:

```text
https://jsiaga.me
```

J-SIAGA dirancang agar pengguna nonteknis dapat memahami kondisi sungai atau miniatur pemantauan tanpa harus membaca data mentah sensor.

## 2. Tujuan Sistem

Tujuan utama J-SIAGA adalah:

- Memantau perubahan level air secara real-time.
- Mengubah data sensor menjadi status yang mudah dipahami.
- Memberikan peringatan saat kondisi memburuk.
- Menyediakan rekomendasi keselamatan berdasarkan status terkini.
- Menyimpan riwayat data untuk analisis.
- Menjangkau banyak pengguna melalui website dan Telegram.
- Tetap menyediakan informasi dasar ketika layanan AI tidak tersedia.

## 3. Arsitektur Sistem

Alur utama sistem:

```text
ESP1 dan ESP2
      |
      v
HiveMQ Cloud (MQTT/TLS)
      |
      v
Node.js MQTT Bridge
      |
      v
Laravel API --> Perhitungan status --> Database
      |                                  |
      |                                  v
      |                           Website J-SIAGA
      |
      +--> Status servo --> HiveMQ --> ESP2
      |
      +--> Telegram Bot API --> Seluruh pelanggan aktif
```

Komponen utamanya adalah:

1. ESP1 mengirim telemetri jarak air, suhu, dan kelembapan.
2. ESP2 mengirim data cahaya dan menerima perintah status servo.
3. HiveMQ menjadi broker MQTT antara perangkat dan server.
4. MQTT bridge meneruskan data dari HiveMQ ke Laravel.
5. Laravel memvalidasi data dan menghitung status resmi.
6. Database menyimpan pembacaan sensor dan pelanggan Telegram.
7. Website menyajikan informasi kepada pengguna.
8. Telegram menyebarkan notifikasi perubahan kondisi.

Node-RED tidak lagi menjadi komponen wajib. Flow Node-RED lama masih dapat dipakai sebagai alat tambahan, tetapi tidak boleh dijalankan bersamaan dengan MQTT bridge apabila keduanya mengirim data yang sama.

## 4. Data Sensor

Data yang dapat diterima J-SIAGA meliputi:

| Data | Keterangan |
| --- | --- |
| Jarak | Jarak sensor ultrasonik terhadap permukaan air dalam sentimeter |
| Level sensor | Persentase level air dari 0% sampai 100% |
| Suhu | Temperatur lingkungan dalam derajat Celsius |
| Kelembapan | Kelembapan lingkungan dalam persen |
| Cahaya | Nilai pembacaan sensor cahaya/LDR |
| Waktu | Waktu pembacaan sensor |

Jarak sensor tidak ditampilkan sebagai indikator utama kepada pengguna Telegram. Informasi tersebut dikonversi menjadi `Sensor level` atau `Level sensor` dalam skala 0%–100% agar lebih mudah dipahami.

## 5. Perhitungan Level Air

Sensor ultrasonik membaca jarak dari sensor menuju permukaan air. Semakin kecil jaraknya, semakin tinggi airnya.

Rumus yang digunakan:

```text
level = ((12 - jarak) / (12 - 6)) × 100
```

Hasil dibulatkan dan dibatasi ke rentang 0%–100%.

Contoh:

| Jarak sensor | Level air |
| --- | ---: |
| 12 cm atau lebih | 0% |
| 9 cm | 50% |
| 7,5 cm | 75% |
| 6 cm atau kurang | 100% |

## 6. Klasifikasi Status Banjir

Status dihitung di Laravel sehingga perangkat atau pengirim data tidak dapat menentukan status secara sepihak.

| Kondisi jarak | Status | Makna |
| --- | --- | --- |
| Lebih dari 8,5 cm | `SAFE` | Kondisi aman |
| Lebih dari 6,8 cm sampai 8,5 cm | `WARNING` | Kondisi waspada |
| 6,5 cm sampai 6,8 cm | `DANGER` | Kondisi berbahaya |
| Kurang dari 6,5 cm | `FLOOD` | Banjir terdeteksi |
| Data tidak diperbarui melewati batas waktu | `OFFLINE` | Sensor atau aliran data terputus |

Nilai status dan level yang dikirim klien diabaikan. Laravel menghitung ulang keduanya berdasarkan jarak sensor yang sudah divalidasi.

## 7. Fitur Website

### 7.1 Beranda

Beranda memberikan gambaran kondisi terbaru dalam satu tampilan. Fitur yang tersedia:

- Status terkini: `SAFE`, `WARNING`, `DANGER`, `FLOOD`, atau `OFFLINE`.
- Indikator level air 0%–100%.
- Gauge visual level air.
- Data suhu, kelembapan, dan kondisi cahaya.
- Waktu pembaruan terakhir.
- Grafik tren pembacaan terbaru.
- Rekomendasi singkat berdasarkan kondisi terkini.
- Peringatan visual saat status tidak aman atau sensor offline.
- Pembaruan data otomatis tanpa reload halaman penuh.

### 7.2 Peringatan Browser dan Suara

Website memiliki sistem peringatan di sisi pengguna:

- Menampilkan banner peringatan untuk `WARNING`, `DANGER`, `FLOOD`, dan `OFFLINE`.
- Membacakan peringatan menggunakan speech synthesis browser apabila diaktifkan pengguna.
- Menghindari pengulangan suara jika status tidak berubah.
- Memberikan pemberitahuan pemulihan saat status kembali aman.
- Menyesuaikan isi peringatan dengan bahasa website.

### 7.3 Riwayat Sensor

Halaman Riwayat menyediakan:

- Filter waktu `1 jam`, `6 jam`, `24 jam`, dan `7 hari`.
- Grafik level air menggunakan Chart.js.
- Ringkasan level tertinggi.
- Ringkasan level rata-rata.
- Jumlah kejadian `WARNING`.
- Jumlah kejadian `DANGER` dan `FLOOD`.
- Daftar pembacaan dalam bentuk kartu pada perangkat seluler.
- Tabel data pada layar desktop.
- Pagination sebanyak 12 data per halaman.
- Tombol refresh manual.

API riwayat membatasi maksimal 1.000 data dalam satu permintaan agar penggunaan memori dan transfer data tetap terkendali.

### 7.4 Rekomendasi Keselamatan

Rekomendasi tersedia untuk setiap kondisi:

- `SAFE`: aktivitas pemantauan rutin.
- `WARNING`: persiapan dokumen, barang penting, tas darurat, dan rute evakuasi.
- `DANGER`: menjauh dari arus, membantu kelompok rentan jika aman, dan bergerak ke tempat yang lebih tinggi atau pos resmi.
- `FLOOD`: tindakan darurat dan evakuasi sesuai arahan petugas.
- `OFFLINE`: pemeriksaan ESP, HiveMQ, bridge MQTT, dan jaringan.

Jika status masih `SAFE` tetapi kondisi cahaya terdeteksi mendung, sistem menambahkan konteks kewaspadaan tanpa mengubah status resmi.

Halaman ini juga menyediakan penjelasan tambahan dari AI. Tombol AI dinonaktifkan apabila data sensor sudah kedaluwarsa agar AI tidak menjelaskan kondisi lama sebagai kondisi terkini.

### 7.5 Chatbot J-SIAGA Assistant

Chatbot dapat membantu menjawab pertanyaan tentang:

- Status banjir terkini.
- Level air.
- Data suhu, kelembapan, dan cahaya.
- Waktu pembaruan sensor.
- Batas `SAFE`, `WARNING`, `DANGER`, dan `FLOOD`.
- Tindakan keselamatan.
- Rekomendasi berdasarkan status.

Chatbot menyediakan pertanyaan cepat seperti:

- Status sekarang.
- Data sensor.
- Apa yang harus dilakukan.
- Level air.

Status chatbot mengikuti sensor. Jika pembacaan sensor kedaluwarsa, header chatbot menampilkan `Offline`; jika data kembali diperbarui, status kembali `Online`.

### 7.6 Chatbot Hibrida Lokal dan AI

Chatbot menggunakan dua lapisan:

1. Jawaban lokal untuk pertanyaan umum dan data sensor terstruktur.
2. Groq AI untuk pertanyaan relevan yang tidak dapat dijawab oleh pola lokal.

Keuntungannya:

- Pertanyaan penting tetap dapat dijawab tanpa AI.
- Data sensor diambil dari database, bukan dikarang model.
- AI tidak diizinkan menentukan ulang status banjir.
- Pertanyaan di luar lingkup J-SIAGA dibatasi.
- Jika Groq timeout, terkena rate limit, salah konfigurasi, atau gagal, chatbot memberikan fallback lokal.

### 7.7 Dukungan Tiga Bahasa

Website mendukung:

- Bahasa Indonesia (`id`).
- English (`en`).
- 한국어/Korean (`ko`).

Bahasa diterapkan pada:

- Navigasi dan halaman website.
- Status dan rekomendasi.
- Peringatan keselamatan.
- Chatbot lokal.
- Jawaban AI.
- Notifikasi Telegram.

Pilihan bahasa website disimpan dalam session. Pilihan bahasa Telegram disimpan secara terpisah untuk setiap pelanggan.

### 7.8 Antarmuka Responsif

Website dirancang untuk berbagai ukuran layar:

- Navigasi bawah pada perangkat seluler.
- Sidebar pada layar desktop.
- Kartu riwayat pada perangkat seluler.
- Tabel riwayat pada desktop.
- Grafik responsif.
- Logo dan favicon J-SIAGA.
- Tata letak yang tidak memotong logo.
- Navigasi antarhalaman dengan transisi yang mengurangi kesan logo dimuat ulang.

## 8. Kondisi Cahaya

Nilai sensor cahaya diterjemahkan menjadi kondisi yang lebih mudah dipahami:

| Nilai cahaya | Kondisi |
| ---: | --- |
| 0–199 | Terang |
| 200–449 | Mendung |
| 450–699 | Redup |
| 700 atau lebih | Gelap |

Kondisi cahaya menjadi informasi pendukung. Status banjir tetap ditentukan oleh jarak permukaan air.

## 9. Fitur Telegram Bot

Telegram Bot J-SIAGA digunakan untuk menyebarkan informasi kepada banyak orang tanpa satu `chat_id` statis.

### 9.1 Langganan Multi-Pengguna

- Setiap pengguna membuka bot dan menjalankan `/start`.
- Chat ID pengguna disimpan otomatis di database.
- Perubahan status dikirim kepada seluruh pelanggan aktif.
- Pengguna menjalankan `/stop` untuk berhenti berlangganan.
- Pengguna yang memblokir bot atau tidak dapat menerima pesan dapat dinonaktifkan otomatis dari daftar aktif.

Menu publik bot hanya berisi:

```text
/start
/language
/stop
```

### 9.2 Bahasa Telegram Per Pengguna

Perintah `/language` menampilkan pilihan:

- Indonesia.
- English.
- 한국어.

Bahasa disimpan untuk setiap pelanggan. Dua pengguna dapat menerima notifikasi yang sama dalam bahasa yang berbeda.

Perintah `/start` tidak selalu meminta pengguna memilih bahasa. Pengguna lama menggunakan bahasa yang sudah tersimpan, sedangkan pengguna baru menggunakan bahasa Telegram yang didukung atau Bahasa Indonesia sebagai fallback.

### 9.3 Notifikasi Perubahan Status

Telegram mengirim pesan ketika status berubah, bukan pada setiap pembacaan. Hal ini mencegah spam.

Notifikasi dapat dikirim untuk:

- `WARNING`.
- `DANGER`.
- `FLOOD`.
- Pemulihan ke `SAFE`.
- Sensor `OFFLINE`.
- Sensor kembali `ONLINE`.

Pesan mencakup:

- Status.
- Perubahan status.
- Sensor level 0%–100%.
- Suhu.
- Kelembapan.
- Cahaya.
- Waktu pembacaan.
- Tautan menuju `jsiaga.me`.

### 9.4 Deteksi OFFLINE Otomatis

Laravel scheduler memeriksa sensor setiap 10 detik.

Jika data terbaru melewati `JSIAGA_OFFLINE_SECONDS`:

1. Sistem mengirim satu pesan `OFFLINE`.
2. Penanda disimpan agar pesan yang sama tidak dikirim berulang-ulang.
3. Ketika data masuk lagi, penanda offline dihapus.
4. Sistem mengirim pesan bahwa sensor kembali online.
5. Notifikasi status normal dilanjutkan.

### 9.5 Keamanan Webhook Telegram

- Telegram mengirim update ke endpoint HTTPS J-SIAGA.
- Setiap request harus membawa `X-Telegram-Bot-Api-Secret-Token` yang sesuai.
- Secret webhook tidak disimpan di frontend.
- Endpoint dibatasi dengan rate limiting.
- Bot token tidak ditampilkan kepada pengguna.

## 10. MQTT Bridge dan Kontrol ESP

MQTT bridge dibuat dengan Node.js dan package `mqtt`.

Fungsinya:

- Terhubung ke HiveMQ menggunakan MQTT melalui TLS.
- Subscribe ke telemetri ESP1.
- Subscribe ke data cahaya ESP2.
- Subscribe ke konfirmasi status servo ESP2.
- Menggabungkan data cahaya terbaru dengan telemetri ESP1.
- Mengirim payload ke Laravel API dengan device token.
- Mengantrikan pengiriman agar data tidak diproses tumpang tindih.
- Memiliki timeout HTTP 10 detik.
- Mencoba terhubung kembali ke MQTT setiap 3 detik.
- Mengirim status hasil Laravel kembali ke ESP2.

Status `FLOOD` dikirim ke servo ESP2 sebagai posisi `DANGER` karena posisi jembatan untuk keduanya menggunakan kondisi pengamanan yang sama.

## 11. API J-SIAGA

| Method | Endpoint | Fungsi |
| --- | --- | --- |
| `POST` | `/api/v1/sensor-readings` | Menerima dan menyimpan data sensor |
| `GET` | `/api/v1/sensor-readings/latest` | Mengambil data terbaru |
| `GET` | `/api/v1/sensor-readings/history` | Mengambil riwayat berdasarkan rentang |
| `POST` | `/api/v1/chat` | Mengirim pertanyaan ke chatbot |
| `POST` | `/api/v1/recommendations/explain` | Meminta penjelasan tambahan dari AI |
| `POST` | `/api/telegram/webhook` | Menerima update Telegram |

Endpoint ingest sensor membutuhkan header:

```text
X-Device-Token
```

## 12. Validasi dan Keamanan Data

J-SIAGA menerapkan beberapa lapisan perlindungan:

- Device token untuk endpoint pengiriman sensor.
- Perbandingan token yang aman.
- Validasi jarak 0–400 cm.
- Validasi suhu -50°C sampai 100°C.
- Validasi kelembapan 0% sampai 100%.
- Validasi waktu agar tidak terlalu lama atau jauh di masa depan.
- Status dan level dihitung ulang oleh server.
- Rate limit ingest sensor 120 request per menit.
- Rate limit chatbot per menit dan per hari.
- Webhook secret Telegram.
- Kredensial HiveMQ, Groq, dan Telegram berada di `.env`.
- Mode produksi menggunakan `APP_DEBUG=false`.
- Cookie session dapat diamankan dengan HTTPS.

## 13. Penyimpanan dan Pengelolaan Data

Data utama yang disimpan:

- Riwayat pembacaan sensor.
- Status hasil perhitungan.
- Waktu pembacaan.
- Penanda notifikasi offline.
- Pelanggan Telegram.
- Status aktif langganan.
- Bahasa pilihan pelanggan.

Optimasi data:

- Pembacaan cepat dalam interval tertentu memperbarui baris terbaru agar database tidak tumbuh terlalu cepat.
- Interval kompaksi bawaan adalah 10 detik.
- Riwayat lama dihapus otomatis berdasarkan masa retensi.
- Masa retensi bawaan adalah 7 hari.
- Pembersihan dijalankan setiap hari pukul 02.00.

Database yang digunakan saat ini adalah SQLite. Struktur migration tetap memakai tipe yang portabel sehingga dapat dikembangkan menuju MySQL atau PostgreSQL.

## 14. Teknologi yang Digunakan

### Backend

- PHP 8.3 atau lebih baru.
- Laravel 13.
- Laravel HTTP Client.
- Laravel Scheduler.
- SQLite.

### Frontend

- Blade Template.
- Tailwind CSS 4.
- JavaScript.
- Vite 8.
- Chart.js 4.

### IoT dan Integrasi

- ESP1 dan ESP2.
- HiveMQ Cloud.
- MQTT melalui TLS.
- Node.js.
- MQTT.js.
- Telegram Bot API.
- Groq API.

### Infrastruktur Produksi

- Ubuntu 24.04 LTS.
- Nginx/PHP untuk website Laravel.
- Supervisor untuk proses jangka panjang.
- Cloudflare dan HTTPS untuk domain publik.
- Git untuk pembaruan kode.

## 15. Proses Produksi yang Berjalan

Proses utama di VPS:

```text
jsiaga-mqtt
jsiaga-scheduler
```

`jsiaga-mqtt` menjaga MQTT bridge tetap berjalan. `jsiaga-scheduler` menjalankan pemantauan offline dan tugas terjadwal Laravel.

Supervisor memberikan:

- Autostart saat VPS menyala.
- Restart otomatis jika proses berhenti.
- Pengelolaan status melalui CLI.
- Log stdout dan stderr.

## 16. Perintah CLI Khusus

| Perintah | Fungsi |
| --- | --- |
| `php artisan jsiaga:seed-demo` | Membuat data demo berbagai status |
| `php artisan jsiaga:prune-sensor-readings` | Menghapus data melewati masa retensi |
| `php artisan jsiaga:test-telegram` | Mengirim pesan tes ke pelanggan aktif |
| `php artisan jsiaga:telegram-set-webhook` | Mendaftarkan webhook dan menu bot |
| `php artisan jsiaga:telegram-subscribers` | Menampilkan jumlah pelanggan Telegram |
| `php artisan jsiaga:monitor-sensor` | Memeriksa dan mengirim notifikasi offline |

Panduan aktivasi, maintenance, deployment, dan troubleshooting tersedia di `DOCUMENTATION.md`.

## 17. Mode Lokal

Semua komponen utama dapat dijalankan di komputer lokal.

Perintah gabungan:

```bash
npm run local:mqtt
```

Perintah tersebut menjalankan:

- Laravel development server.
- Vite development server.
- MQTT bridge.

Perangkat lain dalam jaringan yang sama dapat mengakses Laravel melalui IP lokal komputer apabila firewall mengizinkan port 8000.

Webhook Telegram lokal membutuhkan URL HTTPS publik sementara seperti Cloudflare Quick Tunnel atau ngrok. Pengujian unit dan feature test tidak membutuhkan tunnel.

## 18. Keandalan Sistem

J-SIAGA dirancang agar kegagalan satu layanan tidak langsung menghentikan semuanya:

- Kegagalan Telegram tidak menggagalkan penyimpanan sensor.
- Kegagalan Groq tidak menghilangkan jawaban lokal.
- Rekomendasi lokal tetap tersedia tanpa AI.
- MQTT bridge mencoba menyambung kembali secara otomatis.
- Supervisor menghidupkan kembali bridge dan scheduler.
- Data lama diberi status `OFFLINE`, bukan ditampilkan sebagai kondisi terkini.
- Status banjir hanya dihitung oleh Laravel sebagai sumber kebenaran.

## 19. Pengujian

Proyek memiliki pengujian otomatis untuk:

- Perhitungan level air dan status banjir.
- Validasi payload sensor.
- Keamanan device token.
- Penyimpanan dan kompaksi histori.
- Status offline.
- Retensi data.
- API data terbaru dan riwayat.
- Chatbot dan bahasa.
- Langganan Telegram `/start` dan `/stop`.
- Pilihan bahasa Telegram.
- Broadcast multi-pengguna.
- Keamanan webhook.
- Notifikasi perubahan status.
- Notifikasi offline satu kali.
- Notifikasi pemulihan online.
- Kegagalan Telegram tanpa mengganggu sensor.

Perintah pengujian:

```bash
php artisan test
npm run build
```

## 20. Keunggulan J-SIAGA

- Menggabungkan IoT, website, AI, dan Telegram dalam satu sistem.
- Status dihitung terpusat di server agar konsisten.
- Informasi teknis diterjemahkan menjadi indikator yang mudah dipahami.
- Mendukung banyak pelanggan Telegram.
- Mendukung tiga bahasa per pengguna.
- Memiliki deteksi sensor offline dan pemulihan otomatis.
- Menghindari spam notifikasi berdasarkan perubahan status.
- Tetap memiliki fallback lokal ketika AI gagal.
- Responsif untuk ponsel, tablet, dan desktop.
- Dapat berjalan lokal maupun di VPS.
- Node-RED tidak menjadi ketergantungan utama.

## 21. Batasan Saat Ini

- Akurasi sistem bergantung pada kalibrasi dan posisi sensor fisik.
- SQLite cocok untuk skala saat ini, tetapi penggunaan besar dapat memerlukan PostgreSQL atau MySQL.
- Notifikasi Telegram hanya dapat diterima setelah pengguna memulai bot.
- Telegram membutuhkan koneksi internet.
- Penjelasan AI membutuhkan koneksi dan kuota Groq.
- Sistem belum menggantikan informasi resmi dari BPBD atau petugas lapangan.
- Status miniatur harus dikalibrasi ulang sebelum digunakan pada sungai nyata.

## 22. Pengembangan Lanjutan yang Mungkin Dilakukan

- Integrasi data curah hujan dan prakiraan cuaca.
- Peta lokasi beberapa sensor.
- Pengelolaan banyak perangkat dan banyak sungai.
- Dashboard administrator.
- Hak akses pengguna.
- Ekspor laporan PDF atau spreadsheet.
- Push notification PWA.
- Integrasi WhatsApp atau SMS gateway.
- Backup database otomatis.
- PostgreSQL untuk skala lebih besar.
- Grafik perbandingan antarperiode.
- Audit log perangkat dan notifikasi.
- Kalibrasi threshold melalui halaman admin.

## 23. Kesimpulan

J-SIAGA bukan hanya dashboard pembacaan sensor. Proyek ini merupakan sistem peringatan banjir terpadu yang menghubungkan perangkat IoT, broker MQTT, backend Laravel, database, website responsif, chatbot, rekomendasi keselamatan, dan Telegram multi-pengguna.

Sistem memprioritaskan konsistensi status, kemudahan pemahaman, ketahanan terhadap kegagalan layanan, serta penyampaian informasi secara cepat. Dengan pengembangan dan kalibrasi lebih lanjut, arsitektur J-SIAGA dapat diperluas dari miniatur menjadi platform pemantauan beberapa lokasi.
