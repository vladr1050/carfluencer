<?php

namespace App\Services\ImpressionEngine;

final readonly class MobilityDatasetImportResult
{
    /**
     * @param  array<string, int|float|string|null>  $quality
     */
    public function __construct(
        public int $rowsRead,
        public int $validRows,
        public int $skippedRows,
        public int $uniqueCells,
        public int $insertedRows,
        public string $dataVersion,
        public array $quality,
    ) {}
}
