<?php

namespace App\Models;

use Database\Factories\SensorReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SensorReading extends Model
{
    /** @use HasFactory<SensorReadingFactory> */
    use HasFactory;

    protected $fillable = [
        'distance',
        'water_level',
        'status',
        'temperature',
        'humidity',
        'light',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'distance' => 'float',
            'water_level' => 'float',
            'temperature' => 'float',
            'humidity' => 'float',
            'light' => 'integer',
            'recorded_at' => 'datetime',
            'offline_notified_at' => 'datetime',
        ];
    }

    public function isStale(): bool
    {
        if (! $this->recorded_at) {
            return true;
        }

        return $this->recorded_at->lt(now()->subSeconds(
            max(1, (int) config('services.jsiaga.offline_seconds', 15))
        ));
    }

    public function effectiveStatus(): string
    {
        return $this->isStale() ? 'OFFLINE' : $this->status;
    }
}
