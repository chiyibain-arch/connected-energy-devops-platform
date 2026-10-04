<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['device_id', 'voltage', 'current', 'power', 'measured_at'])]
class Measurement extends Model
{
    /**
     * Get the device that produced the measurement.
     *
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Keep the database value in UTC while exposing it in the application timezone.
     *
     * @return Attribute<CarbonImmutable, DateTimeInterface|string>
     */
    protected function measuredAt(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): CarbonImmutable => CarbonImmutable::parse($value, 'UTC')
                ->setTimezone((string) config('app.timezone', 'UTC')),
            set: fn (DateTimeInterface|string $value): string => ($value instanceof DateTimeInterface
                ? CarbonImmutable::instance($value)
                : CarbonImmutable::parse($value, 'UTC'))
                ->utc()
                ->format($this->getDateFormat()),
        )->withoutObjectCaching();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'voltage' => 'decimal:3',
            'current' => 'decimal:3',
            'power' => 'decimal:3',
        ];
    }
}
