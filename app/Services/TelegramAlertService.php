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

    public function localeFromTelegram(mixed $languageCode): string
    {
        $language = strtolower((string) $languageCode);

        return str_starts_with($language, 'ko') ? 'ko' : (str_starts_with($language, 'en') ? 'en' : 'id');
    }

    public function text(string $key, string $locale): string
    {
        $locale = $this->normalizeLocale($locale);

        $messages = [
            'id' => [
                'started' => "Notifikasi J-SIAGA sudah aktif.\nAnda akan menerima peringatan saat status air berubah.\nKirim /stop untuk berhenti berlangganan.",
                'stopped' => 'Notifikasi J-SIAGA sudah dinonaktifkan. Kirim /start untuk mengaktifkannya kembali.',
                'help' => "Perintah bot J-SIAGA:\n/start - aktifkan notifikasi\n/stop - hentikan notifikasi\n/bahasa - ganti bahasa\n/help - tampilkan bantuan",
                'choose_language' => 'Pilih bahasa notifikasi:',
                'language_saved' => 'Bahasa notifikasi berhasil diubah ke Bahasa Indonesia.',
                'language_saved_short' => 'Bahasa disimpan',
                'test' => "[OK] Tes notifikasi J-SIAGA berhasil.\nTelegram telah terhubung ke aplikasi produksi.",
            ],
            'en' => [
                'started' => "J-SIAGA notifications are now active.\nYou will receive alerts when the water status changes.\nSend /stop to unsubscribe.",
                'stopped' => 'J-SIAGA notifications have been disabled. Send /start to enable them again.',
                'help' => "J-SIAGA bot commands:\n/start - enable notifications\n/stop - disable notifications\n/language - change language\n/help - show help",
                'choose_language' => 'Choose your notification language:',
                'language_saved' => 'Notification language changed to English.',
                'language_saved_short' => 'Language saved',
                'test' => "[OK] J-SIAGA notification test succeeded.\nTelegram is connected to the production application.",
            ],
            'ko' => [
                'started' => "J-SIAGA 알림이 활성화되었습니다.\n수위 상태가 변경되면 알림을 받습니다.\n구독을 중지하려면 /stop을 보내세요.",
                'stopped' => 'J-SIAGA 알림이 비활성화되었습니다. 다시 활성화하려면 /start를 보내세요.',
                'help' => "J-SIAGA 봇 명령어:\n/start - 알림 활성화\n/stop - 알림 중지\n/language - 언어 변경\n/help - 도움말 보기",
                'choose_language' => '알림 언어를 선택하세요:',
                'language_saved' => '알림 언어가 한국어로 변경되었습니다.',
                'language_saved_short' => '언어가 저장되었습니다',
                'test' => "[OK] J-SIAGA 알림 테스트에 성공했습니다.\nTelegram이 운영 애플리케이션에 연결되었습니다.",
            ],
        ];

        return $messages[$locale][$key] ?? $messages['id'][$key] ?? $key;
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

        return $this->broadcastLocalized(
            fn (string $locale): string => $this->statusMessage($previousStatus, $reading, $locale)
        ) > 0;
    }

    public function sendTest(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $this->broadcastLocalized(fn (string $locale): string => implode("\n", [
            $this->text('test', $locale),
            $this->timeLabel($locale).': '.now()->format('d-m-Y H:i:s').' '.config('app.timezone'),
        ])) > 0;
    }

    public function sendLanguageMenu(string $chatId, string $locale): bool
    {
        return $this->sendToChat($chatId, $this->text('choose_language', $locale), [
            'inline_keyboard' => [[
                ['text' => '🇮🇩 Indonesia', 'callback_data' => 'language:id'],
                ['text' => '🇬🇧 English', 'callback_data' => 'language:en'],
                ['text' => '🇰🇷 한국어', 'callback_data' => 'language:ko'],
            ]],
        ]);
    }

    public function answerCallback(string $callbackId, string $text): bool
    {
        return $this->request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => $text,
        ]);
    }

    public function sendToChat(string $chatId, string $message, ?array $replyMarkup = null): bool
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
            'link_preview_options' => ['is_disabled' => true],
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $successful = $this->request('sendMessage', $payload, $chatId);

        if (! $successful) {
            return false;
        }

        return true;
    }

    private function broadcastLocalized(callable $messageForLocale): int
    {
        $sent = 0;

        TelegramSubscriber::query()->active()->chunkById(100, function ($subscribers) use ($messageForLocale, &$sent): void {
            foreach ($subscribers as $subscriber) {
                if ($this->sendToChat($subscriber->chat_id, $messageForLocale($this->normalizeLocale($subscriber->locale)))) {
                    $sent++;
                }
            }
        });

        return $sent;
    }

    private function request(string $method, array $payload, ?string $chatId = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $url = 'https://api.telegram.org/bot'.config('services.telegram.bot_token').'/'.$method;

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(max(1, (int) config('services.telegram.timeout', 5)))
                ->retry(2, 200, throw: false)
                ->post($url, $payload);

            if ($response->successful() && $response->json('ok', true)) {
                return true;
            }

            if ($chatId !== null && in_array($response->status(), [400, 403], true)) {
                TelegramSubscriber::query()->where('chat_id', $chatId)->update([
                    'is_active' => false,
                    'unsubscribed_at' => now(),
                ]);
            }

            $this->logFailure($response, $chatId, $method);
        } catch (Throwable $exception) {
            Log::warning('Notifikasi Telegram J-SIAGA gagal dikirim.', [
                'chat_id' => $chatId,
                'method' => $method,
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

    private function logFailure(Response $response, ?string $chatId, string $method): void
    {
        Log::warning('Telegram Bot API menolak permintaan J-SIAGA.', [
            'chat_id' => $chatId,
            'method' => $method,
            'status_code' => $response->status(),
        ]);
    }

    private function statusMessage(?string $previousStatus, SensorReading $reading, string $locale): string
    {
        $locale = $this->normalizeLocale($locale);
        $status = (string) $reading->status;
        $copy = $this->statusCopy($locale);

        $lines = [
            'J-SIAGA - '.$copy['headlines'][$status],
            '',
            $copy['status'].': '.$status,
            $copy['change'].': '.($previousStatus ?? $copy['no_data']).' -> '.$status,
            $copy['water_level'].': '.round((float) $reading->water_level).'%',
            $copy['distance'].': '.number_format((float) $reading->distance, 2, ',', '.').' cm',
        ];

        if ($reading->temperature !== null) {
            $lines[] = $copy['temperature'].': '.number_format((float) $reading->temperature, 1, ',', '.').' C';
        }
        if ($reading->humidity !== null) {
            $lines[] = $copy['humidity'].': '.number_format((float) $reading->humidity, 1, ',', '.').'%';
        }
        if ($reading->light !== null) {
            $lines[] = $copy['light'].': '.$reading->light;
        }

        $lines[] = $copy['time'].': '.($reading->recorded_at?->format('d-m-Y H:i:s') ?? now()->format('d-m-Y H:i:s')).' '.config('app.timezone');
        $lines[] = (string) config('app.url');

        return implode("\n", $lines);
    }

    private function statusCopy(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'headlines' => ['SAFE' => '[SAFE] CONDITIONS ARE SAFE AGAIN', 'WARNING' => '[WARNING] WATER LEVEL WARNING', 'DANGER' => '[DANGER] DANGEROUS WATER LEVEL', 'FLOOD' => '[FLOOD] FLOOD DETECTED'],
                'status' => 'Status', 'change' => 'Change', 'no_data' => 'NO PREVIOUS DATA', 'water_level' => 'Water level', 'distance' => 'Sensor distance', 'temperature' => 'Temperature', 'humidity' => 'Humidity', 'light' => 'Light', 'time' => 'Time',
            ],
            'ko' => [
                'headlines' => ['SAFE' => '[안전] 다시 안전한 상태입니다', 'WARNING' => '[주의] 수위 주의', 'DANGER' => '[위험] 위험 수위', 'FLOOD' => '[홍수] 홍수가 감지되었습니다'],
                'status' => '상태', 'change' => '변경', 'no_data' => '이전 데이터 없음', 'water_level' => '수위', 'distance' => '센서 거리', 'temperature' => '온도', 'humidity' => '습도', 'light' => '조도', 'time' => '시간',
            ],
            default => [
                'headlines' => ['SAFE' => '[AMAN] KONDISI KEMBALI AMAN', 'WARNING' => '[WASPADA] STATUS WARNING', 'DANGER' => '[BAHAYA] STATUS DANGER', 'FLOOD' => '[BANJIR] BANJIR TERDETEKSI'],
                'status' => 'Status', 'change' => 'Perubahan', 'no_data' => 'BELUM ADA DATA', 'water_level' => 'Level air', 'distance' => 'Jarak sensor', 'temperature' => 'Suhu', 'humidity' => 'Kelembapan', 'light' => 'Cahaya', 'time' => 'Waktu',
            ],
        };
    }

    private function timeLabel(string $locale): string
    {
        return match ($this->normalizeLocale($locale)) {
            'en' => 'Time',
            'ko' => '시간',
            default => 'Waktu',
        };
    }

    private function normalizeLocale(?string $locale): string
    {
        return in_array($locale, ['id', 'en', 'ko'], true) ? $locale : 'id';
    }
}
