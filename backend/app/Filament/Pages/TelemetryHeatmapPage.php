<?php

namespace App\Filament\Pages;

use App\Models\Campaign;
use App\Models\Vehicle;
use App\Services\Telemetry\TelemetryHeatmapConfig;
use BackedEnum;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Js;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * @property-read Schema $form
 * @property-read Schema $heatmapSettingsForm
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

    /** @var array<string, mixed> */
    public ?array $heatmapSettings = [];

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
            'motion' => 'moving',
            'heatmap_normalization' => 'p95',
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
        $this->heatmapSettingsForm->fill(TelemetryHeatmapConfig::allForForm());

        if (request()->boolean('no_heatmap_autoload')) {
            return;
        }

        // Use heatmapFormState(), not $this->form->getState(): getState() runs full Filament validate()
        // and can block or throw on hidden/required/date rules while the API only needs a few fields.
        $heatmapState = $this->heatmapFormState();
        $heatmapFilterError = $this->validateHeatmapFilters($heatmapState);
        if ($heatmapFilterError === null) {
            $this->loadHeatmap();
        } else {
            Log::warning('telemetry_heatmap_autoload_skipped', [
                'reason' => $heatmapFilterError,
                'map_scope' => $heatmapState['map_scope'] ?? null,
                'has_campaign_id' => ! empty($heatmapState['campaign_id']),
                'has_vehicle_id' => ! empty($heatmapState['vehicle_id']),
            ]);

            if (($heatmapState['map_scope'] ?? '') === 'campaign' && empty($heatmapState['campaign_id'])) {
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
                'form.heatmap_normalization' => 'heatmap_normalization',
                'form_heatmap_normalization' => 'heatmap_normalization',
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

        if (($row['motion'] ?? '') === 'both') {
            $row['motion'] = 'moving';
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
            'heatmap_normalization',
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

    public function defaultHeatmapSettingsForm(Schema $schema): Schema
    {
        return $schema->statePath('heatmapSettings');
    }

    public function heatmapSettingsForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('intensity_gamma')
                    ->label(__('Heatmap peak emphasis (γ)'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(3)
                    ->step(0.05)
                    ->required()
                    ->helperText(
                        __(
                            '1 = linear (equal scaling). Higher values make the densest map cells hotter; mid-density fades. Applies to this admin heatmap and the advertiser portal API. When saved, overrides TELEMETRY_HEATMAP_INTENSITY_GAMMA from .env. If many grid cells have similar point counts, the map already looks uniform — changing γ has little effect. After save we reload the map when filters are valid; otherwise use “Load / refresh heatmap”.'
                        )
                    ),
            ]);
    }

    public function saveHeatmapDisplaySettings(): void
    {
        try {
            $data = $this->heatmapSettingsForm->getState();
            $fallback = is_array($this->heatmapSettings) ? $this->heatmapSettings : [];
            if (! array_key_exists('intensity_gamma', $data) || $data['intensity_gamma'] === null || $data['intensity_gamma'] === '') {
                $data['intensity_gamma'] = $fallback['intensity_gamma'] ?? 1.55;
            }
            TelemetryHeatmapConfig::saveFromForm($data);
            $gamma = TelemetryHeatmapConfig::intensityGamma();

            $heatmapState = $this->heatmapFormState();
            $refreshed = false;
            if ($this->validateHeatmapFilters($heatmapState) === null) {
                $this->loadHeatmap(withSuccessToast: false);
                $refreshed = true;
            }

            Notification::make()
                ->title(__('Heatmap display settings saved'))
                ->body(
                    $refreshed
                        ? __('Map redrawn with γ=:g. Tip: if cells have similar point counts, the picture barely changes.', ['g' => $gamma])
                        : __('γ=:g saved. Set filters above and click “Load / refresh heatmap” to update the map.', ['g' => $gamma])
                )
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('Could not save settings'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
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
                        'moving' => __('Moving (driving)'),
                        'stopped' => __('Stopped (parking)'),
                    ])
                    ->inline()
                    ->required()
                    ->helperText(__('One layer at a time: driving vs parking density. Same speed/ignition rules as stop-session builder. Map loads data for the current viewport and zoom from daily rollups when available.')),
                Select::make('heatmap_normalization')
                    ->label(__('Intensity normalization'))
                    ->options([
                        'p95' => __('Percentile p95 (recommended)'),
                        'p99' => __('Percentile p99'),
                        'max' => __('Absolute max bucket'),
                    ])
                    ->native(false)
                    ->helperText(__('p95/p99 cap the scale so one hotspot does not flatten mid-density areas. Values above the cap map to peak color.')),
            ]);
    }

    /**
     * Heatmap filters for API calls: read Livewire `data` directly (statePath is `data`).
     *
     * Do not use {@see Schema::getState()}: it runs full schema validation and can fail or omit values
     * when fields are hidden — advertiser JSON API does not have that layer.
     *
     * @return array<string, mixed>
     */
    private function heatmapFormState(): array
    {
        $raw = is_array($this->data) ? $this->data : [];
        if (isset($raw['form']) && is_array($raw['form']) && array_key_exists('map_scope', $raw['form'])) {
            return $this->normalizeHeatmapScopeFields($raw['form']);
        }

        return $this->normalizeHeatmapScopeFields($raw);
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
            'motion' => ($s['motion'] ?? 'both') === 'both' ? 'moving' : ($s['motion'] ?? 'moving'),
            'normalization' => $s['heatmap_normalization'] ?? 'p95',
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

    /**
     * @param  bool  $withSuccessToast  Set false when chaining after another success notification (e.g. save γ).
     */
    public function loadHeatmap(bool $withSuccessToast = true): void
    {
        $s = $this->heatmapFormState();
        if ($msg = $this->validateHeatmapFilters($s)) {
            Notification::make()->title($msg)->danger()->send();

            return;
        }

        $query = $this->heatmapQueryParams();
        $url = route('internal.admin.telemetry.heatmap-data');
        $this->js(
            'window.__adminHeatmapBaseQuery = '.Js::from($query).';'
            .'window.__adminHeatmapDataUrl = '.Js::from($url).';'
            .'window.__adminHeatmapShowToast = '.Js::from($withSuccessToast).';'
            .'if (typeof window.adminHeatmapFetchWithViewport === "function") { window.adminHeatmapFetchWithViewport(); }'
        );
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Heatmap & ClickHouse'))
                    ->description(__('Choose campaign, one vehicle, or a group; period; moving (driving) or stopped (parking) — one layer at a time. The map requests heatmap data for the visible bounds and zoom (daily rollups when populated). OSM base map loads immediately; use “Load / refresh heatmap” or open the page with complete filters. URLs: use only form[…]=… .'))
                    ->schema([
                        Form::make([EmbeddedSchema::make('form')])
                            ->id('heatmap-main-form')
                            ->footer([
                                SchemaActions::make([
                                    Action::make('load')
                                        ->label(__('Load / refresh heatmap'))
                                        ->action('loadHeatmap')
                                        ->color('primary'),
                                ])->alignment(Alignment::Start),
                            ]),
                    ])
                    ->columns(1),
                SchemaView::make('filament.pages.telemetry-heatmap-body'),
                Section::make(__('Heatmap display'))
                    ->description(__('How strongly the densest location buckets stand out (shared with the advertiser heatmap).'))
                    ->collapsed()
                    ->schema([
                        Form::make([EmbeddedSchema::make('heatmapSettingsForm')])
                            ->id('heatmap-display-settings')
                            ->livewireSubmitHandler('saveHeatmapDisplaySettings')
                            ->footer([
                                SchemaActions::make([
                                    Action::make('saveHeatmapDisplaySettings')
                                        ->label(__('Save display settings'))
                                        ->submit('saveHeatmapDisplaySettings')
                                        ->keyBindings(['mod+s']),
                                ])->alignment(Alignment::Start),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
