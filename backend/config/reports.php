<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Headless browser driver for heatmap PNG + PDF
    |--------------------------------------------------------------------------
    |
    | "browsershot" — spatie/browsershot: Node.js + npm package puppeteer (backend/npm install) + Chrome/Chromium.
    | "fake" — writes placeholder files (tests / CI without Chrome).
    |
    */
    'browser_driver' => env('CAMPAIGN_REPORT_BROWSER_DRIVER', 'browsershot'),

    'heatmap_image' => [
        'width' => (int) env('CAMPAIGN_REPORT_HEATMAP_WIDTH', 1280),
        'height' => (int) env('CAMPAIGN_REPORT_HEATMAP_HEIGHT', 720),
    ],

    /*
    | Экспорт теплокарты в PDF: отсечь мусорные GPS и ограничить регион (по умолчанию EE/LV/LT).
    */
    'heatmap_export' => [
        /**
         * null / пусто = как у портала (platform_settings / telemetry.global_default_shadow).
         * Иначе: current | small | xsmall — те же пресеты, что в админке и Advertiser heatmap.
         */
        'shadow_preset' => ($s = env('CAMPAIGN_REPORT_HEATMAP_SHADOW')) !== null && $s !== '' && in_array($s, ['current', 'small', 'xsmall'], true)
            ? $s
            : null,
        'clip_to_bounds' => filter_var(env('CAMPAIGN_REPORT_HEATMAP_CLIP_TO_BOUNDS', true), FILTER_VALIDATE_BOOLEAN),
        'bounds' => [
            'south' => (float) env('CAMPAIGN_REPORT_HEATMAP_BOUNDS_SOUTH', 53.70),
            'north' => (float) env('CAMPAIGN_REPORT_HEATMAP_BOUNDS_NORTH', 59.75),
            'west' => (float) env('CAMPAIGN_REPORT_HEATMAP_BOUNDS_WEST', 20.70),
            'east' => (float) env('CAMPAIGN_REPORT_HEATMAP_BOUNDS_EAST', 28.52),
        ],
        /*
        | Три (или больше) кадра в PDF: fit_to_data = по точкам; иначе фикс. bbox.
        */
        'viewports' => [
            [
                'id' => 'full',
                'label' => 'Full coverage',
                'fit_to_data' => true,
            ],
            [
                'id' => 'riga_jurmala',
                'label' => 'Rīga + Jūrmala',
                'fit_to_data' => false,
                'south' => 56.81,
                'west' => 23.42,
                'north' => 57.10,
                'east' => 24.42,
            ],
            [
                'id' => 'riga_center',
                'label' => 'Rīga centrs',
                'fit_to_data' => false,
                // Уже кадр (~центр ~56.95, 24.11): Vecrīga + ядро центра
                'south' => 56.936,
                'west' => 24.045,
                'north' => 56.966,
                'east' => 24.128,
            ],
        ],
        /**
         * Base Leaflet zoom for rollup tier on wide frames. Tight PDF viewports (e.g. Rīga centrs)
         * auto-use a higher zoom — see {@see ReportHeatmapExportRollupZoom::forViewport}.
         */
        'rollup_read_zoom' => (int) env('CAMPAIGN_REPORT_HEATMAP_ROLLUP_ZOOM', 12),
        /** Cap distinct cells sent to the browser for PNG (keeps heaviest tail). */
        'max_cells' => (int) env('CAMPAIGN_REPORT_HEATMAP_MAX_CELLS', 50000),
        'density_high_cells_per_deg2' => (float) env('CAMPAIGN_REPORT_HEATMAP_DENSITY_HIGH', 2500),
        'density_low_cells_per_deg2' => (float) env('CAMPAIGN_REPORT_HEATMAP_DENSITY_LOW', 200),
        /** PNG Leaflet fitBounds max zoom (cap single-street over-zoom). */
        'leaflet_fit_max_zoom' => max(8, min(18, (int) env('CAMPAIGN_REPORT_HEATMAP_LEAFLET_FIT_MAX_ZOOM', 14))),
        /** Fit map to active export points (rollup cells) instead of full viewport rectangle. */
        'data_fit_to_active_cells' => filter_var(env('CAMPAIGN_REPORT_HEATMAP_DATA_FIT', true), FILTER_VALIDATE_BOOLEAN),
        'data_fit_padding_ratio' => max(0.0, min(0.25, (float) env('CAMPAIGN_REPORT_HEATMAP_DATA_FIT_PAD', 0.12))),
        'data_fit_min_points' => max(1, min(100, (int) env('CAMPAIGN_REPORT_HEATMAP_DATA_FIT_MIN_POINTS', 2))),
        'data_fit_min_lat_span_deg' => max(0.001, min(1.0, (float) env('CAMPAIGN_REPORT_HEATMAP_DATA_FIT_MIN_LAT_SPAN', 0.012))),
        'data_fit_min_lng_span_deg' => max(0.001, min(1.0, (float) env('CAMPAIGN_REPORT_HEATMAP_DATA_FIT_MIN_LNG_SPAN', 0.018))),
        /** If data span exceeds this, keep full viewport fit (e.g. scattered national-scale). */
        'data_fit_max_lat_span_deg' => max(0.05, min(5.0, (float) env('CAMPAIGN_REPORT_HEATMAP_DATA_FIT_MAX_LAT_SPAN', 0.45))),
        'data_fit_max_lng_span_deg' => max(0.05, min(5.0, (float) env('CAMPAIGN_REPORT_HEATMAP_DATA_FIT_MAX_LNG_SPAN', 0.7))),
    ],

    'normalization' => env('CAMPAIGN_REPORT_HEATMAP_NORMALIZATION', 'max'),

    'chrome_path' => env('CAMPAIGN_REPORT_CHROME_PATH'),

    /**
     * Snap Chromium under PHP/queue/Supervisor usually fails (snap cgroup, /var/www/snap). False = fail fast with a clear error.
     */
    'allow_snap_chrome' => filter_var(env('CAMPAIGN_REPORT_ALLOW_SNAP_CHROME'), FILTER_VALIDATE_BOOLEAN),

    'node_binary' => env('CAMPAIGN_REPORT_NODE_BINARY'),

    'npm_binary' => env('CAMPAIGN_REPORT_NPM_BINARY'),

    /*
    | null = auto: enable for Docker (/.dockerenv) and snap Chromium paths (/snap/).
    | true/false = force (see CAMPAIGN_REPORT_CHROME_NO_SANDBOX).
    */
    'chrome_no_sandbox' => env('CAMPAIGN_REPORT_CHROME_NO_SANDBOX'),

    /** Browsershot navigation/screenshot timeout (seconds). */
    'browsershot_timeout' => (int) env('CAMPAIGN_REPORT_BROWSERSHOT_TIMEOUT', 180),

    /** After DOM load, wait for map tiles/CDN (heatmap PNG only). */
    'heatmap_render_delay_ms' => (int) env('CAMPAIGN_REPORT_HEATMAP_DELAY_MS', 3500),

    /** Applied at start of GenerateCampaignReportJob (heatmap + PDF are heavy). */
    'php_memory_limit' => env('CAMPAIGN_REPORT_PHP_MEMORY_LIMIT', '1024M'),

    /**
     * Optional cap on inclusive calendar-day span for campaign reports.
     * When set, combined with telemetry.heatmap.max_date_range_days (stricter wins).
     */
    'max_calendar_days' => env('CAMPAIGN_REPORT_MAX_CALENDAR_DAYS'),

    /*
    |--------------------------------------------------------------------------
    | Analytics snapshot (CampaignAnalyticsService)
    |--------------------------------------------------------------------------
    |
    | Leaflet map zoom used to pick heatmap_cells_daily.zoom_tier for top parking
    | locations (same mapping as HeatmapBucketStrategy::tierFromMapZoom). Align
    | with typical advertiser viewport zoom if you need parity with rollup tiles.
    |
    */
    'analytics' => [
        'top_locations_map_zoom' => (int) env('CAMPAIGN_REPORT_ANALYTICS_TOP_LOCATIONS_ZOOM', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | Driving spatial coverage (CampaignCoverageService, heatmap_cells_daily)
    |--------------------------------------------------------------------------
    |
    | map_zoom — Leaflet-style zoom; converted via HeatmapBucketStrategy::tierFromMapZoom
    | to heatmap_cells_daily.zoom_tier (not the tier index you pass manually elsewhere).
    | Denominator = grid cell count inside reports.heatmap_export.bounds at that tier's
    | decimal precision (operational_bounds_grid).
    |
    */
    'coverage' => [
        'map_zoom' => (int) env('CAMPAIGN_REPORT_COVERAGE_MAP_ZOOM', 12),
        'patterns' => [
            'focused_max' => (float) env('CAMPAIGN_REPORT_COVERAGE_FOCUSED_MAX', 0.20),
            'balanced_max' => (float) env('CAMPAIGN_REPORT_COVERAGE_BALANCED_MAX', 0.50),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deterministic campaign insights (PDF snapshot; hours-based exposure wording)
    |--------------------------------------------------------------------------
    */
    'insights' => [
        'exposure' => [
            'parking_dominant_min' => (float) env('REPORT_INSIGHTS_PARKING_DOM_MIN', 0.75),
            'balanced_min' => (float) env('REPORT_INSIGHTS_BALANCED_MIN', 0.40),
        ],
        'location' => [
            'highly_concentrated_top1_min' => (float) env('REPORT_INSIGHTS_LOC_TOP1_MIN', 0.50),
            'highly_concentrated_top3_min' => (float) env('REPORT_INSIGHTS_LOC_TOP3_HIGH_MIN', 0.75),
            'moderately_concentrated_top3_min' => (float) env('REPORT_INSIGHTS_LOC_TOP3_MOD_MIN', 0.50),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Report export heatmap visuals (PNG/PDF only — not portal/API)
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | Human-readable top location labels (reverse geocoding, report export only)
    |--------------------------------------------------------------------------
    |
    | Provider "none" skips external calls. Use nominatim in production (.env).
    | Respect Nominatim usage policy (max ~1 req/s; inter_request_delay_ms).
    |
    */
    'location_labels' => [
        'provider' => env('REPORT_LOCATION_LABEL_PROVIDER', 'nominatim'),
        'cache_ttl_days' => (int) env('REPORT_LOCATION_LABEL_CACHE_TTL_DAYS', 90),
        'timeout_seconds' => (int) env('REPORT_LOCATION_LABEL_TIMEOUT', 5),
        'inter_request_delay_ms' => (int) env('REPORT_LOCATION_LABEL_DELAY_MS', 1100),
        'nominatim' => [
            'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
            'user_agent' => env('NOMINATIM_USER_AGENT', ''),
        ],
    ],

    /*
    | Report heatmap PNGs use the same Leaflet.heat settings as the Advertiser portal:
    | HeatmapLeafletStyle::heatLayerOptionsForExport + heatmap_export.shadow_preset.
    */
    'heatmaps' => [],
];
