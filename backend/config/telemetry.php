<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Remote metrics endpoint (optional)
    |--------------------------------------------------------------------------
    |
    | When set, the advertiser dashboard will try to GET this URL with optional
    | bearer token. Expected JSON keys: impressions, driving_distance_km,
    | driving_time_hours, parking_time_hours (all optional). On failure, mock
    | data is used.
    |
    */
    'metrics_url' => env('TELEMETRY_METRICS_URL'),

    'metrics_token' => env('TELEMETRY_METRICS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | ClickHouse → PostgreSQL collector (docs/ARCHITECTURE/05_telemetry_pipeline.md)
    |--------------------------------------------------------------------------
    */
    'clickhouse' => [
        'enabled' => env('TELEMETRY_CLICKHOUSE_ENABLED', false),
        'base_url' => rtrim((string) env('TELEMETRY_CLICKHOUSE_URL', 'http://127.0.0.1:8123'), '/'),
        'database' => env('TELEMETRY_CLICKHOUSE_DATABASE', 'default'),
        'username' => env('TELEMETRY_CLICKHOUSE_USER', ''),
        'password' => env('TELEMETRY_CLICKHOUSE_PASSWORD', ''),
        'locations_table' => env('TELEMETRY_CLICKHOUSE_LOCATIONS_TABLE', 'location'),
        /**
         * location = imei + Int64 unix timestamp + gpsSpeed (production CH).
         * legacy = device_id + DateTime timestamp + speed column.
         */
        'schema_preset' => env('TELEMETRY_CH_SCHEMA_PRESET', 'location'),
        'device_id_column' => env('TELEMETRY_CH_DEVICE_ID_COLUMN', 'imei'),
        'timestamp_column' => env('TELEMETRY_CH_TIMESTAMP_COLUMN', 'timestamp'),
        /** unix_seconds | datetime_string */
        'timestamp_type' => env('TELEMETRY_CH_TIMESTAMP_TYPE', 'unix_seconds'),
        'speed_column' => env('TELEMETRY_CH_SPEED_COLUMN', 'gpsSpeed'),
        /**
         * Columns returned by ClickHouse must match this order for the importer:
         * device_id, event_at, latitude, longitude, speed, battery, gsm_signal, odometer, ignition
         * Use SQL aliases if CH uses "timestamp" instead of event_at.
         */
        'select_sql_suffix' => env('TELEMETRY_CLICKHOUSE_SELECT_SUFFIX', ''),
    ],

    'cursor_incremental' => 'clickhouse:device_locations:incremental',

    /*
    |--------------------------------------------------------------------------
    | Heatmap data source: "mock" | "database"
    |--------------------------------------------------------------------------
    */
    'heatmap' => [
        'driver' => env('TELEMETRY_HEATMAP_DRIVER', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics tuning
    |--------------------------------------------------------------------------
    */
    'parking_speed_kmh_max' => (float) env('TELEMETRY_PARKING_SPEED_MAX', 3.0),
    'min_session_seconds' => (int) env('TELEMETRY_MIN_SESSION_SECONDS', 60),
    'impression_sample_multiplier' => (int) env('TELEMETRY_IMPRESSION_MULTIPLIER', 10),
];
