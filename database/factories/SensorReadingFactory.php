<?php

namespace Database\Factories;

use App\Models\SensorReading;
use App\Services\FloodStatusService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SensorReading> */
class SensorReadingFactory extends Factory
{
    protected $model = SensorReading::class;

    public function definition(): array
    {
        $distance = fake()->randomFloat(2, 5, 28);
        $calculated = app(FloodStatusService::class)->calculate($distance);

        return [
            'distance' => $distance,
            'water_level' => $calculated['water_level'],
            'status' => $calculated['status'],
            'temperature' => fake()->randomFloat(1, 24, 34),
            'humidity' => fake()->randomFloat(1, 55, 92),
            'light' => fake()->numberBetween(120, 900),
            'recorded_at' => now(),
        ];
    }
}
