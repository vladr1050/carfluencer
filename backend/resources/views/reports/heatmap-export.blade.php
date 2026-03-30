<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Heatmap export</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body, #map { width: 100%; height: 100%; }
        .legend {
            position: absolute;
            bottom: 12px;
            left: 12px;
            z-index: 1000;
            background: rgba(255,255,255,0.92);
            padding: 8px 12px;
            border-radius: 6px;
            font: 12px/1.4 system-ui, sans-serif;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
        }
        .legend-activity {
            left: auto;
            right: 12px;
            max-width: 240px;
        }
        .legend-activity .row { display: flex; align-items: center; gap: 8px; margin: 4px 0; font-size: 11px; }
        .legend-activity .swatch { width: 28px; height: 10px; border-radius: 2px; border: 1px solid rgba(0,0,0,0.15); }
        .legend-activity .swatch-wide {
            width: 120px;
            height: 12px;
            border-radius: 3px;
            border: 1px solid rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
<div id="map"></div>
<div class="legend">
    <strong>{{ $modeLabel }}</strong><br>
    <span style="color:#333;">{{ $viewportLabel }}</span><br>
    {{ $periodLabel }} · {{ $vehicleCount }} vehicles
</div>
@php
    $lv = $legendVariant ?? 'driving_heat';
@endphp
@if($lv === 'parking_heat')
<div class="legend legend-activity">
    <strong>Parking (same as advertiser portal)</strong>
    <div class="row" style="margin-top:6px;">
        <span class="swatch-wide" style="background:linear-gradient(to right,#1b5e20 0%,#43a047 22%,#c6d84a 45%,#ffeb3b 62%,#fb8c00 80%,#c62828 100%);"></span>
    </div>
    <div class="row" style="margin-top:6px;">Low → high stopped intensity</div>
</div>
@else
<div class="legend legend-activity">
    <strong>Driving (same as advertiser portal)</strong>
    <div class="row" style="margin-top:6px;">
        <span class="swatch-wide" style="background:linear-gradient(to right,#440154,#3b528b,#21918c,#5ec962,#fde725);"></span>
    </div>
    <div class="row" style="margin-top:6px;">Low → high movement intensity</div>
</div>
@endif
@php
    $leafletFitMaxExport = max(8, min(19, (int) config('reports.heatmap_export.leaflet_fit_max_zoom', 14)));
    $heatGlowSafety = max(1.0, min(2.0, (float) config('reports.heatmap_export.data_fit_heat_glow_safety_factor', 1.22)));
    $heatGlowMinPx = max(0, min(80, (int) config('reports.heatmap_export.data_fit_heat_min_pixel_padding', 10)));
    $heatGlowMaxPx = max(16, min(200, (int) config('reports.heatmap_export.data_fit_heat_max_pixel_padding', 140)));
@endphp
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
    const heatData = @json($heatData);
    const viewport = @json($viewport);
    const mapFit = @json($mapFit ?? null);
    const leafletFitMax = @json($leafletFitMaxExport);
    const tile = @json($tileLayer);
    const heatOpts = @json($heatLayerOptions);
    const heatGlowSafety = @json($heatGlowSafety);
    const heatGlowMinPx = @json($heatGlowMinPx);
    const heatGlowMaxPx = @json($heatGlowMaxPx);
    const map = L.map('map', { zoomControl: false, attributionControl: true });
    const tileMax = Math.min(19, parseInt(tile.max_zoom, 10) || 20);
    const tileOptions = {
        maxZoom: tileMax,
        attribution: tile.attribution || ''
    };
    if (tile.subdomains) {
        tileOptions.subdomains = tile.subdomains;
    }
    L.tileLayer(tile.url, tileOptions).addTo(map);

    /** Pixel inset for fitBounds so leaflet.heat radius+blur is not clipped at PNG edges (driving + parking). */
    function heatmapFitBoundsPadding() {
        const r = Number(heatOpts.radius) || 0;
        const b = Number(heatOpts.blur) || 0;
        let p = Math.ceil((r + b) * heatGlowSafety);
        p = Math.max(heatGlowMinPx, Math.min(heatGlowMaxPx, p));
        return [p, p];
    }
    const edgePadding = heatmapFitBoundsPadding();

    function applyViewport() {
        if (mapFit && mapFit.use_data_fit) {
            const mz = Math.min(tileMax, parseInt(mapFit.max_zoom, 10) || leafletFitMax);
            const b = L.latLngBounds(
                [mapFit.south, mapFit.west],
                [mapFit.north, mapFit.east]
            );
            map.fitBounds(b, { padding: edgePadding, maxZoom: mz, animate: false });
            return;
        }
        if (viewport.fit_to_data) {
            if (heatData.length) {
                const bounds = L.latLngBounds(heatData.map(p => [p[0], p[1]]));
                const mz = Math.min(tileMax, leafletFitMax);
                map.fitBounds(bounds, {
                    padding: edgePadding,
                    maxZoom: mz,
                    animate: false
                });
            } else {
                map.setView([56.95, 24.11], 11);
            }
            return;
        }
        const b = L.latLngBounds(
            [viewport.south, viewport.west],
            [viewport.north, viewport.east]
        );
        const mz = Math.min(tileMax, leafletFitMax);
        map.fitBounds(b, { padding: edgePadding, maxZoom: mz, animate: false });
    }

    applyViewport();

    if (heatData.length && typeof L.heatLayer === 'function') {
        L.heatLayer(heatData, heatOpts).addTo(map);
    }
</script>
</body>
</html>
