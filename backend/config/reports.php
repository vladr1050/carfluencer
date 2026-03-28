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
                'south' => 56.914,
                'west' => 23.985,
                'north' => 56.992,
                'east' => 24.178,
            ],
        ],
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
];
