<?php

namespace Tests\Feature;

use App\Enums\DeviceTransport;
use App\Models\Device;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnergySummaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_calculates_today_month_averages_and_calendar_series(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-25 12:00:00'));
        $device = $this->createDevice('DEV-001');

        $this->createPair($device, '2026-08-25 10:00:00', 1000, 1000);
        $this->createPair($device, '2026-08-24 09:00:00', 500, 1000);
        $this->createPair($device, '2026-07-15 08:00:00', 1000, 1000);

        $response = $this->getJson('/api/v1/devices/DEV-001/energy-summary')
            ->assertOk()
            ->assertJsonPath('data.today_kwh', 0.083333)
            ->assertJsonPath('data.daily_average_kwh', 0.076389)
            ->assertJsonPath('data.current_month_kwh', 0.145833)
            ->assertJsonPath('data.monthly_average_kwh', 0.114583)
            ->assertJsonPath('data.has_data.today', true)
            ->assertJsonPath('data.has_data.daily_average', true)
            ->assertJsonPath('data.meta.timezone', 'UTC')
            ->assertJsonPath('data.meta.max_integration_gap_seconds', 300)
            ->assertJsonCount(30, 'data.last_30_days')
            ->assertJsonCount(12, 'data.last_12_months');

        $days = collect($response->json('data.last_30_days'))->keyBy('date');
        $months = collect($response->json('data.last_12_months'))->keyBy('month');

        $this->assertSame(0.0625, $days['2026-08-24']['energy_kwh']);
        $this->assertSame(0.083333, $days['2026-08-25']['energy_kwh']);
        $this->assertSame(0.083333, $months['2026-07']['energy_kwh']);
        $this->assertSame(0.145833, $months['2026-08']['energy_kwh']);
    }

    public function test_summary_is_isolated_to_the_requested_device(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-25 12:00:00'));
        $requested = $this->createDevice('DEV-001');
        $other = $this->createDevice('DEV-002', DeviceTransport::Mqtt);
        $this->createPair($requested, '2026-08-25 10:00:00', 1000, 1000);
        $this->createPair($other, '2026-08-25 10:00:00', 5000, 5000);

        $this->getJson('/api/v1/devices/DEV-001/energy-summary')
            ->assertOk()
            ->assertJsonPath('data.today_kwh', 0.083333);
    }

    public function test_unknown_device_returns_not_found(): void
    {
        $this->getJson('/api/v1/devices/UNKNOWN/energy-summary')->assertNotFound();
    }

    public function test_device_without_measurements_returns_zero_with_no_data_markers(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-25 12:00:00'));
        $this->createDevice('DEV-001');

        $this->getJson('/api/v1/devices/DEV-001/energy-summary')
            ->assertOk()
            ->assertJsonPath('data.today_kwh', 0)
            ->assertJsonPath('data.daily_average_kwh', 0)
            ->assertJsonPath('data.current_month_kwh', 0)
            ->assertJsonPath('data.monthly_average_kwh', 0)
            ->assertJsonPath('data.has_data.today', false)
            ->assertJsonPath('data.has_data.daily_average', false)
            ->assertJsonCount(30, 'data.last_30_days')
            ->assertJsonCount(12, 'data.last_12_months');
    }

    public function test_single_measurement_does_not_extrapolate_energy(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-25 12:00:00'));
        $device = $this->createDevice('DEV-001');
        $this->createMeasurement($device, '2026-08-25 10:00:00', 1000);

        $this->getJson('/api/v1/devices/DEV-001/energy-summary')
            ->assertOk()
            ->assertJsonPath('data.today_kwh', 0)
            ->assertJsonPath('data.current_month_kwh', 0)
            ->assertJsonPath('data.has_data.today', false);
    }

    public function test_summary_uses_application_timezone_for_calendar_grouping(): void
    {
        config(['app.timezone' => 'Europe/Berlin']);
        date_default_timezone_set('Europe/Berlin');
        $this->travelTo(CarbonImmutable::parse('2026-08-25 12:00:00', 'Europe/Berlin'));
        $device = $this->createDevice('DEV-001');

        $this->createMeasurement($device, '2026-08-24 21:58:00', 1000);
        $this->createMeasurement($device, '2026-08-24 22:02:00', 1000);

        $response = $this->getJson('/api/v1/devices/DEV-001/energy-summary')->assertOk();
        $days = collect($response->json('data.last_30_days'))->keyBy('date');

        $this->assertSame(0.033333, $days['2026-08-24']['energy_kwh']);
        $this->assertSame(0.033333, $days['2026-08-25']['energy_kwh']);
        $this->assertSame('Europe/Berlin', $response->json('data.meta.timezone'));
    }

    public function test_api_key_is_never_exposed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-25 12:00:00'));
        $device = $this->createDevice('DEV-001');
        $this->createPair($device, '2026-08-25 10:00:00', 1000, 1000);

        $response = $this->getJson('/api/v1/devices/DEV-001/energy-summary')->assertOk();

        $this->assertStringNotContainsString('api_key', $response->getContent());
        $this->assertStringNotContainsString($device->api_key, $response->getContent());
    }

    private function createDevice(string $uid, DeviceTransport $transport = DeviceTransport::Http): Device
    {
        return Device::create([
            'device_uid' => $uid,
            'name' => $uid.' Meter',
            'transport' => $transport,
            'api_key' => $transport === DeviceTransport::Http ? $uid.'-secret-key' : null,
        ]);
    }

    private function createPair(
        Device $device,
        string $start,
        float $startPower,
        float $endPower,
    ): void {
        $startAt = CarbonImmutable::parse($start, 'UTC');
        $this->createMeasurement($device, $startAt->format('Y-m-d H:i:s'), $startPower);
        $this->createMeasurement($device, $startAt->addMinutes(5)->format('Y-m-d H:i:s'), $endPower);
    }

    private function createMeasurement(Device $device, string $measuredAt, float $power): void
    {
        $device->measurements()->create([
            'voltage' => 230,
            'current' => $power / 230,
            'power' => $power,
            'measured_at' => $measuredAt,
        ]);
    }
}
