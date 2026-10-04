<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LatestDeviceMeasurementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'device_uid' => $this->device->device_uid,
            'voltage' => (float) $this->voltage,
            'current' => (float) $this->current,
            'power' => (float) $this->power,
            'measured_at' => $this->measured_at->toIso8601String(),
            'status' => $this->device->status,
            'last_seen_at' => $this->device->last_seen_at?->toIso8601String(),
        ];
    }
}
