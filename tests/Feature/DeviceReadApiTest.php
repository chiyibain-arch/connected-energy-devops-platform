<?php

namespace Tests\Feature;

use App\Enums\DeviceTransport;
use App\Models\Device;
use App\Models\Measurement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_list_returns_devices(): void
    {
        $this->createDevice('DEV-002', [
            'name' => 'MQTT Energy Meter',
            'transport' => DeviceTransport::Mqtt,
            'api_key' => null,
        ]);
        $this->createDevice('DEV-001', [
            'name' => 'HTTP Energy Meter',
            'transport' => DeviceTransport::Http,
            'status' => 'online',
            'last_seen_at' => '2026-08-25 11:30:00',
        ]);

        $this->getJson('/api/v1/devices')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.device_uid', 'DEV-001')
            ->assertJsonPath('data.0.name', 'HTTP Energy Meter')
            ->assertJsonPath('data.0.transport', 'http')
            ->assertJsonPath('data.0.status', 'online')
            ->assertJsonPath('data.0.last_seen_at', '2026-08-25T11:30:00+00:00')
            ->assertJsonPath('data.1.device_uid', 'DEV-002')
            ->assertJsonPath('data.1.name', 'MQTT Energy Meter')
            ->assertJsonPath('data.1.transport', 'mqtt');
    }

    public function test_api_key_is_not_exposed_by_read_endpoints(): void
    {
        $device = $this->createDevice('DEV-001');
        $this->createMeasurement($device, '2026-08-25 11:30:00');

        $responses = [
            $this->getJson('/api/v1/devices'),
            $this->getJson('/api/v1/devices/DEV-001/measurements'),
            $this->getJson('/api/v1/devices/DEV-001/latest'),
        ];

        foreach ($responses as $response) {
            $response->assertOk();
            $this->assertStringNotContainsString($device->api_key, $response->getContent());
            $this->assertStringNotContainsString('api_key', $response->getContent());
        }
    }

    public function test_measurement_list_is_ordered_and_limited_to_the_requested_device(): void
    {
        $device = $this->createDevice('DEV-001');
        $otherDevice = $this->createDevice('DEV-002');
        $older = $this->createMeasurement($device, '2026-08-25 10:00:00');
        $newer = $this->createMeasurement($device, '2026-08-25 11:00:00');
        $otherMeasurement = $this->createMeasurement($otherDevice, '2026-08-25 12:00:00');

        $this->getJson('/api/v1/devices/DEV-001/measurements')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonMissing(['id' => $otherMeasurement->id]);
    }

    public function test_measurement_list_uses_fifty_item_pagination(): void
    {
        $device = $this->createDevice('DEV-001');
        $start = CarbonImmutable::parse('2026-08-25 10:00:00');

        $rows = [];

        for ($index = 0; $index < 51; $index++) {
            $rows[] = [
                'device_id' => $device->id,
                'voltage' => 230,
                'current' => 2,
                'power' => 460,
                'measured_at' => $start->addMinutes($index),
                'created_at' => $start,
                'updated_at' => $start,
            ];
        }

        Measurement::query()->insert($rows);

        $this->getJson('/api/v1/devices/DEV-001/measurements')
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 51);

        $this->getJson('/api/v1/devices/DEV-001/measurements?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_from_filter_limits_measurements_by_measured_at(): void
    {
        $device = $this->createDevice('DEV-001');
        $this->createMeasurement($device, '2026-08-25 09:00:00');
        $included = $this->createMeasurement($device, '2026-08-25 10:00:00');
        $newest = $this->createMeasurement($device, '2026-08-25 11:00:00');

        $this->getJson('/api/v1/devices/DEV-001/measurements?from=2026-08-25T10:00:00Z')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $included->id);
    }

    public function test_to_filter_limits_measurements_by_measured_at(): void
    {
        $device = $this->createDevice('DEV-001');
        $oldest = $this->createMeasurement($device, '2026-08-25 09:00:00');
        $included = $this->createMeasurement($device, '2026-08-25 10:00:00');
        $this->createMeasurement($device, '2026-08-25 11:00:00');

        $this->getJson('/api/v1/devices/DEV-001/measurements?to=2026-08-25T10:00:00Z')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $included->id)
            ->assertJsonPath('data.1.id', $oldest->id);
    }

    public function test_measurement_filters_must_be_valid_dates(): void
    {
        $this->createDevice('DEV-001');

        $this->getJson('/api/v1/devices/DEV-001/measurements?from=invalid&to=also-invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'to']);
    }

    public function test_latest_returns_the_most_recent_measurement(): void
    {
        $device = $this->createDevice('DEV-001', [
            'status' => 'online',
            'last_seen_at' => '2026-08-25 11:31:00',
        ]);
        $this->createMeasurement($device, '2026-08-25 10:00:00');
        $latest = $this->createMeasurement($device, '2026-08-25 11:30:00', [
            'voltage' => 231.5,
            'current' => 2.5,
            'power' => 578.75,
        ]);

        $this->getJson('/api/v1/devices/DEV-001/latest')
            ->assertOk()
            ->assertJsonPath('data.device_uid', 'DEV-001')
            ->assertJsonPath('data.voltage', 231.5)
            ->assertJsonPath('data.current', 2.5)
            ->assertJsonPath('data.power', 578.75)
            ->assertJsonPath('data.measured_at', $latest->measured_at->toIso8601String())
            ->assertJsonPath('data.status', 'online')
            ->assertJsonPath('data.last_seen_at', '2026-08-25T11:31:00+00:00');
    }

    public function test_latest_endpoint_is_isolated_between_devices(): void
    {
        $httpDevice = $this->createDevice('DEV-001');
        $mqttDevice = $this->createDevice('DEV-002', [
            'transport' => DeviceTransport::Mqtt,
            'api_key' => null,
        ]);
        $httpLatest = $this->createMeasurement($httpDevice, '2026-08-25 11:00:00', ['power' => 111]);
        $mqttLatest = $this->createMeasurement($mqttDevice, '2026-08-25 12:00:00', ['power' => 999]);

        $this->getJson('/api/v1/devices/DEV-001/latest')
            ->assertOk()
            ->assertJsonPath('data.device_uid', 'DEV-001')
            ->assertJsonPath('data.power', 111)
            ->assertJsonMissing(['power' => 999]);

        $this->getJson('/api/v1/devices/DEV-002/latest')
            ->assertOk()
            ->assertJsonPath('data.device_uid', 'DEV-002')
            ->assertJsonPath('data.power', 999)
            ->assertJsonMissing(['id' => $httpLatest->id])
            ->assertJsonMissing(['power' => 111]);

        $this->assertNotSame($httpLatest->device_id, $mqttLatest->device_id);
    }

    public function test_unknown_device_returns_not_found(): void
    {
        $this->getJson('/api/v1/devices/UNKNOWN/measurements')->assertNotFound();
        $this->getJson('/api/v1/devices/UNKNOWN/latest')->assertNotFound();
    }

    public function test_device_without_measurements_returns_a_clear_response(): void
    {
        $this->createDevice('DEV-001');

        $this->getJson('/api/v1/devices/DEV-001/latest')
            ->assertOk()
            ->assertExactJson([
                'message' => 'No measurements found for device [DEV-001].',
            ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDevice(string $deviceUid, array $attributes = []): Device
    {
        return Device::create(array_merge([
            'device_uid' => $deviceUid,
            'name' => $deviceUid.' Meter',
            'api_key' => $deviceUid.'-secret-key',
        ], $attributes));
    }

    /**
     * @param  array<string, float>  $attributes
     */
    private function createMeasurement(Device $device, string $measuredAt, array $attributes = []): Measurement
    {
        return $device->measurements()->create(array_merge([
            'voltage' => 230,
            'current' => 2,
            'power' => 460,
            'measured_at' => $measuredAt,
        ], $attributes));
    }
}
