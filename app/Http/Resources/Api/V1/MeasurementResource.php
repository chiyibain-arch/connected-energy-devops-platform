<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasurementResource extends JsonResource
{
    /**
     * The data wrapper that should be applied.
     *
     * @var string|null
     */
    public static $wrap;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device->device_uid,
            'voltage' => (float) $this->voltage,
            'current' => (float) $this->current,
            'power' => (float) $this->power,
            'measured_at' => $this->measured_at->toIso8601String(),
        ];
    }
}
