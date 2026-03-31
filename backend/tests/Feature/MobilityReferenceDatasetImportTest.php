<?php

namespace Tests\Feature;

use App\Models\MobilityReferenceCell;
use App\Services\ImpressionEngine\MobilityReferenceDatasetImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class MobilityReferenceDatasetImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_aggregates_rows_per_h3_cell_with_averages(): void
    {
        $path = $this->writeFixtureXlsx([
            [
                'gps_lat', 'gps_lon', 'vehicle_volume_AADT_2025', 'pedestrian_count_daily',
                'average_speed_kmh', 'hourly_peak_factor',
            ],
            [56.9, 24.4, 100, 10, 50, 1.5],
            [56.9, 24.4, 200, 30, 60, 2.0],
        ]);

        $import = app(MobilityReferenceDatasetImportService::class);
        $result = $import->importFromPath($path, 'test_v1');

        $this->assertSame(2, $result->rowsRead);
        $this->assertSame(2, $result->validRows);
        $this->assertSame(0, $result->skippedRows);
        $this->assertSame(1, $result->uniqueCells);

        $row = MobilityReferenceCell::query()->where('data_version', 'test_v1')->firstOrFail();
        $this->assertSame(150, $row->vehicle_aadt);
        $this->assertSame(20, $row->pedestrian_daily);
        $this->assertEqualsWithDelta(55.0, (float) $row->average_speed_kmh, 0.01);
        $this->assertEqualsWithDelta(1.75, (float) $row->hourly_peak_factor, 0.001);
        $this->assertSame(2, $row->records_count);
        $this->assertEqualsWithDelta(56.9, (float) $row->lat_center, 0.001);
        $this->assertEqualsWithDelta(24.4, (float) $row->lng_center, 0.001);
    }

    public function test_import_skips_row_with_missing_coordinates(): void
    {
        $path = $this->writeFixtureXlsx([
            [
                'gps_lat', 'gps_lon', 'vehicle_volume_AADT_2025', 'pedestrian_count_daily',
                'average_speed_kmh', 'hourly_peak_factor',
            ],
            ['', 24.4, 100, 10, 50, 1.5],
            [56.9, 24.4, 200, 30, 60, 2.0],
        ]);

        $import = app(MobilityReferenceDatasetImportService::class);
        $result = $import->importFromPath($path, 'test_v2');

        $this->assertSame(1, $result->skippedRows);
        $this->assertSame(1, $result->validRows);
        $this->assertSame(1, (int) MobilityReferenceCell::query()->where('data_version', 'test_v2')->count());
    }

    public function test_import_throws_when_required_column_missing(): void
    {
        $path = $this->writeFixtureXlsx([
            ['gps_lat', 'gps_lon', 'wrong'],
            [56.9, 24.4, 1],
        ]);

        $import = app(MobilityReferenceDatasetImportService::class);
        $this->expectException(\RuntimeException::class);
        $import->importFromPath($path, 'test_v3');
    }

    /**
     * @param  list<list<string|int|float>>  $rows
     */
    private function writeFixtureXlsx(array $rows): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mobility_');
        if ($tmp === false) {
            self::fail('tempnam failed');
        }
        unlink($tmp);
        $path = $tmp.'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Dataset');
        foreach ($rows as $r) {
            $writer->addRow(Row::fromValues($r));
        }
        $writer->close();

        return $path;
    }
}
