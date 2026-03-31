<?php

namespace App\Jobs;

use App\Services\ImpressionEngine\MobilityReferenceDatasetImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportMobilityReferenceDatasetJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(
        public ?string $absolutePath = null,
        public string $dataVersion = 'riga_v1_2025',
    ) {}

    public function handle(MobilityReferenceDatasetImportService $import): void
    {
        $path = $this->absolutePath;
        if ($path === null || $path === '') {
            $path = (string) config('impression_engine.mobility_import.default_dataset_path');
        }
        if ($path !== '' && ! str_starts_with($path, '/')) {
            $path = base_path('../'.$path);
        }

        $import->importFromPath($path, $this->dataVersion);
    }
}
