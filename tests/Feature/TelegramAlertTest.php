<?php

namespace Tests\Feature;

use App\Models\SensorReading;
use App\Models\TelegramSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TelegramAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://jsiaga.me',
            'services.jsiaga.device_token' => 'device-test-token',
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'telegram-test-token',
            'services.telegram.webhook_secret' => 'webhook-test-secret',
            'services.telegram.statuses' => ['WARNING', 'DANGER', 'FLOOD', 'SAFE'],
            'services.telegram.timeout' => 1,
        ]);
    }

    public function test_start_mendaftarkan_pelanggan_dan_mengirim_konfirmasi(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->postWebhook('/start', '101', 'andi')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('telegram_subscribers', [
            'chat_id' => '101',
            'username' => 'andi',
            'is_active' => true,
        ]);
        Http::assertSent(fn (Request $request): bool => $request['chat_id'] === '101'
            && str_contains($request['text'], 'Notifikasi J-SIAGA sudah aktif'));
    }

    public function test_webhook_menolak_request_dengan_secret_salah(): void
    {
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'salah')
            ->postJson('/api/telegram/webhook', [])
            ->assertForbidden();
    }

    public function test_stop_menonaktifkan_pelanggan(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        TelegramSubscriber::query()->create(['chat_id' => '101', 'is_active' => true]);

        $this->postWebhook('/stop', '101')->assertOk();

        $this->assertDatabaseHas('telegram_subscribers', ['chat_id' => '101', 'is_active' => false]);
    }

    public function test_pengguna_dapat_mengganti_bahasa_dengan_tombol(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        TelegramSubscriber::query()->create(['chat_id' => '101', 'is_active' => true, 'locale' => 'id']);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'webhook-test-secret')
            ->postJson('/api/telegram/webhook', [
                'update_id' => 2,
                'callback_query' => [
                    'id' => 'callback-1',
                    'data' => 'language:en',
                    'message' => ['chat' => ['id' => '101']],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('telegram_subscribers', ['chat_id' => '101', 'locale' => 'en']);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/answerCallbackQuery')
            && $request['callback_query_id'] === 'callback-1');
        Http::assertSent(fn (Request $request): bool => ($request['chat_id'] ?? null) === '101'
            && str_contains($request['text'], 'changed to English'));
    }

    public function test_perintah_bahasa_menampilkan_tiga_tombol(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        TelegramSubscriber::query()->create(['chat_id' => '101', 'is_active' => true, 'locale' => 'id']);

        $this->postWebhook('/bahasa', '101')->assertOk();

        Http::assertSent(function (Request $request): bool {
            $buttons = $request['reply_markup']['inline_keyboard'][0] ?? [];

            return count($buttons) === 3
                && $buttons[0]['callback_data'] === 'language:id'
                && $buttons[1]['callback_data'] === 'language:en'
                && $buttons[2]['callback_data'] === 'language:ko';
        });
    }

    public function test_notifikasi_status_dikirim_ke_semua_pelanggan_aktif(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        TelegramSubscriber::query()->create(['chat_id' => '101', 'is_active' => true]);
        TelegramSubscriber::query()->create(['chat_id' => '202', 'is_active' => true]);
        TelegramSubscriber::query()->create(['chat_id' => '303', 'is_active' => false]);

        $this->sendReading(9)->assertCreated();
        Http::assertNothingSent();

        $this->sendReading(7.5)->assertOk()->assertJsonPath('data.status', 'WARNING');
        $this->sendReading(7.4)->assertOk()->assertJsonPath('data.status', 'WARNING');

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request['chat_id'] === '101'
            && str_contains($request['text'], 'SAFE -> WARNING'));
        Http::assertSent(fn (Request $request): bool => $request['chat_id'] === '202'
            && str_contains($request['text'], 'SAFE -> WARNING'));
        Http::assertNotSent(fn (Request $request): bool => $request['chat_id'] === '303');
    }

    public function test_notifikasi_mengikuti_bahasa_masing_masing_pelanggan(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        TelegramSubscriber::query()->create(['chat_id' => 'id-user', 'is_active' => true, 'locale' => 'id']);
        TelegramSubscriber::query()->create(['chat_id' => 'en-user', 'is_active' => true, 'locale' => 'en']);
        TelegramSubscriber::query()->create(['chat_id' => 'ko-user', 'is_active' => true, 'locale' => 'ko']);

        $this->sendReading(6.6)->assertCreated();

        Http::assertSent(fn (Request $request): bool => $request['chat_id'] === 'id-user'
            && str_contains($request['text'], 'Level sensor:'));
        Http::assertSent(fn (Request $request): bool => $request['chat_id'] === 'en-user'
            && str_contains($request['text'], 'Sensor level:'));
        Http::assertSent(fn (Request $request): bool => $request['chat_id'] === 'ko-user'
            && str_contains($request['text'], '센서 수위:'));
    }

    public function test_status_bahaya_pertama_tetap_mengirim_notifikasi(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        TelegramSubscriber::query()->create(['chat_id' => '101', 'is_active' => true]);

        $this->sendReading(6.6)->assertCreated()->assertJsonPath('data.status', 'DANGER');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'BELUM ADA DATA -> DANGER'));
    }

    public function test_kegagalan_telegram_tidak_menggagalkan_penyimpanan_sensor(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);
        TelegramSubscriber::query()->create(['chat_id' => '101', 'is_active' => true]);

        $this->sendReading(6)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'FLOOD');

        $this->assertDatabaseCount('sensor_readings', 1);
    }

    public function test_perintah_cli_mengirim_pesan_tes_ke_semua_pelanggan(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        TelegramSubscriber::query()->create(['chat_id' => '101', 'is_active' => true]);
        TelegramSubscriber::query()->create(['chat_id' => '202', 'is_active' => true]);

        $this->artisan('jsiaga:test-telegram')->assertSuccessful();

        Http::assertSentCount(2);
    }

    public function test_monitor_mengirim_offline_satu_kali_saat_data_sensor_berhenti(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        TelegramSubscriber::query()->create(['chat_id' => '101', 'is_active' => true, 'locale' => 'id']);
        SensorReading::factory()->create([
            'status' => 'SAFE',
            'recorded_at' => now()->subSeconds(20),
        ]);

        $this->artisan('jsiaga:monitor-sensor')->assertSuccessful();
        $this->artisan('jsiaga:monitor-sensor')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], '[OFFLINE] DATA SENSOR TERHENTI'));
        $this->assertNotNull(SensorReading::query()->latest('recorded_at')->first()->offline_notified_at);
    }

    public function test_monitor_tidak_mengirim_offline_selama_data_masih_segar(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        TelegramSubscriber::query()->create(['chat_id' => '101', 'is_active' => true]);
        SensorReading::factory()->create(['recorded_at' => now()]);

        $this->artisan('jsiaga:monitor-sensor')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_data_baru_setelah_offline_mengirim_notifikasi_online(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        TelegramSubscriber::query()->create(['chat_id' => '101', 'is_active' => true, 'locale' => 'en']);
        SensorReading::factory()->create([
            'status' => 'SAFE',
            'recorded_at' => now()->subSeconds(20),
            'offline_notified_at' => now(),
        ]);

        $this->sendReading(9)->assertOk()->assertJsonPath('data.status', 'SAFE');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], '[ONLINE] SENSOR IS ACTIVE AGAIN')
            && str_contains($request['text'], 'OFFLINE -> SAFE'));
    }

    public function test_perintah_cli_mendaftarkan_webhook(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);

        $this->artisan('jsiaga:telegram-set-webhook')->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.telegram.org/bottelegram-test-token/setWebhook'
                && $request['url'] === 'https://jsiaga.me/api/telegram/webhook'
                && $request['secret_token'] === 'webhook-test-secret';
        });
        Http::assertSent(function (Request $request): bool {
            if (! str_ends_with($request->url(), '/setMyCommands')) {
                return false;
            }

            return array_column($request['commands'], 'command') === ['start', 'language', 'stop'];
        });
    }

    private function postWebhook(string $command, string $chatId, ?string $username = null): TestResponse
    {
        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'webhook-test-secret')
            ->postJson('/api/telegram/webhook', [
                'update_id' => 1,
                'message' => [
                    'message_id' => 1,
                    'text' => $command,
                    'chat' => [
                        'id' => $chatId,
                        'type' => 'private',
                        'username' => $username,
                        'first_name' => 'Andi',
                    ],
                    'from' => [
                        'id' => $chatId,
                        'username' => $username,
                        'first_name' => 'Andi',
                        'language_code' => 'id',
                    ],
                ],
            ]);
    }

    private function sendReading(float $distance): TestResponse
    {
        return $this->withHeader('X-Device-Token', 'device-test-token')
            ->postJson('/api/v1/sensor-readings', ['distance' => $distance]);
    }
}
