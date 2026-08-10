<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SensorReading;
use App\Services\GroqService;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RecommendationController extends Controller
{
    public function __invoke(Request $request, RecommendationService $recommendations, GroqService $ai): JsonResponse
    {
        $locale = (string) $request->input('locale', 'id');
        app()->setLocale(in_array($locale, ['id', 'en', 'ko'], true) ? $locale : 'id');

        $latest = SensorReading::query()->latest('recorded_at')->first();

        if (! $latest) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada data sensor untuk dijelaskan.',
                'errors' => (object) [],
            ], 404);
        }

        if ($latest->isStale()) {
            return response()->json([
                'success' => false,
                'message' => 'Data sensor sudah tidak diperbarui. Tunggu data baru sebelum meminta rekomendasi AI.',
                'errors' => (object) [],
            ], 409);
        }

        try {
            $answer = $ai->explainRecommendation($latest, $recommendations->forReading($latest));
        } catch (RuntimeException $exception) {
            Log::warning('AI recommendation request failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Penjelasan AI tidak tersedia. Rekomendasi keselamatan lokal tetap dapat digunakan.',
                'errors' => (object) [],
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Penjelasan tambahan AI berhasil dibuat.',
            'data' => ['answer' => $answer, 'source' => 'ai'],
        ]);
    }
}
