<?php

namespace App\Services\ImpressionEngine;

final class CampaignImpressionInputFingerprint
{
    /**
     * @param  list<int>  $vehicleIds
     */
    public static function hash(
        int $campaignId,
        string $dateFrom,
        string $dateTo,
        string $calculationVersion,
        string $mobilityDataVersion,
        string $coefficientsVersion,
        int $telemetrySamplingSeconds,
        string $campaignPrice,
        array $vehicleIds,
    ): string {
        $vehicleIds = array_values(array_unique(array_map('intval', $vehicleIds)));
        sort($vehicleIds);

        $payload = [
            'campaign_id' => $campaignId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'calculation_version' => $calculationVersion,
            'mobility_data_version' => $mobilityDataVersion,
            'coefficients_version' => $coefficientsVersion,
            'telemetry_sampling_seconds' => $telemetrySamplingSeconds,
            'campaign_price' => $campaignPrice,
            'vehicle_ids' => $vehicleIds,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
