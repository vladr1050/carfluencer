<?php

namespace App\Services\Analytics;

/**
 * Deterministic, rule-based interpretation of {@see CampaignAnalyticsService} snapshots for PDF/API.
 * Wording reflects that exposure_split is derived from driving vs parking hours, not impression shares.
 */
final class CampaignInsightsService
{
    private const INSUFFICIENT_SUMMARY = 'Insufficient data was available to derive strong campaign insights for the selected period.';

    /**
     * @param  array<string, mixed>  $analyticsSnapshot  Snapshot without required `insights` key (it is produced here).
     * @return array{
     *     summary: string|null,
     *     highlights: list<string>,
     *     exposure_pattern: string|null,
     *     location_pattern: string|null
     * }
     */
    public function buildInsights(array $analyticsSnapshot): array
    {
        if ($this->isInsufficient($analyticsSnapshot)) {
            return [
                'summary' => self::INSUFFICIENT_SUMMARY,
                'highlights' => [],
                'exposure_pattern' => null,
                'location_pattern' => null,
            ];
        }

        /** @var array<string, float> $split */
        $split = $analyticsSnapshot['exposure_split'] ?? [];
        $parkingShare = (float) ($split['parking_share'] ?? 0.0);
        $drivingShare = (float) ($split['driving_share'] ?? 0.0);

        /** @var list<array<string, mixed>> $topLocations */
        $topLocations = $analyticsSnapshot['top_locations'] ?? [];
        if (! is_array($topLocations)) {
            $topLocations = [];
        }

        $exposurePattern = $this->classifyExposurePattern($parkingShare, $drivingShare);
        $locationPattern = $this->classifyLocationPattern($topLocations);
        $zonePhrase = $this->buildZonePhrase($topLocations);

        /** @var array<string, mixed> $coverage */
        $coverage = $analyticsSnapshot['coverage'] ?? [];
        if (! is_array($coverage)) {
            $coverage = [];
        }

        $summary = $this->buildSummary($exposurePattern, $locationPattern, $zonePhrase, $parkingShare, $drivingShare);
        $highlights = $this->buildHighlights($exposurePattern, $locationPattern, $zonePhrase, $topLocations, $coverage);

        return [
            'summary' => $summary,
            'highlights' => $highlights,
            'exposure_pattern' => $exposurePattern,
            'location_pattern' => $locationPattern,
        ];
    }

    /**
     * @param  array<string, mixed>  $snap
     */
    private function isInsufficient(array $snap): bool
    {
        /** @var array<string, mixed> $kpis */
        $kpis = $snap['kpis'] ?? [];
        $imp = (int) ($kpis['impressions'] ?? 0);
        $dh = (float) ($kpis['driving_hours'] ?? 0.0);
        $ph = (float) ($kpis['parking_hours'] ?? 0.0);

        /** @var list<array<string, mixed>> $top */
        $top = $snap['top_locations'] ?? [];
        if (! is_array($top)) {
            $top = [];
        }

        $hasHours = $dh > 0.0 || $ph > 0.0;

        return $imp <= 0 && ! $hasHours && $top === [];
    }

    /**
     * @param  list<array<string, mixed>>  $topLocations
     */
    private function totalDwellProxy(array $topLocations): float
    {
        $sum = 0.0;
        foreach ($topLocations as $loc) {
            $sum += max(0.0, (float) ($loc['dwell_proxy'] ?? $loc['samples'] ?? 0));
        }

        return $sum;
    }

    private function classifyExposurePattern(float $parkingShare, float $drivingShare): ?string
    {
        if ($parkingShare <= 0.0 && $drivingShare <= 0.0) {
            return null;
        }

        $dom = (float) config('reports.insights.exposure.parking_dominant_min', 0.75);
        $bal = (float) config('reports.insights.exposure.balanced_min', 0.40);

        if ($parkingShare >= $dom) {
            return 'parking_dominant';
        }
        if ($parkingShare >= $bal) {
            return 'balanced';
        }

        return 'movement_dominant';
    }

    /**
     * @param  list<array<string, mixed>>  $topLocations
     */
    private function classifyLocationPattern(array $topLocations): ?string
    {
        $weights = [];
        foreach ($topLocations as $loc) {
            if (! is_array($loc)) {
                continue;
            }
            $weights[] = max(0.0, (float) ($loc['dwell_proxy'] ?? $loc['samples'] ?? 0));
        }

        $total = array_sum($weights);
        if ($total <= 0.0 || $weights === []) {
            return null;
        }

        $top1 = $weights[0] / $total;
        $top3 = array_sum(array_slice($weights, 0, 3)) / $total;

        $t1High = (float) config('reports.insights.location.highly_concentrated_top1_min', 0.50);
        $t3High = (float) config('reports.insights.location.highly_concentrated_top3_min', 0.75);
        $t3Mod = (float) config('reports.insights.location.moderately_concentrated_top3_min', 0.50);

        if ($top1 >= $t1High || $top3 >= $t3High) {
            return 'highly_concentrated';
        }
        if ($top3 >= $t3Mod) {
            return 'moderately_concentrated';
        }

        return 'distributed';
    }

    /**
     * @param  list<array<string, mixed>>  $topLocations
     */
    private function buildZonePhrase(array $topLocations): string
    {
        $labels = [];
        foreach (array_slice($topLocations, 0, 3) as $loc) {
            if (! is_array($loc)) {
                continue;
            }
            $l = $loc['label'] ?? null;
            if (is_string($l) && trim($l) !== '') {
                $labels[] = trim($l);
            }
        }

        if (count($labels) >= 2) {
            return $labels[0].' and '.$labels[1];
        }
        if (count($labels) === 1) {
            return $labels[0];
        }

        return '';
    }

    private function buildSummary(
        ?string $exposurePattern,
        ?string $locationPattern,
        string $zonePhrase,
        float $parkingShare,
        float $drivingShare
    ): string {
        $segments = [];

        if ($exposurePattern === 'parking_dominant') {
            $segments[] = 'Campaign exposure time was dominated by parked visibility relative to active driving time.';
        } elseif ($exposurePattern === 'balanced') {
            $segments[] = 'Parked and movement-related visibility time were both material to the campaign footprint.';
        } elseif ($exposurePattern === 'movement_dominant') {
            $segments[] = 'Campaign exposure time was driven primarily by vehicle movement across the map.';
        }

        if ($locationPattern === 'highly_concentrated') {
            if ($zonePhrase !== '') {
                $segments[] = 'Strongest parking intensity clustered around '.$zonePhrase.', suggesting a tight urban concentration.';
            } else {
                $segments[] = 'Top parking intensity clustered sharply in a small number of high-signal zones.';
            }
        } elseif ($locationPattern === 'moderately_concentrated') {
            if ($zonePhrase !== '') {
                $segments[] = 'Key parking intensity appeared across several zones including '.$zonePhrase.'.';
            } else {
                $segments[] = 'Parking intensity focused on several distinct zones while retaining broader city presence.';
            }
        } elseif ($locationPattern === 'distributed') {
            $segments[] = 'Parking intensity signals were spread across multiple areas rather than a single hotspot.';
        }

        if ($segments === []) {
            if ($parkingShare > 0.0 || $drivingShare > 0.0) {
                return 'The campaign mix of parked vs movement-related visibility time is reflected in the exposure split above.';
            }
            if ($zonePhrase !== '') {
                return 'Notable parking intensity surfaced around '.$zonePhrase.' for the selected period.';
            }

            return 'See key metrics and top parking zones above for this campaign period.';
        }

        return implode(' ', $segments);
    }

    /**
     * @param  list<array<string, mixed>>  $topLocations
     * @param  array<string, mixed>  $coverage  {@see CampaignCoverageService::buildCoverage}
     * @return list<string>
     */
    private function buildHighlights(
        ?string $exposurePattern,
        ?string $locationPattern,
        string $zonePhrase,
        array $topLocations,
        array $coverage = []
    ): array {
        $out = [];

        if ($exposurePattern === 'parking_dominant') {
            $out[] = 'Parked visibility time outweighed movement-related time in the campaign footprint.';
        } elseif ($exposurePattern === 'balanced') {
            $out[] = 'Parked presence and movement both contributed meaningfully to visibility time.';
        } elseif ($exposurePattern === 'movement_dominant') {
            $out[] = 'Movement-related visibility time led the campaign footprint versus parked time.';
        }

        if ($zonePhrase !== '') {
            $out[] = 'Stand-out parking intensity zones included '.$zonePhrase.'.';
        } elseif ($this->totalDwellProxy($topLocations) > 0.0) {
            $out[] = 'Top campaign parking zones are listed in the table above.';
        }

        if ($locationPattern === 'highly_concentrated') {
            $out[] = 'Intensity concentrated heavily in a limited set of locations.';
        } elseif ($locationPattern === 'moderately_concentrated') {
            $out[] = 'Intensity spread across several key zones with remaining breadth elsewhere.';
        } elseif ($locationPattern === 'distributed') {
            $out[] = 'Signals indicate a distributed footprint across multiple city areas.';
        }

        $covPattern = $coverage['coverage_pattern'] ?? null;
        if (is_string($covPattern) && $covPattern !== '') {
            if ($covPattern === 'focused') {
                $out[] = 'Spatial coverage of driving activity was narrow relative to the configured operational map grid (see footprint metrics).';
            } elseif ($covPattern === 'balanced') {
                $out[] = 'Spatial coverage of driving activity was moderate relative to the configured operational map grid (see footprint metrics).';
            } elseif ($covPattern === 'wide') {
                $out[] = 'Spatial coverage of driving activity was broad relative to the configured operational map grid (see footprint metrics).';
            }
        }

        $out = array_values(array_unique(array_filter($out, fn ($s) => is_string($s) && $s !== '')));

        $maxHighlights = (is_string($covPattern ?? null) && $covPattern !== '') ? 5 : 4;
        if (count($out) > $maxHighlights) {
            $out = array_slice($out, 0, $maxHighlights);
        }

        if (count($out) === 1) {
            $out[] = 'Refer to the exposure split and top parking locations for supporting detail.';
        }

        if ($out === []) {
            $out[] = 'Review key metrics, exposure split, and top parking zones for this campaign period.';
        }

        return $out;
    }
}
