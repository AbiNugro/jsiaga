<?php

namespace Tests\Feature;

use App\Models\SensorReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_hi_dan_halo_dijawab_lokal(): void
    {
        foreach (['hi', 'halo'] as $greeting) {
            $this->postJson('/api/v1/chat', ['message' => $greeting])
                ->assertOk()
                ->assertJsonPath('data.source', 'lokal')
                ->assertJsonFragment(['answer' => 'Halo! Saya J-SIAGA Assistant. Saya dapat membantu menjelaskan status banjir, data sensor terbaru, batas WARNING, DANGER, dan FLOOD, serta tindakan keselamatan.']);
        }
    }

    public function test_pertanyaan_status_dan_level_mengambil_sensor_terbaru(): void
    {
        SensorReading::factory()->create(['status' => 'SAFE', 'distance' => 25, 'water_level' => 17, 'recorded_at' => now()->subMinute()]);
        SensorReading::factory()->create(['status' => 'DANGER', 'distance' => 9, 'water_level' => 70, 'recorded_at' => now()]);

        $statusAnswer = $this->postJson('/api/v1/chat', ['message' => 'Status sekarang'])
            ->assertOk()->assertJsonPath('data.source', 'lokal')->assertJsonFragment(['source' => 'lokal'])
            ->json('data.answer');
        $this->assertStringContainsString('DANGER', $statusAnswer);
        $this->assertStringContainsString('70%', $statusAnswer);
        $this->assertStringNotContainsString('9 cm', $statusAnswer);
        $this->assertStringContainsString('70%', $this->postJson('/api/v1/chat', ['message' => 'Berapa level air?'])->json('data.answer'));
    }

    public function test_fallback_aman_ketika_groq_gagal_dan_key_tidak_bocor(): void
    {
        config(['services.groq.key' => 'rahasia-yang-tidak-boleh-muncul']);
        Http::fake(['*' => Http::response(['error' => ['message' => 'rahasia-yang-tidak-boleh-muncul']], 500)]);

        $response = $this->postJson('/api/v1/chat', ['message' => 'Jelaskan hidrologi secara rinci']);
        $response->assertOk()->assertJsonPath('data.source', 'fallback_lokal');
        $this->assertStringNotContainsString('rahasia-yang-tidak-boleh-muncul', $response->getContent());
    }

    public function test_pertanyaan_di_luar_topik_dibatasi_dan_tidak_dikirim_ke_groq(): void
    {
        config(['services.groq.key' => 'groq-test-key']);
        Http::fake();

        $this->postJson('/api/v1/chat', ['message' => 'Buatkan resep nasi goreng'])
            ->assertOk()
            ->assertJsonPath('data.source', 'dibatasi')
            ->assertJsonFragment(['answer' => 'Maaf, chatbot ini hanya dapat menjawab tentang data sensor J-SIAGA, status banjir, batas level, kondisi cahaya atau cuaca terkait banjir, dan tindakan keselamatan.']);

        Http::assertNothingSent();
    }

    public function test_pertanyaan_bebas_dijawab_oleh_groq(): void
    {
        config([
            'services.groq.key' => 'groq-test-key',
            'services.groq.model' => 'llama-3.1-8b-instant',
        ]);
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Jawaban cepat dari Groq.']]],
            ]),
        ]);

        $this->postJson('/api/v1/chat', ['message' => 'Jelaskan hidrologi secara rinci'])
            ->assertOk()
            ->assertJsonPath('data.source', 'ai')
            ->assertJsonPath('data.answer', 'Jawaban cepat dari Groq.');

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
            && $request['model'] === 'llama-3.1-8b-instant'
            && $request['max_completion_tokens'] === 350
            && $request->hasHeader('Authorization', 'Bearer groq-test-key')
        );
    }

    public function test_rekomendasi_groq_menerima_semua_konteks_sensor_dan_kondisi_cahaya(): void
    {
        config(['services.groq.key' => 'groq-test-key']);
        SensorReading::factory()->create([
            'status' => 'SAFE',
            'distance' => 28.2,
            'water_level' => 0,
            'temperature' => 28.4,
            'humidity' => 57,
            'light' => 300,
            'recorded_at' => now(),
        ]);
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Periksa informasi BMKG dan pantau level air lebih sering jika hujan mulai turun.']]],
            ]),
        ]);

        $response = $this->postJson('/api/v1/recommendations/explain', ['locale' => 'id'])
            ->assertOk()
            ->assertJsonPath('data.source', 'ai');
        $answer = $response->json('data.answer');
        $this->assertStringStartsWith('Status SAFE', $answer);
        $this->assertStringNotContainsString('Ringkasan database', $answer);
        $this->assertStringContainsString('Suhu 28.4 °C', $answer);
        $this->assertStringContainsString('Kelembapan 57%', $answer);
        $this->assertStringNotContainsString('Jarak air', $answer);
        $this->assertStringNotContainsString('cm', $answer);
        $this->assertStringContainsString('Level air 0%', $answer);
        $this->assertStringContainsString('Kondisi cahaya Mendung', $answer);
        $this->assertStringContainsString('Rekomendasi: Periksa informasi BMKG', $answer);

        Http::assertSent(function ($request) {
            $prompt = $request['messages'][0]['content'];

            return str_contains($prompt, 'Status: SAFE')
                && ! str_contains($prompt, 'Jarak air:')
                && ! str_contains($prompt, ' cm')
                && str_contains($prompt, 'Level air: 0%')
                && str_contains($prompt, 'Suhu: 28.4')
                && str_contains($prompt, 'Kelembapan: 57%')
                && str_contains($prompt, 'Kondisi cahaya: Mendung')
                && str_contains($prompt, 'maksimal 60 kata')
                && str_contains($prompt, 'dua tindakan nyata paling penting')
                && str_contains($prompt, 'belum membuktikan hujan')
                && str_contains($prompt, 'BMKG');
        });
    }

    public function test_rekomendasi_ai_ditolak_saat_data_sensor_sudah_offline(): void
    {
        config([
            'services.groq.key' => 'groq-test-key',
            'services.jsiaga.offline_seconds' => 15,
        ]);
        SensorReading::factory()->create([
            'status' => 'SAFE',
            'recorded_at' => now()->subMinute(),
        ]);
        Http::fake();

        $this->postJson('/api/v1/recommendations/explain', ['locale' => 'id'])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        Http::assertNothingSent();
    }

    public function test_rekomendasi_ai_mengikuti_bahasa_inggris_yang_dipilih(): void
    {
        config(['services.groq.key' => 'groq-test-key']);
        SensorReading::factory()->create([
            'status' => 'FLOOD',
            'distance' => 6,
            'water_level' => 100,
            'temperature' => 26,
            'humidity' => 50,
            'light' => 300,
            'recorded_at' => now(),
        ]);
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Recommendation: Move to higher ground immediately and stay away from currents and wet electrical equipment.']]],
            ]),
        ]);

        $answer = $this->postJson('/api/v1/recommendations/explain', ['locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.source', 'ai')
            ->json('data.answer');

        $this->assertStringStartsWith('Status FLOOD', $answer);
        $this->assertStringContainsString('Recommendation: Move to higher ground immediately', $answer);
        $this->assertStringNotContainsString('Recommendation: Recommendation:', $answer);

        Http::assertSent(function ($request) {
            $prompt = $request['messages'][0]['content'];

            return str_contains($prompt, 'Write the complete answer only in English')
                && str_contains($prompt, 'Return only the recommendation text in plain English')
                && str_contains($prompt, 'Do not use Indonesian or Korean')
                && str_contains($prompt, 'Sound the warning and evacuate everyone');
        });
    }

    public function test_ringkasan_chat_mengambil_data_terbaru_dan_menampilkan_kondisi_cahaya(): void
    {
        SensorReading::factory()->create(['status' => 'WARNING', 'light' => 120, 'recorded_at' => now()->subMinute()]);
        SensorReading::factory()->create([
            'status' => 'SAFE',
            'distance' => 30,
            'water_level' => 0,
            'temperature' => 27,
            'humidity' => 60,
            'light' => 300,
            'recorded_at' => now(),
        ]);

        $answer = $this->postJson('/api/v1/chat', ['message' => 'Semua data sensor'])
            ->assertOk()
            ->assertJsonPath('data.source', 'lokal')
            ->json('data.answer');

        $this->assertStringContainsString('SAFE', $answer);
        $this->assertStringContainsString('Level air: 0%', $answer);
        $this->assertStringContainsString('Kondisi cahaya: Mendung', $answer);
        $this->assertStringNotContainsString('Jarak air', $answer);
        $this->assertStringNotContainsString('cm', $answer);
        $this->assertStringNotContainsString('Cahaya LDR: 300', $answer);
    }

    public function test_sensor_belum_ada_dijelaskan_dengan_baik(): void
    {
        $this->postJson('/api/v1/chat', ['message' => 'Data sensor'])
            ->assertOk()
            ->assertJsonPath('data.source', 'lokal')
            ->assertJsonFragment(['answer' => 'Belum ada pembacaan sensor yang tersimpan. Pastikan ESP terhubung ke HiveMQ dan bridge MQTT sedang berjalan.']);
    }

    public function test_pesan_terlalu_panjang_ditolak(): void
    {
        $this->postJson('/api/v1/chat', ['message' => str_repeat('a', 501)])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_chat_dibatasi_per_menit(): void
    {
        config([
            'services.ai_limits.per_minute' => 2,
            'services.ai_limits.per_day' => 100,
        ]);

        $client = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.44']);
        $client->postJson('/api/v1/chat', ['message' => 'halo'])->assertOk();
        $client->postJson('/api/v1/chat', ['message' => 'halo'])->assertOk();
        $client->postJson('/api/v1/chat', ['message' => 'halo'])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }
}
