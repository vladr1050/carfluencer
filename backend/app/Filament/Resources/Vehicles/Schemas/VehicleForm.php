<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Models\User;
use App\Models\Vehicle;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('media_owner_id')
                    ->label('Media owner')
                    ->relationship(
                        name: 'mediaOwner',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->where('role', User::ROLE_MEDIA_OWNER)->where('status', 'active'),
                    )
                    ->searchable()
                    ->required(),
                TextInput::make('brand')
                    ->required(),
                TextInput::make('model')
                    ->required(),
                TextInput::make('year')
                    ->numeric(),
                Select::make('color_key')
                    ->label(__('Body color'))
                    ->options(config('vehicle.colors', []))
                    ->searchable()
                    ->placeholder(__('Select…')),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(1),
                FileUpload::make('image_path')
                    ->label('Vehicle image')
                    ->image()
                    ->disk('public')
                    ->directory('vehicles')
                    ->visibility('public'),
                TextInput::make('imei')
                    ->required(),
                Toggle::make('telemetry_pull_enabled')
                    ->label(__('Scheduled ClickHouse pull'))
                    ->helperText(__('When off, this vehicle is skipped by the platform telemetry scheduler (manual sync from Fleet / Telematics still works).'))
                    ->default(true),
                Select::make('status')
                    ->label(__('Fleet status'))
                    ->options(config('vehicle.fleet_statuses', []))
                    ->required()
                    ->default(Vehicle::STATUS_ACTIVE)
                    ->helperText(__('“In campaign” is usually set automatically when the vehicle is linked to a campaign.')),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
