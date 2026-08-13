<?php

namespace Tests\Unit;

use App\Services\FloodStatusService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FloodStatusServiceTest extends TestCase
{
    private FloodStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FloodStatusService;
    }

    public static function statusCases(): array
    {
        return [
            'lebih dari 8.5 aman' => [8.51, 'SAFE'],
            'tepat 8.5 waspada' => [8.5, 'WARNING'],
            'lebih dari 6.8 waspada' => [6.81, 'WARNING'],
            'tepat 6.8 bahaya' => [6.8, 'DANGER'],
            'tepat 6.5 bahaya' => [6.5, 'DANGER'],
            'kurang dari 6.5 banjir' => [6.49, 'FLOOD'],
        ];
    }

    #[DataProvider('statusCases')]
    public function test_status_dihitung_dari_jarak(float $distance, string $expected): void
    {
        $this->assertSame($expected, $this->service->statusFor($distance));
    }

    public function test_water_level_menggunakan_tinggi_maksimum_12_cm(): void
    {
        $this->assertSame(0, $this->service->waterLevelFor(12));
        $this->assertSame(50, $this->service->waterLevelFor(9));
    }

    public function test_water_level_mencapai_seratus_persen_pada_jarak_6_cm(): void
    {
        $this->assertSame(100, $this->service->waterLevelFor(6));
    }

    public function test_water_level_tidak_kurang_dari_nol(): void
    {
        $this->assertSame(0, $this->service->waterLevelFor(45));
    }

    public function test_water_level_tidak_lebih_dari_seratus(): void
    {
        $this->assertSame(100, $this->service->waterLevelFor(-5));
    }

    public function test_warning_tidak_langsung_kembali_safe_di_dekat_batas(): void
    {
        $this->assertSame('WARNING', $this->service->statusFor(8.6, 'WARNING'));
        $this->assertSame('WARNING', $this->service->statusFor(8.8, 'WARNING'));
        $this->assertSame('SAFE', $this->service->statusFor(8.81, 'WARNING'));
    }

    public function test_perubahan_ke_status_lebih_buruk_tidak_ditunda(): void
    {
        $this->assertSame('WARNING', $this->service->statusFor(8.5, 'SAFE'));
        $this->assertSame('DANGER', $this->service->statusFor(6.8, 'WARNING'));
        $this->assertSame('FLOOD', $this->service->statusFor(6.49, 'DANGER'));
    }
}
