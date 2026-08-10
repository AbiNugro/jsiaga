<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SensorReadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'distance' => $this->distance,
            'water_level' => $this->water_level,
            'status' => $this->status,
            'effective_status' => $this->effectiveStatus(),
            'is_stale' => $this->isStale(),
            'status_label' => match ($this->status) {
                'FLOOD' => 'Banjir',
                'DANGER' => 'Bahaya',
                'WARNING' => 'Waspada',
                default => 'Aman',
            },
            'temperature' => $this->temperature,
            'humidity' => $this->humidity,
            'light' => $this->light,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'recorded_at_label' => $this->recorded_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i').' WIB',
        ];
    }
}
