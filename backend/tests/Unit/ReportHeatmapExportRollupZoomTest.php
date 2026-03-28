<?php

namespace Tests\Unit;

use App\Services\Reports\ReportHeatmapExportBBox;
use App\Services\Reports\ReportHeatmapExportRollupZoom;
use App\Services\Reports\ReportHeatmapViewports;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReportHeatmapExportRollupZoomTest extends TestCase
{
    public function test_riga_center_uses_higher_rollup_zoom_than_regional_frame(): void
    {
        Config::set('reports.heatmap_export.rollup_read_zoom', 12);

        $center = ReportHeatmapViewports::byId('riga_center');
        $this->assertNotNull($center);
        $bboxCenter = ReportHeatmapExportBBox::forRollup($center);
        $this->assertSame(15, ReportHeatmapExportRollupZoom::forViewport($center, $bboxCenter));

        $rj = ReportHeatmapViewports::byId('riga_jurmala');
        $this->assertNotNull($rj);
        $bboxRj = ReportHeatmapExportBBox::forRollup($rj);
        $this->assertSame(12, ReportHeatmapExportRollupZoom::forViewport($rj, $bboxRj));
    }

    public function test_fit_to_data_uses_base_config_zoom(): void
    {
        Config::set('reports.heatmap_export.rollup_read_zoom', 11);

        $vp = ['id' => 'full', 'label' => 'Full', 'fit_to_data' => true];
        $bbox = ReportHeatmapExportBBox::forRollup($vp);
        $this->assertSame(11, ReportHeatmapExportRollupZoom::forViewport($vp, $bbox));
    }
}
