<?php

namespace App\Filament\Resources\ImpressionCoefficients\Pages;

use App\Filament\Resources\ImpressionCoefficients\ImpressionCoefficientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImpressionCoefficients extends ListRecords
{
    protected static string $resource = ImpressionCoefficientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
