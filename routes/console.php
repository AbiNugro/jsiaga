<?php

use App\Services\SensorRetentionService;
use Database\Seeders\SensorReadingSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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

Schedule::command('jsiaga:prune-sensor-readings')
    ->dailyAt('02:00')
    ->withoutOverlapping();
