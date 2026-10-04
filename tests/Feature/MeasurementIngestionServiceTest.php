<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Services\MeasurementIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasurementIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_a_measurement_and_updates_its_device_atomically(): void
    {
        $device = Device::create([
            'device_uid' => 'DEV-001',
            'name' => 'Main Meter',
            'api_key' => 'secret-device-key',
            'status' => 'offline',
        ]);
        $receivedAt = CarbonImmutable::parse('2026-08-25T14:31:00Z');

        $measurement = app(MeasurementIngestionService::class)->ingest($device, [
            'voltage' => 230.4,
            'current' => 2.8,
            'power' => 645.1,
            'timestamp' => '2026-08-25T14:30:00+02:00',
        ], $receivedAt);

        $this->assertTrue($measurement->device->is($device));
        $this->assertSame($device->id, $measurement->device_id);
        $this->assertSame('2026-08-25 12:30:00', $measurement->measured_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('measurements', [
            'id' => $measurement->id,
            'device_id' => $device->id,
            'voltage' => 230.4,
            'current' => 2.8,
            'power' => 645.1,
            'measured_at' => '2026-08-25 12:30:00',
        ]);

        $device->refresh();

        $this->assertSame('online', $device->status);
        $this->assertSame('2026-08-25 14:31:00', $device->last_seen_at->format('Y-m-d H:i:s'));
    }

    public function test_measured_at_preserves_its_instant_with_a_non_utc_application_timezone(): void
    {
        config(['app.timezone' => 'Europe/Berlin']);

        $device = Device::create([
            'device_uid' => 'DEV-001',
            'name' => 'Main Meter',
            'api_key' => 'secret-device-key',
            'status' => 'offline',
        ]);

        $measurement = app(MeasurementIngestionService::class)->ingest($device, [
            'voltage' => 230.4,
            'current' => 2.8,
            'power' => 645.1,
            'timestamp' => '2026-08-25T14:30:00Z',
        ], CarbonImmutable::parse('2026-08-25T14:31:00Z'));

        $this->assertSame('2026-08-25 14:30:00', $measurement->getRawOriginal('measured_at'));
        $this->assertSame('2026-08-25T16:30:00+02:00', $measurement->measured_at->toIso8601String());
    }
}
