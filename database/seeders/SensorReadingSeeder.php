<?php

namespace Database\Seeders;

use App\Models\SensorReading;
use App\Services\FloodStatusService;
use Illuminate\Database\Seeder;

class SensorReadingSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(FloodStatusService::class);
        $distances = [9.4, 9.1, 8.8, 8.6, 8.5, 8.1, 7.5, 6.9, 6.8, 6.6, 6.5, 6.4, 6.2, 6.7, 7.3, 8.2, 8.7, 6.3, 7.8, 9.2];
        $start = now()->subMinutes((count($distances) - 1) * 5);

        foreach ($distances as $index => $distance) {
            $calculated = $service->calculate($distance);

            SensorReading::query()->create([
                'distance' => $distance,
                'water_level' => $calculated['water_level'],
                'status' => $calculated['status'],
                'temperature' => 27.2 + (($index % 5) * .4),
                'humidity' => 66 + ($index % 8),
                'light' => 680 - ($index * 17),
                'recorded_at' => $start->copy()->addMinutes($index * 5),
            ]);
        }
    }
}
