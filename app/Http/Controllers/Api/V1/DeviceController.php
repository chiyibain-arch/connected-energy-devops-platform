<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DeviceResource;
use App\Models\Device;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return DeviceResource::collection(
            Device::query()->orderBy('name')->get(),
        );
    }
}
