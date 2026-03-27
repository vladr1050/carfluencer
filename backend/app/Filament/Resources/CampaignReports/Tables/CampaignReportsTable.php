<?php

namespace App\Filament\Resources\CampaignReports\Tables;

use App\Enums\CampaignReportStatus;
use App\Jobs\GenerateCampaignReportJob;
use App\Models\CampaignReport;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class CampaignReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('campaign.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date_from')
                    ->date()
                    ->sortable(),
                TextColumn::make('date_to')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (CampaignReportStatus $state): string => $state->value)
                    ->color(fn (CampaignReportStatus $state): string => match ($state) {
                        CampaignReportStatus::Done => 'success',
                        CampaignReportStatus::Failed => 'danger',
                        CampaignReportStatus::Processing => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('generated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('createdBy.name')
                    ->label('Created by')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error_message')
                    ->wrap()
                    ->limit(120)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (CampaignReport $record): bool => $record->status === CampaignReportStatus::Done
                        && filled($record->file_path))
                    ->action(function (CampaignReport $record) {
                        $path = Storage::disk('local')->path($record->file_path);

                        return response()->download($path, $record->file_name ?? 'report.pdf');
                    }),
                Action::make('regenerate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (CampaignReport $record): bool => in_array($record->status, [
                        CampaignReportStatus::Done,
                        CampaignReportStatus::Failed,
                    ], true))
                    ->action(function (CampaignReport $record): void {
                        $record->update([
                            'status' => CampaignReportStatus::Queued,
                            'error_message' => null,
                            'file_path' => null,
                            'file_name' => null,
                            'file_size' => null,
                            'generated_at' => null,
                            'report_data_json' => null,
                        ]);
                        dispatch(new GenerateCampaignReportJob($record->id));
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
