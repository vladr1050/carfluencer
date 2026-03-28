<?php

namespace Tests\Unit;

use App\Services\Reports\ReportHeatmapViewports;
use Tests\TestCase;

class ReportHeatmapViewportsTest extends TestCase
{
    public function test_default_config_has_three_viewports(): void
    {
        $all = ReportHeatmapViewports::all();
        $this->assertCount(3, $all);
        $this->assertSame('full', $all[0]['id']);
        $this->assertTrue($all[0]['fit_to_data']);
        $this->assertFalse($all[1]['fit_to_data']);
        $this->assertArrayHasKey('south', $all[1]);
    }

    public function test_by_id_returns_riga_center(): void
    {
        $v = ReportHeatmapViewports::byId('riga_center');
        $this->assertNotNull($v);
        $this->assertSame('Rīga centrs', $v['label']);
    }
}
