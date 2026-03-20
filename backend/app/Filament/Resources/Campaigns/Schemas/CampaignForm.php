<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('advertiser_id')
                    ->label('Advertiser')
                    ->relationship(
                        name: 'advertiser',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->where('role', User::ROLE_ADVERTISER)->where('status', 'active'),
                    )
                    ->searchable()
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required()
                    ->default('draft'),
                DatePicker::make('start_date'),
                DatePicker::make('end_date'),
                Toggle::make('created_by_admin')
                    ->label('Created by admin')
                    ->default(false),
                Select::make('created_by_user_id')
                    ->label('Created by user')
                    ->relationship(
                        name: 'createdBy',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                    )
                    ->searchable()
                    ->nullable(),
                TextInput::make('discount_percent')
                    ->numeric()
                    ->suffix('%'),
                TextInput::make('platform_commission_percent')
                    ->label('Platform commission %')
                    ->numeric()
                    ->suffix('%'),
                TextInput::make('agency_commission_percent')
                    ->label('Agency commission %')
                    ->numeric()
                    ->suffix('%'),
                TextInput::make('total_price')
                    ->numeric(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
