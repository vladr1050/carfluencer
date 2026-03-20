<?php

namespace App\Filament\Widgets;

use App\Models\AdPlacementPolicy;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Media owners', (string) User::query()->where('role', User::ROLE_MEDIA_OWNER)->count()),
            Stat::make('Advertisers', (string) User::query()->where('role', User::ROLE_ADVERTISER)->count()),
            Stat::make('Vehicles', (string) Vehicle::query()->count()),
            Stat::make('Campaigns', (string) Campaign::query()->count()),
            Stat::make('Active campaigns', (string) Campaign::query()->where('status', 'active')->count()),
            Stat::make('Active placement policies', (string) AdPlacementPolicy::query()->where('active', true)->count()),
        ];
    }
}
