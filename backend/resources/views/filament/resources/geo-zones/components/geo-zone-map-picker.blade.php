@php
    /** @var array{url: string, attribution: string, subdomains: string|null, max_zoom: int} $tileLayer */
    /** @var array{min_lat: mixed, max_lat: mixed, min_lng: mixed, max_lng: mixed} $initial */
    $mapId = 'geo-zone-map-picker';
@endphp
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" crossorigin="" />
<style>
    #{{ $mapId }} .leaflet-container { background: #e8e8e8; }
</style>
<div class="fi-geo-zone-map space-y-2">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ __('Use the rectangle tool on the map to draw the zone. Drag corners to adjust. Values above update automatically. After editing numbers, click the button to move the rectangle on the map.') }}
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

    function livewireFromMapEl(el) {
        if (!window.Livewire || !el) return null;
        const root = el.closest('[wire\\:id]');
        if (!root) return null;
        const id = root.getAttribute('wire:id');
        if (!id) return null;
        return window.Livewire.find(id);
    }

    function pushBoundsToForm(cmp, bounds) {
        const sw = bounds.getSouthWest();
        const ne = bounds.getNorthEast();
        const live = true;
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
                polygon: false,
                polyline: false,
                circle: false,
                marker: false,
                circlemarker: false,
                rectangle: {
                    shapeOptions: {
                        color: '#2563eb',
                        weight: 2,
                    },
                },
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
            const b = layer.getBounds();
            pushBoundsToForm(cmp, b);
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

        map.on('draw:created', function (e) {
            if (e.layerType !== 'rectangle') return;
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
            /* Keep form values; user can refresh from fields or draw again. */
        });

        const btn = document.getElementById('geo-zone-map-refresh-from-fields');
        if (btn) {
            btn.addEventListener('click', function () {
                const b = readBoundsFromInputs();
                if (!b) return;
                replaceRectangle(b);
            });
        }

        const initialBounds = parseInitialBounds();
        if (initialBounds) {
            replaceRectangle(initialBounds);
        } else {
            map.setView([56.95, 24.1], 11);
        }

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
