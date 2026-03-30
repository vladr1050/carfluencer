<?php

namespace App\Console\Commands;

use App\Services\ImpressionEngine\MobilityReferenceDatasetImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportMobilityReferenceDatasetCommand extends Command
{
    protected $signature = 'impression-engine:import-mobility-dataset
        {--path= : Path to xlsx (default: config impression_engine.mobility_import.default_dataset_path)}
        {--data-version=riga_v1_2025 : mobility data_version string}';

    protected $description = 'Stream-import Riga mobility reference XLSX into mobility_reference_cells (H3 res from config).';

    public function handle(MobilityReferenceDatasetImportService $import): int
    {
        $path = $this->option('path');
        if (! is_string($path) || $path === '') {
            $path = (string) config('impression_engine.mobility_import.default_dataset_path');
        }
        if ($path !== '' && ! str_starts_with($path, '/')) {
            $path = base_path('../'.$path);
        }

        $dataVersion = (string) $this->option('data-version');

        $this->info("Importing [{$path}] data_version [{$dataVersion}] …");

        try {
            $result = $import->importFromPath($path, $dataVersion);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['total_rows_read', $result->rowsRead],
                ['valid_rows', $result->validRows],
                ['skipped_rows', $result->skippedRows],
                ['unique_cells', $result->uniqueCells],
                ['inserted_rows', $result->insertedRows],
                ['min_lat', $result->quality['min_lat'] ?? '—'],
                ['max_lat', $result->quality['max_lat'] ?? '—'],
                ['min_lon', $result->quality['min_lon'] ?? '—'],
                ['max_lon', $result->quality['max_lon'] ?? '—'],
                ['avg_aadt', $result->quality['avg_aadt'] ?? '—'],
                ['avg_pedestrian', $result->quality['avg_pedestrian'] ?? '—'],
                ['cells_ped_zero_pct', $result->quality['cells_with_pedestrian_zero_pct'] ?? '—'],
                ['cells_low_aadt_pct', $result->quality['cells_with_low_aadt_pct'] ?? '—'],
            ]
        );

        return self::SUCCESS;
    }
}
