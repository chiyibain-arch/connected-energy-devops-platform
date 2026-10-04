<?php

namespace Database\Seeders;

use App\Enums\DeviceTransport;
use App\Models\Device;
use Illuminate\Database\Seeder;

class DemoDeviceSeeder extends Seeder
{
    /**
     * Seed the two local-development demo meters.
     */
    public function run(): void
    {
        $httpDevice = Device::query()->firstOrNew(['device_uid' => 'DEV-001']);
        $httpDevice->fill([
            'name' => 'HTTP Energy Meter',
            'transport' => DeviceTransport::Http,
            'api_key' => 'dev-device-key',
        ]);
        $httpDevice->status ??= 'offline';
        $httpDevice->save();

        $mqttDevice = Device::query()->firstOrNew(['device_uid' => 'DEV-002']);
        $mqttDevice->fill([
            'name' => 'MQTT Energy Meter',
            'transport' => DeviceTransport::Mqtt,
            'api_key' => null,
        ]);
        $mqttDevice->status ??= 'offline';
        $mqttDevice->save();
    }
}
