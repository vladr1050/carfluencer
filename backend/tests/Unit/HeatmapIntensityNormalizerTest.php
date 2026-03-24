<?php

namespace Tests\Unit;

use App\Services\Telemetry\HeatmapIntensityNormalizer;
use PHPUnit\Framework\TestCase;

class HeatmapIntensityNormalizerTest extends TestCase
{
    public function test_cap_max_uses_largest_weight(): void
    {
        $this->assertSame(10, HeatmapIntensityNormalizer::capFromWeights([1, 10, 3], 'max'));
    }

    public function test_cap_p95_below_extreme_hotspot(): void
    {
        $weights = array_merge(array_fill(0, 80, 1), array_fill(0, 15, 50), [1000]);
        $cap = HeatmapIntensityNormalizer::capFromWeights($weights, 'p95');
        $this->assertSame(50, $cap);
        $this->assertLessThan(1000, $cap);
    }

    public function test_normalize_applies_gamma(): void
    {
        $this->assertEqualsWithDelta(0.25, HeatmapIntensityNormalizer::normalize(5, 10, 2.0), 1e-9);
    }

    public function test_normalize_stopped_applies_power_below_one_to_boost_mids(): void
    {
        $half = HeatmapIntensityNormalizer::normalizeStopped(5, 10);
        $this->assertGreaterThan(0.5, $half);
        $this->assertEqualsWithDelta(pow(0.5, 0.7), $half, 1e-9);
        $this->assertEqualsWithDelta(1.0, HeatmapIntensityNormalizer::normalizeStopped(10, 10), 1e-9);
    }

    public function test_rank_percent_below_batch_matches_naive(): void
    {
        $weights = [0, 5, 5, 10, 3, 7];
        $batch = HeatmapIntensityNormalizer::rankPercentBelowBatch($weights);
        $this->assertCount(count($weights), $batch);
        foreach ($weights as $i => $w) {
            $this->assertEquals(
                HeatmapIntensityNormalizer::rankPercentBelow($w, $weights),
                $batch[$i],
                "index $i w=$w",
            );
        }
    }
}
