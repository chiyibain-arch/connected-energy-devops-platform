<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EnergySummaryResource;
use App\Models\Device;
use App\Services\EnergyAnalyticsService;

class DeviceEnergyController extends Controller
{
    public function show(Device $device, EnergyAnalyticsService $analytics): EnergySummaryResource
    {
        return new EnergySummaryResource($analytics->summarize($device));
    }
}
