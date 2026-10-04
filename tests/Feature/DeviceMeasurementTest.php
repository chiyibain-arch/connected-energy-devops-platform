<?php

namespace Tests\Feature;

use App\Enums\DeviceTransport;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceMeasurementTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_and_measurement_relationships_and_casts(): void
    {
        $device = Device::create([
            'device_uid' => 'meter-001',
            'name' => 'Main Meter',
            'transport' => DeviceTransport::Http,
            'api_key' => 'test-api-key',
            'last_seen_at' => '2026-08-25 10:00:00',
        ])->refresh();

        $measurement = $device->measurements()->create([
            'voltage' => 230.5,
            'current' => 4.125,
            'power' => 950.75,
            'measured_at' => '2026-08-25 10:00:00',
        ])->refresh();

        $this->assertSame('offline', $device->status);
        $this->assertSame(DeviceTransport::Http, $device->transport);
        $this->assertSame('230.500', $measurement->voltage);
        $this->assertSame('4.125', $measurement->current);
        $this->assertSame('950.750', $measurement->power);
        $this->assertSame('2026-08-25 10:00:00', $device->last_seen_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-25 10:00:00', $measurement->measured_at->format('Y-m-d H:i:s'));
        $this->assertTrue($device->measurements->contains($measurement));
        $this->assertTrue($measurement->device->is($device));
    }

    public function test_mqtt_device_supports_a_null_api_key(): void
    {
        $device = Device::create([
            'device_uid' => 'DEV-002',
            'name' => 'MQTT Energy Meter',
            'transport' => DeviceTransport::Mqtt,
            'api_key' => null,
        ])->refresh();

        $this->assertSame(DeviceTransport::Mqtt, $device->transport);
        $this->assertNull($device->api_key);
    }

    public function test_deleting_a_device_cascades_to_its_measurements(): void
    {
        $device = Device::create([
            'device_uid' => 'meter-002',
            'name' => 'Secondary Meter',
            'api_key' => 'another-test-api-key',
        ]);

        $measurement = $device->measurements()->create([
            'voltage' => 230,
            'current' => 2,
            'power' => 460,
            'measured_at' => now(),
        ]);

        $device->delete();

        $this->assertDatabaseMissing('measurements', ['id' => $measurement->id]);
        $this->assertDatabaseCount('devices', 0);
    }
}
