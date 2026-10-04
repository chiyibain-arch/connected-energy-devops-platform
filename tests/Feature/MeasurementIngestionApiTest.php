<?php

namespace Tests\Feature;

use App\Enums\DeviceTransport;
use App\Models\Device;
use App\Models\Measurement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasurementIngestionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_measurement_is_stored_and_device_is_marked_online(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-25 11:31:00'));

        $device = Device::create([
            'device_uid' => 'DEV-001',
            'name' => 'HTTP Energy Meter',
            'transport' => DeviceTransport::Http,
            'api_key' => 'secret-device-key',
        ]);

        $response = $this->withHeader('X-Device-Key', 'secret-device-key')->postJson('/api/v1/measurements', [
            'device_id' => 'DEV-001',
            'voltage' => 230.4,
            'current' => 2.8,
            'power' => 645.1,
            'timestamp' => '2026-08-25T11:30:00',
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'device_id' => 'DEV-001',
                'voltage' => 230.4,
                'current' => 2.8,
                'power' => 645.1,
                'measured_at' => '2026-08-25T11:30:00+00:00',
            ]);

        $this->assertStringNotContainsString('secret-device-key', $response->getContent());

        $measurement = Measurement::query()->sole();

        $this->assertSame($device->id, $measurement->device_id);
        $this->assertTrue($measurement->device->is($device));
        $this->assertDatabaseHas('measurements', [
            'id' => $measurement->id,
            'device_id' => $device->id,
            'measured_at' => '2026-08-25 11:30:00',
        ]);

        $device->refresh();

        $this->assertSame('online', $device->status);
        $this->assertSame('2026-08-25 11:31:00', $device->last_seen_at->format('Y-m-d H:i:s'));
    }

    public function test_invalid_payload_returns_validation_errors(): void
    {
        Device::create([
            'device_uid' => 'DEV-001',
            'name' => 'Main Meter',
            'api_key' => 'secret-device-key',
        ]);

        $response = $this->withHeader('X-Device-Key', 'secret-device-key')->postJson('/api/v1/measurements', [
            'device_id' => '',
            'voltage' => -1,
            'current' => 'invalid',
            'power' => -1,
            'timestamp' => 'not-a-date',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'device_id',
                'voltage',
                'current',
                'power',
                'timestamp',
            ]);

        $this->assertDatabaseCount('measurements', 0);
    }

    public function test_unknown_device_returns_not_found(): void
    {
        Device::create([
            'device_uid' => 'DEV-001',
            'name' => 'Main Meter',
            'api_key' => 'secret-device-key',
        ]);

        $response = $this->withHeader('X-Device-Key', 'secret-device-key')->postJson('/api/v1/measurements', [
            'device_id' => 'UNKNOWN',
            'voltage' => 230.4,
            'current' => 2.8,
            'power' => 645.1,
            'timestamp' => '2026-08-25T11:30:00',
        ]);

        $response
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Device [UNKNOWN] not found.',
            ]);

        $this->assertDatabaseCount('measurements', 0);
    }

    public function test_missing_api_key_returns_unauthorized(): void
    {
        $device = Device::create([
            'device_uid' => 'DEV-001',
            'name' => 'Main Meter',
            'api_key' => 'secret-device-key',
        ]);

        $response = $this->postJson('/api/v1/measurements', [
            'device_id' => 'DEV-001',
            'voltage' => 230.4,
            'current' => 2.8,
            'power' => 645.1,
            'timestamp' => '2026-08-25T11:30:00',
        ]);

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Device API key is required.',
            ]);

        $this->assertStringNotContainsString($device->api_key, $response->getContent());
        $this->assertDatabaseCount('measurements', 0);
    }

    public function test_invalid_api_key_returns_unauthorized(): void
    {
        $device = Device::create([
            'device_uid' => 'DEV-001',
            'name' => 'Main Meter',
            'api_key' => 'secret-device-key',
        ]);

        $response = $this->withHeader('X-Device-Key', 'invalid-key')->postJson('/api/v1/measurements', [
            'device_id' => 'DEV-001',
            'voltage' => 230.4,
            'current' => 2.8,
            'power' => 645.1,
            'timestamp' => '2026-08-25T11:30:00',
        ]);

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Invalid device API key.',
            ]);

        $this->assertStringNotContainsString($device->api_key, $response->getContent());
        $this->assertDatabaseCount('measurements', 0);
    }

    public function test_another_devices_api_key_returns_forbidden(): void
    {
        $requestedDevice = Device::create([
            'device_uid' => 'DEV-001',
            'name' => 'Main Meter',
            'api_key' => 'main-meter-key',
        ]);
        $otherDevice = Device::create([
            'device_uid' => 'DEV-002',
            'name' => 'Secondary Meter',
            'api_key' => 'secondary-meter-key',
        ]);

        $response = $this->withHeader('X-Device-Key', $otherDevice->api_key)->postJson('/api/v1/measurements', [
            'device_id' => $requestedDevice->device_uid,
            'voltage' => 230.4,
            'current' => 2.8,
            'power' => 645.1,
            'timestamp' => '2026-08-25T11:30:00',
        ]);

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Device API key is not authorized for this device.',
            ]);

        $this->assertStringNotContainsString($requestedDevice->api_key, $response->getContent());
        $this->assertStringNotContainsString($otherDevice->api_key, $response->getContent());
        $this->assertDatabaseCount('measurements', 0);
    }

    public function test_mqtt_device_is_rejected_by_the_http_ingestion_endpoint(): void
    {
        $httpDevice = Device::create([
            'device_uid' => 'DEV-001',
            'name' => 'HTTP Energy Meter',
            'transport' => DeviceTransport::Http,
            'api_key' => 'dev-device-key',
        ]);
        $mqttDevice = Device::create([
            'device_uid' => 'DEV-002',
            'name' => 'MQTT Energy Meter',
            'transport' => DeviceTransport::Mqtt,
            'api_key' => null,
        ]);

        $response = $this->withHeader('X-Device-Key', $httpDevice->api_key)->postJson('/api/v1/measurements', [
            'device_id' => $mqttDevice->device_uid,
            'voltage' => 230.4,
            'current' => 2.8,
            'power' => 645.1,
            'timestamp' => '2026-08-25T11:30:00Z',
        ]);

        $response
            ->assertConflict()
            ->assertExactJson([
                'message' => 'Device [DEV-002] is configured for MQTT ingestion.',
            ]);

        $this->assertDatabaseCount('measurements', 0);
        $this->assertSame('offline', $httpDevice->fresh()->status);
        $this->assertSame('offline', $mqttDevice->fresh()->status);
    }
}
