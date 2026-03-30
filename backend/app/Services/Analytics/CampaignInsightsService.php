<?php

namespace App\Services\Analytics;

/**
 * Deterministic, rule-based interpretation of {@see CampaignAnalyticsService} snapshots for PDF/API.
 * Wording reflects that exposure_split is derived from driving vs parking hours, not impression shares.
 * Parking geography uses {@see CampaignParkingByZoneService} (GeoZones), not heatmap top cells.
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

        /** @var array<string, mixed> $parkingByZone */
        $parkingByZone = $analyticsSnapshot['parking_by_zone'] ?? [];
        if (! is_array($parkingByZone)) {
            $parkingByZone = [];
        }

        $zoneWeights = $this->zoneParkingMinuteWeights($parkingByZone);

        $exposurePattern = $this->classifyExposurePattern($parkingShare, $drivingShare);
        $locationPattern = $this->classifyLocationPattern($zoneWeights);
        $zonePhrase = $this->buildZonePhraseFromParkingByZone($parkingByZone);

        /** @var array<string, mixed> $coverage */
        $coverage = $analyticsSnapshot['coverage'] ?? [];
        if (! is_array($coverage)) {
            $coverage = [];
        }

        $summary = $this->buildSummary($exposurePattern, $locationPattern, $zonePhrase, $parkingShare, $drivingShare);
        $highlights = $this->buildHighlights(
            $exposurePattern,
            $locationPattern,
            $zonePhrase,
            $parkingByZone,
            $coverage
        );

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

        /** @var array<string, mixed> $pbz */
        $pbz = $snap['parking_by_zone'] ?? [];
        if (! is_array($pbz)) {
            $pbz = [];
        }
        $windowMin = (int) ($pbz['totals']['parking_minutes_in_window'] ?? 0);

        $hasHours = $dh > 0.0 || $ph > 0.0;

        return $imp <= 0 && ! $hasHours && $windowMin <= 0;
    }

    /**
     * @param  array<string, mixed>  $parkingByZone
     * @return list<float>
     */
    private function zoneParkingMinuteWeights(array $parkingByZone): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $parkingByZone['by_zone'] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $weights = [];
        foreach ($rows as $z) {
            if (! is_array($z)) {
                continue;
            }
            $weights[] = max(0.0, (float) ($z['parking_minutes'] ?? 0));
        }

        return $weights;
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
     * @param  list<float>  $weights  Parking minutes per GeoZone (descending sort expected from upstream).
     */
    private function classifyLocationPattern(array $weights): ?string
    {
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
     * @param  array<string, mixed>  $parkingByZone
     */
    private function buildZonePhraseFromParkingByZone(array $parkingByZone): string
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $parkingByZone['by_zone'] ?? [];
        if (! is_array($rows)) {
            return '';
        }

        $labels = [];
        foreach (array_slice($rows, 0, 3) as $z) {
            if (! is_array($z)) {
                continue;
            }
            $n = $z['name'] ?? null;
            if (is_string($n) && trim($n) !== '') {
                $labels[] = trim($n);
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
                $segments[] = 'Credited parking time in configured GeoZones clustered strongly around '.$zonePhrase.'.';
            } else {
                $segments[] = 'Credited parking time in GeoZones was concentrated in a small set of zones (see Parking time by zone).';
            }
        } elseif ($locationPattern === 'moderately_concentrated') {
            if ($zonePhrase !== '') {
                $segments[] = 'Credited parking time appeared across several GeoZones, notably '.$zonePhrase.'.';
            } else {
                $segments[] = 'Credited parking time spread across several GeoZones while some zones lead the table.';
            }
        } elseif ($locationPattern === 'distributed') {
            $segments[] = 'Credited parking time was spread across many GeoZones rather than a single zone.';
        }

        if ($segments === []) {
            if ($parkingShare > 0.0 || $drivingShare > 0.0) {
                return 'The campaign mix of parked vs movement-related visibility time is reflected in the exposure split above.';
            }
            if ($zonePhrase !== '') {
                return 'Notable credited parking time in GeoZones surfaced around '.$zonePhrase.' for the selected period.';
            }

            return 'See key metrics and Parking time by zone for this campaign period.';
        }

        return implode(' ', $segments);
    }

    /**
     * @param  array<string, mixed>  $parkingByZone
     * @param  array<string, mixed>  $coverage  {@see CampaignCoverageService::buildCoverage}
     * @return list<string>
     */
    private function buildHighlights(
        ?string $exposurePattern,
        ?string $locationPattern,
        string $zonePhrase,
        array $parkingByZone,
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
            $out[] = 'Largest shares of credited parking time in GeoZones included '.$zonePhrase.'.';
        } elseif ($this->totalAttributedZoneMinutes($parkingByZone) > 0) {
            $out[] = 'Breakdown by GeoZone is in the Parking time by zone table.';
        }

        if ($locationPattern === 'highly_concentrated') {
            $out[] = 'Parking time credited to zones concentrated heavily in a limited set of GeoZones.';
        } elseif ($locationPattern === 'moderately_concentrated') {
            $out[] = 'Parking time credited to zones spread across several leading GeoZones.';
        } elseif ($locationPattern === 'distributed') {
            $out[] = 'Parking time credited to zones indicates a distributed footprint across GeoZones.';
        }

        $covPattern = $coverage['coverage_pattern'] ?? null;
        if (is_string($covPattern) && $covPattern !== '') {
            if ($covPattern === 'focused') {
                $out[] = 'Spatial coverage of driving activity was narrow relative to the configured operational map grid (see Footprint).';
            } elseif ($covPattern === 'balanced') {
                $out[] = 'Spatial coverage of driving activity was moderate relative to the configured operational map grid (see Footprint).';
            } elseif ($covPattern === 'wide') {
                $out[] = 'Spatial coverage of driving activity was broad relative to the configured operational map grid (see Footprint).';
            }
        }

        $out = array_values(array_unique(array_filter($out, fn ($s) => is_string($s) && $s !== '')));

        $maxHighlights = (is_string($covPattern ?? null) && $covPattern !== '') ? 5 : 4;
        if (count($out) > $maxHighlights) {
            $out = array_slice($out, 0, $maxHighlights);
        }

        if (count($out) === 1) {
            $out[] = 'Refer to the exposure split and Parking time by zone for supporting detail.';
        }

        if ($out === []) {
            $out[] = 'Review key metrics, exposure split, and Parking time by zone for this campaign period.';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $parkingByZone
     */
    private function totalAttributedZoneMinutes(array $parkingByZone): int
    {
        $sum = 0;
        /** @var list<array<string, mixed>> $rows */
        $rows = $parkingByZone['by_zone'] ?? [];
        if (! is_array($rows)) {
            return 0;
        }
        foreach ($rows as $z) {
            if (! is_array($z)) {
                continue;
            }
            $sum += (int) ($z['parking_minutes'] ?? 0);
        }

        return $sum;
    }
}
