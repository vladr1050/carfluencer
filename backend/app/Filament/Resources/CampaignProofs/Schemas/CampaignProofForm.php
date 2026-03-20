<?php

namespace App\Filament\Resources\CampaignProofs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CampaignProofForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('campaign_id')
                    ->relationship('campaign', 'name')
                    ->required(),
                Select::make('vehicle_id')
                    ->relationship('vehicle', 'id')
                    ->required(),
                TextInput::make('uploaded_by_user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('file_path')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('uploaded'),
                Textarea::make('comment')
                    ->columnSpanFull(),
            ]);
    }
}
