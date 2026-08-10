<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class SensorRange
{
    public static function since(string $range): Carbon
    {
        return match ($range) {
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            default => now()->subHour(),
        };
    }
}
