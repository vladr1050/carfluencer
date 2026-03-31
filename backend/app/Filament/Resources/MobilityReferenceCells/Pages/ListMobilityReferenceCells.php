<?php

namespace App\Filament\Resources\MobilityReferenceCells\Pages;

use App\Filament\Resources\MobilityReferenceCells\MobilityReferenceCellResource;
use App\Jobs\ImportMobilityReferenceDatasetJob;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMobilityReferenceCells extends ListRecords
{
    protected static string $resource = MobilityReferenceCellResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importDataset')
                ->label('Queue dataset import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->form([
                    TextInput::make('absolute_path')
                        ->label('Absolute path to xlsx (optional)')
                        ->placeholder('Leave empty for default from config'),
                    TextInput::make('data_version')
                        ->default('riga_v1_2025')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $path = trim((string) ($data['absolute_path'] ?? ''));
                    ImportMobilityReferenceDatasetJob::dispatch(
                        $path !== '' ? $path : null,
                        (string) $data['data_version'],
                    );
                    Notification::make()
                        ->title('Import job queued')
                        ->success()
                        ->send();
                }),
        ];
    }
}
