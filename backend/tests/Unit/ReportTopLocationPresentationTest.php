<?php

namespace Tests\Unit;

use App\Services\Reports\ReportTopLocationPresentation;
use Tests\TestCase;

class ReportTopLocationPresentationTest extends TestCase
{
    public function test_prefers_label(): void
    {
        $this->assertSame(
            'Riga Center / Brīvības iela area',
            ReportTopLocationPresentation::locationCell([
                'lat' => 56.95,
                'lng' => 24.1,
                'label' => 'Riga Center / Brīvības iela area',
            ])
        );
    }

    public function test_falls_back_to_coordinates(): void
    {
        $this->assertSame(
            '56.95°, 24.10°',
            ReportTopLocationPresentation::locationCell([
                'lat' => 56.95,
                'lng' => 24.1,
                'label' => null,
            ])
        );
    }
}
