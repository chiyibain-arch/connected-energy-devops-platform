<?php

namespace App\Models;

use App\Enums\DeviceTransport;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['device_uid', 'name', 'transport', 'api_key', 'status', 'last_seen_at'])]
#[Hidden(['api_key'])]
class Device extends Model
{
    public function getRouteKeyName(): string
    {
        return 'device_uid';
    }

    /**
     * Get the measurements recorded by the device.
     *
     * @return HasMany<Measurement, $this>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transport' => DeviceTransport::class,
            'last_seen_at' => 'datetime',
        ];
    }
}
