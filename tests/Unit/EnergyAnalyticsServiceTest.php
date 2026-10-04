<?php

namespace Tests\Unit;

use App\Services\EnergyAnalyticsService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

class EnergyAnalyticsServiceTest extends TestCase
{
    #[Test]
    public function it_integrates_constant_power_over_time(): void
    {
        $analytics = new EnergyAnalyticsService(3600);

        $energy = $analytics->calculateEnergyKwh([
            $this->measurement(1000, '2026-08-25 10:00:00'),
            $this->measurement(1000, '2026-08-25 11:00:00'),
        ]);

        $this->assertSame(1.0, $energy);
    }

    #[Test]
    public function it_uses_trapezoidal_integration_when_power_changes(): void
    {
        $analytics = new EnergyAnalyticsService(3600);

        $energy = $analytics->calculateEnergyKwh([
            $this->measurement(500, '2026-08-25 10:00:00'),
            $this->measurement(1000, '2026-08-25 11:00:00'),
        ]);

        $this->assertSame(0.75, $energy);
    }

    #[Test]
    public function it_does_not_integrate_across_gaps_larger_than_the_threshold(): void
    {
        $analytics = new EnergyAnalyticsService(300);

        $energy = $analytics->calculateEnergyKwh([
            $this->measurement(1000, '2026-08-25 10:00:00'),
            $this->measurement(1000, '2026-08-25 10:05:00'),
            $this->measurement(1000, '2026-08-25 10:11:00'),
        ]);

        $this->assertSame(0.083333, $energy);
    }

    #[Test]
    public function it_splits_a_trapezoid_at_local_calendar_boundaries(): void
    {
        $this->setAppTimezone('Europe/Berlin');
        $analytics = new EnergyAnalyticsService(300);

        $summary = $analytics->summarizeMeasurements([
            $this->measurement(1000, '2026-08-24T21:58:00Z'),
            $this->measurement(1000, '2026-08-24T22:02:00Z'),
        ], CarbonImmutable::parse('2026-08-25T12:00:00+02:00'));

        $previousDay = collect($summary['last_30_days'])->firstWhere('date', '2026-08-24');
        $today = collect($summary['last_30_days'])->firstWhere('date', '2026-08-25');

        $this->assertSame(0.033333, $previousDay['energy_kwh']);
        $this->assertSame(0.033333, $today['energy_kwh']);
    }

    private function measurement(float $power, string $measuredAt): stdClass
    {
        return (object) [
            'power' => $power,
            'measured_at' => CarbonImmutable::parse($measuredAt),
        ];
    }

    private function setAppTimezone(string $timezone): void
    {
        date_default_timezone_set($timezone);

        config(['app.timezone' => $timezone]);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set('UTC');
        config(['app.timezone' => 'UTC']);

        parent::tearDown();
    }
}
