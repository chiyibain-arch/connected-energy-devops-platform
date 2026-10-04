#!/usr/bin/env php
<?php

declare(strict_types=1);

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

require dirname(__DIR__).'/vendor/autoload.php';

const DEFAULT_API_URL = 'http://localhost:8081/api/v1/measurements';
const DEFAULT_HTTP_DEVICE_ID = 'DEV-001';
const DEFAULT_MQTT_DEVICE_ID = 'DEV-002';
const DEFAULT_MQTT_HOST = 'localhost';
const DEFAULT_MQTT_PORT = '1883';
const DEFAULT_TRANSPORT = 'http';
const SEND_INTERVAL_SECONDS = 5;

$options = getopt('', [
    'transport:',
    'api-url:',
    'device-id:',
    'api-key:',
    'mqtt-host:',
    'mqtt-port:',
    'mqtt-client-id:',
    'mqtt-username:',
    'mqtt-password:',
    'count:',
    'help',
]);

if (isset($options['help'])) {
    printUsage();
    exit(0);
}

$transport = strtolower(configuredValue($options, 'transport', 'IOT_SIMULATOR_TRANSPORT', DEFAULT_TRANSPORT));
$apiUrl = configuredValue($options, 'api-url', 'IOT_SIMULATOR_API_URL', DEFAULT_API_URL);
$defaultDeviceId = $transport === 'mqtt' ? DEFAULT_MQTT_DEVICE_ID : DEFAULT_HTTP_DEVICE_ID;
$deviceId = configuredValue($options, 'device-id', 'IOT_SIMULATOR_DEVICE_ID', $defaultDeviceId);
$apiKey = configuredValue($options, 'api-key', 'IOT_SIMULATOR_API_KEY');
$mqttHost = configuredValue($options, 'mqtt-host', 'IOT_SIMULATOR_MQTT_HOST', DEFAULT_MQTT_HOST);
$mqttPortValue = configuredValue($options, 'mqtt-port', 'IOT_SIMULATOR_MQTT_PORT', DEFAULT_MQTT_PORT);
$mqttUsername = configuredValue($options, 'mqtt-username', 'IOT_SIMULATOR_MQTT_USERNAME');
$mqttPassword = configuredValue($options, 'mqtt-password', 'IOT_SIMULATOR_MQTT_PASSWORD');
$mqttClientId = configuredValue(
    $options,
    'mqtt-client-id',
    'IOT_SIMULATOR_MQTT_CLIENT_ID',
    sprintf('iot-simulator-%s-%d', preg_replace('/[^a-zA-Z0-9_-]/', '-', $deviceId), getmypid()),
);
$countValue = configuredValue($options, 'count', 'IOT_SIMULATOR_COUNT', '0');

if (! in_array($transport, ['http', 'mqtt'], true)) {
    fwrite(STDERR, "Transport must be either [http] or [mqtt].\n");
    exit(1);
}

if ($deviceId === '') {
    fwrite(STDERR, "The device ID cannot be empty.\n");
    exit(1);
}

if ($transport === 'http' && $apiKey === '') {
    fwrite(STDERR, "Missing device API key. Use --api-key or IOT_SIMULATOR_API_KEY.\n\n");
    printUsage(STDERR);
    exit(1);
}

if ($transport === 'http' && (filter_var($apiUrl, FILTER_VALIDATE_URL) === false
    || ! in_array(parse_url($apiUrl, PHP_URL_SCHEME), ['http', 'https'], true))) {
    fwrite(STDERR, "The configured API URL must be a valid HTTP or HTTPS URL.\n");
    exit(1);
}

if ($transport === 'mqtt' && ($mqttHost === '' || str_contains($deviceId, '/'))) {
    fwrite(STDERR, "MQTT host cannot be empty and the device ID cannot contain a slash.\n");
    exit(1);
}

$mqttPort = filter_var($mqttPortValue, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535],
]);

if ($transport === 'mqtt' && $mqttPort === false) {
    fwrite(STDERR, "The configured MQTT port must be between 1 and 65535.\n");
    exit(1);
}

$count = filter_var($countValue, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0],
]);

if ($count === false) {
    fwrite(STDERR, "The configured count must be zero or a positive integer.\n");
    exit(1);
}

$running = true;

if (function_exists('pcntl_async_signals') && defined('SIGINT')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, static function () use (&$running): void {
        $running = false;
        fwrite(STDOUT, "\nStopping simulator...\n");
    });
    pcntl_signal(SIGTERM, static function () use (&$running): void {
        $running = false;
        fwrite(STDOUT, "\nStopping simulator...\n");
    });
}

$mqttClient = null;
$mqttTopic = "devices/{$deviceId}/measurements";

if ($transport === 'mqtt') {
    try {
        $mqttClient = connectMqtt(
            $mqttHost,
            (int) $mqttPort,
            $mqttClientId,
            optionalString($mqttUsername),
            optionalString($mqttPassword),
        );
    } catch (Throwable $exception) {
        fwrite(STDERR, 'MQTT connection failed: '.safeErrorMessage($exception, $mqttPassword)."\n");
        exit(1);
    }
}

fwrite(STDOUT, "IoT device simulator started. Press Ctrl+C to stop.\n");

if ($transport === 'http') {
    fwrite(STDOUT, "Transport: HTTP | API: {$apiUrl} | Device: {$deviceId} | Interval: ".SEND_INTERVAL_SECONDS." seconds\n\n");
} else {
    fwrite(STDOUT, "Transport: MQTT | Broker: {$mqttHost}:{$mqttPort} | Topic: {$mqttTopic} | Interval: ".SEND_INTERVAL_SECONDS." seconds\n\n");
}

$attempted = 0;
$failed = false;

try {
    while ($running && ($count === 0 || $attempted < $count)) {
        $measurement = generateMeasurement();

        if ($transport === 'http') {
            $payload = httpPayload($deviceId, $measurement);
            $result = sendHttpMeasurement($apiUrl, $apiKey, $payload);
            $status = $result['status'] === null ? 'N/A' : (string) $result['status'];
            $succeeded = $result['status'] !== null && $result['status'] >= 200 && $result['status'] < 300;
            $outcome = $succeeded ? 'SUCCESS' : 'FAILURE: '.httpFailureMessage($result);

            fwrite(STDOUT, sprintf(
                "[HTTP] %s | %.1f V | %.2f A | %.1f W | timestamp=%s | HTTP %s | %s\n",
                $deviceId,
                $measurement['voltage'],
                $measurement['current'],
                $measurement['power'],
                $payload['timestamp'],
                $status,
                $outcome,
            ));
        } else {
            $payload = mqttPayload($measurement);

            try {
                $mqttClient->publish(
                    $mqttTopic,
                    json_encode($payload, JSON_THROW_ON_ERROR),
                    MqttClient::QOS_AT_MOST_ONCE,
                );

                fwrite(STDOUT, sprintf(
                    "[MQTT] %s | topic=%s | %.1f V | %.2f A | %.1f W | timestamp=%s | PUBLISHED\n",
                    $deviceId,
                    $mqttTopic,
                    $measurement['voltage'],
                    $measurement['current'],
                    $measurement['power'],
                    $payload['timestamp'],
                ));
            } catch (Throwable $exception) {
                fwrite(STDERR, sprintf(
                    "[MQTT] %s | topic=%s | FAILURE: %s\n",
                    $deviceId,
                    $mqttTopic,
                    safeErrorMessage($exception, $mqttPassword),
                ));
                $running = false;
                $failed = true;
            }
        }

        $attempted++;

        if ($running && ($count === 0 || $attempted < $count)) {
            sleep(SEND_INTERVAL_SECONDS);
        }
    }
} finally {
    if ($mqttClient?->isConnected()) {
        try {
            $mqttClient->disconnect();
        } catch (Throwable $exception) {
            fwrite(STDERR, 'MQTT disconnect warning: '.safeErrorMessage($exception, $mqttPassword)."\n");
        }
    }
}

fwrite(STDOUT, "Simulator stopped after {$attempted} measurement attempt(s).\n");

exit($failed ? 1 : 0);

/**
 * @param  array<string, string|false>  $options
 */
function configuredValue(array $options, string $option, string $environment, string $default = ''): string
{
    $optionValue = $options[$option] ?? false;

    if (is_string($optionValue)) {
        return $optionValue;
    }

    $environmentValue = getenv($environment);

    return is_string($environmentValue) ? $environmentValue : $default;
}

/**
 * @return array{voltage: float, current: float, power: float, recorded_at: DateTimeImmutable}
 */
function generateMeasurement(): array
{
    $voltage = random_int(2200, 2400) / 10;
    $current = random_int(50, 500) / 100;

    return [
        'voltage' => $voltage,
        'current' => $current,
        'power' => round($voltage * $current, 1),
        'recorded_at' => new DateTimeImmutable,
    ];
}

/**
 * @param  array{voltage: float, current: float, power: float, recorded_at: DateTimeImmutable}  $measurement
 * @return array{device_id: string, voltage: float, current: float, power: float, timestamp: string}
 */
function httpPayload(string $deviceId, array $measurement): array
{
    return [
        'device_id' => $deviceId,
        'voltage' => $measurement['voltage'],
        'current' => $measurement['current'],
        'power' => $measurement['power'],
        'timestamp' => $measurement['recorded_at']->format(DateTimeInterface::ATOM),
    ];
}

/**
 * @param  array{voltage: float, current: float, power: float, recorded_at: DateTimeImmutable}  $measurement
 * @return array{voltage: float, current: float, power: float, timestamp: string}
 */
function mqttPayload(array $measurement): array
{
    return [
        'voltage' => $measurement['voltage'],
        'current' => $measurement['current'],
        'power' => $measurement['power'],
        'timestamp' => $measurement['recorded_at']
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z'),
    ];
}

function connectMqtt(
    string $host,
    int $port,
    string $clientId,
    ?string $username,
    ?string $password,
): MqttClient {
    $client = new MqttClient($host, $port, $clientId, MqttClient::MQTT_3_1_1);
    $settings = (new ConnectionSettings)
        ->setUsername($username)
        ->setPassword($password)
        ->setConnectTimeout(10)
        ->setSocketTimeout(5)
        ->setKeepAliveInterval(10);

    $client->connect($settings, true);

    return $client;
}

/**
 * @param  array{device_id: string, voltage: float, current: float, power: float, timestamp: string}  $payload
 * @return array{status: int|null, body: string, error: string|null}
 */
function sendHttpMeasurement(string $apiUrl, string $apiKey, array $payload): array
{
    $requestError = null;
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Accept: application/json',
                'Content-Type: application/json',
                "X-Device-Key: {$apiKey}",
            ]),
            'content' => json_encode($payload, JSON_THROW_ON_ERROR),
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ]);

    set_error_handler(static function (int $severity, string $message) use (&$requestError): bool {
        $requestError = $message;

        return true;
    });

    try {
        $body = file_get_contents($apiUrl, false, $context);
    } finally {
        restore_error_handler();
    }

    $status = null;

    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches) === 1) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'error' => $requestError,
    ];
}

/**
 * @param  array{status: int|null, body: string, error: string|null}  $result
 */
function httpFailureMessage(array $result): string
{
    if ($result['body'] !== '') {
        $decoded = json_decode($result['body'], true);

        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            return $decoded['message'];
        }
    }

    return $result['error'] ?? 'The API request failed without an error message.';
}

function optionalString(string $value): ?string
{
    return $value !== '' ? $value : null;
}

function safeErrorMessage(Throwable $exception, string ...$secrets): string
{
    $message = trim($exception->getMessage());
    $message = $message !== '' ? $message : $exception::class;

    foreach ($secrets as $secret) {
        if ($secret !== '') {
            $message = str_replace($secret, '[redacted]', $message);
        }
    }

    return $message;
}

/**
 * @param  resource  $stream
 */
function printUsage($stream = STDOUT): void
{
    fwrite($stream, <<<'TEXT'
Usage:
  php tools/device-simulator.php [options]

Options:
  --transport=MODE          Transport: http or mqtt (default: http)
  --api-url=URL             HTTP API endpoint
  --device-id=ID            Device UID (default: DEV-001 for HTTP, DEV-002 for MQTT)
  --api-key=KEY             HTTP device API key (required only for HTTP)
  --mqtt-host=HOST          MQTT broker host (default: localhost)
  --mqtt-port=PORT          MQTT broker port (default: 1883)
  --mqtt-client-id=ID       MQTT client ID (default: generated per process)
  --mqtt-username=USERNAME  Optional MQTT username
  --mqtt-password=PASSWORD  Optional MQTT password
  --count=NUMBER            Stop after NUMBER attempts; 0 means indefinitely
  --help                    Show this help

Environment variables:
  IOT_SIMULATOR_TRANSPORT
  IOT_SIMULATOR_API_URL
  IOT_SIMULATOR_DEVICE_ID
  IOT_SIMULATOR_API_KEY
  IOT_SIMULATOR_MQTT_HOST
  IOT_SIMULATOR_MQTT_PORT
  IOT_SIMULATOR_MQTT_CLIENT_ID
  IOT_SIMULATOR_MQTT_USERNAME
  IOT_SIMULATOR_MQTT_PASSWORD
  IOT_SIMULATOR_COUNT

TEXT);
}
