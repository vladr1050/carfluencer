<?php

namespace App\Services\ImpressionEngine;

use App\Models\CampaignImpressionStat;

/**
 * Resolves {@see CampaignImpressionStat} rows produced by {@see CampaignImpressionCalculationService}
 * (same rules as {@see AdvertiserCampaignImpressionController}).
 */
final class CampaignImpressionSnapshotResolver
{
    public function findLatestDone(int $campaignId, string $dateFromYmd, string $dateToYmd): ?CampaignImpressionStat
    {
        return CampaignImpressionStat::query()
            ->where('campaign_id', $campaignId)
            ->whereDate('date_from', $dateFromYmd)
            ->whereDate('date_to', $dateToYmd)
            ->where('status', CampaignImpressionStat::STATUS_DONE)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{
     *     total_gross_impressions: int,
     *     driving_impressions: int,
     *     parking_impressions: int,
     *     date_from: string,
     *     date_to: string
     * }|null
     */
    public function summaryBlock(?CampaignImpressionStat $stat): ?array
    {
        if ($stat === null) {
            return null;
        }

        return [
            'total_gross_impressions' => (int) $stat->total_gross_impressions,
            'driving_impressions' => (int) $stat->driving_impressions,
            'parking_impressions' => (int) $stat->parking_impressions,
            'date_from' => $stat->date_from->format('Y-m-d'),
            'date_to' => $stat->date_to->format('Y-m-d'),
        ];
    }
}
