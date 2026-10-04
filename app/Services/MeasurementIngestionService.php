<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Measurement;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class MeasurementIngestionService
{
    /**
     * Store a validated measurement and update its device atomically.
     *
     * @param  array{voltage: numeric-string|int|float, current: numeric-string|int|float, power: numeric-string|int|float, timestamp: string}  $data
     */
    public function ingest(
        Device $device,
        array $data,
        ?CarbonInterface $receivedAt = null,
    ): Measurement {
        $receivedAt ??= now();

        $measurement = DB::transaction(function () use ($data, $device, $receivedAt): Measurement {
            $measurement = $device->measurements()->create([
                'voltage' => $data['voltage'],
                'current' => $data['current'],
                'power' => $data['power'],
                'measured_at' => CarbonImmutable::parse($data['timestamp'], 'UTC')->utc(),
            ]);

            $device->update([
                'status' => 'online',
                'last_seen_at' => $receivedAt,
            ]);

            return $measurement;
        });

        $measurement->setRelation('device', $device);

        return $measurement;
    }
}
