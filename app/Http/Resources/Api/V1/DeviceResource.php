<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'device_uid' => $this->device_uid,
            'name' => $this->name,
            'transport' => $this->transport->value,
            'status' => $this->status,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
