<?php

namespace App\Filament\Resources\AdPlacementPolicies;

use App\Filament\Resources\AdPlacementPolicies\Pages\CreateAdPlacementPolicy;
use App\Filament\Resources\AdPlacementPolicies\Pages\EditAdPlacementPolicy;
use App\Filament\Resources\AdPlacementPolicies\Pages\ListAdPlacementPolicies;
use App\Filament\Resources\AdPlacementPolicies\Schemas\AdPlacementPolicyForm;
use App\Filament\Resources\AdPlacementPolicies\Tables\AdPlacementPoliciesTable;
use App\Models\AdPlacementPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdPlacementPolicyResource extends Resource
{
    protected static ?string $model = AdPlacementPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog & settings';

    public static function form(Schema $schema): Schema
    {
        return AdPlacementPolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdPlacementPoliciesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdPlacementPolicies::route('/'),
            'create' => CreateAdPlacementPolicy::route('/create'),
            'edit' => EditAdPlacementPolicy::route('/{record}/edit'),
        ];
    }
}
