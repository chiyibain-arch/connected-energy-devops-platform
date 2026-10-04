<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListDeviceMeasurementsRequest;
use App\Http\Resources\Api\V1\DeviceMeasurementResource;
use App\Http\Resources\Api\V1\LatestDeviceMeasurementResource;
use App\Models\Device;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceMeasurementController extends Controller
{
    public function index(ListDeviceMeasurementsRequest $request, Device $device): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = $device->measurements()->orderByDesc('measured_at');

        if (isset($filters['from'])) {
            $query->where('measured_at', '>=', CarbonImmutable::parse($filters['from'])->utc());
        }

        if (isset($filters['to'])) {
            $query->where('measured_at', '<=', CarbonImmutable::parse($filters['to'])->utc());
        }

        return DeviceMeasurementResource::collection(
            $query->paginate(50)->withQueryString(),
        );
    }

    public function latest(Device $device): LatestDeviceMeasurementResource|JsonResponse
    {
        $measurement = $device->measurements()->latest('measured_at')->first();

        if ($measurement === null) {
            return response()->json([
                'message' => "No measurements found for device [{$device->device_uid}].",
            ]);
        }

        $measurement->setRelation('device', $device);

        return new LatestDeviceMeasurementResource($measurement);
    }
}
