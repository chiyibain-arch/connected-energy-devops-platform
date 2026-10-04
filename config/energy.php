<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maximum Integration Gap
    |--------------------------------------------------------------------------
    |
    | Measurements farther apart than this are not integrated. This prevents
    | missing telemetry from being treated as continuous energy consumption.
    |
    */

    'max_integration_gap_seconds' => (int) env('ENERGY_MAX_INTEGRATION_GAP_SECONDS', 300),
];
