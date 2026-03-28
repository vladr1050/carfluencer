<?php

namespace App\Services\Analytics;

/**
 * Human-readable footprint line for PDF (replaces bare "focused|balanced|wide").
 */
final class CampaignCoverageNarrative
{
    /**
     * @param  list<array<string, mixed>>  $topLocations
     */
    public static function describe(?string $coveragePattern, array $topLocations): ?string
    {
        if ($coveragePattern === null || $coveragePattern === '') {
            return null;
        }

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

        $zoneHint = match (count($labels)) {
            0 => '',
            1 => ' Strongest parking rollup signals line up near '.$labels[0].'.',
            default => ' Strongest parking rollup signals include '.$labels[0].' and '.$labels[1].'.',
        };

        return match ($coveragePattern) {
            'focused' => 'Relative to the configured operational map grid, driving footprint is narrow.'.$zoneHint,
            'balanced' => 'Driving footprint covers a moderate share of the operational map grid.'.$zoneHint,
            'wide' => 'Driving footprint spans a broad share of the operational map grid.'.$zoneHint,
            default => null,
        };
    }
}
