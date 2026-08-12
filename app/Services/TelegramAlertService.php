<?php

namespace App\Services;

use App\Models\SensorReading;
use App\Models\TelegramSubscriber;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramAlertService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.telegram.enabled')
            && filled(config('services.telegram.bot_token'));
    }

    public function sendStatusChange(?string $previousStatus, SensorReading $reading): bool
    {
        $status = strtoupper((string) $reading->status);
        $previousStatus = $previousStatus ? strtoupper($previousStatus) : null;

        if (! $this->isEnabled()
            || $status === $previousStatus
            || ($previousStatus === null && $status === FloodStatusService::SAFE)
            || ! in_array($status, $this->notifiableStatuses(), true)) {
            return false;
        }

        return $this->broadcast($this->statusMessage($previousStatus, $reading)) > 0;
    }

    public function sendTest(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $this->broadcast(implode("\n", [
            '[OK] Tes notifikasi J-SIAGA berhasil.',
            'Telegram telah terhubung ke aplikasi produksi.',
            'Waktu: '.now()->format('d-m-Y H:i:s').' '.config('app.timezone'),
        ])) > 0;
    }

    public function broadcast(string $message): int
    {
        $sent = 0;

        TelegramSubscriber::query()->active()->chunkById(100, function ($subscribers) use ($message, &$sent): void {
            foreach ($subscribers as $subscriber) {
                if ($this->sendToChat($subscriber->chat_id, $message)) {
                    $sent++;
                }
            }
        });

        return $sent;
    }

    public function sendToChat(string $chatId, string $message): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $token = (string) config('services.telegram.bot_token');
        $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(max(1, (int) config('services.telegram.timeout', 5)))
                ->retry(2, 200, throw: false)
                ->post($url, [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'link_preview_options' => ['is_disabled' => true],
                ]);

            if ($response->successful()) {
                return true;
            }

            if (in_array($response->status(), [400, 403], true)) {
                TelegramSubscriber::query()->where('chat_id', $chatId)->update([
                    'is_active' => false,
                    'unsubscribed_at' => now(),
                ]);
            }

            $this->logFailure($response, $chatId);
        } catch (Throwable $exception) {
            Log::warning('Notifikasi Telegram J-SIAGA gagal dikirim.', [
                'chat_id' => $chatId,
                'exception' => $exception::class,
            ]);
        }

        return false;
    }

    /** @return list<string> */
    private function notifiableStatuses(): array
    {
        return array_values(array_map(
            static fn (mixed $status): string => strtoupper(trim((string) $status)),
            (array) config('services.telegram.statuses', []),
        ));
    }

    private function logFailure(Response $response, string $chatId): void
    {
        Log::warning('Telegram Bot API menolak notifikasi J-SIAGA.', [
            'chat_id' => $chatId,
            'status_code' => $response->status(),
        ]);
    }

    private function statusMessage(?string $previousStatus, SensorReading $reading): string
    {
        $status = (string) $reading->status;
        $headline = match ($status) {
            FloodStatusService::WARNING => '[WASPADA] STATUS WARNING',
            FloodStatusService::DANGER => '[BAHAYA] STATUS DANGER',
            FloodStatusService::FLOOD => '[BANJIR] BANJIR TERDETEKSI',
            default => '[AMAN] KONDISI KEMBALI AMAN',
        };

        $lines = [
            'J-SIAGA - '.$headline,
            '',
            'Status: '.$status,
            'Perubahan: '.($previousStatus ?? 'BELUM ADA DATA').' -> '.$status,
            'Level air: '.round((float) $reading->water_level).'%',
            'Jarak sensor: '.number_format((float) $reading->distance, 2, ',', '.').' cm',
        ];

        if ($reading->temperature !== null) {
            $lines[] = 'Suhu: '.number_format((float) $reading->temperature, 1, ',', '.').' C';
        }

        if ($reading->humidity !== null) {
            $lines[] = 'Kelembapan: '.number_format((float) $reading->humidity, 1, ',', '.').'%';
        }

        if ($reading->light !== null) {
            $lines[] = 'Cahaya: '.$reading->light;
        }

        $lines[] = 'Waktu: '.($reading->recorded_at?->format('d-m-Y H:i:s') ?? now()->format('d-m-Y H:i:s')).' '.config('app.timezone');
        $lines[] = (string) config('app.url');

        return implode("\n", $lines);
    }
}
