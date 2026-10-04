<?php

namespace Tests\Feature;

use App\Enums\DeviceTransport;
use App\Models\Device;
use App\Models\Measurement;
use App\Services\Mqtt\MqttMeasurementHandler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MqttMeasurementHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_the_device_uid_from_a_measurement_topic(): void
    {
        $handler = app(MqttMeasurementHandler::class);

        $this->assertSame('DEV-001', $handler->deviceUidFromTopic('devices/DEV-001/measurements'));
        $this->assertSame('meter-42', $handler->deviceUidFromTopic('devices/meter-42/measurements'));
        $this->assertNull($handler->deviceUidFromTopic('devices/DEV-001'));
        $this->assertNull($handler->deviceUidFromTopic('devices/DEV-001/status'));
        $this->assertNull($handler->deviceUidFromTopic('invalid/DEV-001/measurements'));
        $this->assertNull($handler->deviceUidFromTopic('devices/DEV-001/extra/measurements'));
    }

    public function test_valid_mqtt_payload_is_stored_for_the_topic_device(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-25 14:31:00'));
        $device = $this->createDevice('DEV-002');

        $handled = app(MqttMeasurementHandler::class)->handle(
            'devices/DEV-002/measurements',
            $this->validPayload(),
        );

        $this->assertTrue($handled);
        $measurement = Measurement::query()->sole();
        $this->assertSame($device->id, $measurement->device_id);
        $this->assertTrue($measurement->device->is($device));
        $this->assertSame('2026-08-25 14:30:00', $measurement->measured_at->format('Y-m-d H:i:s'));

        $device->refresh();

        $this->assertSame('online', $device->status);
        $this->assertSame('2026-08-25 14:31:00', $device->last_seen_at->format('Y-m-d H:i:s'));
    }

    public function test_invalid_json_is_logged_and_skipped(): void
    {
        Log::spy();
        $this->createDevice('DEV-001');

        $handled = app(MqttMeasurementHandler::class)->handle(
            'devices/DEV-001/measurements',
            '{invalid-json',
        );

        $this->assertFalse($handled);
        $this->assertDatabaseCount('measurements', 0);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('MQTT measurement rejected: invalid JSON.', ['topic' => 'devices/DEV-001/measurements']);
    }

    public function test_invalid_measurement_values_are_logged_and_skipped(): void
    {
        Log::spy();
        $this->createDevice('DEV-001');

        $handled = app(MqttMeasurementHandler::class)->handle(
            'devices/DEV-001/measurements',
            json_encode([
                'voltage' => -230.4,
                'current' => 'not-numeric',
                'power' => -645.1,
                'timestamp' => '2026-08-25T14:30:00Z',
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertFalse($handled);
        $this->assertDatabaseCount('measurements', 0);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'MQTT measurement rejected: validation failed.'
                && array_keys($context['errors']) === ['voltage', 'current', 'power']);
    }

    public function test_invalid_timestamp_is_logged_and_skipped(): void
    {
        Log::spy();
        $this->createDevice('DEV-001');

        $handled = app(MqttMeasurementHandler::class)->handle(
            'devices/DEV-001/measurements',
            $this->validPayload(['timestamp' => 'not-a-date']),
        );

        $this->assertFalse($handled);
        $this->assertDatabaseCount('measurements', 0);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'MQTT measurement rejected: validation failed.'
                && array_keys($context['errors']) === ['timestamp']);
    }

    public function test_unknown_device_is_logged_and_skipped(): void
    {
        Log::spy();

        $handled = app(MqttMeasurementHandler::class)->handle(
            'devices/UNKNOWN/measurements',
            $this->validPayload(),
        );

        $this->assertFalse($handled);
        $this->assertDatabaseCount('measurements', 0);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('MQTT measurement rejected: unknown device.', [
                'topic' => 'devices/UNKNOWN/measurements',
                'device_uid' => 'UNKNOWN',
            ]);
    }

    public function test_topic_controls_measurement_device_ownership(): void
    {
        $firstDevice = $this->createDevice('DEV-001');
        $secondDevice = $this->createDevice('DEV-002');

        $handled = app(MqttMeasurementHandler::class)->handle(
            'devices/DEV-002/measurements',
            $this->validPayload(),
        );

        $this->assertTrue($handled);
        $measurement = Measurement::query()->sole();
        $this->assertSame($secondDevice->id, $measurement->device_id);
        $this->assertNotSame($firstDevice->id, $measurement->device_id);
    }

    public function test_http_device_is_rejected_and_the_next_mqtt_device_message_is_processed(): void
    {
        Log::spy();
        $httpDevice = $this->createDevice('DEV-001', DeviceTransport::Http);
        $mqttDevice = $this->createDevice('DEV-002');
        $handler = app(MqttMeasurementHandler::class);

        $this->assertFalse($handler->handle('devices/DEV-001/measurements', $this->validPayload()));
        $this->assertDatabaseCount('measurements', 0);
        $this->assertSame('offline', $httpDevice->fresh()->status);
        $this->assertNull($httpDevice->fresh()->last_seen_at);

        $this->assertTrue($handler->handle('devices/DEV-002/measurements', $this->validPayload()));
        $measurement = Measurement::query()->sole();

        $this->assertSame($mqttDevice->id, $measurement->device_id);
        $this->assertSame('online', $mqttDevice->fresh()->status);
        $this->assertNotNull($mqttDevice->fresh()->last_seen_at);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('MQTT measurement rejected: device transport is not MQTT.', [
                'topic' => 'devices/DEV-001/measurements',
                'device_uid' => 'DEV-001',
                'configured_transport' => 'http',
            ]);
    }

    private function createDevice(string $deviceUid, DeviceTransport $transport = DeviceTransport::Mqtt): Device
    {
        return Device::create([
            'device_uid' => $deviceUid,
            'name' => $deviceUid.' Meter',
            'transport' => $transport,
            'api_key' => $transport === DeviceTransport::Http ? $deviceUid.'-secret-key' : null,
            'status' => 'offline',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function validPayload(array $overrides = []): string
    {
        return json_encode(array_merge([
            'voltage' => 230.4,
            'current' => 2.8,
            'power' => 645.1,
            'timestamp' => '2026-08-25T14:30:00Z',
        ], $overrides), JSON_THROW_ON_ERROR);
    }
}
