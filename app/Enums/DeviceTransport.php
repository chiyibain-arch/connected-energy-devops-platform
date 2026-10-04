<?php

namespace App\Enums;

enum DeviceTransport: string
{
    case Http = 'http';
    case Mqtt = 'mqtt';
}
