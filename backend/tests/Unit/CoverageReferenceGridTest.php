<?php

namespace Tests\Unit;

use App\Services\Analytics\CoverageReferenceGrid;
use Tests\TestCase;

class CoverageReferenceGridTest extends TestCase
{
    public function test_invalid_bbox_returns_zero(): void
    {
        $this->assertSame(0, CoverageReferenceGrid::referenceCellCountInBBox(1.0, 1.0, 0.0, 1.0, 3));
        $this->assertSame(0, CoverageReferenceGrid::referenceCellCountInBBox(0.0, 1.0, 2.0, 1.0, 3));
    }

    public function test_small_bbox_three_by_three_at_three_decimals(): void
    {
        $n = CoverageReferenceGrid::referenceCellCountInBBox(56.0, 56.002, 24.0, 24.002, 3);
        $this->assertSame(9, $n);
    }
}
