<?php

return [

    'h3' => [
        /**
         * real: FFI + libh3 (Ubuntu: libh3.so, macOS: libh3.dylib via brew).
         * fake: deterministic cell_id for tests / CI without native library.
         */
        'driver' => env('IMPRESSION_ENGINE_H3_DRIVER', 'real'),

        /** Absolute path to shared library; empty = default by OS (see LibH3Indexer). */
        'library_path' => env('IMPRESSION_ENGINE_H3_LIBRARY_PATH'),

        'resolution' => (int) env('IMPRESSION_ENGINE_H3_RESOLUTION', 9),
    ],

    'mobility_import' => [
        'default_dataset_path' => env(
            'IMPRESSION_ENGINE_MOBILITY_XLSX',
            dirname(__DIR__, 2).'/datasets/riga_mobility_bigdata_reference_dataset.xlsx'
        ),
        'sheet_name' => 'Dataset',
        'low_aadt_threshold' => (int) env('IMPRESSION_ENGINE_LOW_AADT_THRESHOLD', 3000),
    ],

    'calculation' => [
        'calculation_version' => env('IMPRESSION_ENGINE_CALCULATION_VERSION', 'v1.0'),
        /** Local timezone for hour-of-day (peak windows). */
        'timezone' => env('IMPRESSION_ENGINE_TIMEZONE', 'Europe/Riga'),
        'telemetry_assumed_seconds_per_point' => (int) env('TELEMETRY_ASSUMED_SECONDS_PER_POINT', 10),
        /** Persist aggregated rows to campaign_vehicle_exposure_hourly (can be large). */
        'store_exposure_hourly' => filter_var(env('IMPRESSION_ENGINE_STORE_EXPOSURE_HOURLY', true), FILTER_VALIDATE_BOOLEAN),
        /** Driving if speed (km/h) strictly greater than this. */
        'driving_speed_threshold_kmh' => (float) env('IMPRESSION_ENGINE_DRIVING_SPEED_THRESHOLD', 5.0),
        /** Peak hour windows (local), inclusive start/end hour. */
        'peak_hours' => [
            ['start' => 7, 'end' => 10],
            ['start' => 16, 'end' => 19],
        ],
        /** Dwell tiers for parking (seconds in the hourly bucket). */
        'dwell_short_max_seconds' => 15 * 60 - 1,
        'dwell_medium_max_seconds' => 60 * 60,
        /** Nearest mobility cell fallback (meters) using lat/lng centers. */
        'mobility_fallback_max_meters' => (float) env('IMPRESSION_ENGINE_MOBILITY_FALLBACK_M', 300),
        /**
         * Above this many hourly exposure rows, Filament/PDF must not recompute zone breakdown synchronously;
         * use {@see BuildCampaignImpressionZoneBreakdownJob} and cached {@see CampaignImpressionStat::zone_breakdown_json}.
         */
        'zone_breakdown_max_hourly_rows_live' => (int) env('IMPRESSION_ENGINE_ZONE_BREAKDOWN_MAX_HOURLY_ROWS_LIVE', 25_000),
    ],

];
