<?php

namespace Tests\Feature;

use App\Models\SensorReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatPageStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_header_is_online_when_latest_sensor_data_is_fresh(): void
    {
        SensorReading::factory()->create(['recorded_at' => now()]);

        $this->get('/chatbot')
            ->assertOk()
            ->assertSee('data-chat-connection-status', false)
            ->assertSee('Online');
    }

    public function test_chat_header_is_offline_when_latest_sensor_data_is_stale(): void
    {
        SensorReading::factory()->create(['recorded_at' => now()->subMinute()]);

        $this->get('/chatbot')
            ->assertOk()
            ->assertSee('data-chat-connection-status', false)
            ->assertSee('Offline');
    }
}
