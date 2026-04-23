<?php

namespace App\Services\Telemetry;

use App\Models\User;

interface DashboardMetricsServiceInterface
{
    /**
     * @return array{
     *     active_campaigns_count:int,
     *     impressions:int|float,
     *     driving_distance_km:int|float,
     *     driving_time_hours:int|float,
     *     parking_time_hours:int|float,
     *     impression_engine?: array{
     *         total_gross_impressions: int|null,
     *         driving_impressions: int|null,
     *         parking_impressions: int|null,
     *         campaigns_with_snapshot: int,
     *         campaigns_in_scope: int,
     *         coverage: string
     *     },
     *     note?:string,
     *     source:string
     * }
     */
    public function advertiserSummary(User $user): array;
}
