<?php

namespace App\Services;

use App\Models\SensorReading;

final class SensorRetentionService
{
    public function prune(): int
    {
        $days = max(1, (int) config('services.jsiaga.retention_days', 7));

        return SensorReading::query()
            ->where('recorded_at', '<', now()->subDays($days))
            ->delete();
    }
}
