<?php

namespace App\Filament\Resources\ImpressionCoefficients;

use App\Filament\Resources\ImpressionCoefficients\Pages\CreateImpressionCoefficient;
use App\Filament\Resources\ImpressionCoefficients\Pages\EditImpressionCoefficient;
use App\Filament\Resources\ImpressionCoefficients\Pages\ListImpressionCoefficients;
use App\Filament\Resources\ImpressionCoefficients\Schemas\ImpressionCoefficientForm;
use App\Filament\Resources\ImpressionCoefficients\Tables\ImpressionCoefficientsTable;
use App\Models\ImpressionCoefficient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ImpressionCoefficientResource extends Resource
{
    protected static ?string $model = ImpressionCoefficient::class;

    protected static ?string $navigationLabel = 'Coefficients';

    protected static ?string $modelLabel = 'Impression coefficient set';

    protected static ?string $pluralModelLabel = 'Impression coefficients';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'Impression Engine';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return ImpressionCoefficientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImpressionCoefficientsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImpressionCoefficients::route('/'),
            'create' => CreateImpressionCoefficient::route('/create'),
            'edit' => EditImpressionCoefficient::route('/{record}/edit'),
        ];
    }
}
