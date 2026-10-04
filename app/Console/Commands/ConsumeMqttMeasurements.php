<?php

namespace App\Console\Commands;

use App\Services\Mqtt\MqttMeasurementHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Throwable;

class ConsumeMqttMeasurements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:consume';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume IoT measurements from MQTT';

    public function handle(MqttMeasurementHandler $handler): int
    {
        $host = (string) config('mqtt.host');
        $port = (int) config('mqtt.port');
        $clientId = (string) config('mqtt.client_id');
        $topic = (string) config('mqtt.topic');
        $client = new MqttClient(
            $host,
            $port,
            $clientId,
            MqttClient::MQTT_3_1_1,
        );
        $settings = (new ConnectionSettings)
            ->setUsername($this->optionalString(config('mqtt.username')))
            ->setPassword($this->optionalString(config('mqtt.password')))
            ->setConnectTimeout(10)
            ->setSocketTimeout(5)
            ->setKeepAliveInterval(10);

        $this->registerSignalHandlers($client);

        try {
            $client->connect($settings, true);
            $client->subscribe(
                $topic,
                static fn (string $messageTopic, string $message): bool => $handler->handle($messageTopic, $message),
                MqttClient::QOS_AT_MOST_ONCE,
            );

            $this->info("Listening for MQTT measurements on [{$topic}] via {$host}:{$port}.");
            $this->line('Press Ctrl+C to stop.');

            $client->loop(true);
            $client->disconnect();
        } catch (Throwable $exception) {
            Log::error('MQTT consumer stopped unexpectedly.', [
                'exception' => $exception,
            ]);

            $this->error('MQTT consumer stopped: '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function registerSignalHandlers(MqttClient $client): void
    {
        if (! function_exists('pcntl_async_signals') || ! defined('SIGINT')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, static function () use ($client): void {
            $client->interrupt();
        });
        pcntl_signal(SIGTERM, static function () use ($client): void {
            $client->interrupt();
        });
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
