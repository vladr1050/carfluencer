<?php

namespace App\Services\ImpressionEngine;

use App\Models\MobilityReferenceCell;
use App\Services\ImpressionEngine\Contracts\H3IndexerInterface;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

final class MobilityReferenceDatasetImportService
{
    private const REQUIRED_HEADERS = [
        'gps_lat',
        'gps_lon',
        'vehicle_volume_AADT_2025',
        'pedestrian_count_daily',
        'average_speed_kmh',
        'hourly_peak_factor',
    ];

    public function __construct(
        private readonly H3IndexerInterface $h3,
    ) {}

    public function importFromPath(string $absolutePath, string $dataVersion): MobilityDatasetImportResult
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException("Mobility dataset file not readable: {$absolutePath}");
        }

        $sheetName = (string) config('impression_engine.mobility_import.sheet_name', 'Dataset');
        $lowAadt = (int) config('impression_engine.mobility_import.low_aadt_threshold', 3000);

        /** @var array<string, array{sum_aadt: float, sum_ped: float, sum_speed: float, sum_peak: float, count: int}> $agg */
        $agg = [];
        $totalRowsRead = 0;
        $validRows = 0;
        $skippedRows = 0;
        $minLat = null;
        $maxLat = null;
        $minLon = null;
        $maxLon = null;

        $reader = new Reader;
        try {
            $reader->open($absolutePath);
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to open XLSX: '.$e->getMessage(), 0, $e);
        }

        try {
            $headerMap = null;
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== $sheetName) {
                    continue;
                }

                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $this->rowToScalarArray($row);

                    if ($headerMap === null) {
                        $headerMap = $this->buildHeaderMap($cells);
                        $this->assertHeaders($headerMap);

                        continue;
                    }

                    $totalRowsRead++;

                    $lat = $this->getNumeric($cells, $headerMap, 'gps_lat');
                    $lon = $this->getNumeric($cells, $headerMap, 'gps_lon');
                    $aadt = $this->getIntOrNull($cells, $headerMap, 'vehicle_volume_AADT_2025');
                    $ped = $this->getIntOrNull($cells, $headerMap, 'pedestrian_count_daily');

                    if ($lat === null || $lon === null || $aadt === null || $ped === null) {
                        $skippedRows++;

                        continue;
                    }

                    try {
                        $cellId = $this->h3->latLngToCellId($lat, $lon);
                    } catch (Throwable) {
                        $skippedRows++;

                        continue;
                    }

                    $speed = $this->getNumeric($cells, $headerMap, 'average_speed_kmh');
                    $peak = $this->getNumeric($cells, $headerMap, 'hourly_peak_factor');
                    if ($speed === null || $peak === null) {
                        $skippedRows++;

                        continue;
                    }

                    $validRows++;
                    $minLat = $minLat === null ? $lat : min($minLat, $lat);
                    $maxLat = $maxLat === null ? $lat : max($maxLat, $lat);
                    $minLon = $minLon === null ? $lon : min($minLon, $lon);
                    $maxLon = $maxLon === null ? $lon : max($maxLon, $lon);

                    if (! isset($agg[$cellId])) {
                        $agg[$cellId] = [
                            'sum_aadt' => 0.0,
                            'sum_ped' => 0.0,
                            'sum_speed' => 0.0,
                            'sum_peak' => 0.0,
                            'count' => 0,
                        ];
                    }
                    $agg[$cellId]['sum_aadt'] += $aadt;
                    $agg[$cellId]['sum_ped'] += $ped;
                    $agg[$cellId]['sum_speed'] += $speed;
                    $agg[$cellId]['sum_peak'] += $peak;
                    $agg[$cellId]['count']++;
                }
                break;
            }
        } finally {
            $reader->close();
        }

        if ($headerMap === null) {
            throw new RuntimeException("Sheet [{$sheetName}] not found in workbook.");
        }

        $uniqueCells = count($agg);

        DB::transaction(function () use ($agg, $dataVersion): void {
            MobilityReferenceCell::query()->where('data_version', $dataVersion)->delete();
            $now = now();
            $batch = [];
            foreach ($agg as $cellId => $bucket) {
                $n = max(1, $bucket['count']);
                $batch[] = [
                    'cell_id' => $cellId,
                    'vehicle_aadt' => (int) round($bucket['sum_aadt'] / $n),
                    'pedestrian_daily' => (int) round($bucket['sum_ped'] / $n),
                    'average_speed_kmh' => round($bucket['sum_speed'] / $n, 2),
                    'hourly_peak_factor' => round($bucket['sum_peak'] / $n, 4),
                    'data_version' => $dataVersion,
                    'records_count' => $bucket['count'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($batch) >= 500) {
                    DB::table('mobility_reference_cells')->insert($batch);
                    $batch = [];
                }
            }
            if ($batch !== []) {
                DB::table('mobility_reference_cells')->insert($batch);
            }
        });

        $insertedRows = $uniqueCells;

        $cellsPedZero = 0;
        $cellsLowAadt = 0;
        foreach ($agg as $bucket) {
            $n = max(1, $bucket['count']);
            $avgPed = $bucket['sum_ped'] / $n;
            $avgAadt = $bucket['sum_aadt'] / $n;
            if ($avgPed <= 0.0) {
                $cellsPedZero++;
            }
            if ($avgAadt < $lowAadt) {
                $cellsLowAadt++;
            }
        }

        $quality = [
            'min_lat' => $minLat,
            'max_lat' => $maxLat,
            'min_lon' => $minLon,
            'max_lon' => $maxLon,
            'avg_aadt' => $insertedRows > 0
                ? round(array_sum(array_map(
                    static fn (array $b): float => $b['sum_aadt'] / max(1, $b['count']),
                    $agg
                )) / $insertedRows, 2)
                : null,
            'avg_pedestrian' => $insertedRows > 0
                ? round(array_sum(array_map(
                    static fn (array $b): float => $b['sum_ped'] / max(1, $b['count']),
                    $agg
                )) / $insertedRows, 2)
                : null,
            'cells_with_pedestrian_zero_pct' => $insertedRows > 0
                ? round(100.0 * $cellsPedZero / $insertedRows, 2)
                : null,
            'cells_with_low_aadt_pct' => $insertedRows > 0
                ? round(100.0 * $cellsLowAadt / $insertedRows, 2)
                : null,
            'low_aadt_threshold' => $lowAadt,
        ];

        return new MobilityDatasetImportResult(
            rowsRead: $totalRowsRead,
            validRows: $validRows,
            skippedRows: $skippedRows,
            uniqueCells: $uniqueCells,
            insertedRows: $insertedRows,
            dataVersion: $dataVersion,
            quality: $quality,
        );
    }

    /**
     * @return list<string|float|int|null>
     */
    private function rowToScalarArray(Row $row): array
    {
        $out = [];
        foreach ($row->getCells() as $cell) {
            $v = $cell->getValue();
            if ($v === null) {
                $out[] = null;
            } elseif (is_numeric($v)) {
                $out[] = $v + 0;
            } elseif (is_string($v)) {
                $t = trim($v);
                $out[] = $t === '' ? null : $t;
            } else {
                $out[] = (string) $v;
            }
        }

        return $out;
    }

    /**
     * @param  list<string|float|int|null>  $headerRow
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $i => $name) {
            if (! is_string($name)) {
                continue;
            }
            $key = strtolower(trim($name));
            if ($key !== '') {
                $map[$key] = $i;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $headerMap
     */
    private function assertHeaders(array $headerMap): void
    {
        foreach (self::REQUIRED_HEADERS as $h) {
            if (! isset($headerMap[strtolower($h)])) {
                throw new RuntimeException("Missing required column [{$h}] in Dataset sheet.");
            }
        }
    }

    /**
     * @param  list<string|float|int|null>  $cells
     * @param  array<string, int>  $headerMap
     */
    private function getNumeric(array $cells, array $headerMap, string $header): ?float
    {
        $idx = $headerMap[strtolower($header)] ?? null;
        if ($idx === null || ! array_key_exists($idx, $cells)) {
            return null;
        }
        $v = $cells[$idx];
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        if (is_string($v) && is_numeric($v)) {
            return (float) $v;
        }

        return null;
    }

    /**
     * @param  list<string|float|int|null>  $cells
     * @param  array<string, int>  $headerMap
     */
    private function getIntOrNull(array $cells, array $headerMap, string $header): ?int
    {
        $f = $this->getNumeric($cells, $headerMap, $header);
        if ($f === null) {
            return null;
        }

        return (int) round($f);
    }
}
