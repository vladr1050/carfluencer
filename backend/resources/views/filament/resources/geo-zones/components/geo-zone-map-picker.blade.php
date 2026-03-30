@php
    /** @var array{url: string, attribution: string, subdomains: string|null, max_zoom: int} $tileLayer */
    /** @var array{min_lat: mixed, max_lat: mixed, min_lng: mixed, max_lng: mixed, polygon_geojson: mixed} $initial */
    $mapId = 'geo-zone-map-picker';
@endphp
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" crossorigin="" />
<style>
    #{{ $mapId }} .leaflet-container { background: #e8e8e8; }
</style>
<div class="fi-geo-zone-map space-y-2">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ __('Draw a polygon or a rectangle on the map. Session centers must fall inside the shape. Vertices can be dragged after drawing. The bounding box fields update from the shape. “Refresh map from fields” replaces the shape with a rectangle from the numbers and clears the polygon.') }}
    </p>
    <div>
        <button
            type="button"
            id="geo-zone-map-refresh-from-fields"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
        >
            {{ __('Refresh map from fields') }}
        </button>
    </div>
    <div
        wire:ignore
        id="{{ $mapId }}"
        class="z-0 w-full rounded-lg border border-gray-200 dark:border-gray-700"
        style="height: min(420px, 50vh); min-height: 280px; background-color: #e8e8e8;"
    ></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js" crossorigin=""></script>
<script>
(function () {
    const mapId = @js($mapId);
    const tileLayer = @js($tileLayer);
    const initial = @js($initial);

    function roundCoord(v) {
        const n = Number(v);
        if (!Number.isFinite(n)) return null;
        return Math.round(n * 1e7) / 1e7;
    }

    function parseInitialBounds() {
        const minLat = roundCoord(initial.min_lat);
        const maxLat = roundCoord(initial.max_lat);
        const minLng = roundCoord(initial.min_lng);
        const maxLng = roundCoord(initial.max_lng);
        if (
            minLat === null || maxLat === null || minLng === null || maxLng === null ||
            minLat >= maxLat || minLng >= maxLng
        ) {
            return null;
        }
        return L.latLngBounds([minLat, minLng], [maxLat, maxLng]);
    }

    function initialPolygonLatLngs() {
        const p = initial.polygon_geojson;
        if (!p || p.type !== 'Polygon' || !Array.isArray(p.coordinates) || !Array.isArray(p.coordinates[0])) {
            return null;
        }
        const ring = p.coordinates[0];
        if (ring.length < 3) return null;
        return ring.map(function (pt) {
            if (!Array.isArray(pt) || pt.length < 2) return null;
            return L.latLng(Number(pt[1]), Number(pt[0]));
        }).filter(Boolean);
    }

    function livewireFromMapEl(el) {
        if (!window.Livewire || !el) return null;
        const root = el.closest('[wire\\:id]');
        if (!root) return null;
        const id = root.getAttribute('wire:id');
        if (!id) return null;
        return window.Livewire.find(id);
    }

    function ringLatLngsToGeoJson(latlngs) {
        const ring = [];
        for (let i = 0; i < latlngs.length; i++) {
            const ll = L.latLng(latlngs[i]);
            const lng = roundCoord(ll.lng);
            const lat = roundCoord(ll.lat);
            if (lng === null || lat === null) return null;
            ring.push([lng, lat]);
        }
        if (ring.length < 3) return null;
        const a = ring[0];
        const b = ring[ring.length - 1];
        if (a[0] !== b[0] || a[1] !== b[1]) {
            ring.push([a[0], a[1]]);
        }
        return { type: 'Polygon', coordinates: [ring] };
    }

    function rectangleToGeoJson(layer) {
        const b = layer.getBounds();
        const nw = b.getNorthWest();
        const ne = b.getNorthEast();
        const se = b.getSouthEast();
        const sw = b.getSouthWest();
        return ringLatLngsToGeoJson([nw, ne, se, sw]);
    }

    function layerToGeoJsonAndBounds(layer) {
        if (layer instanceof L.Rectangle) {
            const gj = rectangleToGeoJson(layer);
            return gj ? { geojson: gj, bounds: layer.getBounds() } : null;
        }
        if (layer instanceof L.Polygon) {
            const raw = layer.getLatLngs();
            const ring = Array.isArray(raw[0]) ? raw[0] : raw;
            const gj = ringLatLngsToGeoJson(ring);
            return gj ? { geojson: gj, bounds: layer.getBounds() } : null;
        }
        return null;
    }

    function pushShapeToForm(cmp, geojson, bounds) {
        const live = true;
        cmp.set('data.polygon_geojson', geojson, live);
        const sw = bounds.getSouthWest();
        const ne = bounds.getNorthEast();
        cmp.set('data.min_lat', roundCoord(sw.lat), live);
        cmp.set('data.min_lng', roundCoord(sw.lng), live);
        cmp.set('data.max_lat', roundCoord(ne.lat), live);
        cmp.set('data.max_lng', roundCoord(ne.lng), live);
    }

    function readBoundsFromInputs() {
        const ids = ['min_lat', 'max_lat', 'min_lng', 'max_lng'];
        const vals = {};
        for (const key of ids) {
            const el = document.getElementById('geo-zone-input-' + key);
            if (!el) return null;
            const n = parseFloat(String(el.value).replace(',', '.'));
            if (!Number.isFinite(n)) return null;
            vals[key] = n;
        }
        if (vals.min_lat >= vals.max_lat || vals.min_lng >= vals.max_lng) return null;
        return L.latLngBounds(
            [vals.min_lat, vals.min_lng],
            [vals.max_lat, vals.max_lng],
        );
    }

    function init() {
        const el = document.getElementById(mapId);
        if (!el || el._geoZoneLeafletMap) return;

        const map = L.map(el, { zoomControl: true });
        const subdomains = tileLayer.subdomains ? String(tileLayer.subdomains).split('') : false;
        L.tileLayer(tileLayer.url, {
            attribution: tileLayer.attribution,
            maxZoom: tileLayer.max_zoom,
            ...(subdomains ? { subdomains } : {}),
        }).addTo(map);

        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        const drawControl = new L.Control.Draw({
            position: 'topright',
            draw: {
                polygon: {
                    allowIntersection: false,
                    showArea: false,
                    shapeOptions: {
                        color: '#2563eb',
                        weight: 2,
                    },
                },
                rectangle: {
                    shapeOptions: {
                        color: '#2563eb',
                        weight: 2,
                    },
                },
                polyline: false,
                circle: false,
                marker: false,
                circlemarker: false,
            },
            edit: {
                featureGroup: drawnItems,
                remove: true,
            },
        });
        map.addControl(drawControl);

        function syncFromLayer(layer) {
            const cmp = livewireFromMapEl(el);
            if (!cmp || typeof cmp.set !== 'function') return;
            const parsed = layerToGeoJsonAndBounds(layer);
            if (!parsed) return;
            pushShapeToForm(cmp, parsed.geojson, parsed.bounds);
        }

        function replaceRectangle(bounds) {
            drawnItems.clearLayers();
            const rect = L.rectangle(bounds, {
                color: '#2563eb',
                weight: 2,
            });
            drawnItems.addLayer(rect);
            map.fitBounds(bounds.pad(0.08));
        }

        function loadInitialShape() {
            const latlngs = initialPolygonLatLngs();
            if (latlngs && latlngs.length >= 3) {
                drawnItems.clearLayers();
                const poly = L.polygon(latlngs, { color: '#2563eb', weight: 2 });
                drawnItems.addLayer(poly);
                map.fitBounds(poly.getBounds().pad(0.08));
                return;
            }
            const initialBounds = parseInitialBounds();
            if (initialBounds) {
                replaceRectangle(initialBounds);
                return;
            }
            map.setView([56.95, 24.1], 11);
        }

        map.on('draw:created', function (e) {
            if (e.layerType !== 'rectangle' && e.layerType !== 'polygon') return;
            drawnItems.clearLayers();
            drawnItems.addLayer(e.layer);
            syncFromLayer(e.layer);
            map.fitBounds(e.layer.getBounds().pad(0.08));
        });

        map.on('draw:edited', function (e) {
            e.layers.eachLayer(function (layer) {
                syncFromLayer(layer);
            });
        });

        map.on('draw:deleted', function () {
            const cmp = livewireFromMapEl(el);
            if (cmp && typeof cmp.set === 'function') {
                cmp.set('data.polygon_geojson', null, true);
            }
        });

        const btn = document.getElementById('geo-zone-map-refresh-from-fields');
        if (btn) {
            btn.addEventListener('click', function () {
                const cmp = livewireFromMapEl(el);
                if (cmp && typeof cmp.set === 'function') {
                    cmp.set('data.polygon_geojson', null, true);
                }
                const b = readBoundsFromInputs();
                if (!b) return;
                replaceRectangle(b);
            });
        }

        loadInitialShape();

        el._geoZoneLeafletMap = map;
    }

    function teardownThenInit() {
        const el = document.getElementById(mapId);
        if (el && el._geoZoneLeafletMap) {
            el._geoZoneLeafletMap.remove();
            delete el._geoZoneLeafletMap;
        }
        init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', teardownThenInit);
})();
</script>
