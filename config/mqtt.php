<?php

return [
    'host' => env('MQTT_HOST', 'mosquitto'),
    'port' => (int) env('MQTT_PORT', 1883),
    'client_id' => env('MQTT_CLIENT_ID', 'iot-energy-monitor-backend'),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),
    'topic' => 'devices/+/measurements',
];
