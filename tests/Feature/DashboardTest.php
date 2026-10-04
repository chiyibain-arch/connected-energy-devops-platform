<?php

namespace Tests\Feature;

use App\Enums\DeviceTransport;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_is_available_without_exposing_device_secrets(): void
    {
        $this->withoutVite();

        Device::create([
            'device_uid' => 'DEV-001',
            'name' => 'HTTP Energy Meter',
            'transport' => DeviceTransport::Http,
            'api_key' => 'secret-device-key',
        ]);
        Device::create([
            'device_uid' => 'DEV-002',
            'name' => 'MQTT Energy Meter',
            'transport' => DeviceTransport::Mqtt,
            'api_key' => null,
        ]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Energy Monitor')
            ->assertSee('Realtime Usage')
            ->assertSee('Realtime Power Trend')
            ->assertSee('Energy Consumption')
            ->assertSee('Total Today')
            ->assertSee('Daily Average')
            ->assertSee('Total This Month')
            ->assertSee('Monthly Average')
            ->assertSee('Last 30 Days')
            ->assertSee('Last 12 Months')
            ->assertSee('Recent Measurements')
            ->assertSee('selected-device-transport')
            ->assertDontSee('secret-device-key');

        $this->getJson('/api/v1/devices')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.device_uid', 'DEV-001')
            ->assertJsonPath('data.0.transport', 'http')
            ->assertJsonPath('data.1.device_uid', 'DEV-002')
            ->assertJsonPath('data.1.transport', 'mqtt')
            ->assertJsonMissingPath('data.0.api_key')
            ->assertJsonMissingPath('data.1.api_key');
    }
}
