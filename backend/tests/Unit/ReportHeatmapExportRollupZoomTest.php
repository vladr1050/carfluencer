<?php

namespace Tests\Unit;

use App\Services\Reports\ReportHeatmapExportBBox;
use App\Services\Reports\ReportHeatmapExportRollupZoom;
use App\Services\Reports\ReportHeatmapViewports;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReportHeatmapExportRollupZoomTest extends TestCase
{
    public function test_riga_uses_higher_rollup_zoom_than_latvia(): void
    {
        Config::set('reports.heatmap_export.rollup_read_zoom', 12);

        $riga = ReportHeatmapViewports::byId('riga');
        $this->assertNotNull($riga);
        $bboxRiga = ReportHeatmapExportBBox::forRollup($riga);
        $this->assertSame(15, ReportHeatmapExportRollupZoom::forViewport($riga, $bboxRiga));

        $lv = ReportHeatmapViewports::byId('latvia');
        $this->assertNotNull($lv);
        $bboxLv = ReportHeatmapExportBBox::forRollup($lv);
        $this->assertSame(12, ReportHeatmapExportRollupZoom::forViewport($lv, $bboxLv));
    }

    public function test_fit_to_data_uses_base_config_zoom(): void
    {
        Config::set('reports.heatmap_export.rollup_read_zoom', 11);

        $vp = ['id' => 'full', 'label' => 'Full', 'fit_to_data' => true];
        $bbox = ReportHeatmapExportBBox::forRollup($vp);
        $this->assertSame(11, ReportHeatmapExportRollupZoom::forViewport($vp, $bbox));
    }
}
