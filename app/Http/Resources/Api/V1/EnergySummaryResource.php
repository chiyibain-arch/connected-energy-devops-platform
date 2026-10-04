<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnergySummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'today_kwh' => $this->resource['today_kwh'],
            'daily_average_kwh' => $this->resource['daily_average_kwh'],
            'current_month_kwh' => $this->resource['current_month_kwh'],
            'monthly_average_kwh' => $this->resource['monthly_average_kwh'],
            'has_data' => $this->resource['has_data'],
            'last_30_days' => $this->resource['last_30_days'],
            'last_12_months' => $this->resource['last_12_months'],
            'meta' => $this->resource['meta'],
        ];
    }
}
