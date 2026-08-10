<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatRequest;
use App\Models\SensorReading;
use App\Services\GroqService;
use App\Services\LocalChatbotService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class ChatController extends Controller
{
    public function __invoke(ChatRequest $request, LocalChatbotService $local, GroqService $ai): JsonResponse
    {
        $message = $request->validated('message');
        app()->setLocale($request->validated('locale') ?? 'id');

        $latest = SensorReading::query()->latest('recorded_at')->first();
        $answer = $local->answer($message, $latest);

        if ($answer !== null) {
            return $this->response($answer, 'lokal');
        }

        if (! $local->isInScope($message)) {
            return $this->response(__('ui.chat.answers.out_of_scope'), 'dibatasi');
        }

        try {
            return $this->response($ai->answer($message, $latest), 'ai');
        } catch (RuntimeException) {
            return $this->response(__('ui.chat.answers.fallback'), 'fallback_lokal');
        }
    }

    private function response(string $answer, string $source): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('ui.chat.send'),
            'data' => ['answer' => $answer, 'source' => $source],
        ]);
    }
}
