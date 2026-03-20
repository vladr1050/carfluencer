<?php

namespace App\Filament\Resources\CampaignVehicles\Schemas;

use App\Models\Vehicle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CampaignVehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('campaign_id')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->required(),
                Select::make('vehicle_id')
                    ->label('Vehicle')
                    ->relationship(
                        name: 'vehicle',
                        titleAttribute: 'imei',
                        modifyQueryUsing: fn ($query) => $query->orderBy('brand')->orderBy('model'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Vehicle $record): string => "{$record->brand} {$record->model} — {$record->imei}",
                    )
                    ->searchable(['brand', 'model', 'imei'])
                    ->required(),
                Select::make('placement_size_class')
                    ->label('Ad placement size')
                    ->options([
                        'S' => 'S',
                        'M' => 'M',
                        'L' => 'L',
                        'XL' => 'XL',
                    ])
                    ->required(),
                TextInput::make('agreed_price')
                    ->numeric(),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required()
                    ->default('pending'),
            ]);
    }
}
