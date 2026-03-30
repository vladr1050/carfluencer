<?php

namespace App\Services\Analytics;

/**
 * Human-readable driving footprint line for PDF, tied to {@see CampaignParkingByZoneService} zones.
 */
final class CampaignCoverageNarrative
{
    /**
     * @param  array<string, mixed>  $parkingByZone  Output of {@see CampaignParkingByZoneService::build()}
     */
    public static function describe(?string $coveragePattern, array $parkingByZone): ?string
    {
        if ($coveragePattern === null || $coveragePattern === '') {
            return null;
        }

        /** @var list<array<string, mixed>> $byZone */
        $byZone = $parkingByZone['by_zone'] ?? [];
        if (! is_array($byZone)) {
            $byZone = [];
        }

        $labels = [];
        foreach (array_slice($byZone, 0, 3) as $z) {
            if (! is_array($z)) {
                continue;
            }
            $n = $z['name'] ?? null;
            if (is_string($n) && trim($n) !== '') {
                $labels[] = trim($n);
            }
        }

        $zoneHint = match (count($labels)) {
            0 => ' Refer to Parking time by zone for credited stop time when GeoZones are configured.',
            1 => ' Most parking time credited in the period maps to the GeoZone «'.$labels[0].'» (see Parking time by zone).',
            default => ' Most parking time credited in the period maps to «'.$labels[0].'» and «'.$labels[1].'» (see Parking time by zone).',
        };

        return match ($coveragePattern) {
            'focused' => 'Relative to the configured operational map grid, driving footprint is narrow.'.$zoneHint,
            'balanced' => 'Driving footprint covers a moderate share of the operational map grid.'.$zoneHint,
            'wide' => 'Driving footprint spans a broad share of the operational map grid.'.$zoneHint,
            default => null,
        };
    }
}
