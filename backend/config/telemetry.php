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

        /*
        | Нагрузка на внешний ClickHouse: меньше строк за запрос, паузы между IMEI/чанками,
        | лимит объектов за один tick планировщика (остальные — на следующих циклах).
        */
        'incremental_rows_per_imei' => max(500, min(500_000, (int) env('TELEMETRY_CH_INCREMENTAL_ROWS_PER_IMEI', 15_000))),
        'pause_ms_between_imei' => max(0, min(60_000, (int) env('TELEMETRY_CH_PAUSE_MS_BETWEEN_IMEI', 300))),
        'max_imeis_per_scheduler_tick' => max(0, min(10_000, (int) env('TELEMETRY_CH_MAX_IMEIS_PER_TICK', 35))),
        /** telemetry:sync-incremental без --limit (глобальный курсор по всей таблице) */
        'global_incremental_rows' => max(1_000, min(2_000_000, (int) env('TELEMETRY_CH_GLOBAL_INCREMENTAL_ROWS', 25_000))),
        'historical_rows_per_chunk' => max(5_000, min(2_000_000, (int) env('TELEMETRY_CH_HISTORICAL_ROWS_PER_CHUNK', 80_000))),
        'historical_imei_chunk_size' => max(1, min(500, (int) env('TELEMETRY_CH_HISTORICAL_IMEI_CHUNK', 40))),
        'pause_ms_between_historical_chunks' => max(0, min(120_000, (int) env('TELEMETRY_CH_PAUSE_MS_HISTORICAL_CHUNK', 500))),
        'http_timeout_seconds' => max(30, min(600, (int) env('TELEMETRY_CH_HTTP_TIMEOUT', 120))),
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
