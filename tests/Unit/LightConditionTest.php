<?php

namespace Tests\Unit;

use App\Support\LightCondition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LightConditionTest extends TestCase
{
    public static function conditions(): array
    {
        return [
            'missing' => [null, null],
            'bright minimum' => [0, 'bright'],
            'bright maximum' => [199, 'bright'],
            'cloudy minimum' => [200, 'cloudy'],
            'cloudy maximum' => [449, 'cloudy'],
            'dim minimum' => [450, 'dim'],
            'dim maximum' => [699, 'dim'],
            'dark minimum' => [700, 'dark'],
            'dark maximum' => [1023, 'dark'],
        ];
    }

    #[DataProvider('conditions')]
    public function test_it_maps_raw_light_values_to_conditions(?int $value, ?string $expected): void
    {
        $this->assertSame($expected, LightCondition::key($value));
    }
}
