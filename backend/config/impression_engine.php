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

];
