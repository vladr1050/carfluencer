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
        table.data { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 10px; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        table.data th { background: #f5f5f5; }
        table.data td.num, table.data th.num { text-align: right; }
        .insights-summary { margin: 8px 0; line-height: 1.45; font-size: 10.5px; }
        .insights-highlights { margin: 8px 0 0 18px; padding: 0; font-size: 10px; line-height: 1.4; }
        .insights-highlights li { margin: 4px 0; }
    </style>
</head>
<body>

@php
    $aMeta = $analytics['meta'] ?? [];
    $aKpis = $analytics['kpis'] ?? [];
    $aDataSource = $aMeta['data_source'] ?? '—';
    $aIsEstimated = !empty($aMeta['is_estimated']);
    $aExposure = $analytics['exposure_split'] ?? [];
    $aInsights = $analytics['insights'] ?? [];
    $aCoverage = is_array($analytics['coverage'] ?? null) ? $analytics['coverage'] : [];
    $aParkingByZone = is_array($analytics['parking_by_zone'] ?? null) ? $analytics['parking_by_zone'] : null;
@endphp

<section>
    <h1>Campaign report</h1>
    <div class="meta">
        <div><strong>Advertiser:</strong> {{ $advertiserName }}</div>
        <div><strong>Campaign:</strong> {{ $campaignName }}</div>
        <div><strong>Period:</strong> {{ $dateFrom }} — {{ $dateTo }}</div>
        <div><strong>Vehicles:</strong> {{ $vehicleCount }}</div>
        <div><strong>Metrics source:</strong> {{ $aDataSource }} @if($aIsEstimated)(estimated)@endif</div>
    </div>
    <h2>Key metrics</h2>
    <div class="kpis">
        <div class="kpi"><div class="label">Impressions</div><div class="value">{{ $aKpis['impressions'] ?? '—' }}</div></div>
        <div class="kpi"><div class="label">Carfluencers</div><div class="value">{{ $aKpis['carfluencers'] ?? 0 }}</div></div>
        <div class="kpi"><div class="label">KM driven</div><div class="value">{{ $aKpis['km_driven'] ?? '—' }}</div></div>
        <div class="kpi"><div class="label">Hours driving</div><div class="value">{{ $aKpis['driving_hours'] ?? '—' }}</div></div>
        <div class="kpi"><div class="label">Hours parked</div><div class="value">{{ $aKpis['parking_hours'] ?? '—' }}</div></div>
    </div>
    @if($impSnap)
        <p class="note"><strong>Impressions (KPI above):</strong> total gross impressions from impression engine snapshot #{{ $impSnap['id'] }} (same value as snapshot total below).</p>
    @endif

    @if($impSnap)
        <h2>Impressions (impression engine)</h2>
        <p class="insights-summary">
            Estimated impressions combine campaign vehicle exposure in time (driving and parking) with impression coefficients,
            and align locations to a mobility reference grid. Gross totals allocate campaign price into impression units for reporting.
            Geo zone rows below attribute estimated impressions to active Geo zones when the underlying exposure can be mapped to a zone;
            the remainder is reported as outside zones.
        </p>
        <div class="meta">
            <div><strong>Snapshot ID:</strong> #{{ $impSnap['id'] }}</div>
            <div><strong>Snapshot period:</strong> {{ $impSnap['date_from'] }} — {{ $impSnap['date_to'] }}</div>
            <div><strong>Total gross impressions (snapshot):</strong> {{ number_format((int) ($impSnap['total_gross_impressions'] ?? 0)) }}</div>
            <div><strong>Mobility data version:</strong> {{ $impSnap['mobility_data_version'] ?? '—' }}</div>
            <div><strong>Coefficients version:</strong> {{ $impSnap['coefficients_version'] ?? '—' }}</div>
            <div><strong>Engine version:</strong> {{ $impSnap['calculation_version'] ?? '—' }}</div>
        </div>

        @if($impZones && !empty($impZones['available']))
            @if(!empty($impZones['note']))
                <p class="note">{{ $impZones['note'] }}</p>
            @endif
            <h3>Top 10 districts (Geo zones) by estimated impressions</h3>
            @if(!empty($impZones['top_zones']) && is_array($impZones['top_zones']))
                <table class="data">
                    <thead>
                    <tr>
                        <th>District</th>
                        <th>Code</th>
                        <th class="num">Impressions</th>
                        <th class="num">Share</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($impZones['top_zones'] as $z)
                        @if(is_array($z))
                            <tr>
                                <td>{{ $z['name'] ?? '—' }}</td>
                                <td>{{ $z['code'] ?? '—' }}</td>
                                <td class="num">{{ number_format((int) ($z['impressions'] ?? 0)) }}</td>
                                <td class="num">{{ number_format((float) ($z['share_pct'] ?? 0), 2) }}%</td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
                <p class="note">
                    <strong>Attributed total (zones):</strong> {{ number_format((int) ($impZones['total_impressions'] ?? 0)) }}.
                    <strong>Outside zones:</strong> {{ number_format((int) ($impZones['unattributed_impressions'] ?? 0)) }}
                    ({{ number_format((float) ($impZones['unattributed_share_pct'] ?? 0), 2) }}%).
                </p>
            @else
                <p class="note">No zone rows returned for this snapshot.</p>
            @endif
        @else
            <p class="note">{{ is_array($impZones) ? ($impZones['reason'] ?? 'Zone breakdown is not available.') : 'Zone breakdown is not available.' }}</p>
        @endif
    @endif

    <h2>Exposure split</h2>
    <div class="meta">
        <div><strong>Driving:</strong> {{ number_format((float)($aExposure['driving_share'] ?? 0) * 100, 2) }}%</div>
        <div><strong>Parking:</strong> {{ number_format((float)($aExposure['parking_share'] ?? 0) * 100, 2) }}%</div>
    </div>
    <p class="note">Share of driving vs parking hours (same basis as key metrics).</p>

    <h2>Parking time by zone</h2>
    @if($aParkingByZone)
        <p class="note">Parking duration is aggregated in whole minutes; values below are shown as decimal hours (minutes ÷ 60).</p>
        <div class="meta">
            <div><strong>Parking hours (window overlap):</strong> {{ \App\Services\Reports\ReportParkingMinutesAsHours::format((int)($aParkingByZone['totals']['parking_minutes_in_window'] ?? 0)) }}</div>
            <div><strong>Parking sessions:</strong> {{ (int)($aParkingByZone['totals']['parking_sessions_in_window'] ?? 0) }}</div>
        </div>
        @if(!empty($aParkingByZone['by_zone']) && is_array($aParkingByZone['by_zone']))
            <table class="data">
                <thead>
                <tr>
                    <th>Zone</th>
                    <th>Code</th>
                    <th>Hours parked</th>
                    <th>Sessions</th>
                    <th>Vehicles</th>
                </tr>
                </thead>
                <tbody>
                @foreach($aParkingByZone['by_zone'] as $z)
                    @if(is_array($z))
                        <tr>
                            <td>{{ $z['name'] ?? '—' }}</td>
                            <td>{{ $z['code'] ?? '—' }}</td>
                            <td>{{ \App\Services\Reports\ReportParkingMinutesAsHours::format((int)($z['parking_minutes'] ?? 0)) }}</td>
                            <td>{{ (int)($z['sessions_count'] ?? 0) }}</td>
                            <td>{{ (int)($z['vehicles_distinct'] ?? 0) }}</td>
                        </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        @else
            <p class="note">No active GeoZones matched session centers (configure zones or check attribution).</p>
        @endif
        @php
            $uMin = (int)($aParkingByZone['unattributed']['parking_minutes'] ?? 0);
            $uSess = (int)($aParkingByZone['unattributed']['sessions_count'] ?? 0);
        @endphp
        @if($uMin > 0 || $uSess > 0)
            <p class="note">Outside zones: {{ \App\Services\Reports\ReportParkingMinutesAsHours::format($uMin) }} ({{ $uSess }} sessions).</p>
        @endif
        <p class="note">{{ $aParkingByZone['overlap_note'] ?? '' }}</p>
    @else
        <p class="note">No parking-by-zone breakdown in this snapshot.</p>
    @endif

    <h2>Footprint (driving)</h2>
    <div class="meta">
        <div><strong>Distinct driving grid cells:</strong> {{ $aCoverage['unique_cells'] ?? '—' }}</div>
        <div><strong>Reference grid cells (operational bounds):</strong> {{ $aCoverage['reference_cells'] ?? '—' }}</div>
        <div><strong>Footprint coverage ratio:</strong> @if(isset($aCoverage['coverage_ratio'])){{ number_format((float)$aCoverage['coverage_ratio'] * 100, 2) }}%@else—@endif</div>
        @if(!empty($aCoverage['coverage_narrative']))
            <div><strong>Spatial summary:</strong> {{ $aCoverage['coverage_narrative'] }}</div>
        @elseif(!empty($aCoverage['coverage_pattern']))
            <div><strong>Spatial pattern:</strong> {{ $aCoverage['coverage_pattern'] }}</div>
        @endif
    </div>
    <p class="note">Ratio = distinct driving rollup cells ÷ cells in the configured export bounds at the report coverage zoom tier (not “share of a city”). Denominator: operational_bounds_grid. The spatial summary links driving grid coverage to credited parking time in GeoZones (table above).</p>

    <h2>Campaign insights</h2>
    @if(!empty($aInsights['summary']))
        <p class="insights-summary">{{ $aInsights['summary'] }}</p>
    @endif
    @if(!empty($aInsights['highlights']) && is_array($aInsights['highlights']))
        <ul class="insights-highlights">
            @foreach($aInsights['highlights'] as $line)
                @if(is_string($line) && $line !== '')
                    <li>{{ $line }}</li>
                @endif
            @endforeach
        </ul>
    @endif
    <p class="note">Parking geography in this section follows the Parking time by zone table (GeoZone attribution), not heatmap sample cells.</p>
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
    <p class="note">Heat layer shows parking rollup density (sample-weighted cells).</p>
</section>
@endforeach
@endif

</body>
</html>
