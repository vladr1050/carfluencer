import { useState, useEffect, useCallback } from 'react';
import { MapContainer, TileLayer, useMap } from 'react-leaflet';
import { Calendar, Navigation, Clock, Eye, ChevronDown, ChevronUp, X, RotateCcw, AlertCircle } from 'lucide-react';
import 'leaflet/dist/leaflet.css';
import { LatLngTuple } from 'leaflet';
import '../../utils/leaflet-icon-fix';
import L from 'leaflet';
import 'leaflet.heat';
import { SegmentedControl } from '../../components/ui/segmented-control';
import { useSearchParams } from 'react-router';
import { apiJson } from '@/lib/api';

declare module 'leaflet' {
  function heatLayer(latlngs: [number, number, number][], options?: Record<string, unknown>): L.Layer;
}

/** Same JSON as GET /api/advertiser/map-basemap (aligned with admin Filament heatmap blade). */
type AdvertiserMapBasemap = {
  provider: string;
  url: string;
  attribution: string;
  subdomains: string | null;
  max_zoom: number;
};

const CARTO_POSITRON_FALLBACK: AdvertiserMapBasemap = {
  provider: 'carto',
  url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
  attribution:
    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
  subdomains: 'abcd',
  max_zoom: 20,
};

/** Approx. geographic centre of Latvia */
const HEATMAP_DEFAULT_CENTER: LatLngTuple = [56.88, 24.6];
const HEATMAP_DEFAULT_ZOOM_NO_DATA = 7;
const HEATMAP_DEFAULT_ZOOM_WITH_DATA = 11;

/**
 * leaflet.heat (simpleheat): each stamp uses globalAlpha = max(intensity/max, minOpacity).
 * Defaults are very faint on light basemaps — raise minOpacity and lower max for readable color.
 */
const HEATMAP_MAX_DENOMINATOR = 2.35;
const HEATMAP_MIN_OPACITY = 0.14;

function HeatmapLayer({ data, mode }: { data: [number, number, number][]; mode: string }) {
  const map = useMap();

  useEffect(() => {
    if (!data || data.length === 0) return;

    const addHeatmapWhenReady = (): L.Layer | undefined => {
      const container = map.getContainer();
      if (!container || container.offsetWidth === 0 || container.offsetHeight === 0) {
        setTimeout(addHeatmapWhenReady, 100);
        return undefined;
      }

      const gradient =
        mode === 'parking'
          ? { 0.0: '#140010', 0.18: '#F10DBF', 0.45: '#F10DBF', 0.85: '#FF8AE8', 1.0: '#FFFFFF' }
          : mode === 'driving'
            ? { 0.0: '#101400', 0.18: '#C1F60D', 0.45: '#C1F60D', 0.85: '#E8FF7A', 1.0: '#FFFFFF' }
            : {
                0.0: '#0a0a0a',
                0.15: '#C1F60D',
                0.4: '#F10DBF',
                0.65: '#C1F60D',
                0.85: '#FFFFFF',
                1.0: '#FFFFFF',
              };

      const heatLayer = L.heatLayer(data, {
        radius: 25,
        blur: 12,
        maxZoom: 17,
        minOpacity: HEATMAP_MIN_OPACITY,
        max: 1.0 / HEATMAP_MAX_DENOMINATOR,
        gradient,
      });

      heatLayer.addTo(map);
      return heatLayer;
    };

    const layer = addHeatmapWhenReady();

    return () => {
      if (layer && map.hasLayer(layer)) {
        map.removeLayer(layer);
      }
    };
  }, [map, data, mode]);

  return null;
}

/** Leaflet often renders 0×0 until layout settles; flex parents make this worse. */
function MapInvalidateOnResize() {
  const map = useMap();
  useEffect(() => {
    const el = map.getContainer();
    const target = el.parentElement ?? el;
    const ro = new ResizeObserver(() => {
      map.invalidateSize({ animate: false });
    });
    ro.observe(target);
    requestAnimationFrame(() => map.invalidateSize({ animate: false }));
    return () => ro.disconnect();
  }, [map]);
  return null;
}

function MapContent({
  data,
  mode,
  basemap,
}: {
  data: [number, number, number][];
  mode: string;
  basemap: AdvertiserMapBasemap;
}) {
  return (
    <>
      <TileLayer
        key={basemap.url}
        attribution={basemap.attribution}
        url={basemap.url}
        maxZoom={basemap.max_zoom}
        {...(basemap.subdomains ? { subdomains: basemap.subdomains } : {})}
      />
      <MapInvalidateOnResize />
      <HeatmapLayer data={data} mode={mode} />
    </>
  );
}

function resolveDates(
  dateRange: string,
  customFrom: string,
  customTo: string,
): { from?: string; to?: string } {
  const fmt = (d: Date) => d.toISOString().slice(0, 10);
  const today = new Date();
  if (dateRange === 'custom' && customFrom && customTo) {
    return { from: customFrom, to: customTo };
  }
  if (dateRange === 'last-24-hours') {
    const y = new Date(today);
    y.setDate(y.getDate() - 1);
    return { from: fmt(y), to: fmt(today) };
  }
  if (dateRange === 'last-7-days') {
    const y = new Date(today);
    y.setDate(y.getDate() - 7);
    return { from: fmt(y), to: fmt(today) };
  }
  if (dateRange === 'last-30-days') {
    const y = new Date(today);
    y.setDate(y.getDate() - 30);
    return { from: fmt(y), to: fmt(today) };
  }
  return {};
}

type CampaignOpt = { id: number; name: string };
type VehicleOpt = { id: number; brand: string; model: string; imei: string };

type HeatmapApi = {
  campaign: { id: number; name: string; start_date?: string | null; end_date?: string | null };
  heatmap: {
    points: { lat: number; lng: number; intensity: number }[];
    metrics: {
      impressions: number;
      driving_distance_km: number;
      driving_time_hours: number;
      parking_time_hours: number;
      mode: string;
      heatmap_motion?: string;
    };
  };
};

export function AdvertiserHeatmap() {
  const [searchParams, setSearchParams] = useSearchParams();

  const campaignIdFromUrl = searchParams.get('campaignId');
  const campaignNameFromUrl = searchParams.get('campaignName');
  const dateFromUrl = searchParams.get('dateFrom');
  const dateToUrl = searchParams.get('dateTo');
  const vehicleFromUrl = searchParams.get('vehicle') || 'all';

  const [campaigns, setCampaigns] = useState<CampaignOpt[]>([]);
  const [vehicles, setVehicles] = useState<VehicleOpt[]>([]);
  const [selectedCampaignId, setSelectedCampaignId] = useState(() => campaignIdFromUrl || '');
  const [selectedVehicle, setSelectedVehicle] = useState(vehicleFromUrl);
  const [dateRange, setDateRange] = useState(() => {
    if (dateFromUrl && dateToUrl) return 'custom';
    return 'last-7-days';
  });
  const [customDateFrom, setCustomDateFrom] = useState(dateFromUrl || '');
  const [customDateTo, setCustomDateTo] = useState(dateToUrl || '');
  const [mode, setMode] = useState<'driving' | 'parking' | 'both'>('both');
  const [filtersCollapsed, setFiltersCollapsed] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [apiError, setApiError] = useState<string | null>(null);
  const [heatmapPayload, setHeatmapPayload] = useState<HeatmapApi | null>(null);
  const [mapBasemap, setMapBasemap] = useState<AdvertiserMapBasemap>(CARTO_POSITRON_FALLBACK);

  useEffect(() => {
    apiJson<AdvertiserMapBasemap>('/api/advertiser/map-basemap')
      .then((cfg) => {
        if (cfg && typeof cfg.url === 'string' && cfg.url.length > 0) {
          setMapBasemap({
            provider: typeof cfg.provider === 'string' ? cfg.provider : 'carto',
            url: cfg.url,
            attribution: typeof cfg.attribution === 'string' ? cfg.attribution : CARTO_POSITRON_FALLBACK.attribution,
            subdomains: typeof cfg.subdomains === 'string' && cfg.subdomains.length > 0 ? cfg.subdomains : null,
            max_zoom: typeof cfg.max_zoom === 'number' && cfg.max_zoom > 0 ? cfg.max_zoom : 20,
          });
        }
      })
      .catch(() => {
        /* unauthenticated or offline — keep CARTO fallback (same as admin without MAPTILER_API_KEY) */
      });
  }, []);

  useEffect(() => {
    apiJson<{ data: { id: number; name: string }[] }>('/api/advertiser/campaigns')
      .then((r) => setCampaigns((r.data ?? []).map((c) => ({ id: c.id, name: c.name }))))
      .catch(() => setCampaigns([]));
    apiJson<{ data: VehicleOpt[] }>('/api/advertiser/vehicles?per_page=200')
      .then((r) => setVehicles(r.data ?? []))
      .catch(() => setVehicles([]));
  }, []);

  useEffect(() => {
    if (!selectedCampaignId && campaigns.length > 0) {
      setSelectedCampaignId(String(campaigns[0].id));
    }
  }, [campaigns, selectedCampaignId]);

  const hasCampaignContext = !!campaignIdFromUrl;

  const loadHeatmap = useCallback(async () => {
    if (!selectedCampaignId) {
      setHeatmapPayload(null);
      return;
    }
    setIsLoading(true);
    setApiError(null);
    const { from, to } = resolveDates(dateRange, customDateFrom, customDateTo);
    const qs = new URLSearchParams();
    qs.set('campaign_id', selectedCampaignId);
    if (selectedVehicle !== 'all') qs.set('vehicle_id', selectedVehicle);
    if (from) qs.set('date_from', from);
    if (to) qs.set('date_to', to);
    qs.set('mode', mode);
    try {
      const data = await apiJson<HeatmapApi>(`/api/advertiser/heatmap?${qs.toString()}`);
      setHeatmapPayload(data);
    } catch (e) {
      setApiError(e instanceof Error ? e.message : 'Failed to load heatmap');
      setHeatmapPayload(null);
    } finally {
      setIsLoading(false);
    }
  }, [selectedCampaignId, selectedVehicle, dateRange, customDateFrom, customDateTo, mode]);

  useEffect(() => {
    void loadHeatmap();
  }, [loadHeatmap]);

  const heatmapData: [number, number, number][] =
    heatmapPayload?.heatmap.points.map((p) => [p.lat, p.lng, p.intensity]) ?? [];

  const hasData = heatmapData.length > 0;
  const metrics = heatmapPayload?.heatmap.metrics;

  const totalImpressions = metrics?.impressions ?? 0;
  const drivingDistance = metrics?.driving_distance_km ?? 0;
  const drivingTime = metrics?.driving_time_hours ?? 0;
  const parkingTime = metrics?.parking_time_hours ?? 0;

  const handleModeChange = (newMode: 'driving' | 'parking' | 'both') => {
    setMode(newMode);
  };

  const handleResetFilters = () => {
    setSelectedVehicle('all');
    setDateRange('last-7-days');
    setCustomDateFrom('');
    setCustomDateTo('');
    setMode('both');
    setSearchParams({});
  };

  const legendGradient =
    mode === 'parking'
      ? 'linear-gradient(to right, #140010, #F10DBF, #FF8AE8, #FFFFFF)'
      : mode === 'driving'
        ? 'linear-gradient(to right, #101400, #C1F60D, #E8FF7A, #FFFFFF)'
        : 'linear-gradient(to right, #0a0a0a, #C1F60D, #F10DBF, #FFFFFF)';

  const center: LatLngTuple =
    heatmapData.length > 0 ? [heatmapData[0][0], heatmapData[0][1]] : HEATMAP_DEFAULT_CENTER;

  return (
    <div className="flex h-dvh min-h-0 flex-col bg-background">
      {hasCampaignContext && (
        <div className="bg-[#C1F60D] text-black px-4 py-3 flex items-center justify-between flex-wrap gap-2">
          <div className="flex items-center gap-6 flex-wrap">
            <div className="flex items-center gap-2">
              <span className="text-sm opacity-80">Campaign:</span>
              <span className="font-medium">{campaignNameFromUrl || `Campaign #${campaignIdFromUrl}`}</span>
            </div>
            {dateFromUrl && dateToUrl && (
              <div className="flex items-center gap-2">
                <Calendar className="w-4 h-4 opacity-80" />
                <span className="text-sm">
                  {dateFromUrl} → {dateToUrl}
                </span>
              </div>
            )}
          </div>
          <button
            type="button"
            onClick={handleResetFilters}
            className="flex items-center gap-2 px-3 py-1.5 bg-black text-white rounded-lg hover:bg-[#545454] text-sm"
          >
            <X className="w-4 h-4" />
            Clear Context
          </button>
        </div>
      )}

      <div className={`border-b border-border bg-card transition-all ${filtersCollapsed ? 'h-14' : 'auto'}`}>
        <div className="p-4 flex items-center justify-between">
          <h1 className="text-xl">Heatmap Analytics</h1>
          <div className="flex items-center gap-2">
            {!hasCampaignContext && (
              <button
                type="button"
                onClick={handleResetFilters}
                className="flex items-center gap-2 px-3 py-2 text-sm hover:bg-muted rounded-lg"
              >
                <RotateCcw className="w-4 h-4" />
                Reset Filters
              </button>
            )}
            <button type="button" onClick={() => setFiltersCollapsed(!filtersCollapsed)} className="p-2 hover:bg-muted rounded-lg">
              {filtersCollapsed ? <ChevronDown className="w-5 h-5" /> : <ChevronUp className="w-5 h-5" />}
            </button>
          </div>
        </div>

        {!filtersCollapsed && (
          <div className="px-4 pb-4 space-y-3">
            {apiError ? <p className="text-sm text-destructive">{apiError}</p> : null}
            <div className="flex flex-wrap items-center gap-3">
              <div className="flex items-center gap-2">
                <label className="text-sm text-muted-foreground">Campaign:</label>
                <select
                  value={selectedCampaignId}
                  onChange={(e) => setSelectedCampaignId(e.target.value)}
                  className="px-3 py-2 rounded-lg bg-input-background dark:bg-input border border-border min-w-[200px]"
                >
                  <option value="">Select…</option>
                  {campaigns.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex items-center gap-2">
                <label className="text-sm text-muted-foreground">Vehicle:</label>
                <select
                  value={selectedVehicle}
                  onChange={(e) => setSelectedVehicle(e.target.value)}
                  className="px-3 py-2 rounded-lg bg-input-background dark:bg-input border border-border min-w-[200px]"
                >
                  <option value="all">All vehicles</option>
                  {vehicles.map((v) => (
                    <option key={v.id} value={v.id}>
                      {v.brand} {v.model} ({v.imei})
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex items-center gap-2">
                <label className="text-sm text-muted-foreground">Date range:</label>
                <select
                  value={dateRange}
                  onChange={(e) => setDateRange(e.target.value)}
                  className="px-3 py-2 rounded-lg bg-input-background dark:bg-input border border-border"
                >
                  <option value="last-24-hours">Last 24 hours</option>
                  <option value="last-7-days">Last 7 days</option>
                  <option value="last-30-days">Last 30 days</option>
                  <option value="custom">Custom</option>
                </select>
              </div>

              {dateRange === 'custom' && (
                <div className="flex items-center gap-2">
                  <input
                    type="date"
                    value={customDateFrom}
                    onChange={(e) => setCustomDateFrom(e.target.value)}
                    className="px-3 py-2 rounded-lg bg-input-background dark:bg-input border border-border text-sm"
                  />
                  <span className="text-sm text-muted-foreground">to</span>
                  <input
                    type="date"
                    value={customDateTo}
                    onChange={(e) => setCustomDateTo(e.target.value)}
                    className="px-3 py-2 rounded-lg bg-input-background dark:bg-input border border-border text-sm"
                  />
                </div>
              )}

              <div className="flex items-center gap-2 ml-auto">
                <label className="text-sm text-muted-foreground">Mode:</label>
                <SegmentedControl
                  value={mode}
                  onChange={handleModeChange}
                  options={[
                    { value: 'driving', label: 'Driving' },
                    { value: 'parking', label: 'Parking' },
                    { value: 'both', label: 'Both' },
                  ]}
                />
              </div>
            </div>
          </div>
        )}
      </div>

      <div className="relative min-h-0 flex-1">
        {isLoading && (
          <div className="absolute inset-0 bg-background/80 backdrop-blur-sm z-[2000] flex items-center justify-center">
            <div className="bg-card border border-border rounded-lg p-6 shadow-lg flex items-center gap-3">
              <div className="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin" />
              <span className="text-sm">Loading map data…</span>
            </div>
          </div>
        )}

        {!isLoading && !hasData && selectedCampaignId && (
          <div className="absolute inset-0 bg-background/90 backdrop-blur-sm z-[1500] flex items-center justify-center">
            <div className="bg-card border border-border rounded-lg p-8 max-w-md text-center shadow-lg">
              <AlertCircle className="w-16 h-16 mx-auto mb-4 text-muted-foreground" />
              <h3 className="text-xl mb-2">No telemetry points</h3>
              <p className="text-muted-foreground text-sm">API returned no points for this selection (mock data may still show zeros in KPIs).</p>
            </div>
          </div>
        )}

        {!selectedCampaignId ? (
          <div className="absolute inset-0 flex items-center justify-center text-muted-foreground text-sm z-[500]">
            Select a campaign to load the heatmap.
          </div>
        ) : null}

        <MapContainer
          center={center}
          zoom={hasData ? HEATMAP_DEFAULT_ZOOM_WITH_DATA : HEATMAP_DEFAULT_ZOOM_NO_DATA}
          className="z-0 h-full w-full min-h-[280px] bg-[#e8e8e8]"
          style={{ height: '100%', width: '100%', minHeight: '280px', background: '#e8e8e8' }}
        >
          <MapContent data={heatmapData} mode={mode} basemap={mapBasemap} />
        </MapContainer>

        <div className="absolute top-4 right-4 bg-card border border-border rounded-lg p-4 shadow-lg z-[1000]">
          <div className="text-sm mb-3">Heat intensity</div>
          <div className="w-32 h-4 rounded" style={{ background: legendGradient }} />
        </div>
      </div>

      <div className="p-4 border-t border-border bg-card">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="flex items-center gap-3 p-4 bg-muted rounded-lg">
            <Eye className="w-5 h-5" style={{ color: '#C1F60D' }} />
            <div>
              <div className="text-2xl" style={{ color: '#C1F60D' }}>
                {totalImpressions.toLocaleString()}
              </div>
              <div className="text-xs text-muted-foreground">Impressions</div>
            </div>
          </div>
          <div className="flex items-center gap-3 p-4 bg-muted rounded-lg">
            <Navigation className="w-5 h-5 text-accent" />
            <div>
              <div className="text-2xl">{drivingDistance} km</div>
              <div className="text-xs text-muted-foreground">Driving distance</div>
            </div>
          </div>
          <div className="flex items-center gap-3 p-4 bg-muted rounded-lg">
            <Clock className="w-5 h-5" />
            <div>
              <div className="text-2xl">{drivingTime} hrs</div>
              <div className="text-xs text-muted-foreground">Driving time</div>
            </div>
          </div>
          <div className="flex items-center gap-3 p-4 bg-muted rounded-lg">
            <Clock className="w-5 h-5" />
            <div>
              <div className="text-2xl">{parkingTime} hrs</div>
              <div className="text-xs text-muted-foreground">Parking time</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
