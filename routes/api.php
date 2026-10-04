<?php

use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\DeviceEnergyController;
use App\Http\Controllers\Api\V1\DeviceMeasurementController;
use App\Http\Controllers\Api\V1\MeasurementController;
use Illuminate\Support\Facades\Route;

Route::post('v1/measurements', [MeasurementController::class, 'store'])
    ->middleware('iot.device')
    ->name('api.v1.measurements.store');

Route::get('v1/devices', [DeviceController::class, 'index'])
    ->name('api.v1.devices.index');
Route::get('v1/devices/{device}/measurements', [DeviceMeasurementController::class, 'index'])
    ->name('api.v1.devices.measurements.index');
Route::get('v1/devices/{device}/latest', [DeviceMeasurementController::class, 'latest'])
    ->name('api.v1.devices.measurements.latest');
Route::get('v1/devices/{device}/energy-summary', [DeviceEnergyController::class, 'show'])
    ->name('api.v1.devices.energy-summary.show');
