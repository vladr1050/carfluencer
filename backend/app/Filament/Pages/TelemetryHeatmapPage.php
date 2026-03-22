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
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Js;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class TelemetryHeatmapPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Telematics';

    protected static ?int $navigationSort = 10;

    /**
     * Syncs heatmap filters to the browser URL as form[...] (e.g. form[campaign_id]=12).
     * vehicle_ids omitted — multi-select can be huge; use "Load / refresh" for groups.
     */
    #[Url(as: 'form', history: false, keep: true, except: ['vehicle_ids'])]
    public array $data = [];

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
        $defaults = [
            'map_scope' => 'vehicle',
            'campaign_id' => null,
            'vehicle_id' => Vehicle::query()->orderByDesc('id')->value('id'),
            'vehicle_ids' => [],
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),
            'motion' => 'both',
        ];

        $defaults = $this->mergeHeatmapDefaultsFromRequest($defaults);
        $defaults = $this->normalizeHeatmapScopeFields($defaults);

        // PHP parses form.x=y as query key form_x=y (underscore). That coexists with form[key]=… and confuses
        // browsers/bookmarks. Strip legacy aliases once by redirecting to canonical ?form[...]= only.
        if ($this->shouldRedirectToCanonicalHeatmapQuery()) {
            $this->redirectToCanonicalHeatmapQuery($defaults);

            return;
        }

        // #[Url] may have hydrated partial $this->data from ?form[...]= before mount; merge defaults then sync back.
        $this->data = array_replace($defaults, array_filter(
            $this->data,
            static fn (mixed $v): bool => $v !== null && $v !== '' && (! is_array($v) || $v !== [])
        ));
        $this->data = $this->normalizeHeatmapScopeFields($this->data);

        $this->form->fill($this->data);

        if (request()->boolean('no_heatmap_autoload')) {
            return;
        }

        // Use $this->data, not getState(): full form validation can throw when e.g. campaign_id is empty.
        $heatmapFilterError = $this->validateHeatmapFilters($this->data);
        if ($heatmapFilterError === null) {
            $this->loadHeatmap();
        } else {
            Log::warning('telemetry_heatmap_autoload_skipped', [
                'reason' => $heatmapFilterError,
                'map_scope' => $this->data['map_scope'] ?? null,
                'has_campaign_id' => ! empty($this->data['campaign_id']),
                'has_vehicle_id' => ! empty($this->data['vehicle_id']),
            ]);

            if (($this->data['map_scope'] ?? '') === 'campaign' && empty($this->data['campaign_id'])) {
                Notification::make()
                    ->title(__('Choose a campaign'))
                    ->body(__('Campaign is selected but no campaign is chosen. Pick a campaign in the form or add form[campaign_id]=… to the URL, then use “Load / refresh”. A leftover vehicle_id in the link is ignored in campaign mode.'))
                    ->warning()
                    ->send();
            }
        }
    }

    /**
     * Merge ?data[...]= and legacy ?form.map_scope= style query keys into form defaults.
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function mergeHeatmapDefaultsFromRequest(array $defaults): array
    {
        $formNested = request()->query('form');
        $hasBracketForm = is_array($formNested);

        if ($hasBracketForm) {
            $defaults = $this->applyHeatmapKeyValuePairsToDefaults($defaults, $formNested);
        }

        $data = request()->query('data');
        if (is_array($data)) {
            $defaults = $this->applyHeatmapKeyValuePairsToDefaults($defaults, $data);
        }

        // Do NOT merge flat form.map_scope / form.motion when form[...] exists: PHP keeps them as separate
        // keys, and applying both produced campaign scope without campaign_id while vehicle_id stayed set.
        if (! $hasBracketForm) {
            // Dotted form.* becomes form_* in PHP (see shouldRedirectToCanonicalHeatmapQuery).
            $flatAliases = [
                'form.map_scope' => 'map_scope',
                'form_map_scope' => 'map_scope',
                'form.motion' => 'motion',
                'form_motion' => 'motion',
                'form.campaign_id' => 'campaign_id',
                'form_campaign_id' => 'campaign_id',
                'form.vehicle_id' => 'vehicle_id',
                'form_vehicle_id' => 'vehicle_id',
                'form.date_from' => 'date_from',
                'form_date_from' => 'date_from',
                'form.date_to' => 'date_to',
                'form_date_to' => 'date_to',
            ];
            foreach ($flatAliases as $queryKey => $field) {
                if (! request()->has($queryKey)) {
                    continue;
                }
                $v = request()->query($queryKey);
                if ($field === 'campaign_id' || $field === 'vehicle_id') {
                    $defaults[$field] = $v === null || $v === '' ? null : (int) $v;
                } elseif ($field === 'date_from' || $field === 'date_to') {
                    $defaults[$field] = $this->normalizeHeatmapDateInput($v);
                } else {
                    $defaults[$field] = $v;
                }
            }
        }

        return $defaults;
    }

    /**
     * Drop IDs that do not apply to the current map_scope (stale URL/bookmark fields).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeHeatmapScopeFields(array $row): array
    {
        $scope = $row['map_scope'] ?? '';

        if ($scope === 'campaign') {
            $row['vehicle_id'] = null;
            $row['vehicle_ids'] = [];
        } elseif ($scope === 'vehicle') {
            $row['campaign_id'] = null;
            $row['vehicle_ids'] = [];
        } elseif ($scope === 'vehicles') {
            $row['campaign_id'] = null;
            $row['vehicle_id'] = null;
        }

        return $row;
    }

    /**
     * Detects form_map_scope / form_motion etc. alongside form[...] (PHP's encoding of form.scope).
     */
    private function shouldRedirectToCanonicalHeatmapQuery(): bool
    {
        if (! is_array(request()->query('form'))) {
            return false;
        }

        return $this->requestHasLegacyPhpFormDotAliases();
    }

    private function requestHasLegacyPhpFormDotAliases(): bool
    {
        foreach (array_keys(request()->query()) as $key) {
            $key = (string) $key;
            if (preg_match('/^form_(map_scope|motion|campaign_id|vehicle_id|date_from|date_to)$/', $key) === 1) {
                return true;
            }
            if ($key === 'form.map_scope' || $key === 'form.motion' || $key === 'form.campaign_id' || $key === 'form.vehicle_id' || $key === 'form.date_from' || $key === 'form.date_to') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $mergedDefaults
     */
    private function redirectToCanonicalHeatmapQuery(array $mergedDefaults): void
    {
        $form = $this->onlyHeatmapUrlPersistedKeys($mergedDefaults);
        $url = request()->url();
        if ($form !== []) {
            $url .= '?'.http_build_query(['form' => $form]);
        }

        $this->redirect($url, navigate: false);
    }

    /**
     * Keys persisted in #[Url] (except vehicle_ids).
     *
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function onlyHeatmapUrlPersistedKeys(array $merged): array
    {
        $out = [];
        foreach (['map_scope', 'campaign_id', 'vehicle_id', 'date_from', 'date_to', 'motion'] as $k) {
            if (! array_key_exists($k, $merged)) {
                continue;
            }
            $v = $merged[$k];
            if ($v === null || $v === '' || (is_array($v) && $v === [])) {
                continue;
            }
            if ($k === 'date_from' || $k === 'date_to') {
                $v = $this->normalizeHeatmapDateInput($v);
            }
            if ($v === null || $v === '') {
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $pairs
     * @return array<string, mixed>
     */
    private function applyHeatmapKeyValuePairsToDefaults(array $defaults, array $pairs): array
    {
        foreach ([
            'map_scope',
            'campaign_id',
            'vehicle_id',
            'vehicle_ids',
            'date_from',
            'date_to',
            'motion',
        ] as $key) {
            if (! array_key_exists($key, $pairs)) {
                continue;
            }
            $v = $pairs[$key];
            if ($key === 'campaign_id' || $key === 'vehicle_id') {
                $defaults[$key] = $v === null || $v === '' ? null : (int) $v;
            } elseif ($key === 'vehicle_ids') {
                $defaults[$key] = is_array($v)
                    ? array_values(array_filter(array_map('intval', $v)))
                    : array_values(array_filter([(int) $v]));
            } elseif ($key === 'date_from' || $key === 'date_to') {
                $defaults[$key] = $this->normalizeHeatmapDateInput($v);
            } else {
                $defaults[$key] = $v;
            }
        }

        return $defaults;
    }

    private function normalizeHeatmapDateInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }
        if (is_string($value)) {
            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Throwable) {
                return strlen($value) >= 10 ? substr($value, 0, 10) : $value;
            }
        }

        return null;
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
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if ($state === 'campaign') {
                            $set('vehicle_id', null);
                            $set('vehicle_ids', []);
                        } elseif ($state === 'vehicle') {
                            $set('campaign_id', null);
                            $set('vehicle_ids', []);
                        } elseif ($state === 'vehicles') {
                            $set('campaign_id', null);
                            $set('vehicle_id', null);
                        }
                    }),
                Select::make('campaign_id')
                    ->label(__('Campaign'))
                    ->options(fn () => Campaign::query()->orderBy('name')->limit(500)->pluck('name', 'id')->all())
                    ->searchable()
                    ->live()
                    ->visible(fn (Get $get): bool => $get('map_scope') === 'campaign')
                    ->required(fn (Get $get): bool => $get('map_scope') === 'campaign'),
                Select::make('vehicle_id')
                    ->label(__('Vehicle'))
                    ->options($vehicleOptions)
                    ->searchable()
                    ->live()
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

    /**
     * Lightweight checks (no full Filament getState / validate).
     *
     * @param  array<string, mixed>  $s
     */
    private function validateHeatmapFilters(array $s): ?string
    {
        if (isset($s['form']) && is_array($s['form']) && array_key_exists('map_scope', $s['form'])) {
            $s = $s['form'];
        }

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
        if ($msg = $this->validateHeatmapFilters($s)) {
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
                    ->description(__('Choose campaign, one vehicle, or a group; period and point type (moving / stopped / both). Data comes from PostgreSQL device_locations after ClickHouse import. OSM base map loads immediately; the coloured heat layer loads after "Load / refresh", or automatically on page open when filters are complete. URLs: use only form[…]=… . PHP rewrites form.map_scope into form_map_scope=…; mixing that with form[map_scope]=… breaks filters — the page will redirect once to a clean form[…] URL. Campaign mode needs form[campaign_id] or a selected campaign.'))
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
