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
     * Admin-only heat styling: one dominant bucket (e.g. city centre) no longer eats the whole scale;
     * sparse / parking-like buckets get a higher relative weight and a gradient with more colour in the low–mid range.
     */
    function buildAdminHeatmapPoints(pts) {
        if (!pts.length) {
            return [];
        }
        const vals = pts.map(function (p) {
            return Math.max(0, Number(p.intensity) || 0);
        });
        const positive = vals.filter(function (v) {
            return v > 0;
        });
        let clip;
        if (positive.length >= 8) {
            const sorted = positive.slice().sort(function (a, b) {
                return a - b;
            });
            const idx = Math.min(sorted.length - 1, Math.floor(sorted.length * 0.86));
            clip = sorted[idx];
        } else {
            clip = Math.max.apply(null, positive.concat([1e-6]));
        }
        clip = Math.max(clip, 1e-6);

        return pts.map(function (p) {
            const raw = Math.min(1, (Number(p.intensity) || 0) / clip);
            const curved = Math.pow(raw, 0.48);
            const w = 0.15 + 0.85 * curved;

            return [Number(p.lat), Number(p.lng), Math.min(1, Math.max(0.14, w))];
        });
    }

    window.renderAdminTelemetryHeatmap = function (payload) {
        const pts = (payload.heatmap && payload.heatmap.points) ? payload.heatmap.points : [];
        const heatData = buildAdminHeatmapPoints(pts);

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
                radius: 28,
                blur: 16,
                maxZoom: 17,
                max: 0.78,
                minOpacity: 0.09,
                gradient: {
                    0.0: '#0f2942',
                    0.15: '#1c5f8f',
                    0.28: '#2a8f84',
                    0.42: '#4caf6a',
                    0.55: '#9cbd3e',
                    0.68: '#d4a84b',
                    0.8: '#e0783a',
                    0.9: '#d94a3d',
                    1.0: '#a31b2d',
                },
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
