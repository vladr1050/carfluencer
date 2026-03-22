<?php

namespace App\Filament\Pages;

use App\Http\Controllers\Admin\AdminTelemetryHeatmapController;
use App\Models\Campaign;
use App\Models\Vehicle;
use App\Services\Telemetry\AdminHeatmapDataService;
use BackedEnum;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Request;
use Illuminate\Support\Js;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class TelemetryHeatmapPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Telematics';

    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Heatmap');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Heatmap');
    }

    public function getHeading(): string|Htmlable
    {
        return __('Heatmap');
    }

    public function getSubheading(): ?string
    {
        return __('Map from PostgreSQL `device_locations` (after ClickHouse import on the sibling sidebar page).');
    }

    public function mount(): void
    {
        $this->form->fill([
            'map_scope' => 'vehicle',
            'campaign_id' => null,
            'vehicle_id' => Vehicle::query()->orderByDesc('id')->value('id'),
            'vehicle_ids' => [],
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),
            'motion' => 'both',
        ]);
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
                    ->label(__('Objects on the map'))
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
                DatePicker::make('date_from')
                    ->label(__('Period from'))
                    ->native(false),
                DatePicker::make('date_to')
                    ->label(__('Period to'))
                    ->native(false)
                    ->afterOrEqual('date_from'),
                ToggleButtons::make('motion')
                    ->label(__('Point type'))
                    ->options([
                        'moving' => __('Moving'),
                        'stopped' => __('Stopped'),
                        'both' => __('Both'),
                    ])
                    ->inline()
                    ->required()
                    ->helperText(__('Moving / stopped uses speed and ignition (same rules as stop-session builder).')),
            ]);
    }

    /**
     * EmbeddedSchema::make('form') can nest field state under `form` depending on Filament version.
     *
     * @return array<string, mixed>
     */
    private function heatmapFormState(): array
    {
        $s = $this->form->getState();
        if (isset($s['form']) && is_array($s['form']) && array_key_exists('map_scope', $s['form'])) {
            return $s['form'];
        }

        return $s;
    }

    private function scalarForHttpQuery(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function heatmapQueryParams(): array
    {
        $s = $this->heatmapFormState();

        $params = [
            'scope' => $s['map_scope'] ?? 'vehicle',
            'motion' => $s['motion'] ?? 'both',
            'date_from' => array_key_exists('date_from', $s) ? $this->scalarForHttpQuery($s['date_from']) : null,
            'date_to' => array_key_exists('date_to', $s) ? $this->scalarForHttpQuery($s['date_to']) : null,
        ];

        if ($params['scope'] === 'campaign') {
            $params['campaign_id'] = $s['campaign_id'] ?? null;
        }
        if ($params['scope'] === 'vehicle') {
            $params['vehicle_id'] = $s['vehicle_id'] ?? null;
        }
        if ($params['scope'] === 'vehicles') {
            $params['vehicle_ids'] = array_values(array_filter(array_map('intval', $s['vehicle_ids'] ?? [])));
        }

        return array_filter($params, fn ($v) => $v !== null && $v !== '' && $v !== []);
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

    public function loadHeatmap(): void
    {
        $s = $this->heatmapFormState();
        if ($msg = $this->validateObjectScope($s)) {
            Notification::make()->title($msg)->danger()->send();

            return;
        }

        // Never use http_build_query() here: it drops DateTime/Carbon values, so date filters were lost.
        $query = $this->heatmapQueryParams();
        $sub = Request::create(route('internal.admin.telemetry.heatmap-data'), 'GET', $query);
        $sub->setUserResolver(fn () => auth()->user());

        $response = app(AdminTelemetryHeatmapController::class)->data(
            $sub,
            app(AdminHeatmapDataService::class),
        );
        if ($response->getStatusCode() === 422) {
            $json = json_decode($response->getContent(), true);
            Notification::make()->title($json['error'] ?? __('Invalid request'))->danger()->send();

            return;
        }

        $payload = json_decode($response->getContent(), true);
        if (! is_array($payload)) {
            Notification::make()->title(__('Failed to load data'))->danger()->send();

            return;
        }

        $count = count($payload['heatmap']['points'] ?? []);
        $samples = (int) ($payload['heatmap']['metrics']['location_samples'] ?? 0);
        Notification::make()
            ->title(__('Heatmap updated'))
            ->body(__('Clusters: :clusters · GPS samples in range: :samples', ['clusters' => $count, 'samples' => $samples]))
            ->success()
            ->send();

        $this->js('window.renderAdminTelemetryHeatmap('.Js::from($payload).')');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Heatmap & ClickHouse'))
                    ->description(__('Choose campaign, one vehicle, or a group; period and point type (moving / stopped / both). Data comes from PostgreSQL `device_locations` after import from ClickHouse.'))
                    ->schema([
                        Form::make([EmbeddedSchema::make('form')])
                            ->id('heatmap-main-form')
                            ->footer([
                                SchemaActions::make([
                                    Action::make('load')
                                        ->label(__('Load / refresh heatmap'))
                                        ->submit('loadHeatmap')
                                        ->color('primary'),
                                ])->alignment(Alignment::Start),
                            ]),
                    ])
                    ->columns(1),
                SchemaView::make('filament.pages.telemetry-heatmap-body'),
            ]);
    }
}
