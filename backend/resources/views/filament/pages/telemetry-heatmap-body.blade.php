@php
    $maptilerKey = filled(config('services.maptiler.api_key')) ? (string) config('services.maptiler.api_key') : '';
    $heatmapShadowBootstrap = isset($heatmapShadow) && in_array($heatmapShadow, ['current', 'small', 'xsmall'], true) ? $heatmapShadow : 'current';
    $heatmapViewMode = isset($heatmapMapView) && $heatmapMapView === 'grid' ? 'grid' : 'heatmap';
@endphp
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    #admin-telemetry-map .leaflet-container { background: #e8e8e8; }
    #admin-hm-map-wrap { position: relative; }
    #admin-hm-legend {
        position: absolute;
        z-index: 1000;
        bottom: 12px;
        left: 12px;
        max-width: 220px;
        padding: 10px 12px;
        border-radius: 8px;
        font-size: 11px;
        line-height: 1.35;
        background: rgba(255, 255, 255, 0.94);
        border: 1px solid rgba(0, 0, 0, 0.12);
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        color: #1a1a1a;
    }
    .dark #admin-hm-legend {
        background: rgba(30, 30, 30, 0.94);
        border-color: rgba(255, 255, 255, 0.12);
        color: #f3f4f6;
    }
    #admin-hm-legend .hm-leg-bar {
        height: 10px;
        border-radius: 3px;
        margin: 6px 0 4px;
        border: 1px solid rgba(0, 0, 0, 0.15);
    }
    #admin-hm-toolbar select {
        border-radius: 6px;
        border: 1px solid rgb(209 213 219);
        padding: 4px 8px;
        font-size: 13px;
        background: white;
    }
    .dark #admin-hm-toolbar select {
        background: rgb(31 41 55);
        border-color: rgb(75 85 99);
        color: #f3f4f6;
    }
</style>
<div class="space-y-3">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ __('Moving = smooth Viridis flow. Parking = continuous green→yellow→orange→red density (p95/p99 cap, ratio^0.7 for mids). Softer, wider heat blend. Optional iso-rings. Grid = discrete cells.') }}
    </p>
    <div id="admin-hm-toolbar" class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-gray-700 dark:text-gray-300">
        <label class="inline-flex items-center gap-2">
            <span class="font-medium">{{ __('View') }}</span>
            <select id="admin-hm-view-mode" aria-label="{{ __('View mode') }}">
                <option value="heatmap" @selected($heatmapViewMode === 'heatmap')>{{ __('Heatmap') }}</option>
                <option value="grid" @selected($heatmapViewMode === 'grid')>{{ __('Grid') }}</option>
            </select>
        </label>
        <label id="admin-hm-grid-metric-wrap" class="hidden inline-flex items-center gap-2">
            <span class="font-medium">{{ __('Grid metric') }}</span>
            <select id="admin-hm-grid-metric" aria-label="{{ __('Grid metric') }}">
                <option value="moving">{{ __('Moving samples') }}</option>
                <option value="stopped">{{ __('Stopped samples') }}</option>
            </select>
        </label>
        <label id="admin-hm-contour-wrap" class="hidden inline-flex items-center gap-2 cursor-pointer">
            <input type="checkbox" id="admin-hm-parking-contours" class="rounded border-gray-300" />
            <span>{{ __('Parking iso-rings (experimental)') }}</span>
        </label>
        <p class="w-full text-xs text-gray-500 dark:text-gray-400 sm:w-auto sm:flex-none">
            {{ __('Shadow size is set in the filters above (same as URL form[heatmap_shadow]).') }}
        </p>
    </div>
    <div id="admin-hm-map-wrap">
        <div
            id="admin-telemetry-map"
            wire:ignore
            class="z-0 w-full rounded-lg border border-gray-200 dark:border-gray-700"
            style="height: min(560px, 55vh); min-height: 320px; background-color: #e8e8e8;"
        ></div>
        <div id="admin-hm-legend" style="display: none;"></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
(function () {
    const GRADIENT_MOVING = { 0.0: '#440154', 0.25: '#3b528b', 0.5: '#21918c', 0.75: '#5ec962', 1.0: '#fde725' };
    /** Parking: Google-style traffic density (green → yellow → orange → red, no white peak). */
    const GRADIENT_STOPPED = { 0.0: '#1b5e20', 0.22: '#43a047', 0.45: '#c6d84a', 0.62: '#ffeb3b', 0.8: '#fb8c00', 1.0: '#c62828' };
    /**
     * Leaflet.heat spot spread presets (moving + parking). Keys: current | small | xsmall.
     * "current" matches historical production defaults.
     */
    const HEAT_SHADOW_PRESETS = {
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
    const HEAT_MIN_OPACITY = 0.16;
    const HEAT_MAX_DENOM_MOVING = 2.15;
    const PARKING_HEAT_MIN_OPACITY = 0.27;
    const PARKING_HEAT_MAX_DENOM = 1.82;
    /** Iso-ring stroke colors by intensity tier (green → red). */
    const PARKING_CONTOUR_COLORS = ['#2e7d32', '#66bb6a', '#cddc39', '#ffca28', '#f57c00', '#b71c1c'];

    function parkingBandIndex(t) {
        const x = Math.max(0, Math.min(1, Number(t)));
        if (x <= 0.12) {
            return 0;
        }
        if (x <= 0.28) {
            return 1;
        }
        if (x <= 0.44) {
            return 2;
        }
        if (x <= 0.6) {
            return 3;
        }
        if (x <= 0.78) {
            return 4;
        }

        return 5;
    }
    const CELL_HALF = 0.0005;

    if (typeof window.__heatmapShadowPreset === 'undefined' || window.__heatmapShadowPreset === null || window.__heatmapShadowPreset === '') {
        window.__heatmapShadowPreset = {!! json_encode($heatmapShadowBootstrap) !!};
    }

    function heatmapShadowKey() {
        const k = String(window.__heatmapShadowPreset || 'current');
        if (k === 'small' || k === 'xsmall') {
            return k;
        }

        return 'current';
    }

    function movingHeatDims() {
        return HEAT_SHADOW_PRESETS[heatmapShadowKey()].moving;
    }

    function parkingHeatDims() {
        return HEAT_SHADOW_PRESETS[heatmapShadowKey()].parking;
    }

    /** Scale parking contour circle radii relative to production parking heat radius. */
    function parkingContourSizeFactor() {
        const base = HEAT_SHADOW_PRESETS.current.parking.radius;
        return parkingHeatDims().radius / base;
    }

    let map = null;
    let heatLayerMoving = null;
    let heatLayerStopped = null;
    let parkingContourLayer = null;
    let gridLayer = null;
    let resizeObserver = null;
        let toolbarBound = false;
        let heatmapRefetchTimer = null;
    const defaultCenter = [56.88, 24.6];
    const defaultZoom = 7;
    const maptilerKey = {!! json_encode($maptilerKey) !!};
    const positronTileUrl = maptilerKey
        ? ('https://api.maptiler.com/maps/positron/{z}/{x}/{y}.png?key=' + encodeURIComponent(maptilerKey))
        : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png';
    const positronAttribution = maptilerKey
        ? '<a href="https://www.maptiler.com/copyright/">MapTiler</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        : '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>';

    function invalidateMapSize() {
        if (map) {
            map.invalidateSize({ animate: false });
        }
    }

    function ensureBaseMap() {
        const el = document.getElementById('admin-telemetry-map');
        if (!el || el.offsetHeight < 32) {
            return false;
        }
        if (map) {
            invalidateMapSize();
            return true;
        }
        map = L.map(el, { preferCanvas: true }).setView(defaultCenter, defaultZoom);
        map.on('moveend', scheduleAdminHeatmapRefetch);
        map.on('zoomend', scheduleAdminHeatmapRefetch);
        L.tileLayer(positronTileUrl, maptilerKey ? {
            maxZoom: 20,
            attribution: positronAttribution,
        } : {
            subdomains: 'abcd',
            maxZoom: 20,
            attribution: positronAttribution,
        }).addTo(map);
        if (resizeObserver) {
            resizeObserver.disconnect();
        }
        resizeObserver = new ResizeObserver(function () {
            invalidateMapSize();
        });
        resizeObserver.observe(el);
        requestAnimationFrame(invalidateMapSize);
        setTimeout(invalidateMapSize, 200);
        setTimeout(invalidateMapSize, 600);
        return true;
    }

    function tryInitMap() {
        if (ensureBaseMap()) {
            return;
        }
        setTimeout(tryInitMap, 100);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInitMap);
    } else {
        tryInitMap();
    }

    document.addEventListener('livewire:navigated', function () {
        heatLayerMoving = null;
        heatLayerStopped = null;
        parkingContourLayer = null;
        gridLayer = null;
        toolbarBound = false;
        if (resizeObserver) {
            resizeObserver.disconnect();
            resizeObserver = null;
        }
        if (map) {
            try {
                map.remove();
            } catch (e) { /* ignore */ }
            map = null;
        }
        tryInitMap();
    });

    function parseHex(h) {
        const s = h.replace('#', '');
        return [parseInt(s.slice(0, 2), 16), parseInt(s.slice(2, 4), 16), parseInt(s.slice(4, 6), 16)];
    }

    function mix(a, b, t) {
        return [
            Math.round(a[0] + (b[0] - a[0]) * t),
            Math.round(a[1] + (b[1] - a[1]) * t),
            Math.round(a[2] + (b[2] - a[2]) * t),
        ];
    }

    function rgbToHex(rgb) {
        return '#' + rgb.map(function (x) {
            const h = Math.max(0, Math.min(255, x)).toString(16);
            return h.length === 1 ? '0' + h : h;
        }).join('');
    }

    /** @param {Array<[number, string]>} stops sorted by t 0..1 */
    function colorFromStops(t, stops) {
        t = Math.max(0, Math.min(1, t));
        for (let i = 0; i < stops.length - 1; i++) {
            const [t0, c0] = stops[i];
            const [t1, c1] = stops[i + 1];
            if (t <= t1 || i === stops.length - 2) {
                if (t1 <= t0) {
                    return c1;
                }
                const u = (t - t0) / (t1 - t0);
                return rgbToHex(mix(parseHex(c0), parseHex(c1), Math.max(0, Math.min(1, u))));
            }
        }
        return stops[stops.length - 1][1];
    }

    const STOPS_MOVING = [[0, '#440154'], [0.25, '#3b528b'], [0.5, '#21918c'], [0.75, '#5ec962'], [1, '#fde725']];
    const STOPS_STOPPED = [[0, '#1b5e20'], [0.22, '#43a047'], [0.45, '#c6d84a'], [0.62, '#ffeb3b'], [0.8, '#fb8c00'], [1, '#c62828']];

    function clearHeatmapLayers() {
        if (heatLayerMoving && map) {
            map.removeLayer(heatLayerMoving);
        }
        if (heatLayerStopped && map) {
            map.removeLayer(heatLayerStopped);
        }
        if (parkingContourLayer && map) {
            map.removeLayer(parkingContourLayer);
        }
        heatLayerMoving = null;
        heatLayerStopped = null;
        parkingContourLayer = null;
        if (gridLayer && map) {
            map.removeLayer(gridLayer);
        }
        gridLayer = null;
    }

    function buildLegendHtml(metrics, motion, viewMode) {
        const norm = metrics && metrics.normalization ? String(metrics.normalization) : 'p95';
        const capM = metrics && metrics.cap_moving != null ? metrics.cap_moving : '—';
        const capS = metrics && metrics.cap_stopped != null ? metrics.cap_stopped : '—';
        const normNote = norm === 'max'
            ? '{{ __('Scale: absolute max per layer.') }}'
            : (norm === 'p99'
                ? '{{ __('Scale capped at p99 sample count per layer.') }}'
                : '{{ __('Scale capped at p95 sample count per layer.') }}');

        let title = '{{ __('Heat intensity') }}';
        if (motion === 'moving') {
            title = '{{ __('Moving — sample density') }}';
        } else {
            title = '{{ __('Parking — city density (green → red)') }}';
        }

        const barMoving = 'linear-gradient(90deg, #440154 0%, #3b528b 25%, #21918c 50%, #5ec962 75%, #fde725 100%)';
        const barStopped = 'linear-gradient(90deg, #1b5e20 0%, #43a047 22%, #c6d84a 45%, #ffeb3b 62%, #fb8c00 80%, #c62828 100%)';

        let bars = '';
        if (motion === 'stopped') {
            bars = '<div class="hm-leg-bar" style="background:' + barStopped + ';"></div>'
                + '<div class="text-[10px] flex flex-wrap gap-x-1 justify-between"><span>{{ __('Low') }}</span><span>{{ __('Mid') }}</span><span>{{ __('High') }}</span><span>{{ __('Peak') }}</span></div>'
                + '<div class="text-[10px] opacity-75 mt-1">{{ __('Intensity: min(1, w/cap) then ^0.7 (cap = p95/p99 or max).') }}</div>';
        } else {
            bars = '<div class="hm-leg-bar" style="background:' + barMoving + ';"></div>'
                + '<div class="text-[10px] flex justify-between"><span>{{ __('Low') }}</span><span>{{ __('Medium') }}</span><span>{{ __('High') }}</span><span>{{ __('Peak') }}</span></div>';
        }

        const gridNote = viewMode === 'grid'
            ? '<div class="mt-2 text-[10px] opacity-80">{{ __('Grid uses the same bucket resolution as the current zoom tier (rollup).') }}</div>'
            : '';

        return '<div class="font-semibold">' + title + '</div>'
            + bars
            + '<div class="mt-2 text-[10px] opacity-85">' + normNote + '</div>'
            + '<div class="text-[10px] opacity-75 mt-1">{{ __('Cap moving') }}: ' + capM + ' · {{ __('Cap stopped') }}: ' + capS + '</div>'
            + gridNote;
    }

    function renderGrid(buckets, metric, capMoving, capStopped, gamma) {
        const layer = L.layerGroup();
        const stops = metric === 'stopped' ? STOPS_STOPPED : STOPS_MOVING;
        const cap = metric === 'stopped' ? Math.max(1, capStopped) : Math.max(1, capMoving);
        const g = Number(gamma) > 0 ? Number(gamma) : 1.55;

        buckets.forEach(function (b) {
            const w = metric === 'stopped' ? (b.w_stopped || 0) : (b.w_moving || 0);
            if (w <= 0) {
                return;
            }
            const ratio = Math.min(1, w / cap);
            const intenMoving = g <= 1 ? ratio : Math.min(1, Math.pow(ratio, g));
            let fill;
            if (metric === 'stopped') {
                const serverIs = b.intensity_stopped != null ? Number(b.intensity_stopped) : Math.min(1, Math.pow(ratio, 0.7));
                fill = colorFromStops(serverIs, stops);
            } else {
                fill = colorFromStops(intenMoving, stops);
            }
            const lat = Number(b.lat);
            const lng = Number(b.lng);
            const bounds = [[lat - CELL_HALF, lng - CELL_HALF], [lat + CELL_HALF, lng + CELL_HALF]];
            const rect = L.rectangle(bounds, {
                stroke: true,
                color: 'rgba(0,0,0,0.25)',
                weight: 1,
                fillColor: fill,
                fillOpacity: metric === 'stopped' ? 0.82 : 0.72,
            });
            layer.addLayer(rect);
        });
        return layer;
    }

    function scheduleAdminHeatmapRefetch() {
        if (!window.__adminHeatmapBaseQuery || !window.__adminHeatmapDataUrl) {
            return;
        }
        const last = window.__adminHeatmapLastPayload;
        if (!last || !last.heatmap || !last.heatmap.metrics || !last.heatmap.metrics.heatmap_rollup) {
            return;
        }
        if (heatmapRefetchTimer) {
            clearTimeout(heatmapRefetchTimer);
        }
        heatmapRefetchTimer = setTimeout(function () {
            heatmapRefetchTimer = null;
            if (typeof window.adminHeatmapFetchWithViewport === 'function') {
                window.adminHeatmapFetchWithViewport(true);
            }
        }, 480);
    }

    window.adminHeatmapFetchWithViewport = function (isAuto) {
        const base = window.__adminHeatmapBaseQuery;
        const urlBase = window.__adminHeatmapDataUrl;
        if (!base || !urlBase || !map) {
            return;
        }
        const b = map.getBounds();
        const params = new URLSearchParams();
        Object.keys(base).forEach(function (k) {
            const v = base[k];
            if (v === null || v === undefined || v === '') {
                return;
            }
            if (Array.isArray(v)) {
                v.forEach(function (x) {
                    params.append(k + '[]', String(x));
                });
            } else {
                params.set(k, String(v));
            }
        });
        params.set('south', String(b.getSouth()));
        params.set('west', String(b.getWest()));
        params.set('north', String(b.getNorth()));
        params.set('east', String(b.getEast()));
        params.set('zoom', String(map.getZoom()));
        fetch(urlBase + '?' + params.toString(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) {
            if (!r.ok) {
                return r.json().then(function (j) {
                    throw new Error(j.error || j.message || String(r.status));
                });
            }
            return r.json();
        }).then(function (payload) {
            window.renderAdminTelemetryHeatmap(payload);
        }).catch(function (e) {
            console.error('admin heatmap fetch', e);
        });
    };

    function redrawFromState() {
        const payload = window.__adminHeatmapLastPayload;
        if (!payload || !map) {
            return;
        }
        const motion = (payload.filter && payload.filter.motion) ? payload.filter.motion : 'moving';
        const metrics = (payload.heatmap && payload.heatmap.metrics) ? payload.heatmap.metrics : {};
        const buckets = (payload.heatmap && payload.heatmap.buckets) ? payload.heatmap.buckets : [];
        const rollup = !!(metrics && metrics.heatmap_rollup);
        const viewModeEl = document.getElementById('admin-hm-view-mode');
        const viewMode = viewModeEl ? viewModeEl.value : 'heatmap';
        const gridMetricEl = document.getElementById('admin-hm-grid-metric');
        if (gridMetricEl) {
            gridMetricEl.value = motion === 'stopped' ? 'stopped' : 'moving';
        }
        const gridMetric = gridMetricEl ? gridMetricEl.value : 'moving';

        clearHeatmapLayers();

        const leg = document.getElementById('admin-hm-legend');
        if (leg) {
            leg.style.display = buckets.length ? 'block' : 'none';
            leg.innerHTML = buildLegendHtml(metrics, motion, viewMode);
        }

        const gridWrap = document.getElementById('admin-hm-grid-metric-wrap');
        if (gridWrap) {
            gridWrap.classList.toggle('hidden', viewMode !== 'grid');
        }

        if (buckets.length === 0) {
            map.setView(defaultCenter, defaultZoom);
            requestAnimationFrame(invalidateMapSize);
            return;
        }

        const capM = Number(metrics.cap_moving) || 1;
        const capS = Number(metrics.cap_stopped) || 1;
        const gamma = metrics.intensity_gamma != null ? Number(metrics.intensity_gamma) : 1.55;

        if (viewMode === 'grid') {
            gridLayer = renderGrid(buckets, gridMetric, capM, capS, gamma);
            gridLayer.addTo(map);
            if (!rollup) {
                try {
                    map.fitBounds(gridLayer.getBounds(), { padding: [48, 48], maxZoom: 16 });
                } catch (e) {
                    map.setView([Number(buckets[0].lat), Number(buckets[0].lng)], 11);
                }
            }
        } else {
            const heatM = buckets.filter(function (b) { return (b.w_moving || 0) > 0; }).map(function (b) {
                return [Number(b.lat), Number(b.lng), Number(b.intensity_moving) || 0];
            });
            const heatS = buckets.filter(function (b) { return (b.w_stopped || 0) > 0; }).map(function (b) {
                return [Number(b.lat), Number(b.lng), Number(b.intensity_stopped) || 0];
            });

            const pd = parkingHeatDims();
            const md = movingHeatDims();
            const parkingHeatOpts = {
                radius: pd.radius,
                blur: pd.blur,
                maxZoom: 17,
                minOpacity: PARKING_HEAT_MIN_OPACITY,
                max: 1.0 / PARKING_HEAT_MAX_DENOM,
                gradient: GRADIENT_STOPPED,
            };

            if (motion === 'moving' && heatM.length && typeof L.heatLayer === 'function') {
                heatLayerMoving = L.heatLayer(heatM, {
                    radius: md.radius,
                    blur: md.blur,
                    maxZoom: 17,
                    minOpacity: HEAT_MIN_OPACITY,
                    max: 1.0 / HEAT_MAX_DENOM_MOVING,
                    gradient: GRADIENT_MOVING,
                });
                heatLayerMoving.addTo(map);
            } else if (motion === 'stopped' && heatS.length && typeof L.heatLayer === 'function') {
                heatLayerStopped = L.heatLayer(heatS, parkingHeatOpts);
                heatLayerStopped.addTo(map);
            }

            var contourEl = document.getElementById('admin-hm-parking-contours');
            var contourOn = contourEl && contourEl.checked;
            if (contourOn && motion === 'stopped') {
                parkingContourLayer = L.layerGroup();
                var contourMul = parkingContourSizeFactor();
                buckets.forEach(function (b) {
                    if ((b.w_stopped || 0) <= 0) {
                        return;
                    }
                    var band = parkingBandIndex(Number(b.intensity_stopped) || 0);
                    if (band < 2) {
                        return;
                    }
                    var color = PARKING_CONTOUR_COLORS[band];
                    var c = L.circle([Number(b.lat), Number(b.lng)], {
                        radius: (36 + band * 30) * contourMul,
                        color: color,
                        weight: 1.5,
                        opacity: 0.45,
                        fillColor: color,
                        fillOpacity: 0.05 + band * 0.014,
                        interactive: false,
                    });
                    parkingContourLayer.addLayer(c);
                });
                if (parkingContourLayer.getLayers().length) {
                    parkingContourLayer.addTo(map);
                } else {
                    parkingContourLayer = null;
                }
            }

            const layers = [];
            if (heatLayerMoving) {
                layers.push(heatLayerMoving);
            }
            if (heatLayerStopped) {
                layers.push(heatLayerStopped);
            }
            if (!rollup) {
                if (layers.length) {
                    try {
                        const fg = L.featureGroup(layers);
                        map.fitBounds(fg.getBounds(), { padding: [56, 56], maxZoom: 16 });
                    } catch (e) {
                        map.setView([Number(buckets[0].lat), Number(buckets[0].lng)], 11);
                    }
                } else {
                    map.setView([Number(buckets[0].lat), Number(buckets[0].lng)], 11);
                }
            }

        }

        requestAnimationFrame(invalidateMapSize);
        setTimeout(invalidateMapSize, 300);
    }

    function bindToolbarOnce() {
        if (toolbarBound) {
            return;
        }
        toolbarBound = true;
        ['admin-hm-view-mode', 'admin-hm-grid-metric', 'admin-hm-parking-contours'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', redrawFromState);
                el.addEventListener('input', redrawFromState);
            }
        });
    }

    window.renderAdminTelemetryHeatmap = function (payload) {
        window.__adminHeatmapLastPayload = payload;
        const motion = (payload.filter && payload.filter.motion) ? payload.filter.motion : 'moving';
        const contourWrap = document.getElementById('admin-hm-contour-wrap');
        if (contourWrap) {
            contourWrap.classList.toggle('hidden', motion !== 'stopped');
        }

        if (!ensureBaseMap()) {
            return;
        }
        bindToolbarOnce();
        redrawFromState();
    };

    /** Redraw heatmap/grid from cached payload (e.g. shadow preset changed; no refetch). */
    window.adminHeatmapRedrawFromCache = redrawFromState;
})();
</script>
