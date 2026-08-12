<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramSubscriber;
use App\Services\TelegramAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramAlertService $telegram): JsonResponse
    {
        $configuredSecret = (string) config('services.telegram.webhook_secret');
        $receivedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $receivedSecret)) {
            return response()->json(['ok' => false], 403);
        }

        $message = $request->input('message');

        if (! is_array($message) || ! isset($message['chat']['id'], $message['text'])) {
            return response()->json(['ok' => true]);
        }

        $chat = $message['chat'];
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $command = strtolower(explode('@', explode(' ', trim((string) $message['text']))[0])[0]);
        $chatId = (string) $chat['id'];

        if ($command === '/start') {
            TelegramSubscriber::query()->updateOrCreate(
                ['chat_id' => $chatId],
                [
                    'chat_type' => $chat['type'] ?? null,
                    'username' => $chat['username'] ?? $from['username'] ?? null,
                    'first_name' => $chat['first_name'] ?? $from['first_name'] ?? null,
                    'last_name' => $chat['last_name'] ?? $from['last_name'] ?? null,
                    'language_code' => $from['language_code'] ?? null,
                    'is_active' => true,
                    'subscribed_at' => now(),
                    'unsubscribed_at' => null,
                ],
            );

            $telegram->sendToChat($chatId, implode("\n", [
                'Notifikasi J-SIAGA sudah aktif.',
                'Anda akan menerima peringatan saat status air berubah.',
                'Kirim /stop untuk berhenti berlangganan.',
            ]));
        } elseif ($command === '/stop') {
            TelegramSubscriber::query()->where('chat_id', $chatId)->update([
                'is_active' => false,
                'unsubscribed_at' => now(),
            ]);

            $telegram->sendToChat($chatId, 'Notifikasi J-SIAGA sudah dinonaktifkan. Kirim /start untuk mengaktifkannya kembali.');
        } elseif ($command === '/help') {
            $telegram->sendToChat($chatId, implode("\n", [
                'Perintah bot J-SIAGA:',
                '/start - aktifkan notifikasi',
                '/stop - hentikan notifikasi',
                '/help - tampilkan bantuan',
            ]));
        }

        return response()->json(['ok' => true]);
    }
}
