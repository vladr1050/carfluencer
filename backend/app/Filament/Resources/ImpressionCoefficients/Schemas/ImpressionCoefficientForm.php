<?php

namespace App\Filament\Resources\ImpressionCoefficients\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImpressionCoefficientForm
{
    public static function configure(Schema $schema): Schema
    {
        $dwellShortMax = (int) config('impression_engine.calculation.dwell_short_max_seconds', 899);
        $dwellMedMax = (int) config('impression_engine.calculation.dwell_medium_max_seconds', 3600);

        return $schema
            ->components([
                Section::make('Version')
                    ->description('Stored on each impression snapshot so you can tell which coefficient set was used. Change the version string whenever you materially change these numbers.')
                    ->schema([
                        TextInput::make('version')
                            ->required()
                            ->maxLength(32)
                            ->helperText('Bump when changing coefficients (tracked in impression snapshots).'),
                    ])
                    ->columns(1),

                Section::make('Audience weights — driving (moving vehicle)')
                    ->description(
                        'Used only when telemetry speed is above the driving threshold (see IMPRESSION_ENGINE_DRIVING_SPEED_THRESHOLD). '.
                        'Hourly “flows” come from mobility: vehicle AADT and pedestrian daily counts are spread across 24h, then peak hours are boosted by hourly_peak_factor. '.
                        'Effective audience per hour = vehicle_hourly × vehicle_visibility_share + pedestrian_hourly × pedestrian_visibility_share. '.
                        'Impressions scale with (exposure_seconds / 3600) × audience × speed_factor.'
                    )
                    ->schema([
                        TextInput::make('vehicle_visibility_share')
                            ->numeric()
                            ->required()
                            ->step(0.000001)
                            ->helperText('Share of passing vehicles that are assumed to notice the ad creative (0–1). Multiplies the vehicle flow component in driving mode.'),
                        TextInput::make('pedestrian_visibility_share')
                            ->numeric()
                            ->required()
                            ->step(0.000001)
                            ->helperText('Share of pedestrians that are assumed to notice the ad while the vehicle is moving (0–1). Multiplies the pedestrian flow component in driving mode.'),
                    ])
                    ->columns(2),

                Section::make('Audience weights — parking (stationary vehicle)')
                    ->description(
                        'Used when speed is at or below the driving threshold. Audience is built from the same hourly flows, but with different roles: '.
                        'pedestrian_hourly × pedestrian_parking_share + vehicle_hourly × roadside_vehicle_share. '.
                        'Then multiplied by a dwell factor based on how long the vehicle stayed in that hour bucket.'
                    )
                    ->schema([
                        TextInput::make('pedestrian_parking_share')
                            ->numeric()
                            ->required()
                            ->step(0.000001)
                            ->helperText('How much of the pedestrian flow counts toward “parking” exposure — e.g. people walking past a stopped ad surface (0–1).'),
                        TextInput::make('roadside_vehicle_share')
                            ->numeric()
                            ->required()
                            ->step(0.000001)
                            ->helperText('How much of the vehicle flow counts as roadside audience while parked (other drivers passing the stationary vehicle) (0–1).'),
                    ])
                    ->columns(2),

                Section::make('Speed multipliers (driving mode only)')
                    ->description(
                        'Picks one row from average GPS speed (km/h) inside the hourly H3 bucket. Lower speed ⇒ usually higher multiplier (more time to read). '.
                        'Bands: < 30, 30–50, 50–70, ≥ 70.'
                    )
                    ->schema([
                        TextInput::make('speed_factor_low')
                            ->numeric()
                            ->required()
                            ->step(0.0001)
                            ->helperText('Applied when average speed in the bucket is strictly below 30 km/h.'),
                        TextInput::make('speed_factor_medium')
                            ->numeric()
                            ->required()
                            ->step(0.0001)
                            ->helperText('Applied when average speed is ≥ 30 and < 50 km/h.'),
                        TextInput::make('speed_factor_high')
                            ->numeric()
                            ->required()
                            ->step(0.0001)
                            ->helperText('Applied when average speed is ≥ 50 and < 70 km/h.'),
                        TextInput::make('speed_factor_very_high')
                            ->numeric()
                            ->required()
                            ->step(0.0001)
                            ->helperText('Applied when average speed is ≥ 70 km/h.'),
                    ])
                    ->columns(2),

                Section::make('Dwell multipliers (parking mode only)')
                    ->description(
                        'Total exposure time in the hourly bucket is approximated as point_count × TELEMETRY_ASSUMED_SECONDS_PER_POINT. '.
                        "Short / medium / long tiers use config thresholds: ≤ {$dwellShortMax}s, ≤ {$dwellMedMax}s, above that. ".
                        'Only the parking branch uses these; driving uses speed factors instead.'
                    )
                    ->schema([
                        TextInput::make('dwell_factor_short')
                            ->numeric()
                            ->required()
                            ->step(0.0001)
                            ->helperText("Short dwell: exposure seconds in the bucket ≤ {$dwellShortMax}s (config: dwell_short_max_seconds)."),
                        TextInput::make('dwell_factor_medium')
                            ->numeric()
                            ->required()
                            ->step(0.0001)
                            ->helperText("Medium dwell: exposure seconds ≤ {$dwellMedMax}s (config: dwell_medium_max_seconds)."),
                        TextInput::make('dwell_factor_long')
                            ->numeric()
                            ->required()
                            ->step(0.0001)
                            ->helperText("Long dwell: exposure seconds > {$dwellMedMax}s."),
                    ])
                    ->columns(2),
            ]);
    }
}
