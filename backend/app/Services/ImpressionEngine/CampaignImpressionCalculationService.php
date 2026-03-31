<?php

namespace App\Services\ImpressionEngine;

use App\Models\Campaign;
use App\Models\CampaignImpressionStat;
use App\Models\ImpressionCoefficient;
use App\Models\MobilityReferenceCell;
use App\Services\ImpressionEngine\Contracts\H3IndexerInterface;
use RuntimeException;

final class CampaignImpressionCalculationService
{
    public function __construct(
        private readonly H3IndexerInterface $h3,
        private readonly ImpressionExposureAggregatorService $exposureAggregator,
    ) {}

    /**
     * @param  list<int>  $vehicleIds  Resolved campaign vehicle ids (for fingerprint).
     */
    public function calculate(
        Campaign $campaign,
        string $dateFrom,
        string $dateTo,
        string $mobilityDataVersion,
        bool $forceRecalculate,
        array $vehicleIds,
    ): CampaignImpressionStat {
        $coeff = ImpressionCoefficient::query()->orderByDesc('id')->first();
        if ($coeff === null) {
            throw new RuntimeException('No impression_coefficients row; run migrations.');
        }

        $calcVersion = (string) config('impression_engine.calculation.calculation_version', 'v1.0');
        $sampling = (int) config('impression_engine.calculation.telemetry_assumed_seconds_per_point', 10);
        $priceStr = $this->priceString($campaign);
        $priceNum = (float) ($campaign->total_price ?? 0);

        $fingerprint = CampaignImpressionInputFingerprint::hash(
            $campaign->id,
            $dateFrom,
            $dateTo,
            $calcVersion,
            $mobilityDataVersion,
            (string) $coeff->version,
            $sampling,
            $priceStr,
            $vehicleIds,
        );

        if (! $forceRecalculate) {
            $existing = CampaignImpressionStat::query()->where('input_fingerprint', $fingerprint)->first();
            if ($existing !== null) {
                return $existing;
            }
        } else {
            CampaignImpressionStat::query()->where('input_fingerprint', $fingerprint)->delete();
        }

        $mobilityRows = MobilityReferenceCell::query()
            ->where('data_version', $mobilityDataVersion)
            ->get();

        if ($mobilityRows->isEmpty()) {
            throw new RuntimeException("No mobility_reference_cells for data_version [{$mobilityDataVersion}]. Import dataset first.");
        }

        /** @var array<string, MobilityReferenceCell> $direct */
        $direct = $mobilityRows->keyBy('cell_id')->all();
        $spatial = new MobilitySpatialIndex($mobilityRows);
        $maxM = (float) config('impression_engine.calculation.mobility_fallback_max_meters', 300);

        $buckets = $this->exposureAggregator->aggregate($campaign, $dateFrom, $dateTo, $sampling);

        if (filter_var(config('impression_engine.calculation.store_exposure_hourly', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->exposureAggregator->persist($campaign, $dateFrom, $dateTo, $buckets, $sampling);
        }

        $drivingImp = 0.0;
        $parkingImp = 0.0;
        $matchedDirect = 0;
        $matchedFallback = 0;
        $unmatched = 0;

        foreach ($buckets as $b) {
            $cellId = $b['cell_id'];
            $hour = $b['hour'];
            $exposureSeconds = $b['point_count'] * $sampling;
            $avgSpeed = $b['point_count'] > 0 ? $b['sum_speed'] / $b['point_count'] : 0.0;

            $mobilityArr = null;
            if (isset($direct[$cellId])) {
                $c = $direct[$cellId];
                $mobilityArr = [
                    'vehicle_aadt' => $c->vehicle_aadt,
                    'pedestrian_daily' => $c->pedestrian_daily,
                    'hourly_peak_factor' => $c->hourly_peak_factor,
                ];
                $matchedDirect++;
            } else {
                $geo = $this->h3->cellIdToLatLng($cellId);
                $near = $spatial->nearestWithin($geo['lat'], $geo['lng'], $maxM);
                if ($near !== null) {
                    $mobilityArr = $near['row'];
                    $matchedFallback++;
                } else {
                    $unmatched++;

                    continue;
                }
            }

            if ($b['mode'] === 'driving') {
                $drivingImp += ImpressionFormula::drivingImpressions(
                    $exposureSeconds,
                    $hour,
                    (float) $avgSpeed,
                    $mobilityArr,
                    $coeff
                );
            } else {
                $parkingImp += ImpressionFormula::parkingImpressions(
                    $exposureSeconds,
                    $hour,
                    $mobilityArr,
                    $coeff
                );
            }
        }

        $drivingRounded = (int) round($drivingImp);
        $parkingRounded = (int) round($parkingImp);
        $total = $drivingRounded + $parkingRounded;
        $cpm = $total > 0 ? round($priceNum / ($total / 1000.0), 4) : null;

        return CampaignImpressionStat::query()->create([
            'campaign_id' => $campaign->id,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'vehicles_count' => count(array_unique(array_map('intval', $vehicleIds))),
            'driving_impressions' => $drivingRounded,
            'parking_impressions' => $parkingRounded,
            'total_gross_impressions' => $total,
            'campaign_price' => $priceNum,
            'cpm' => $cpm,
            'calculation_version' => $calcVersion,
            'mobility_data_version' => $mobilityDataVersion,
            'coefficients_version' => (string) $coeff->version,
            'telemetry_sampling_seconds' => $sampling,
            'input_fingerprint' => $fingerprint,
            'matched_direct_count' => $matchedDirect,
            'matched_fallback_count' => $matchedFallback,
            'unmatched_count' => $unmatched,
        ]);
    }

    private function priceString(Campaign $campaign): string
    {
        $p = $campaign->total_price;

        return $p === null ? '0' : (string) $p;
    }
}
