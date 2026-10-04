<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMeasurementRequest;
use App\Http\Resources\Api\V1\MeasurementResource;
use App\Services\MeasurementIngestionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MeasurementController extends Controller
{
    public function store(
        StoreMeasurementRequest $request,
        MeasurementIngestionService $ingestion,
    ): JsonResponse {
        $data = $request->validated();
        $device = $request->authenticatedDevice();
        $measurement = $ingestion->ingest($device, $data);

        return (new MeasurementResource($measurement))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
