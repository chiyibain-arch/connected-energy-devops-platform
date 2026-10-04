<?php

namespace App\Services;

use App\Models\Device;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class EnergyAnalyticsService
{
    private readonly int $maxIntegrationGapSeconds;

    public function __construct(?int $maxIntegrationGapSeconds = null)
    {
        $this->maxIntegrationGapSeconds = max(
            1,
            $maxIntegrationGapSeconds ?? (int) config('energy.max_integration_gap_seconds', 300),
        );
    }

    /**
     * Build the complete energy summary for a device.
     *
     * @return array<string, mixed>
     */
    public function summarize(Device $device, ?CarbonImmutable $now = null): array
    {
        $timezone = $this->timezone();
        $now = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone);

        $measurements = $device->measurements()
            ->where('measured_at', '<=', $now->utc())
            ->orderBy('measured_at')
            ->get(['power', 'measured_at']);

        return $this->summarizeMeasurements($measurements, $now);
    }

    /**
     * Build an energy summary from an ordered or unordered measurement series.
     *
     * @param  iterable<int, object>  $measurements
     * @return array<string, mixed>
     */
    public function summarizeMeasurements(iterable $measurements, ?CarbonImmutable $now = null): array
    {
        $timezone = $this->timezone();
        $now = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $dailyBuckets = $this->dailyBuckets($measurements, $timezone, $now);
        $monthlyBuckets = $this->monthlyBuckets($dailyBuckets);

        $todayKey = $now->format('Y-m-d');
        $currentMonthKey = $now->format('Y-m');
        $today = $dailyBuckets[$todayKey] ?? null;
        $currentMonth = $monthlyBuckets[$currentMonthKey] ?? null;

        return [
            'today_kwh' => $this->kilowattHours($today['energy_wh'] ?? 0.0),
            'daily_average_kwh' => $this->averageKilowattHours($dailyBuckets),
            'current_month_kwh' => $this->kilowattHours($currentMonth['energy_wh'] ?? 0.0),
            'monthly_average_kwh' => $this->averageKilowattHours($monthlyBuckets),
            'has_data' => [
                'today' => $today !== null,
                'daily_average' => $dailyBuckets !== [],
                'current_month' => $currentMonth !== null,
                'monthly_average' => $monthlyBuckets !== [],
            ],
            'last_30_days' => $this->lastDays($dailyBuckets, $now, 30),
            'last_12_months' => $this->lastMonths($monthlyBuckets, $now, 12),
            'meta' => [
                'timezone' => $timezone,
                'max_integration_gap_seconds' => $this->maxIntegrationGapSeconds,
            ],
        ];
    }

    /**
     * Calculate total energy for a measurement series without extrapolation.
     *
     * @param  iterable<int, object>  $measurements
     */
    public function calculateEnergyKwh(iterable $measurements): float
    {
        $dailyBuckets = $this->dailyBuckets($measurements, $this->timezone());

        return $this->kilowattHours(array_sum(array_column($dailyBuckets, 'energy_wh')));
    }

    /**
     * @param  iterable<int, object>  $measurements
     * @return array<string, array{energy_wh: float}>
     */
    private function dailyBuckets(
        iterable $measurements,
        string $timezone,
        ?CarbonImmutable $through = null,
    ): array {
        $points = $this->normalizeMeasurements($measurements, $timezone, $through);
        $buckets = [];

        for ($index = 1, $count = count($points); $index < $count; $index++) {
            $previous = $points[$index - 1];
            $current = $points[$index];
            $elapsedSeconds = $current['time']->getTimestamp() - $previous['time']->getTimestamp();

            if ($elapsedSeconds <= 0 || $elapsedSeconds > $this->maxIntegrationGapSeconds) {
                continue;
            }

            $cursor = $previous['time'];

            while ($cursor->lessThan($current['time'])) {
                $nextDay = $cursor->startOfDay()->addDay();
                $segmentEnd = $nextDay->lessThan($current['time']) ? $nextDay : $current['time'];
                $energyWh = $this->integrateSegment($previous, $current, $cursor, $segmentEnd);
                $date = $cursor->format('Y-m-d');

                $buckets[$date]['energy_wh'] = ($buckets[$date]['energy_wh'] ?? 0.0) + $energyWh;
                $cursor = $segmentEnd;
            }
        }

        ksort($buckets);

        return $buckets;
    }

    /**
     * @param  iterable<int, object>  $measurements
     * @return list<array{power: float, time: CarbonImmutable}>
     */
    private function normalizeMeasurements(
        iterable $measurements,
        string $timezone,
        ?CarbonImmutable $through = null,
    ): array {
        $points = [];

        foreach ($measurements as $measurement) {
            $power = (float) $measurement->power;

            if (! is_finite($power) || $power < 0) {
                continue;
            }

            $measuredAt = method_exists($measurement, 'getRawOriginal')
                ? $measurement->getRawOriginal('measured_at')
                : $measurement->measured_at;

            if ($measuredAt instanceof DateTimeInterface) {
                $time = CarbonImmutable::instance($measuredAt)->setTimezone($timezone);
            } else {
                $time = CarbonImmutable::parse((string) $measuredAt, 'UTC')->setTimezone($timezone);
            }

            if ($through !== null && $time->greaterThan($through)) {
                continue;
            }

            $points[] = ['power' => $power, 'time' => $time];
        }

        usort(
            $points,
            fn (array $left, array $right): int => $left['time']->getTimestamp() <=> $right['time']->getTimestamp(),
        );

        return $points;
    }

    /**
     * @param  array{power: float, time: CarbonImmutable}  $previous
     * @param  array{power: float, time: CarbonImmutable}  $current
     */
    private function integrateSegment(
        array $previous,
        array $current,
        CarbonImmutable $segmentStart,
        CarbonImmutable $segmentEnd,
    ): float {
        $startTimestamp = $previous['time']->getTimestamp();
        $totalSeconds = $current['time']->getTimestamp() - $startTimestamp;
        $powerDelta = $current['power'] - $previous['power'];
        $startRatio = ($segmentStart->getTimestamp() - $startTimestamp) / $totalSeconds;
        $endRatio = ($segmentEnd->getTimestamp() - $startTimestamp) / $totalSeconds;
        $startPower = $previous['power'] + ($powerDelta * $startRatio);
        $endPower = $previous['power'] + ($powerDelta * $endRatio);
        $segmentHours = ($segmentEnd->getTimestamp() - $segmentStart->getTimestamp()) / 3600;

        return (($startPower + $endPower) / 2) * $segmentHours;
    }

    /**
     * @param  array<string, array{energy_wh: float}>  $dailyBuckets
     * @return array<string, array{energy_wh: float}>
     */
    private function monthlyBuckets(array $dailyBuckets): array
    {
        $months = [];

        foreach ($dailyBuckets as $date => $bucket) {
            $month = substr($date, 0, 7);
            $months[$month]['energy_wh'] = ($months[$month]['energy_wh'] ?? 0.0) + $bucket['energy_wh'];
        }

        ksort($months);

        return $months;
    }

    /**
     * @param  array<string, array{energy_wh: float}>  $buckets
     * @return list<array{date: string, energy_kwh: float, has_data: bool}>
     */
    private function lastDays(array $buckets, CarbonImmutable $now, int $count): array
    {
        $days = [];
        $firstDay = $now->startOfDay()->subDays($count - 1);

        for ($offset = 0; $offset < $count; $offset++) {
            $date = $firstDay->addDays($offset)->format('Y-m-d');
            $bucket = $buckets[$date] ?? null;
            $days[] = [
                'date' => $date,
                'energy_kwh' => $this->kilowattHours($bucket['energy_wh'] ?? 0.0),
                'has_data' => $bucket !== null,
            ];
        }

        return $days;
    }

    /**
     * @param  array<string, array{energy_wh: float}>  $buckets
     * @return list<array{month: string, energy_kwh: float, has_data: bool}>
     */
    private function lastMonths(array $buckets, CarbonImmutable $now, int $count): array
    {
        $months = [];
        $firstMonth = $now->startOfMonth()->subMonths($count - 1);

        for ($offset = 0; $offset < $count; $offset++) {
            $month = $firstMonth->addMonths($offset)->format('Y-m');
            $bucket = $buckets[$month] ?? null;
            $months[] = [
                'month' => $month,
                'energy_kwh' => $this->kilowattHours($bucket['energy_wh'] ?? 0.0),
                'has_data' => $bucket !== null,
            ];
        }

        return $months;
    }

    /**
     * @param  array<string, array{energy_wh: float}>  $buckets
     */
    private function averageKilowattHours(array $buckets): float
    {
        if ($buckets === []) {
            return 0.0;
        }

        return $this->kilowattHours(
            array_sum(array_column($buckets, 'energy_wh')) / count($buckets),
        );
    }

    private function kilowattHours(float $energyWh): float
    {
        return round($energyWh / 1000, 6);
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }
}
