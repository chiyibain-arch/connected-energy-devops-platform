<?php

namespace Tests\Feature;

use App\Enums\DeviceTransport;
use App\Models\Device;
use Database\Seeders\DemoDeviceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDeviceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_configures_distinct_http_and_mqtt_demo_devices(): void
    {
        $this->seed(DemoDeviceSeeder::class);

        $httpDevice = Device::query()->where('device_uid', 'DEV-001')->sole();
        $mqttDevice = Device::query()->where('device_uid', 'DEV-002')->sole();

        $this->assertSame('HTTP Energy Meter', $httpDevice->name);
        $this->assertSame(DeviceTransport::Http, $httpDevice->transport);
        $this->assertSame('dev-device-key', $httpDevice->api_key);
        $this->assertSame('offline', $httpDevice->status);

        $this->assertSame('MQTT Energy Meter', $mqttDevice->name);
        $this->assertSame(DeviceTransport::Mqtt, $mqttDevice->transport);
        $this->assertNull($mqttDevice->api_key);
        $this->assertSame('offline', $mqttDevice->status);
    }
}
