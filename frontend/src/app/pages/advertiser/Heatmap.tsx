import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { MapContainer, TileLayer, useMap } from 'react-leaflet';
import {
  Calendar,
  Navigation,
  Clock,
  Eye,
  ChevronDown,
  ChevronUp,
  X,
  RotateCcw,
  AlertCircle,
  Maximize2,
  Minimize2,
} from 'lucide-react';
import 'leaflet/dist/leaflet.css';
import { LatLngTuple } from 'leaflet';
import '../../utils/leaflet-icon-fix';
import L from 'leaflet';
import 'leaflet.heat';
import { SegmentedControl } from '../../components/ui/segmented-control';
import { useSearchParams } from 'react-router-dom';
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
const HEAT_MAX_DENOM_MOVING = 2.15;
const HEAT_MIN_OPACITY = 0.16;
/** Parking: wide blend + floor so full-city density reads on light basemap (Google-style). */
const PARKING_HEAT_MIN_OPACITY = 0.27;
const PARKING_HEAT_MAX_DENOM = 1.82;

/** Leaflet.heat spot spread; aligned with admin Filament heatmap presets. */
type HeatShadowPreset = 'current' | 'small' | 'xsmall';

const HEAT_SHADOW_PRESETS: Record<
  HeatShadowPreset,
  { moving: { radius: number; blur: number }; parking: { radius: number; blur: number } }
> = {
  current: {
    moving: { radius: 24, blur: 14 },
    parking: { radius: 40, blur: 21 },
  },
  small: {
    moving: { radius: 10, blur: 5 },
    parking: { radius: 16, blur: 8 },
  },
  xsmall: {
    moving: { radius: 8, blur: 3 },
    parking: { radius: 12, blur: 6 },
  },
};

function heatShadowDims(preset: HeatShadowPreset) {
  return HEAT_SHADOW_PRESETS[preset] ?? HEAT_SHADOW_PRESETS.current;
}

/** Scale parking iso-ring radii relative to production parking heat radius. */
function parkingContourSizeFactor(preset: HeatShadowPreset): number {
  const base = HEAT_SHADOW_PRESETS.current.parking.radius;
  return heatShadowDims(preset).parking.radius / base;
}

function parseHeatShadowFromSearchParams(sp: URLSearchParams): HeatShadowPreset {
  const v = sp.get('heatmap_shadow');
  return v === 'small' || v === 'xsmall' ? v : 'current';
}

const CELL_HALF = 0.0005;

const GRADIENT_MOVING: Record<number, string> = {
  0.0: '#440154',
  0.25: '#3b528b',
  0.5: '#21918c',
  0.75: '#5ec962',
  1.0: '#fde725',
};
/** Google-style density: green → yellow → orange → red (no white / magenta peak). */
const GRADIENT_STOPPED: Record<number, string> = {
  0.0: '#1b5e20',
  0.22: '#43a047',
  0.45: '#c6d84a',
  0.62: '#ffeb3b',
  0.8: '#fb8c00',
  1.0: '#c62828',
};

const STOPS_MOVING: [number, string][] = [
  [0, '#440154'],
  [0.25, '#3b528b'],
  [0.5, '#21918c'],
  [0.75, '#5ec962'],
  [1, '#fde725'],
];

const STOPS_STOPPED: [number, string][] = [
  [0, '#1b5e20'],
  [0.22, '#43a047'],
  [0.45, '#c6d84a'],
  [0.62, '#ffeb3b'],
  [0.8, '#fb8c00'],
  [1, '#c62828'],
];

const PARKING_CONTOUR_COLORS = ['#2e7d32', '#66bb6a', '#cddc39', '#ffca28', '#f57c00', '#b71c1c'] as const;

function parkingBandIndex(t: number): number {
  const x = Math.max(0, Math.min(1, t));
  if (x <= 0.12) return 0;
  if (x <= 0.28) return 1;
  if (x <= 0.44) return 2;
  if (x <= 0.6) return 3;
  if (x <= 0.78) return 4;
  return 5;
}

function parseHex(h: string): [number, number, number] {
  const s = h.replace('#', '');
  return [parseInt(s.slice(0, 2), 16), parseInt(s.slice(2, 4), 16), parseInt(s.slice(4, 6), 16)];
}
function mix(a: [number, number, number], b: [number, number, number], t: number): [number, number, number] {
  return [
    Math.round(a[0] + (b[0] - a[0]) * t),
    Math.round(a[1] + (b[1] - a[1]) * t),
    Math.round(a[2] + (b[2] - a[2]) * t),
  ];
}
function rgbToHex(rgb: [number, number, number]): string {
  return (
    '#' +
    rgb
      .map((x) => {
        const h = Math.max(0, Math.min(255, x)).toString(16);
        return h.length === 1 ? '0' + h : h;
      })
      .join('')
  );
}
function colorFromStops(t: number, stops: [number, string][]): string {
  t = Math.max(0, Math.min(1, t));
  for (let i = 0; i < stops.length - 1; i++) {
    const [t0, c0] = stops[i];
    const [t1, c1] = stops[i + 1];
    if (t <= t1 || i === stops.length - 2) {
      if (t1 <= t0) return c1;
      const u = (t - t0) / (t1 - t0);
      return rgbToHex(mix(parseHex(c0), parseHex(c1), Math.max(0, Math.min(1, u))));
    }
  }
  return stops[stops.length - 1][1];
}

type HeatmapBucket = {
  lat: number;
  lng: number;
  w_moving: number;
  w_stopped: number;
  w_total: number;
  intensity_moving: number;
  intensity_stopped: number;
  rank_moving_pct?: number;
  rank_stopped_pct?: number;
};

function FitBounds({ buckets, enabled }: { buckets: HeatmapBucket[]; enabled: boolean }) {
  const map = useMap();
  useEffect(() => {
    if (!enabled || !buckets.length) return;
    const ll = buckets.map((b) => L.latLng(b.lat, b.lng));
    map.fitBounds(L.latLngBounds(ll), { padding: [48, 48], maxZoom: 16 });
  }, [map, buckets, enabled]);
  return null;
}

function AnalyticsMapLayers({
  buckets,
  mode,
  viewMode,
  gridMetric,
  showParkingContours,
  shadowPreset,
}: {
  buckets: HeatmapBucket[];
  mode: 'driving' | 'parking';
  viewMode: 'heatmap' | 'grid';
  gridMetric: 'moving' | 'stopped';
  showParkingContours: boolean;
  shadowPreset: HeatShadowPreset;
}) {
  const map = useMap();

  useEffect(() => {
    const toRemove: L.Layer[] = [];
    const add = (ly: L.Layer) => {
      ly.addTo(map);
      toRemove.push(ly);
    };

    const ready = (): boolean => {
      const c = map.getContainer();
      return !!(c && c.offsetWidth > 0 && c.offsetHeight > 0);
    };

    const run = () => {
      if (!ready() || !buckets.length) return;

      if (viewMode === 'grid') {
        const capM = Math.max(1, ...buckets.map((b) => b.w_moving));
        const capS = Math.max(1, ...buckets.map((b) => b.w_stopped));
        buckets.forEach((b) => {
          const w = gridMetric === 'stopped' ? b.w_stopped : b.w_moving;
          if (w <= 0) return;
          const cap = gridMetric === 'stopped' ? capS : capM;
          const ratio = Math.min(1, w / cap);
          const fill =
            gridMetric === 'stopped'
              ? colorFromStops(
                  b.intensity_stopped != null ? Number(b.intensity_stopped) : Math.min(1, ratio ** 0.7),
                  STOPS_STOPPED,
                )
              : colorFromStops(ratio, STOPS_MOVING);
          const bounds: L.LatLngBoundsExpression = [
            [b.lat - CELL_HALF, b.lng - CELL_HALF],
            [b.lat + CELL_HALF, b.lng + CELL_HALF],
          ];
          const rect = L.rectangle(bounds, {
            stroke: true,
            color: 'rgba(0,0,0,0.25)',
            weight: 1,
            fillColor: fill,
            fillOpacity: gridMetric === 'stopped' ? 0.82 : 0.72,
          });
          add(rect);
        });
        return;
      }

      const heatM = buckets
        .filter((b) => b.w_moving > 0)
        .map((b) => [b.lat, b.lng, b.intensity_moving] as [number, number, number]);
      const heatS = buckets
        .filter((b) => b.w_stopped > 0)
        .map((b) => [b.lat, b.lng, Number(b.intensity_stopped) || 0] as [number, number, number]);

      const addHeat = (triples: [number, number, number][], gradient: Record<number, string>, denom: number, r: number, blur: number) => {
        if (!triples.length || typeof L.heatLayer !== 'function') return;
        const h = L.heatLayer(triples, {
          radius: r,
          blur,
          maxZoom: 17,
          minOpacity: HEAT_MIN_OPACITY,
          max: 1.0 / denom,
          gradient,
        });
        add(h);
      };

      const dims = heatShadowDims(shadowPreset);
      const md = dims.moving;
      const pd = dims.parking;

      const addParkingHeat = (triples: [number, number, number][]) => {
        if (!triples.length || typeof L.heatLayer !== 'function') return;
        const h = L.heatLayer(triples, {
          radius: pd.radius,
          blur: pd.blur,
          maxZoom: 17,
          minOpacity: PARKING_HEAT_MIN_OPACITY,
          max: 1.0 / PARKING_HEAT_MAX_DENOM,
          gradient: GRADIENT_STOPPED,
        });
        add(h);
      };

      if (mode === 'driving') {
        addHeat(heatM, GRADIENT_MOVING, HEAT_MAX_DENOM_MOVING, md.radius, md.blur);
      } else {
        addParkingHeat(heatS);
      }

      if (showParkingContours && viewMode === 'heatmap' && mode === 'parking') {
        const contourMul = parkingContourSizeFactor(shadowPreset);
        const g = L.layerGroup();
        buckets.forEach((b) => {
          if (b.w_stopped <= 0) return;
          const band = parkingBandIndex(Number(b.intensity_stopped) || 0);
          if (band < 2) return;
          const color = PARKING_CONTOUR_COLORS[band];
          const c = L.circle([b.lat, b.lng], {
            radius: (36 + band * 30) * contourMul,
            color,
            weight: 1.5,
            opacity: 0.45,
            fillColor: color,
            fillOpacity: 0.05 + band * 0.014,
            interactive: false,
          });
          g.addLayer(c);
        });
        if (g.getLayers().length) add(g);
      }

    };

    let t: ReturnType<typeof setTimeout> | undefined;
    const schedule = () => {
      if (ready()) {
        run();
        return;
      }
      t = setTimeout(schedule, 100);
    };
    schedule();

    return () => {
      if (t) clearTimeout(t);
      toRemove.forEach((ly) => {
        if (map.hasLayer(ly)) map.removeLayer(ly);
      });
    };
  }, [map, buckets, mode, viewMode, gridMetric, showParkingContours, shadowPreset]);

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

/** Leaflet needs invalidateSize after native fullscreen toggles (ResizeObserver may lag on some browsers). */
function MapInvalidateOnFullscreen() {
  const map = useMap();
  useEffect(() => {
    const fix = () => {
      requestAnimationFrame(() => {
        map.invalidateSize({ animate: false });
        setTimeout(() => map.invalidateSize({ animate: false }), 200);
      });
    };
    document.addEventListener('fullscreenchange', fix);
    document.addEventListener('webkitfullscreenchange', fix as EventListener);
    return () => {
      document.removeEventListener('fullscreenchange', fix);
      document.removeEventListener('webkitfullscreenchange', fix as EventListener);
    };
  }, [map]);
  return null;
}

function MapContent({
  buckets,
  mode,
  basemap,
  viewMode,
  gridMetric,
  showParkingContours,
  shadowPreset,
  skipAutoFitBounds,
}: {
  buckets: HeatmapBucket[];
  mode: 'driving' | 'parking';
  basemap: AdvertiserMapBasemap;
  viewMode: 'heatmap' | 'grid';
  gridMetric: 'moving' | 'stopped';
  showParkingContours: boolean;
  shadowPreset: HeatShadowPreset;
  skipAutoFitBounds: boolean;
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
      <MapInvalidateOnFullscreen />
      {buckets.length > 0 ? <FitBounds buckets={buckets} enabled={!skipAutoFitBounds} /> : null}
      <AnalyticsMapLayers
        buckets={buckets}
        mode={mode}
        viewMode={viewMode}
        gridMetric={gridMetric}
        showParkingContours={showParkingContours}
        shadowPreset={shadowPreset}
      />
    </>
  );
}

function HeatmapApiLoader({
  selectedCampaignId,
  selectedVehicle,
  dateFrom,
  dateTo,
  mode,
  normalization,
  setHeatmapPayload,
  setIsLoading,
  setApiError,
}: {
  selectedCampaignId: string;
  selectedVehicle: string;
  dateFrom?: string;
  dateTo?: string;
  mode: 'driving' | 'parking';
  normalization: string;
  setHeatmapPayload: (d: HeatmapApi | null) => void;
  setIsLoading: (v: boolean) => void;
  setApiError: (v: string | null) => void;
}) {
  const map = useMap();
  const heatmapRequestIdRef = useRef(0);
  const heatmapAbortRef = useRef<AbortController | null>(null);

  const fetchHeatmap = useCallback(async () => {
    if (!selectedCampaignId) {
      heatmapAbortRef.current?.abort();
      heatmapAbortRef.current = null;
      setHeatmapPayload(null);
      setIsLoading(false);
      return;
    }
    heatmapAbortRef.current?.abort();
    const ac = new AbortController();
    heatmapAbortRef.current = ac;
    const requestId = ++heatmapRequestIdRef.current;

    setIsLoading(true);
    setApiError(null);
    const b = map.getBounds();
    const qs = new URLSearchParams();
    qs.set('campaign_id', selectedCampaignId);
    if (selectedVehicle !== 'all') qs.set('vehicle_id', selectedVehicle);
    if (dateFrom) qs.set('date_from', dateFrom);
    if (dateTo) qs.set('date_to', dateTo);
    qs.set('mode', mode);
    qs.set('normalization', normalization);
    qs.set('south', String(b.getSouth()));
    qs.set('west', String(b.getWest()));
    qs.set('north', String(b.getNorth()));
    qs.set('east', String(b.getEast()));
    qs.set('zoom', String(map.getZoom()));
    try {
      const data = await apiJson<HeatmapApi>(`/api/advertiser/heatmap?${qs.toString()}`, { signal: ac.signal });
      if (requestId !== heatmapRequestIdRef.current) {
        return;
      }
      setHeatmapPayload(data);
    } catch (e) {
      if (e instanceof Error && e.name === 'AbortError') {
        return;
      }
      if (requestId !== heatmapRequestIdRef.current) {
        return;
      }
      setApiError(e instanceof Error ? e.message : 'Failed to load heatmap');
      setHeatmapPayload(null);
    } finally {
      if (requestId === heatmapRequestIdRef.current) {
        setIsLoading(false);
      }
    }
  }, [
    map,
    selectedCampaignId,
    selectedVehicle,
    dateFrom,
    dateTo,
    mode,
    normalization,
    setHeatmapPayload,
    setIsLoading,
    setApiError,
  ]);

  useEffect(() => {
    void fetchHeatmap();
  }, [fetchHeatmap]);

  useEffect(() => {
    let timer: ReturnType<typeof setTimeout> | undefined;
    const debounced = () => {
      if (timer) clearTimeout(timer);
      timer = setTimeout(() => void fetchHeatmap(), 480);
    };
    map.on('moveend', debounced);
    map.on('zoomend', debounced);
    return () => {
      map.off('moveend', debounced);
      map.off('zoomend', debounced);
      if (timer) clearTimeout(timer);
    };
  }, [map, fetchHeatmap]);

  return null;
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
  map: {
    points: { lat: number; lng: number; intensity: number; w_moving?: number; w_stopped?: number }[];
    buckets: HeatmapBucket[];
    mode: string;
    heatmap_motion?: string;
    normalization?: string;
    heatmap_rollup?: boolean;
  };
  debug: {
    intensity_gamma?: number;
    intensity_stopped_power?: number;
    cap_moving?: number;
    cap_stopped?: number;
    cap_total?: number;
    heatmap_error?: string;
    heatmap_error_detail?: string;
  };
  summary_metrics: {
    impressions: number | null;
    driving_distance_km: number | null;
    driving_time_hours: number | null;
    parking_time_hours: number | null;
    data_source: string;
    is_estimated: boolean;
  };
};

export function AdvertiserHeatmap() {
  const [searchParams, setSearchParams] = useSearchParams();

  const [shadowPreset, setShadowPreset] = useState<HeatShadowPreset>(() => parseHeatShadowFromSearchParams(searchParams));

  useEffect(() => {
    setShadowPreset(parseHeatShadowFromSearchParams(searchParams));
  }, [searchParams]);

  const setShadowSize = useCallback(
    (preset: HeatShadowPreset) => {
      setShadowPreset(preset);
      setSearchParams((prev) => {
        const n = new URLSearchParams(prev);
        if (preset === 'current') {
          n.delete('heatmap_shadow');
        } else {
          n.set('heatmap_shadow', preset);
        }
        return n;
      });
    },
    [setSearchParams],
  );

  const campaignIdFromUrl = searchParams.get('campaignId');
  const campaignNameFromUrl = searchParams.get('campaignName');
  const dateFromUrl = searchParams.get('dateFrom');
  const dateToUrl = searchParams.get('dateTo');
  const vehicleFromUrl = searchParams.get('vehicle') || 'all';

  const [campaigns, setCampaigns] = useState<CampaignOpt[]>([]);
  const [vehicles, setVehicles] = useState<VehicleOpt[]>([]);
  const [selectedCampaignId, setSelectedCampaignId] = useState(() => campaignIdFromUrl || '');
  const [selectedVehicle, setSelectedVehicle] = useState(vehicleFromUrl);

  const initialDateRange = dateFromUrl && dateToUrl ? 'custom' : 'last-7-days';
  const initialCustomFrom = dateFromUrl || '';
  const initialCustomTo = dateToUrl || '';

  /** Draft date UI — map/KPI load uses applied* until Apply. */
  const [dateRange, setDateRange] = useState(initialDateRange);
  const [customDateFrom, setCustomDateFrom] = useState(initialCustomFrom);
  const [customDateTo, setCustomDateTo] = useState(initialCustomTo);
  const [appliedDateRange, setAppliedDateRange] = useState(initialDateRange);
  const [appliedCustomFrom, setAppliedCustomFrom] = useState(initialCustomFrom);
  const [appliedCustomTo, setAppliedCustomTo] = useState(initialCustomTo);
  const [mode, setMode] = useState<'driving' | 'parking'>('driving');
  const [normalization, setNormalization] = useState<'p95' | 'p99' | 'max'>('p95');
  const [mapView, setMapView] = useState<'heatmap' | 'grid'>('heatmap');
  const [gridMetric, setGridMetric] = useState<'moving' | 'stopped'>('moving');
  const [showParkingContours, setShowParkingContours] = useState(false);
  const [filtersCollapsed, setFiltersCollapsed] = useState(false);
  const [mapFullscreen, setMapFullscreen] = useState(false);
  const mapShellRef = useRef<HTMLDivElement>(null);
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

  const { from: dateFrom, to: dateTo } = useMemo(
    () => resolveDates(appliedDateRange, appliedCustomFrom, appliedCustomTo),
    [appliedDateRange, appliedCustomFrom, appliedCustomTo],
  );

  const dateRangePendingDirty =
    dateRange !== appliedDateRange || customDateFrom !== appliedCustomFrom || customDateTo !== appliedCustomTo;

  const canApplyDateRange =
    dateRangePendingDirty && (dateRange !== 'custom' || (Boolean(customDateFrom) && Boolean(customDateTo)));

  const applyDateRange = useCallback(() => {
    if (!canApplyDateRange) {
      return;
    }
    setAppliedDateRange(dateRange);
    setAppliedCustomFrom(customDateFrom);
    setAppliedCustomTo(customDateTo);
  }, [canApplyDateRange, dateRange, customDateFrom, customDateTo]);

  useEffect(() => {
    setGridMetric(mode === 'parking' ? 'stopped' : 'moving');
  }, [mode]);

  useEffect(() => {
    const shell = mapShellRef.current;
    const sync = () => {
      const doc = document as Document & { webkitFullscreenElement?: Element | null };
      const active = document.fullscreenElement ?? doc.webkitFullscreenElement;
      setMapFullscreen(!!shell && active === shell);
    };
    document.addEventListener('fullscreenchange', sync);
    document.addEventListener('webkitfullscreenchange', sync);
    sync();
    return () => {
      document.removeEventListener('fullscreenchange', sync);
      document.removeEventListener('webkitfullscreenchange', sync);
    };
  }, []);

  const toggleMapFullscreen = useCallback(async () => {
    const el = mapShellRef.current;
    if (!el) return;
    const doc = document as Document & { webkitFullscreenElement?: Element | null; webkitExitFullscreen?: () => Promise<void> };
    const htmlEl = el as HTMLElement & { webkitRequestFullscreen?: () => void };
    try {
      if (document.fullscreenElement === el || doc.webkitFullscreenElement === el) {
        if (document.exitFullscreen) await document.exitFullscreen();
        else if (doc.webkitExitFullscreen) await doc.webkitExitFullscreen();
      } else if (htmlEl.requestFullscreen) {
        await htmlEl.requestFullscreen();
      } else if (htmlEl.webkitRequestFullscreen) {
        htmlEl.webkitRequestFullscreen();
      }
    } catch {
      /* user denied or API unsupported */
    }
  }, []);

  const mapLayer = heatmapPayload?.map;
  const mapDebug = heatmapPayload?.debug ?? {};
  const summary = heatmapPayload?.summary_metrics;

  const buckets: HeatmapBucket[] = mapLayer?.buckets ?? [];

  const hasData = buckets.length > 0 || (mapLayer?.points?.length ?? 0) > 0;

  const handleModeChange = (newMode: 'driving' | 'parking') => {
    setMode(newMode);
  };

  const handleResetFilters = () => {
    setSelectedVehicle('all');
    setDateRange('last-7-days');
    setCustomDateFrom('');
    setCustomDateTo('');
    setAppliedDateRange('last-7-days');
    setAppliedCustomFrom('');
    setAppliedCustomTo('');
    setMode('driving');
    setNormalization('p95');
    setMapView('heatmap');
    setGridMetric('moving');
    setShowParkingContours(false);
    setShadowPreset('current');
    setSearchParams({});
  };

  const normLabel = mapLayer?.normalization ?? normalization;
  const legendGradientMoving = 'linear-gradient(to right, #440154, #3b528b, #21918c, #5ec962, #fde725)';
  const legendGradientStopped =
    'linear-gradient(to right, #1b5e20 0%, #43a047 22%, #c6d84a 45%, #ffeb3b 62%, #fb8c00 80%, #c62828 100%)';

  const center: LatLngTuple =
    buckets.length > 0 ? [buckets[0].lat, buckets[0].lng] : HEATMAP_DEFAULT_CENTER;

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

              <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                <button
                  type="button"
                  onClick={applyDateRange}
                  disabled={!canApplyDateRange}
                  className="px-4 py-2 rounded-lg text-sm font-medium bg-primary text-primary-foreground hover:opacity-90 disabled:opacity-40 disabled:pointer-events-none"
                >
                  Apply dates
                </button>
                {dateRangePendingDirty ? (
                  <span className="text-xs text-muted-foreground max-w-[220px]">
                    Map and KPIs still use the previous period until you apply.
                  </span>
                ) : null}
              </div>

              <div className="flex items-center gap-2 ml-auto">
                <label className="text-sm text-muted-foreground">Mode:</label>
                <SegmentedControl
                  value={mode}
                  onChange={handleModeChange}
                  options={[
                    { value: 'driving', label: 'Driving' },
                    { value: 'parking', label: 'Parking' },
                  ]}
                />
              </div>
            </div>
            <div className="flex flex-wrap items-center gap-3 pt-1 border-t border-border/60">
              <div className="flex items-center gap-2">
                <label className="text-sm text-muted-foreground">Intensity scale:</label>
                <select
                  value={normalization}
                  onChange={(e) => setNormalization(e.target.value as 'p95' | 'p99' | 'max')}
                  className="px-3 py-2 rounded-lg bg-input-background dark:bg-input border border-border text-sm"
                >
                  <option value="p95">p95 (recommended)</option>
                  <option value="p99">p99</option>
                  <option value="max">Max bucket</option>
                </select>
              </div>
              <div className="flex items-center gap-2">
                <label className="text-sm text-muted-foreground">Map view:</label>
                <select
                  value={mapView}
                  onChange={(e) => setMapView(e.target.value as 'heatmap' | 'grid')}
                  className="px-3 py-2 rounded-lg bg-input-background dark:bg-input border border-border text-sm"
                >
                  <option value="heatmap">Heatmap</option>
                  <option value="grid">Grid</option>
                </select>
              </div>
              {mapView === 'heatmap' && (
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-sm text-muted-foreground">Shadow size:</span>
                  <SegmentedControl<HeatShadowPreset>
                    value={shadowPreset}
                    onChange={setShadowSize}
                    options={[
                      { value: 'current', label: 'Current' },
                      { value: 'small', label: 'Smaller' },
                      { value: 'xsmall', label: 'Very small' },
                    ]}
                  />
                </div>
              )}
              {mapView === 'grid' && (
                <div className="flex items-center gap-2">
                  <label className="text-sm text-muted-foreground">Grid shows:</label>
                  <select
                    value={gridMetric}
                    onChange={(e) => setGridMetric(e.target.value as 'moving' | 'stopped')}
                    className="px-3 py-2 rounded-lg bg-input-background dark:bg-input border border-border text-sm"
                  >
                    <option value="moving">Moving samples</option>
                    <option value="stopped">Stopped samples</option>
                  </select>
                </div>
              )}
              {mode === 'parking' && mapView === 'heatmap' && (
                <label className="inline-flex items-center gap-2 cursor-pointer text-sm">
                  <input
                    type="checkbox"
                    checked={showParkingContours}
                    onChange={(e) => setShowParkingContours(e.target.checked)}
                    className="rounded border-border"
                  />
                  Parking iso-rings (experimental)
                </label>
              )}
            </div>
          </div>
        )}
      </div>

      <div
        ref={mapShellRef}
        className={`relative min-h-0 flex-1 flex flex-col bg-[#e8e8e8] ${mapFullscreen ? 'rounded-none' : ''}`}
      >
        {isLoading && (
          <div className="absolute inset-0 bg-background/80 backdrop-blur-sm z-[2000] flex items-center justify-center">
            <div className="bg-card border border-border rounded-lg p-6 shadow-lg flex items-center gap-3">
              <div className="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin" />
              <span className="text-sm">Loading map data…</span>
            </div>
          </div>
        )}

        {!isLoading && apiError && selectedCampaignId && (
          <div className="absolute inset-0 bg-background/90 backdrop-blur-sm z-[1500] flex items-center justify-center px-4">
            <div className="bg-card border border-destructive/40 rounded-lg p-8 max-w-md text-center shadow-lg">
              <AlertCircle className="w-16 h-16 mx-auto mb-4 text-destructive" />
              <h3 className="text-xl mb-2">Couldn&apos;t load heatmap</h3>
              <p className="text-destructive text-sm whitespace-pre-wrap break-words">{apiError}</p>
              <p className="text-muted-foreground text-xs mt-4 leading-relaxed">
                Often this is a timeout or memory limit on the server for a very long date range. Try a shorter period, ensure daily rollups exist, or ask ops to check{' '}
                <code className="text-[11px] bg-muted px-1 rounded">storage/logs/laravel.log</code> and PHP/DB timeouts.
              </p>
            </div>
          </div>
        )}

        {!isLoading && !apiError && !hasData && selectedCampaignId && (
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
          className={`z-0 w-full bg-[#e8e8e8] ${mapFullscreen ? 'flex-1 min-h-0 h-full' : 'h-full min-h-[280px]'}`}
          style={{
            height: mapFullscreen ? '100%' : '100%',
            width: '100%',
            minHeight: mapFullscreen ? undefined : 280,
            background: '#e8e8e8',
          }}
        >
          <HeatmapApiLoader
            selectedCampaignId={selectedCampaignId}
            selectedVehicle={selectedVehicle}
            dateFrom={dateFrom}
            dateTo={dateTo}
            mode={mode}
            normalization={normalization}
            setHeatmapPayload={setHeatmapPayload}
            setIsLoading={setIsLoading}
            setApiError={setApiError}
          />
          <MapContent
            buckets={buckets}
            mode={mode}
            basemap={mapBasemap}
            viewMode={mapView}
            gridMetric={gridMetric}
            showParkingContours={showParkingContours}
            shadowPreset={shadowPreset}
            skipAutoFitBounds={mapLayer?.heatmap_rollup === true}
          />
        </MapContainer>

        <button
          type="button"
          onClick={() => void toggleMapFullscreen()}
          className="absolute top-4 left-4 z-[1100] flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm shadow-lg hover:bg-muted/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          aria-pressed={mapFullscreen}
          title={mapFullscreen ? 'Exit full screen' : 'Full screen map'}
        >
          {mapFullscreen ? <Minimize2 className="h-4 w-4" /> : <Maximize2 className="h-4 w-4" />}
          <span className="hidden sm:inline">{mapFullscreen ? 'Exit full screen' : 'Full screen'}</span>
        </button>

        <div className="absolute top-4 right-4 bg-card border border-border rounded-lg p-4 shadow-lg z-[1000] max-w-[220px] text-xs leading-snug">
          <div className="font-semibold text-sm mb-2">Activity scale</div>
          {mode === 'parking' ? (
            <>
              <div className="h-3 rounded border border-border/50" style={{ background: legendGradientStopped }} />
              <div className="mt-1 text-muted-foreground text-[10px]">
                Green → yellow → orange → red; continuous city-scale blend.
              </div>
            </>
          ) : (
            <div className="h-3 rounded border border-border/50" style={{ background: legendGradientMoving }} />
          )}
          <div className="mt-2 text-muted-foreground">
            {normLabel === 'max' ? 'Absolute max per layer' : normLabel === 'p99' ? 'Capped at p99' : 'Capped at p95'}
            {mode === 'driving' && mapDebug.intensity_gamma != null ? ` · moving γ=${mapDebug.intensity_gamma}` : ''}
            {mode === 'parking' ? ` · parking ^${mapDebug.intensity_stopped_power ?? 0.7}` : ''}
          </div>
          <div className="mt-1 text-muted-foreground text-[10px]">
            Parking boosts mid-density (capped ratio to power below 1).
          </div>
        </div>
      </div>

      <div className="p-4 border-t border-border bg-card">
        {summary?.data_source === 'insufficient_aggregates' ? (
          <p className="text-xs text-muted-foreground mb-3">
            Period KPIs need daily_impressions (and related aggregates). Expand the date range or run daily telemetry jobs — raw map points may still appear from rollups.
          </p>
        ) : null}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="flex items-center gap-3 p-4 bg-muted rounded-lg">
            <Eye className="w-5 h-5" style={{ color: '#C1F60D' }} />
            <div>
              <div className="text-2xl" style={{ color: '#C1F60D' }}>
                {summary?.impressions != null ? summary.impressions.toLocaleString() : '—'}
              </div>
              <div className="text-xs text-muted-foreground">Impressions</div>
            </div>
          </div>
          <div className="flex items-center gap-3 p-4 bg-muted rounded-lg">
            <Navigation className="w-5 h-5 text-accent" />
            <div>
              <div className="text-2xl">
                {summary?.driving_distance_km != null ? `${summary.driving_distance_km} km` : '—'}
              </div>
              <div className="text-xs text-muted-foreground">Driving distance</div>
            </div>
          </div>
          <div className="flex items-center gap-3 p-4 bg-muted rounded-lg">
            <Clock className="w-5 h-5" />
            <div>
              <div className="text-2xl">
                {summary?.driving_time_hours != null ? `${summary.driving_time_hours} hrs` : '—'}
              </div>
              <div className="text-xs text-muted-foreground">Driving time</div>
            </div>
          </div>
          <div className="flex items-center gap-3 p-4 bg-muted rounded-lg">
            <Clock className="w-5 h-5" />
            <div>
              <div className="text-2xl">
                {summary?.parking_time_hours != null ? `${summary.parking_time_hours} hrs` : '—'}
              </div>
              <div className="text-xs text-muted-foreground">Parking time</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
