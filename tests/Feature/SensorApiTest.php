<?php

namespace Tests\Feature;

use App\Models\SensorReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensorApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.jsiaga.device_token' => 'device-test-token']);
    }

    public function test_payload_sensor_tidak_valid_ditolak(): void
    {
        $this->withHeader('X-Device-Token', 'device-test-token')
            ->postJson('/api/v1/sensor-readings', ['distance' => 'rusak'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('distance');
    }

    public function test_jarak_di_luar_jangkauan_sensor_ditolak(): void
    {
        $this->withHeader('X-Device-Token', 'device-test-token')
            ->postJson('/api/v1/sensor-readings', ['distance' => 401])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('distance');

        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_jarak_di_atas_tinggi_miniatur_tetap_valid_dengan_level_nol(): void
    {
        $this->withHeader('X-Device-Token', 'device-test-token')
            ->postJson('/api/v1/sensor-readings', ['distance' => 217.6])
            ->assertCreated()
            ->assertJsonPath('data.status', 'SAFE')
            ->assertJsonPath('data.water_level', 0);
    }

    public function test_device_token_salah_ditolak(): void
    {
        $this->withHeader('X-Device-Token', 'salah')
            ->postJson('/api/v1/sensor-readings', ['distance' => 24])
            ->assertUnauthorized()
            ->assertJsonMissing(['device-test-token']);
    }

    public function test_sensor_valid_disimpan_dengan_perhitungan_server(): void
    {
        $this->withHeader('X-Device-Token', 'device-test-token')
            ->postJson('/api/v1/sensor-readings', [
                'distance' => 7.5,
                'water_level' => 0,
                'status' => 'SAFE',
                'temperature' => 29,
                'humidity' => 72,
                'light' => 430,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'WARNING')
            ->assertJsonPath('data.water_level', 75);

        $this->assertDatabaseHas('sensor_readings', ['status' => 'WARNING', 'water_level' => 75]);

        $this->travel(11)->seconds();

        $this->withHeader('X-Device-Token', 'device-test-token')
            ->postJson('/api/v1/sensor-readings', ['distance' => 6])
            ->assertCreated()
            ->assertJsonPath('data.status', 'FLOOD')
            ->assertJsonPath('data.water_level', 100)
            ->assertJsonPath('data.status_label', 'Banjir');
    }

    public function test_latest_mengembalikan_data_terbaru(): void
    {
        SensorReading::factory()->create(['distance' => 25, 'recorded_at' => now()->subMinute()]);
        $latest = SensorReading::factory()->create(['distance' => 8, 'recorded_at' => now()]);

        $this->getJson('/api/v1/sensor-readings/latest')
            ->assertOk()
            ->assertJsonPath('data.id', $latest->id);
    }

    public function test_latest_menandai_data_lama_sebagai_offline(): void
    {
        config(['services.jsiaga.offline_seconds' => 15]);
        SensorReading::factory()->create([
            'status' => 'SAFE',
            'recorded_at' => now()->subSeconds(16),
        ]);

        $this->getJson('/api/v1/sensor-readings/latest')
            ->assertOk()
            ->assertJsonPath('data.status', 'SAFE')
            ->assertJsonPath('data.effective_status', 'OFFLINE')
            ->assertJsonPath('data.is_stale', true);
    }

    public function test_kiriman_cepat_memperbarui_data_terbaru_tanpa_menambah_baris_histori(): void
    {
        config(['services.jsiaga.history_interval_seconds' => 10]);

        $this->withHeader('X-Device-Token', 'device-test-token')
            ->postJson('/api/v1/sensor-readings', ['distance' => 9, 'light' => 500])
            ->assertCreated()
            ->assertJsonPath('meta.history_compacted', false);

        $this->withHeader('X-Device-Token', 'device-test-token')
            ->postJson('/api/v1/sensor-readings', ['distance' => 7.5, 'light' => 700])
            ->assertOk()
            ->assertJsonPath('data.status', 'WARNING')
            ->assertJsonPath('meta.history_compacted', true);

        $this->assertDatabaseCount('sensor_readings', 1);
        $this->assertDatabaseHas('sensor_readings', ['distance' => 7.5, 'light' => 700]);

        $this->travel(11)->seconds();

        $this->withHeader('X-Device-Token', 'device-test-token')
            ->postJson('/api/v1/sensor-readings', ['distance' => 6.6])
            ->assertCreated()
            ->assertJsonPath('data.status', 'DANGER');

        $this->assertDatabaseCount('sensor_readings', 2);
    }

    public function test_perintah_retensi_menghapus_data_yang_melewati_batas(): void
    {
        config(['services.jsiaga.retention_days' => 7]);
        $old = SensorReading::factory()->create(['recorded_at' => now()->subDays(8)]);
        $recent = SensorReading::factory()->create(['recorded_at' => now()->subDays(2)]);

        $this->artisan('jsiaga:prune-sensor-readings')->assertSuccessful();

        $this->assertDatabaseMissing('sensor_readings', ['id' => $old->id]);
        $this->assertDatabaseHas('sensor_readings', ['id' => $recent->id]);
    }

    public function test_latest_tanpa_data_ditangani_dengan_baik(): void
    {
        $this->getJson('/api/v1/sensor-readings/latest')
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Belum ada data sensor.');
    }

    public function test_filter_history_untuk_semua_range(): void
    {
        SensorReading::factory()->create(['recorded_at' => now()->subMinutes(30)]);
        SensorReading::factory()->create(['recorded_at' => now()->subHours(5)]);
        SensorReading::factory()->create(['recorded_at' => now()->subHours(20)]);
        SensorReading::factory()->create(['recorded_at' => now()->subDays(5)]);
        SensorReading::factory()->create(['recorded_at' => now()->subDays(8)]);

        foreach (['1h' => 1, '6h' => 2, '24h' => 3, '7d' => 4] as $range => $count) {
            $this->getJson('/api/v1/sensor-readings/history?range='.$range)
                ->assertOk()
                ->assertJsonCount($count, 'data')
                ->assertJsonPath('meta.range', $range);
        }
    }
}
