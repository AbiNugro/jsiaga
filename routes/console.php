<?php

use App\Models\SensorReading;
use App\Models\TelegramSubscriber;
use App\Services\SensorRetentionService;
use App\Services\TelegramAlertService;
use Database\Seeders\SensorReadingSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('jsiaga:seed-demo', function () {
    $this->call('db:seed', ['--class' => SensorReadingSeeder::class]);
    $this->components->info('Data demo J-SIAGA berhasil dibuat.');
})->purpose('Membuat rangkaian data sensor demo J-SIAGA');

Artisan::command('jsiaga:prune-sensor-readings', function (SensorRetentionService $retention) {
    $deleted = $retention->prune();
    $this->components->info($deleted.' data sensor lama berhasil dihapus.');
})->purpose('Menghapus riwayat sensor yang melewati masa retensi');

Artisan::command('jsiaga:test-telegram', function (TelegramAlertService $telegram) {
    if (! $telegram->isEnabled()) {
        $this->components->error('Telegram belum aktif. Periksa TELEGRAM_NOTIFICATIONS_ENABLED dan TELEGRAM_BOT_TOKEN.');

        return 1;
    }

    if (TelegramSubscriber::query()->active()->doesntExist()) {
        $this->components->error('Belum ada pelanggan aktif. Buka bot Telegram lalu kirim /start.');

        return 1;
    }

    if (! $telegram->sendTest()) {
        $this->components->error('Pesan Telegram gagal dikirim. Periksa token, pelanggan aktif, koneksi VPS, dan storage/logs/laravel.log.');

        return 1;
    }

    $this->components->info('Pesan tes Telegram berhasil dikirim.');

    return 0;
})->purpose('Menguji konfigurasi notifikasi Telegram J-SIAGA');

Artisan::command('jsiaga:telegram-set-webhook', function () {
    $token = (string) config('services.telegram.bot_token');
    $secret = (string) config('services.telegram.webhook_secret');

    if ($token === '' || $secret === '') {
        $this->components->error('TELEGRAM_BOT_TOKEN dan TELEGRAM_WEBHOOK_SECRET wajib diisi.');

        return 1;
    }

    if (! preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secret)) {
        $this->components->error('TELEGRAM_WEBHOOK_SECRET hanya boleh berisi huruf, angka, garis bawah, dan tanda minus.');

        return 1;
    }

    $webhookUrl = rtrim((string) config('app.url'), '/').'/api/telegram/webhook';
    $response = Http::asJson()->timeout(10)->post(
        'https://api.telegram.org/bot'.$token.'/setWebhook',
        [
            'url' => $webhookUrl,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'callback_query'],
            'drop_pending_updates' => false,
        ],
    );

    if (! $response->successful() || ! $response->json('ok')) {
        $this->components->error('Webhook Telegram gagal diaktifkan. Periksa konfigurasi dan log Laravel.');

        return 1;
    }

    $this->components->info('Webhook Telegram aktif: '.$webhookUrl);

    Http::asJson()->timeout(10)->post(
        'https://api.telegram.org/bot'.$token.'/setMyCommands',
        ['commands' => [
            ['command' => 'start', 'description' => 'Berlangganan peringatan'],
            ['command' => 'language', 'description' => 'Pilih bahasa notifikasi'],
            ['command' => 'stop', 'description' => 'Berhenti berlangganan'],
        ]],
    );

    return 0;
})->purpose('Mendaftarkan webhook Telegram untuk pelanggan J-SIAGA');

Artisan::command('jsiaga:telegram-subscribers', function () {
    $active = TelegramSubscriber::query()->active()->count();
    $total = TelegramSubscriber::query()->count();

    $this->components->info("Pelanggan Telegram aktif: {$active} dari {$total} terdaftar.");
})->purpose('Menampilkan jumlah pelanggan Telegram J-SIAGA');

Artisan::command('jsiaga:monitor-sensor', function (TelegramAlertService $telegram) {
    $latest = SensorReading::query()->latest('recorded_at')->first();

    if (! $latest || ! $latest->isStale() || $latest->offline_notified_at) {
        return 0;
    }

    if ($telegram->sendOffline($latest)) {
        $latest->forceFill(['offline_notified_at' => now()])->save();
        $this->components->info('Notifikasi OFFLINE sensor berhasil dikirim.');
    }

    return 0;
})->purpose('Memantau sensor dan mengirim satu notifikasi ketika data berhenti');

Schedule::command('jsiaga:prune-sensor-readings')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('jsiaga:monitor-sensor')
    ->everyTenSeconds()
    ->withoutOverlapping();
