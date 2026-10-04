<?php

namespace App\Services\Mqtt;

use App\Enums\DeviceTransport;
use App\Models\Device;
use App\Services\MeasurementIngestionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use JsonException;
use Throwable;

class MqttMeasurementHandler
{
    public function __construct(
        private readonly MeasurementIngestionService $ingestion,
    ) {}

    public function handle(string $topic, string $payload): bool
    {
        $deviceUid = $this->deviceUidFromTopic($topic);

        if ($deviceUid === null) {
            Log::warning('MQTT measurement rejected: invalid topic.', [
                'topic' => $topic,
            ]);

            return false;
        }

        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            Log::warning('MQTT measurement rejected: invalid JSON.', [
                'topic' => $topic,
            ]);

            return false;
        }

        if (! is_array($data)) {
            Log::warning('MQTT measurement rejected: payload must be a JSON object.', [
                'topic' => $topic,
            ]);

            return false;
        }

        $validator = Validator::make($data, [
            'voltage' => ['required', 'numeric', 'min:0'],
            'current' => ['required', 'numeric', 'min:0'],
            'power' => ['required', 'numeric', 'min:0'],
            'timestamp' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            Log::warning('MQTT measurement rejected: validation failed.', [
                'topic' => $topic,
                'errors' => $validator->errors()->toArray(),
            ]);

            return false;
        }

        $device = Device::query()->where('device_uid', $deviceUid)->first();

        if ($device === null) {
            Log::warning('MQTT measurement rejected: unknown device.', [
                'topic' => $topic,
                'device_uid' => $deviceUid,
            ]);

            return false;
        }

        if ($device->transport !== DeviceTransport::Mqtt) {
            Log::warning('MQTT measurement rejected: device transport is not MQTT.', [
                'topic' => $topic,
                'device_uid' => $deviceUid,
                'configured_transport' => $device->transport->value,
            ]);

            return false;
        }

        try {
            $this->ingestion->ingest($device, $validator->validated());
        } catch (Throwable $exception) {
            Log::error('MQTT measurement could not be stored.', [
                'topic' => $topic,
                'device_uid' => $deviceUid,
                'exception' => $exception,
            ]);

            return false;
        }

        return true;
    }

    public function deviceUidFromTopic(string $topic): ?string
    {
        if (preg_match('/\Adevices\/([^\/]+)\/measurements\z/', $topic, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
