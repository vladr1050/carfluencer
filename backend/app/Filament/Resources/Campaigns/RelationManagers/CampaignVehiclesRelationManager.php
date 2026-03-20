<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Models\PlatformSetting;
use App\Models\Vehicle;
use App\Services\Pricing\PlacementPricingService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CampaignVehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'campaignVehicles';

    protected static ?string $title = 'Campaign vehicles & placement';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('vehicle_id')
                    ->label('Vehicle')
                    ->relationship(
                        name: 'vehicle',
                        titleAttribute: 'imei',
                        modifyQueryUsing: fn ($query) => $query->orderBy('brand')->orderBy('model'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Vehicle $record): string => "{$record->brand} {$record->model} — IMEI {$record->imei}",
                    )
                    ->searchable(['brand', 'model', 'imei'])
                    ->required(),
                Select::make('placement_size_class')
                    ->label('Ad placement size')
                    ->options([
                        'S' => 'S — small placement',
                        'M' => 'M — medium placement',
                        'L' => 'L — large placement',
                        'XL' => 'XL — extra large placement',
                    ])
                    ->required(),
                TextInput::make('agreed_price')
                    ->numeric()
                    ->prefix(fn () => \App\Models\PlatformSetting::get('default_currency', 'EUR'))
                    ->helperText('Leave empty to fill from active pricing policy when saved (API/Filament).'),
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('placement_size_class')
            ->columns([
                TextColumn::make('vehicle.brand')
                    ->label('Brand'),
                TextColumn::make('vehicle.model')
                    ->label('Model'),
                TextColumn::make('vehicle.imei')
                    ->label('IMEI')
                    ->searchable(),
                IconColumn::make('vehicle.telemetry_pull_enabled')
                    ->label(__('Sched. pull'))
                    ->boolean(),
                TextColumn::make('vehicle.telemetry_last_success_at')
                    ->label(__('Last sync'))
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('vehicle.telemetry_last_error')
                    ->label(__('Error'))
                    ->limit(24)
                    ->tooltip(fn ($state): ?string => is_string($state) ? $state : null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('placement_size_class')
                    ->label('Placement')
                    ->badge(),
                TextColumn::make('agreed_price')
                    ->label('Agreed price')
                    ->formatStateUsing(function ($state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }

                        $currency = PlatformSetting::get('default_currency', 'EUR');

                        return number_format((float) $state, 2).' '.$currency;
                    }),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (empty($data['agreed_price']) && ! empty($data['placement_size_class'])) {
                            $svc = app(PlacementPricingService::class);
                            $data['agreed_price'] = $svc->resolveBasePrice($data['placement_size_class']);
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (empty($data['agreed_price']) && ! empty($data['placement_size_class'])) {
                            $svc = app(PlacementPricingService::class);
                            $data['agreed_price'] = $svc->resolveBasePrice($data['placement_size_class']);
                        }

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->campaignVehicles()->count();
    }
}
