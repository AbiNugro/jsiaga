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
        if (! $this->hasValidSecret($request)) {
            return response()->json(['ok' => false], 403);
        }

        if (is_array($request->input('callback_query'))) {
            $this->handleCallback($request->input('callback_query'), $telegram);

            return response()->json(['ok' => true]);
        }

        $message = $request->input('message');

        if (is_array($message) && isset($message['chat']['id'], $message['text'])) {
            $this->handleMessage($message, $telegram);
        }

        return response()->json(['ok' => true]);
    }

    private function hasValidSecret(Request $request): bool
    {
        $configured = (string) config('services.telegram.webhook_secret');
        $received = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        return $configured !== '' && hash_equals($configured, $received);
    }

    /** @param array<string, mixed> $message */
    private function handleMessage(array $message, TelegramAlertService $telegram): void
    {
        $chat = $message['chat'];
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $command = strtolower(explode('@', explode(' ', trim((string) $message['text']))[0])[0]);
        $chatId = (string) $chat['id'];
        $subscriber = TelegramSubscriber::query()->where('chat_id', $chatId)->first();
        $locale = $subscriber?->locale ?? $telegram->localeFromTelegram($from['language_code'] ?? null);

        if ($command === '/start') {
            $subscriber = TelegramSubscriber::query()->updateOrCreate(
                ['chat_id' => $chatId],
                [
                    'chat_type' => $chat['type'] ?? null,
                    'username' => $chat['username'] ?? $from['username'] ?? null,
                    'first_name' => $chat['first_name'] ?? $from['first_name'] ?? null,
                    'last_name' => $chat['last_name'] ?? $from['last_name'] ?? null,
                    'language_code' => $from['language_code'] ?? null,
                    'locale' => $locale,
                    'is_active' => true,
                    'subscribed_at' => now(),
                    'unsubscribed_at' => null,
                ],
            );

            $telegram->sendToChat($chatId, $telegram->text('started', $subscriber->locale));

            return;
        }

        if ($command === '/stop') {
            TelegramSubscriber::query()->where('chat_id', $chatId)->update([
                'is_active' => false,
                'unsubscribed_at' => now(),
            ]);
            $telegram->sendToChat($chatId, $telegram->text('stopped', $locale));

            return;
        }

        if (in_array($command, ['/bahasa', '/language'], true)) {
            $telegram->sendLanguageMenu($chatId, $locale);

            return;
        }

        if ($command === '/help') {
            $telegram->sendToChat($chatId, $telegram->text('help', $locale));
        }
    }

    /** @param array<string, mixed> $callback */
    private function handleCallback(array $callback, TelegramAlertService $telegram): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');

        if ($callbackId === '' || $chatId === '' || ! preg_match('/^language:(id|en|ko)$/', $data, $matches)) {
            return;
        }

        $locale = $matches[1];
        $subscriber = TelegramSubscriber::query()->where('chat_id', $chatId)->first();

        if ($subscriber) {
            $subscriber->update(['locale' => $locale]);
        }

        $telegram->answerCallback($callbackId, $telegram->text('language_saved_short', $locale));
        $telegram->sendToChat($chatId, $telegram->text('language_saved', $locale));
    }
}
