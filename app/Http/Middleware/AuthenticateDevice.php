<?php

namespace App\Http\Middleware;

use App\Enums\DeviceTransport;
use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    public const REQUEST_ATTRIBUTE = 'authenticated_device';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Device-Key');

        if (! is_string($apiKey) || $apiKey === '') {
            return response()->json([
                'message' => 'Device API key is required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $authenticatedDevice = Device::query()->where('api_key', $apiKey)->first();

        if ($authenticatedDevice === null || ! hash_equals($authenticatedDevice->api_key, $apiKey)) {
            return response()->json([
                'message' => 'Invalid device API key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $deviceUid = $request->input('device_id');

        if (! is_string($deviceUid) || $deviceUid === '') {
            $request->attributes->set(self::REQUEST_ATTRIBUTE, $authenticatedDevice);

            return $next($request);
        }

        $requestedDevice = Device::query()->where('device_uid', $deviceUid)->first();

        if ($requestedDevice === null) {
            return response()->json([
                'message' => "Device [{$deviceUid}] not found.",
            ], Response::HTTP_NOT_FOUND);
        }

        if ($requestedDevice->transport !== DeviceTransport::Http) {
            return response()->json([
                'message' => "Device [{$deviceUid}] is configured for MQTT ingestion.",
            ], Response::HTTP_CONFLICT);
        }

        if (! $authenticatedDevice->is($requestedDevice)) {
            return response()->json([
                'message' => 'Device API key is not authorized for this device.',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $requestedDevice);

        return $next($request);
    }
}
