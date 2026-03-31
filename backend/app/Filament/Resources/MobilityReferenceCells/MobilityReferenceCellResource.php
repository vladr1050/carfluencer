<?php

namespace App\Filament\Resources\MobilityReferenceCells;

use App\Filament\Resources\MobilityReferenceCells\Pages\ListMobilityReferenceCells;
use App\Filament\Resources\MobilityReferenceCells\Schemas\MobilityReferenceCellForm;
use App\Filament\Resources\MobilityReferenceCells\Tables\MobilityReferenceCellsTable;
use App\Models\MobilityReferenceCell;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MobilityReferenceCellResource extends Resource
{
    protected static ?string $model = MobilityReferenceCell::class;

    protected static ?string $navigationLabel = 'Mobility cells';

    protected static ?string $modelLabel = 'Mobility reference cell';

    protected static ?string $pluralModelLabel = 'Mobility reference cells';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'Impression Engine';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return MobilityReferenceCellForm::configure($schema);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return MobilityReferenceCellsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMobilityReferenceCells::route('/'),
        ];
    }
}
