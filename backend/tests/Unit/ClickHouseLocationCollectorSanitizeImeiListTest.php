<?php

namespace Tests\Unit;

use App\Services\Telemetry\ClickHouseLocationCollector;
use Tests\TestCase;

class ClickHouseLocationCollectorSanitizeImeiListTest extends TestCase
{
    public function test_sanitize_imei_list_strips_non_digits_and_deduplicates(): void
    {
        /** @var ClickHouseLocationCollector $collector */
        $collector = app(ClickHouseLocationCollector::class);

        $out = $collector->sanitizeImeiList(['12-34 56', '12-34 56', 'abc', '789']);

        $this->assertSame(['123456', '789'], $out);
    }
}
