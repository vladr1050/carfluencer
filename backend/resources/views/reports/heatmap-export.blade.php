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
    </style>
</head>
<body>
<div id="map"></div>
<div class="legend">
    <strong>{{ $modeLabel }}</strong><br>
    {{ $periodLabel }} · {{ $vehicleCount }} vehicles
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
    const heatData = @json($heatData);
    const map = L.map('map', { zoomControl: false, attributionControl: true });
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap &copy; CARTO'
    }).addTo(map);

    if (!heatData.length) {
        map.setView([56.95, 24.11], 11);
    } else {
        const bounds = L.latLngBounds(heatData.map(p => [p[0], p[1]]));
        map.fitBounds(bounds.pad(0.12));
        L.heatLayer(heatData, { radius: 28, blur: 22, maxZoom: 17, max: 1.0 }).addTo(map);
    }
</script>
</body>
</html>
