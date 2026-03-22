<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ __('Aggregated buckets from PostgreSQL `device_locations` for the filters above (campaign / vehicle(s), period, moving vs stopped).') }}
    </p>
    {{-- min-height + vh: Filament flex layout can collapse fixed px height; map must exist before first "Load" --}}
    <div
        id="admin-telemetry-map"
        wire:ignore
        class="z-0 w-full rounded-lg border border-gray-200 dark:border-gray-700"
        style="height: min(560px, 55vh); min-height: 320px;"
    ></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
(function () {
    let map = null;
    let heatLayer = null;
    let resizeObserver = null;
    const defaultCenter = [54.6872, 25.2797];
    const defaultZoom = 7;

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
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
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
        heatLayer = null;
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

    /**
     * Same leaflet.heat options as Advertiser portal (design/Files/.../Heatmap.tsx HeatmapLayer).
     * motion: moving → driving gradient, stopped → parking, both → combined.
     */
    function adminHeatGradientForMotion(motion) {
        if (motion === 'stopped') {
            return { 0.0: '#000000', 0.3: '#F10DBF', 0.6: '#F10DBF', 1.0: '#FFFFFF' };
        }
        if (motion === 'moving') {
            return { 0.0: '#000000', 0.3: '#C1F60D', 0.6: '#C1F60D', 1.0: '#FFFFFF' };
        }

        return { 0.0: '#000000', 0.3: '#C1F60D', 0.5: '#F10DBF', 0.7: '#C1F60D', 1.0: '#FFFFFF' };
    }

    window.renderAdminTelemetryHeatmap = function (payload) {
        const pts = (payload.heatmap && payload.heatmap.points) ? payload.heatmap.points : [];
        const motion = (payload.filter && payload.filter.motion) ? payload.filter.motion : 'both';
        const heatData = pts.map(function (p) {
            const w = Number(p.intensity);

            return [Number(p.lat), Number(p.lng), Number.isFinite(w) ? w : 0];
        });

        const el = document.getElementById('admin-telemetry-map');
        if (!el) {
            return;
        }

        if (!ensureBaseMap()) {
            return;
        }

        const center = heatData.length ? [heatData[0][0], heatData[0][1]] : defaultCenter;

        if (heatLayer) {
            map.removeLayer(heatLayer);
            heatLayer = null;
        }

        if (heatData.length && typeof L.heatLayer === 'function') {
            heatLayer = L.heatLayer(heatData, {
                radius: 25,
                blur: 15,
                maxZoom: 17,
                max: 1.0,
                gradient: adminHeatGradientForMotion(motion),
            });
            heatLayer.addTo(map);
            if (heatData.length === 1) {
                map.setView(center, 14);
            } else {
                try {
                    map.fitBounds(heatLayer.getBounds(), { padding: [56, 56], maxZoom: 16 });
                } catch (e) {
                    map.setView(center, 11);
                }
            }
        } else {
            map.setView(defaultCenter, defaultZoom);
        }

        requestAnimationFrame(invalidateMapSize);
        setTimeout(invalidateMapSize, 300);
    };
})();
</script>
