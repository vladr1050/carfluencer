<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Remote metrics endpoint (optional)
    |--------------------------------------------------------------------------
    |
    | When set, the advertiser dashboard GETs this URL (optional bearer token).
    | Expected JSON: impressions, driving_distance_km, driving_time_hours,
    | parking_time_hours (optional). On HTTP failure → mock fallback.
    |
    | When unset, metrics come from PostgreSQL (daily_impressions + device_locations),
    | same logic as the heatmap database driver — not random mock data.
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
        /** Страховка от бесконечного цикла при keyset-пагинации истории (страниц × размер страницы). */
        'historical_max_pages' => max(100, min(500_000, (int) env('TELEMETRY_CH_HISTORICAL_MAX_PAGES', 50_000))),
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
        /**
         * leaflet heatmap: intensity = (w/maxW)^gamma, w = points per lat/lng bucket.
         * 1.0 = linear; >1 (default) emphasizes peaks vs mid-density areas.
         */
        'intensity_gamma' => max(1.0, min(3.0, (float) env('TELEMETRY_HEATMAP_INTENSITY_GAMMA', 1.55))),
        /**
         * Advertiser heatmap "Trips" KPI: multiplier × vehicle_count × full_calendar_days (inclusive).
         * Overridden by platform_settings.advertiser_heatmap_trips_per_vehicle_full_day when set.
         */
        'advertiser_trips_per_vehicle_full_day' => max(0.0, min(1000.0, (float) env('ADVERTISER_HEATMAP_TRIPS_PER_VEHICLE_FULL_DAY', 1.0))),
        /** Defaults for admin + advertiser heatmap UI (overridden by platform_settings). */
        'global_default_normalization' => (string) env('TELEMETRY_HEATMAP_GLOBAL_DEFAULT_NORMALIZATION', 'max'),
        'global_default_map_view' => (string) env('TELEMETRY_HEATMAP_GLOBAL_DEFAULT_MAP_VIEW', 'heatmap'),
        'global_default_shadow' => (string) env('TELEMETRY_HEATMAP_GLOBAL_DEFAULT_SHADOW', 'xsmall'),
        /**
         * GPS fallback for parking hours: ignore gaps longer than this between consecutive points (device offline).
         */
        'max_parking_segment_seconds' => max(60, min(86400, (int) env('TELEMETRY_HEATMAP_MAX_PARKING_SEGMENT_SECONDS', 7200))),
        /**
         * When set (e.g. 120), advertiser + admin heatmap reject ranges wider than this many calendar days (422).
         * Null / unset = no limit (may still hit DB or PHP timeouts on very large raw aggregates).
         */
        'max_date_range_days' => ($raw = env('TELEMETRY_HEATMAP_MAX_DATE_RANGE_DAYS')) !== null && $raw !== ''
            ? max(1, min(3660, (int) $raw))
            : null,
        /**
         * Daily rollup table heatmap_cells_daily: write path (artisan heatmap:aggregate) and read path (API when bbox+zoom set).
         */
        'rollup' => [
            'read_enabled' => filter_var(env('TELEMETRY_HEATMAP_ROLLUP_READ', true), FILTER_VALIDATE_BOOLEAN),
            /**
             * When true and bbox+zoom are missing, fall back to legacy on-the-fly GROUP BY on device_locations.
             * Prefer false in production so clients always send viewport + zoom.
             */
            'legacy_fallback_without_viewport' => filter_var(env('TELEMETRY_HEATMAP_LEGACY_FALLBACK_NO_VIEWPORT', true), FILTER_VALIDATE_BOOLEAN),
            /**
             * When true, advertiser heatmap summary KPIs may fall back to a full device_locations scan
             * if daily_impressions and session aggregates are empty. Default false (return insufficient_aggregates).
             */
            'advertiser_raw_summary_fallback' => filter_var(env('TELEMETRY_HEATMAP_ADVERTISER_RAW_SUMMARY_FALLBACK', false), FILTER_VALIDATE_BOOLEAN),
            /**
             * Map zoom (Leaflet) → tier index. Each tier uses `decimals` for ROUND(lat/lng, decimals).
             * max_zoom inclusive: first matching tier wins (list order matters).
             */
            'zoom_tiers' => [
                ['max_zoom' => 10, 'decimals' => 2],
                ['max_zoom' => 12, 'decimals' => 3],
                ['max_zoom' => 14, 'decimals' => 4],
                ['max_zoom' => 22, 'decimals' => 5],
            ],
            /**
             * Driving rollup: exclude GPS points with speed <= this (km/h). Report/PDF export expects meaningful movement only.
             */
            'driving_min_speed_kmh' => (float) env('TELEMETRY_HEATMAP_ROLLUP_DRIVING_MIN_SPEED_KMH', 5.0),
        ],
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
