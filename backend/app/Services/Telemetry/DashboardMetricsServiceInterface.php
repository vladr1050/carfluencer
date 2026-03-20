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
     *     note?:string,
     *     source:string
     * }
     */
    public function advertiserSummary(User $user): array;
}
