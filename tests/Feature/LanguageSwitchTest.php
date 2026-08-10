<?php

namespace Tests\Feature;

use App\Models\SensorReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_interface_uses_indonesian_and_replaces_connection_pill(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Kondisi sungai dalam satu pandangan')
            ->assertSee('Pilih bahasa')
            ->assertDontSee('data-connection-indicator', false);
    }

    public function test_language_can_be_switched_to_english_and_persists_in_session(): void
    {
        $this->post('/language/en')->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('River conditions at a glance')
            ->assertSee('Real-time flood monitoring');
    }

    public function test_language_can_be_switched_to_korean(): void
    {
        $this->post('/language/ko')->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="ko"', false)
            ->assertSee('한눈에 보는 하천 상태')
            ->assertSee('실시간 홍수 모니터링');
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $this->post('/language/fr')->assertNotFound();
    }

    public function test_recommendation_card_shows_water_level_and_light_without_ai_provider_brand(): void
    {
        SensorReading::factory()->create([
            'status' => 'SAFE',
            'water_level' => 0,
            'light' => 300,
            'recorded_at' => now(),
        ]);

        $this->withSession(['locale' => 'en'])
            ->get('/rekomendasi')
            ->assertOk()
            ->assertSee('Water level')
            ->assertSee('Light condition')
            ->assertSee('Cloudy')
            ->assertSee('Ask AI')
            ->assertSee('data-status-badge-label', false)
            ->assertDontSee('Water distance')
            ->assertDontSee(' cm')
            ->assertDontSee('Groq')
            ->assertDontSee('Request explanation');
    }

    public function test_home_menampilkan_offline_dan_alarm_untuk_data_kedaluwarsa(): void
    {
        config(['services.jsiaga.offline_seconds' => 15]);
        SensorReading::factory()->create([
            'status' => 'SAFE',
            'recorded_at' => now()->subMinute(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('OFFLINE — data tidak diperbarui')
            ->assertSee('Sensor tidak memperbarui data')
            ->assertSee('Aktifkan peringatan')
            ->assertSee('alert-icon-button', false)
            ->assertSee('data-alert-button-label class="sr-only"', false)
            ->assertSee('data-alert-feedback', false)
            ->assertSee('Suara status aktif untuk SAFE, WARNING, DANGER, dan FLOOD.')
            ->assertSee('WARNING. Waspada, level air meningkat')
            ->assertSee('DANGER. Bahaya banjir')
            ->assertSee('FLOOD. Banjir terdeteksi')
            ->assertDontSee('data-status="SAFE"', false);
    }

    public function test_kondisi_cahaya_riwayat_tampil_sebagai_kategori_tanpa_angka_ldr(): void
    {
        SensorReading::factory()->create([
            'light' => 300,
            'recorded_at' => now(),
        ]);

        $this->get('/riwayat?range=1h')
            ->assertOk()
            ->assertSee('Kondisi cahaya')
            ->assertSee('Mendung')
            ->assertDontSee('>300<', false)
            ->assertDontSee('>LDR<', false);
    }

    public function test_tombol_dan_feedback_peringatan_tersedia_di_semua_halaman(): void
    {
        SensorReading::factory()->create(['recorded_at' => now()]);

        foreach (['/', '/riwayat', '/rekomendasi', '/chatbot'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('data-enable-alerts', false)
                ->assertSee('data-alert-feedback', false);
        }
    }

    public function test_chat_local_answer_follows_requested_language(): void
    {
        $this->postJson('/api/v1/chat', ['message' => 'hello', 'locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.source', 'lokal')
            ->assertJsonPath('data.answer', __('ui.chat.answers.greeting', locale: 'en'));

        $this->postJson('/api/v1/chat', ['message' => '안녕하세요', 'locale' => 'ko'])
            ->assertOk()
            ->assertJsonPath('data.source', 'lokal')
            ->assertJsonPath('data.answer', __('ui.chat.answers.greeting', locale: 'ko'));
    }
}
