<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SensorHistoryRequest;
use App\Http\Requests\StoreSensorReadingRequest;
use App\Http\Resources\SensorReadingResource;
use App\Models\SensorReading;
use App\Services\FloodStatusService;
use App\Services\SensorRetentionService;
use App\Services\TelegramAlertService;
use App\Support\SensorRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SensorReadingController extends Controller
{
    public function store(
        StoreSensorReadingRequest $request,
        FloodStatusService $service,
        SensorRetentionService $retention,
        TelegramAlertService $telegram,
    ): JsonResponse {
        if (Cache::add('jsiaga:retention-pruned', true, now()->addDay())) {
            $retention->prune();
        }

        $validated = $request->validated();
        $calculated = $service->calculate((float) $validated['distance']);
        $values = [
            ...$validated,
            ...$calculated,
            'recorded_at' => $validated['recorded_at'] ?? now(),
        ];
        $interval = max(1, (int) config('services.jsiaga.history_interval_seconds', 10));
        $latest = SensorReading::query()->latest('recorded_at')->first();
        $previousStatus = $latest?->offline_notified_at ? 'OFFLINE' : $latest?->status;
        $compacted = $latest?->created_at?->gte(now()->subSeconds($interval)) ?? false;

        if ($compacted) {
            $latest->fill($values);
            $latest->offline_notified_at = null;
            $latest->save();
            $reading = $latest->fresh();
        } else {
            $reading = SensorReading::query()->create($values);
        }

        $telegram->sendStatusChange($previousStatus, $reading);

        return response()->json([
            'success' => true,
            'message' => $compacted ? 'Data sensor terbaru berhasil diperbarui.' : 'Data sensor berhasil disimpan.',
            'data' => (new SensorReadingResource($reading))->resolve(),
            'meta' => ['history_compacted' => $compacted, 'history_interval_seconds' => $interval],
        ], $compacted ? 200 : 201);
    }

    public function latest(): JsonResponse
    {
        $reading = SensorReading::query()->latest('recorded_at')->first();

        return response()->json([
            'success' => true,
            'message' => $reading ? 'Data sensor terbaru berhasil diambil.' : 'Belum ada data sensor.',
            'data' => $reading ? (new SensorReadingResource($reading))->resolve() : null,
        ]);
    }

    public function history(SensorHistoryRequest $request): JsonResponse
    {
        $range = $request->validated('range');
        $readings = SensorReading::query()
            ->where('recorded_at', '>=', SensorRange::since($range))
            ->oldest('recorded_at')
            ->limit(1000)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Riwayat sensor berhasil diambil.',
            'data' => SensorReadingResource::collection($readings)->resolve(),
            'meta' => ['range' => $range, 'count' => $readings->count()],
        ]);
    }
}
