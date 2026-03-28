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
            max-width: 200px;
        }
        .legend-activity .row { display: flex; align-items: center; gap: 8px; margin: 4px 0; font-size: 11px; }
        .legend-activity .swatch { width: 28px; height: 10px; border-radius: 2px; border: 1px solid rgba(0,0,0,0.15); }
    </style>
</head>
<body>
<div id="map"></div>
<div class="legend">
    <strong>{{ $modeLabel }}</strong><br>
    <span style="color:#333;">{{ $viewportLabel }}</span><br>
    {{ $periodLabel }} · {{ $vehicleCount }} vehicles
</div>
@if(($exportMode ?? '') !== 'parking_circles')
<div class="legend legend-activity">
    <strong>Activity (driving)</strong>
    <div class="row"><span class="swatch" style="background:#2E7D32;"></span> Low activity</div>
    <div class="row"><span class="swatch" style="background:#FDD835;"></span> Medium activity</div>
    <div class="row"><span class="swatch" style="background:#FB8C00;"></span> High activity</div>
    <div class="row"><span class="swatch" style="background:#D32F2F;"></span> Top activity zones</div>
</div>
@else
<div class="legend legend-activity">
    <strong>Parking map</strong>
    <div class="row" style="margin-top:6px;">Circle size = parking intensity</div>
    <div class="row">Darker color = higher activity</div>
    <div class="row" style="margin-top:4px;font-size:10px;color:#555;">Labels 1–5 = highest-ranked spots</div>
</div>
@endif
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
@if(($exportMode ?? '') !== 'parking_circles')
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
@endif
<script>
    const parkingCirclesExport = @json(($exportMode ?? '') === 'parking_circles');
    const heatData = @json($heatData);
    const parkingCircles = @json($parkingCircles);
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

    function applyViewportDriving() {
        if (viewport.fit_to_data) {
            if (heatData.length) {
                const bounds = L.latLngBounds(heatData.map(p => [p[0], p[1]]));
                map.fitBounds(bounds.pad(0.12), { maxZoom: 16 });
            } else {
                map.setView([56.95, 24.11], 11);
            }
            return;
        }
        const b = L.latLngBounds(
            [viewport.south, viewport.west],
            [viewport.north, viewport.east]
        );
        map.fitBounds(b.pad(0.06), { maxZoom: 15, animate: false });
    }

    function applyViewportParkingCircles() {
        if (parkingCircles.length) {
            const bounds = L.latLngBounds(parkingCircles.map(c => [c.lat, c.lng]));
            map.fitBounds(bounds.pad(0.18), { maxZoom: 15, animate: false });
            return;
        }
        if (viewport.fit_to_data) {
            map.setView([56.95, 24.11], 11);
            return;
        }
        const b = L.latLngBounds(
            [viewport.south, viewport.west],
            [viewport.north, viewport.east]
        );
        map.fitBounds(b.pad(0.06), { maxZoom: 15, animate: false });
    }

    if (parkingCirclesExport) {
        applyViewportParkingCircles();
        parkingCircles.forEach(function (c) {
            const m = L.circleMarker([c.lat, c.lng], {
                radius: c.radius_px,
                fillColor: c.fillColor,
                color: c.color,
                weight: c.weight,
                fillOpacity: c.fillOpacity,
                opacity: 1
            }).addTo(map);
            if (c.tooltip) {
                m.bindTooltip(c.tooltip, { permanent: false, direction: 'top' });
            }
            if (c.label != null) {
                L.marker([c.lat, c.lng], {
                    icon: L.divIcon({
                        className: 'parking-rank-label',
                        html: '<div style="font:700 11px system-ui,sans-serif;color:#111;text-shadow:0 0 3px #fff,0 0 3px #fff;">' + c.label + '</div>',
                        iconSize: [18, 18],
                        iconAnchor: [9, 9]
                    })
                }).addTo(map);
            }
        });
    } else {
        applyViewportDriving();
        if (heatData.length && typeof L.heatLayer === 'function') {
            L.heatLayer(heatData, heatOpts).addTo(map);
        }
    }
</script>
</body>
</html>
