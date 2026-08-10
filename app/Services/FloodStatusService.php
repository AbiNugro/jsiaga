<?php

namespace App\Services;

final class FloodStatusService
{
    /*
     | UBAH BATAS KETINGGIAN AIR DI SINI
     |
     | Sensor ultrasonik membaca JARAK dari sensor ke permukaan air.
     | Semakin KECIL jaraknya, semakin TINGGI permukaan air.
     |
     | Aturan hasil kalibrasi miniatur:
     | - SAFE    : jarak > 8.5 cm
     | - WARNING : jarak > 6.8 cm sampai 8.5 cm
     | - DANGER  : jarak 6.5 cm sampai 6.8 cm
     | - FLOOD   : jarak < 6.5 cm
     |
     | Untuk mengubah batas status, ubah tiga konstanta berikut.
     */
    public const MAX_DISTANCE_CM = 12;

    // Saat jarak sensor mencapai 6 cm, miniatur dianggap terisi 100%.
    public const FULL_DISTANCE_CM = 6;

    public const WARNING_DISTANCE_CM = 8.5;

    public const DANGER_DISTANCE_CM = 6.8;

    public const FLOOD_DISTANCE_CM = 6.5;

    // Batas valid pembacaan fisik sensor. Ini bukan batas status banjir.
    public const MAX_SENSOR_DISTANCE_CM = 400;

    public const SAFE = 'SAFE';

    public const WARNING = 'WARNING';

    public const DANGER = 'DANGER';

    public const FLOOD = 'FLOOD';

    public function statusFor(float $distance): string
    {
        if ($distance < self::FLOOD_DISTANCE_CM) {
            return self::FLOOD;
        }

        if ($distance <= self::DANGER_DISTANCE_CM) {
            return self::DANGER;
        }

        if ($distance <= self::WARNING_DISTANCE_CM) {
            return self::WARNING;
        }

        return self::SAFE;
    }

    public function waterLevelFor(float $distance): int
    {
        $measurementRange = self::MAX_DISTANCE_CM - self::FULL_DISTANCE_CM;
        $percentage = round(((self::MAX_DISTANCE_CM - $distance) / $measurementRange) * 100);

        return (int) max(0, min(100, $percentage));
    }

    /** @return array{status: string, water_level: int} */
    public function calculate(float $distance): array
    {
        return [
            'status' => $this->statusFor($distance),
            'water_level' => $this->waterLevelFor($distance),
        ];
    }
}
