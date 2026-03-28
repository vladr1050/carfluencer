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
            max-width: 220px;
        }
        .legend-activity .row { display: flex; align-items: center; gap: 8px; margin: 4px 0; font-size: 11px; }
        .legend-activity .swatch { width: 28px; height: 10px; border-radius: 2px; border: 1px solid rgba(0,0,0,0.15); }
        .hotspot-pin .hotspot-card {
            background: rgba(255,255,255,0.94);
            border: 1px solid rgba(0,0,0,0.12);
            border-radius: 6px;
            padding: 4px 8px;
            font: 11px/1.35 system-ui, sans-serif;
            color: #111;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
            white-space: nowrap;
            max-width: 200px;
            white-space: normal;
        }
        .hotspot-pin .hotspot-card strong { display: block; font-size: 11px; }
        .hotspot-pin .hotspot-card span { font-size: 10px; color: #444; }
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
    <strong>Parking density</strong>
    <p style="font-size:10px;color:#555;margin:4px 0 6px;">Cell weight = rollup samples (dwell proxy; not exact minutes).</p>
    <div class="row"><span class="swatch" style="background:#edf8fb;"></span> Short stay</div>
    <div class="row"><span class="swatch" style="background:#66c2a4;"></span> Medium</div>
    <div class="row"><span class="swatch" style="background:#006d2c;"></span> Long stay</div>
</div>
@else
<div class="legend legend-activity">
    <strong>Driving activity</strong>
    <div class="row"><span class="swatch" style="background:#2c7bb6;"></span> Low</div>
    <div class="row"><span class="swatch" style="background:#ffffbf;"></span> Medium</div>
    <div class="row"><span class="swatch" style="background:#d73027;"></span> High</div>
</div>
@endif
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
    const heatData = @json($heatData);
    const hotspots = @json($hotspots ?? []);
    const viewport = @json($viewport);
    const tile = @json($tileLayer);
    const heatOpts = @json($heatLayerOptions);
    const map = L.map('map', { zoomControl: false, attributionControl: true });
    const tileOptions = {
        maxZoom: Math.min(19, tile.max_zoom || 20),
        attribution: tile.attribution || ''
    };
    if (tile.subdomains) {
        tileOptions.subdomains = tile.subdomains;
    }
    L.tileLayer(tile.url, tileOptions).addTo(map);

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function applyViewport() {
        if (viewport.fit_to_data) {
            if (heatData.length) {
                const bounds = L.latLngBounds(heatData.map(p => [p[0], p[1]]));
                map.fitBounds(bounds.pad(0.14), { maxZoom: 15, animate: false });
            } else {
                map.setView([56.95, 24.11], 11);
            }
            return;
        }
        const b = L.latLngBounds(
            [viewport.south, viewport.west],
            [viewport.north, viewport.east]
        );
        map.fitBounds(b.pad(0.08), { maxZoom: 15, animate: false });
    }

    applyViewport();

    if (heatData.length && typeof L.heatLayer === 'function') {
        L.heatLayer(heatData, heatOpts).addTo(map);
    }

    (hotspots || []).forEach(function (h) {
        if (h == null || typeof h.lat !== 'number' || typeof h.lng !== 'number') return;
        const html = '<div class="hotspot-card"><strong>' + escapeHtml(h.title || '') + '</strong>'
            + (h.subtitle ? '<span>' + escapeHtml(h.subtitle) + '</span>' : '') + '</div>';
        L.marker([h.lat, h.lng], {
            icon: L.divIcon({
                className: 'hotspot-pin',
                html: html,
                iconSize: [200, 52],
                iconAnchor: [100, 52]
            })
        }).addTo(map);
    });
</script>
</body>
</html>
