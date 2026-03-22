<?php

namespace App\Filament\Pages;

use App\Jobs\SyncTelemetryScopeFromClickHouseJob;
use App\Jobs\SyncVehicleTelemetryFromClickHouseJob;
use App\Models\Campaign;
use App\Models\Vehicle;
use App\Services\Telemetry\TelemetrySchedulerConfig;
use App\Services\Telemetry\TelemetrySyncActivityPresenter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * @property-read Schema $form
 * @property-read Schema $schedulerForm
 */
class TelemetryClickHousePage extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'Telematics';

    protected static ?int $navigationSort = 20;

    public ?array $data = [];

    public ?array $schedulerData = [];

    public static function getNavigationLabel(): string
    {
        return __('ClickHouse & automation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('ClickHouse & automation');
    }

    public function getHeading(): string|Htmlable
    {
        return __('ClickHouse & automation');
    }

    public function getSubheading(): ?string
    {
        return __('Queue imports into PostgreSQL and tune how often the platform pulls and processes telemetry.');
    }

    public function mount(): void
    {
        $this->form->fill([
            'map_scope' => 'vehicle',
            'campaign_id' => null,
            'vehicle_id' => Vehicle::query()->orderByDesc('id')->value('id'),
            'vehicle_ids' => [],
            'sync_mode' => 'incremental',
            'sync_date_from' => now()->subDays(7)->toDateString(),
            'sync_date_to' => now()->toDateString(),
        ]);
        $this->schedulerForm->fill(TelemetrySchedulerConfig::allForForm());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $vehicleOptions = fn () => Vehicle::query()
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (Vehicle $v) => [$v->id => "{$v->brand} {$v->model} — {$v->imei}"]);

        return $schema
            ->components([
                ToggleButtons::make('map_scope')
                    ->label(__('Objects to sync'))
                    ->options([
                        'campaign' => __('Campaign'),
                        'vehicle' => __('One vehicle'),
                        'vehicles' => __('Group of vehicles'),
                    ])
                    ->inline()
                    ->required()
                    ->live(),
                Select::make('campaign_id')
                    ->label(__('Campaign'))
                    ->options(fn () => Campaign::query()->orderBy('name')->limit(500)->pluck('name', 'id')->all())
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('map_scope') === 'campaign')
                    ->required(fn (Get $get): bool => $get('map_scope') === 'campaign'),
                Select::make('vehicle_id')
                    ->label(__('Vehicle'))
                    ->options($vehicleOptions)
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('map_scope') === 'vehicle')
                    ->required(fn (Get $get): bool => $get('map_scope') === 'vehicle'),
                Select::make('vehicle_ids')
                    ->label(__('Vehicles'))
                    ->options($vehicleOptions)
                    ->multiple()
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('map_scope') === 'vehicles')
                    ->required(fn (Get $get): bool => $get('map_scope') === 'vehicles'),
                ToggleButtons::make('sync_mode')
                    ->label(__('ClickHouse load mode'))
                    ->options([
                        'incremental' => __('From last cursor (forward)'),
                        'historical' => __('Historical window'),
                    ])
                    ->inline()
                    ->required()
                    ->live()
                    ->helperText(__('Queue worker required. Then open **Heatmap** in the sidebar and refresh the map.')),
                DatePicker::make('sync_date_from')
                    ->label(__('Sync from (UTC)'))
                    ->native(false)
                    ->visible(fn (Get $get): bool => $get('sync_mode') === 'historical')
                    ->required(fn (Get $get): bool => $get('sync_mode') === 'historical'),
                DatePicker::make('sync_date_to')
                    ->label(__('Sync to (UTC)'))
                    ->native(false)
                    ->visible(fn (Get $get): bool => $get('sync_mode') === 'historical')
                    ->required(fn (Get $get): bool => $get('sync_mode') === 'historical')
                    ->afterOrEqual('sync_date_from'),
            ]);
    }

    public function defaultSchedulerForm(Schema $schema): Schema
    {
        return $schema->statePath('schedulerData');
    }

    public function schedulerForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('incremental_interval_minutes')
                    ->label(__('How often: incremental ClickHouse pull (minutes)'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(1440)
                    ->required()
                    ->helperText(__('`telemetry:scheduler-tick` runs every minute; after this interval, all platform IMEIs are pulled from ClickHouse.')),
                TextInput::make('build_sessions_at')
                    ->label(__('Daily: rebuild stop/driving sessions (UTC, HH:MM)'))
                    ->placeholder('01:10')
                    ->required()
                    ->maxLength(5),
                TextInput::make('aggregate_daily_at')
                    ->label(__('Daily: aggregate impressions (UTC, HH:MM)'))
                    ->placeholder('01:40')
                    ->required()
                    ->maxLength(5),
            ]);
    }

    private function validateObjectScope(array $s): ?string
    {
        $scope = $s['map_scope'] ?? '';
        if ($scope === 'campaign' && empty($s['campaign_id'])) {
            return __('Select a campaign.');
        }
        if ($scope === 'vehicle' && empty($s['vehicle_id'])) {
            return __('Select a vehicle.');
        }
        if ($scope === 'vehicles') {
            $ids = array_filter(array_map('intval', $s['vehicle_ids'] ?? []));
            if ($ids === []) {
                return __('Select at least one vehicle for the group.');
            }
        }

        return null;
    }

    public function queueClickhouseSync(): void
    {
        $s = $this->form->getState();
        if ($msg = $this->validateObjectScope($s)) {
            Notification::make()->title($msg)->danger()->send();

            return;
        }

        $mode = (string) ($s['sync_mode'] ?? 'incremental');
        if ($mode === 'historical') {
            if (empty($s['sync_date_from']) || empty($s['sync_date_to'])) {
                Notification::make()->title(__('Pick sync date range for historical load'))->danger()->send();

                return;
            }
        }

        $scope = (string) ($s['map_scope'] ?? 'vehicle');
        $df = $s['sync_date_from'] ?? null;
        $dt = $s['sync_date_to'] ?? null;

        if ($scope === 'vehicle') {
            $vid = (int) $s['vehicle_id'];
            if ($mode === 'incremental') {
                SyncVehicleTelemetryFromClickHouseJob::dispatch($vid, 'incremental', null, null);
            } else {
                SyncVehicleTelemetryFromClickHouseJob::dispatch($vid, 'historical', $df, $dt);
            }
        } elseif ($scope === 'campaign') {
            SyncTelemetryScopeFromClickHouseJob::dispatch(
                $mode,
                'campaign',
                (int) $s['campaign_id'],
                [],
                $df,
                $dt,
            );
        } else {
            $ids = array_values(array_filter(array_map('intval', $s['vehicle_ids'] ?? [])));
            SyncTelemetryScopeFromClickHouseJob::dispatch(
                $mode,
                'vehicles',
                null,
                $ids,
                $df,
                $dt,
            );
        }

        Notification::make()
            ->title(__('ClickHouse sync queued'))
            ->body(__('After the worker finishes, open **Heatmap** in the sidebar and load the map. Ensure a queue worker is running: `php artisan queue:work`.'))
            ->success()
            ->seconds(12)
            ->send();
    }

    public function saveScheduler(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $data = $this->schedulerForm->getState();
            TelemetrySchedulerConfig::saveFromForm($data);
            $this->commitDatabaseTransaction();
            Notification::make()->title(__('Automation settings saved'))->success()->send();
        } catch (\Throwable $e) {
            $this->rollBackDatabaseTransaction();
            throw $e;
        }
    }

    /**
     * Reference copy for admins: where telemetry comes from and what “Queue sync” does.
     */
    public static function telematicsSyncInfoDescription(): HtmlString
    {
        $p1 = e(__('Raw GPS and device rows live in an external ClickHouse cluster (telematics provider / data warehouse). This platform does not talk to trackers directly — it pulls data over HTTP using credentials and endpoints in config/telemetry.php and .env (TELEMETRY_CLICKHOUSE_*).'));
        $p2 = e(__('Pressing “Queue ClickHouse sync” dispatches a Laravel queue job. A worker (php artisan queue:work or Supervisor/systemd) must be running, otherwise nothing is imported.'));
        $p3 = e(__('The collector upserts into PostgreSQL table device_locations. Rows are matched by device_id = vehicle IMEI — the IMEI in Fleet → Vehicles must match telematics.'));
        $modesTitle = e(__('Sync modes'));
        $liIncremental = e(__('Incremental (from last cursor): only points newer than the saved watermark; for routine catch-up.'));
        $liHistorical = e(__('Historical window: backfill between the chosen UTC dates; does not roll back the incremental cursor. Use for gaps or new vehicles.'));
        $liCampaign = e(__('Campaign or vehicle group: resolves to linked vehicles and queues work for that scope (same idea as the Heatmap target).'));
        $liAfter = e(__('After jobs finish, Heatmap and analytics read PostgreSQL. The automation section below controls how often incremental pull runs and when daily sessions / impressions are rebuilt.'));
        $p4 = e(__('Smoke test (no import): php artisan telemetry:test-clickhouse. Full pipeline: docs/ARCHITECTURE/05_telemetry_pipeline.md.'));

        return new HtmlString(
            <<<HTML
            <div class="space-y-3 text-sm leading-relaxed">
                <p>{$p1}</p>
                <p>{$p2}</p>
                <p>{$p3}</p>
                <p class="font-medium text-gray-950 dark:text-white">{$modesTitle}</p>
                <ul class="list-disc space-y-1 ps-5">
                    <li>{$liIncremental}</li>
                    <li>{$liHistorical}</li>
                    <li>{$liCampaign}</li>
                    <li>{$liAfter}</li>
                </ul>
                <p class="text-xs text-gray-600 dark:text-gray-400">{$p4}</p>
            </div>
            HTML
        );
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Sync activity log'))
                    ->description(__('Live snapshot: per-vehicle sync fields in PostgreSQL, scheduler tick, queue backlog, newest stored GPS row, global ClickHouse cursor, and recent failed telemetry jobs. Auto-refresh every 15 seconds.'))
                    ->schema([
                        View::make('filament.pages.partials.telemetry-sync-activity')
                            ->viewData(fn (): array => app(TelemetrySyncActivityPresenter::class)->forView()),
                    ])
                    ->columns(1),
                Callout::make(__('Telematics sync: how it works'))
                    ->info()
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->description(static::telematicsSyncInfoDescription()),
                Section::make(__('ClickHouse → PostgreSQL'))
                    ->description(__('Choose the same kind of target as on the Heatmap page: campaign, one vehicle, or a group. Then incremental (cursor) or historical window.'))
                    ->schema([
                        Form::make([EmbeddedSchema::make('form')])
                            ->id('clickhouse-sync-form')
                            // Required so <form wire:submit="..."> calls the page method (see scheduler form below).
                            ->livewireSubmitHandler('queueClickhouseSync')
                            ->footer([
                                SchemaActions::make([
                                    Action::make('queueClickhouseSync')
                                        ->label(__('Queue ClickHouse sync'))
                                        ->submit('queueClickhouseSync')
                                        ->color('warning'),
                                ])->alignment(Alignment::Start),
                            ]),
                    ])
                    ->columns(1),
                Section::make(__('Automation: how often & how data is processed'))
                    ->description(__('The incremental tick only pulls vehicles with **Scheduled ClickHouse pull** enabled (Fleet → vehicle). Manual sync from Telematics or Fleet still runs for any vehicle. Also: daily stop-sessions + aggregates.'))
                    ->collapsed()
                    ->schema([
                        Form::make([EmbeddedSchema::make('schedulerForm')])
                            ->id('telematics-scheduler')
                            ->livewireSubmitHandler('saveScheduler')
                            ->footer([
                                SchemaActions::make([
                                    Action::make('saveScheduler')
                                        ->label(__('Save automation settings'))
                                        ->submit('saveScheduler')
                                        ->keyBindings(['mod+s']),
                                ])->alignment(Alignment::Start),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
