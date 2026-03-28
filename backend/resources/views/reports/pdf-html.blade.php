<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm; }
        body { font-family: system-ui, -apple-system, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 6px; }
        h2 { font-size: 14px; margin: 18px 0 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        h3 { font-size: 12px; margin: 14px 0 6px; color: #333; }
        .meta { color: #444; margin-bottom: 16px; }
        .kpis { display: flex; flex-wrap: wrap; gap: 10px; margin: 12px 0; }
        .kpi {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px 14px;
            min-width: 120px;
        }
        .kpi .label { font-size: 9px; color: #666; text-transform: uppercase; }
        .kpi .value { font-size: 16px; font-weight: 600; margin-top: 4px; }
        .page-break { page-break-after: always; }
        .map-img { width: 100%; max-height: 380px; object-fit: contain; border: 1px solid #ddd; }
        .note { font-size: 9px; color: #666; margin-top: 6px; }
    </style>
</head>
<body>

<section>
    <h1>Campaign report</h1>
    <div class="meta">
        <div><strong>Advertiser:</strong> {{ $advertiserName }}</div>
        <div><strong>Campaign:</strong> {{ $campaignName }}</div>
        <div><strong>Period:</strong> {{ $dateFrom }} — {{ $dateTo }}</div>
        <div><strong>Vehicles:</strong> {{ $vehicleCount }}</div>
        <div><strong>Metrics source:</strong> {{ $dataSource }} @if($isEstimated)(estimated)@endif</div>
    </div>
    <h2>Key metrics</h2>
    <div class="kpis">
        <div class="kpi"><div class="label">Impressions</div><div class="value">{{ $kpis['impressions'] ?? '—' }}</div></div>
        <div class="kpi"><div class="label">Carfluencers</div><div class="value">{{ $kpis['carfluencers'] ?? 0 }}</div></div>
        <div class="kpi"><div class="label">KM driven</div><div class="value">{{ $kpis['driving_distance_km'] ?? '—' }}</div></div>
        <div class="kpi"><div class="label">Hours driving</div><div class="value">{{ $kpis['driving_time_hours'] ?? '—' }}</div></div>
        <div class="kpi"><div class="label">Hours parked</div><div class="value">{{ $kpis['parking_time_hours'] ?? '—' }}</div></div>
    </div>
</section>

@if(!empty($includeDriving))
@foreach($drivingViewports as $row)
<div class="page-break"></div>
<section>
    <h2>Driving heatmap</h2>
    <h3>{{ $row['label'] }}</h3>
    <div class="meta">{{ $dateFrom }} — {{ $dateTo }} · {{ $vehicleCount }} vehicles</div>
    @if(!empty($row['base64']))
        <img class="map-img" src="data:image/png;base64,{{ $row['base64'] }}" alt="Driving {{ $row['label'] }}">
    @else
        <p>No map image for this view.</p>
    @endif
    <p class="note">Intensity shows sampled driving activity for the selected period and vehicles.</p>
</section>
@endforeach
@endif

@if(!empty($includeParking))
@foreach($parkingViewports as $row)
<div class="page-break"></div>
<section>
    <h2>Parking heatmap</h2>
    <h3>{{ $row['label'] }}</h3>
    <div class="meta">{{ $dateFrom }} — {{ $dateTo }} · {{ $vehicleCount }} vehicles</div>
    @if(!empty($row['base64']))
        <img class="map-img" src="data:image/png;base64,{{ $row['base64'] }}" alt="Parking {{ $row['label'] }}">
    @else
        <p>No map image for this view.</p>
    @endif
    <p class="note">Intensity shows sampled parking activity for the selected period and vehicles.</p>
</section>
@endforeach
@endif

</body>
</html>
