<?php

namespace Database\Seeders;

use App\Models\AdPlacementPolicy;
use App\Models\AdvertiserProfile;
use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\ContentBlock;
use App\Models\GeoZone;
use App\Models\MediaOwnerProfile;
use App\Models\PlatformSetting;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'platform_commission_percent'],
            ['value' => '12.00']
        );
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'default_agency_commission_percent'],
            ['value' => '5.00']
        );
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'default_currency'],
            ['value' => 'EUR']
        );

        foreach (['S' => 199, 'M' => 349, 'L' => 499, 'XL' => 699] as $size => $price) {
            AdPlacementPolicy::query()->updateOrCreate(
                ['size_class' => $size],
                [
                    'base_price' => $price,
                    'currency' => 'EUR',
                    'active' => true,
                    'description' => "Base monthly rate for {$size} ad placement on a vehicle.",
                ]
            );
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@carfluencer.test'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'company_name' => 'Carfluencer',
                'status' => 'active',
            ]
        );

        $mediaOwner = User::query()->updateOrCreate(
            ['email' => 'media@carguru.test'],
            [
                'name' => 'Carguru Fleet',
                'password' => Hash::make('password'),
                'role' => User::ROLE_MEDIA_OWNER,
                'company_name' => 'Carguru',
                'status' => 'active',
            ]
        );
        MediaOwnerProfile::query()->updateOrCreate(
            ['user_id' => $mediaOwner->id],
            ['company_name' => 'Carguru', 'phone' => '+37060000001']
        );

        $advertiser = User::query()->updateOrCreate(
            ['email' => 'advertiser@brand.test'],
            [
                'name' => 'Brand Marketing',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADVERTISER,
                'company_name' => 'Brand Co',
                'status' => 'active',
            ]
        );
        AdvertiserProfile::query()->updateOrCreate(
            ['user_id' => $advertiser->id],
            [
                'company_name' => 'Brand Co',
                'discount_percent' => '5.00',
                'agency_commission_percent' => '3.00',
            ]
        );

        $vehicle = Vehicle::query()->updateOrCreate(
            ['imei' => '353456789012345'],
            [
                'media_owner_id' => $mediaOwner->id,
                'brand' => 'Tesla',
                'model' => 'Model 3',
                'year' => 2024,
                'color' => 'White',
                'quantity' => 2,
                'status' => 'active',
            ]
        );

        $campaign = Campaign::query()->updateOrCreate(
            ['advertiser_id' => $advertiser->id, 'name' => 'Spring Street Reach'],
            [
                'description' => 'City-wide mobility exposure campaign.',
                'status' => 'active',
                'start_date' => now()->subWeek(),
                'end_date' => now()->addMonth(),
                'created_by_admin' => true,
                'created_by_user_id' => $admin->id,
                'discount_percent' => '5.00',
                'platform_commission_percent' => '12.00',
                'agency_commission_percent' => '5.00',
                'total_price' => '2500.00',
            ]
        );

        CampaignVehicle::query()->updateOrCreate(
            [
                'campaign_id' => $campaign->id,
                'vehicle_id' => $vehicle->id,
            ],
            [
                'placement_size_class' => 'M',
                'agreed_price' => AdPlacementPolicy::basePriceForSize('M'),
                'status' => 'active',
            ]
        );

        PlatformSetting::query()->updateOrCreate(
            ['key' => 'telemetry_incremental_interval_minutes'],
            ['value' => '10']
        );
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'telemetry_build_sessions_at'],
            ['value' => '01:10']
        );
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'telemetry_aggregate_daily_at'],
            ['value' => '01:40']
        );

        GeoZone::query()->updateOrCreate(
            ['code' => 'DEMO-CITY'],
            [
                'name' => 'Demo city core (replace with real geofences)',
                'min_lat' => 54.60,
                'max_lat' => 54.80,
                'min_lng' => 25.00,
                'max_lng' => 25.40,
                'active' => true,
            ]
        );

        ContentBlock::query()->updateOrCreate(
            ['key' => 'pricing_help'],
            [
                'title' => 'How placement pricing works',
                'body' => 'S / M / L / XL describe the size of the advertising placement on the vehicle, not the car category.',
                'active' => true,
            ]
        );

        $this->command?->info('Seeded admin / media_owner / advertiser / vehicle / campaign.');
        $this->command?->info('Admin panel: admin@carfluencer.test / password');
        $this->command?->info('API: media@carguru.test / password | advertiser@brand.test / password');
    }
}
