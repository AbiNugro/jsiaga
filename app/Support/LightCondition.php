<?php

namespace App\Support;

final class LightCondition
{
    public static function key(?int $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (true) {
            $value <= 199 => 'bright',
            $value <= 449 => 'cloudy',
            $value <= 699 => 'dim',
            default => 'dark',
        };
    }
}
