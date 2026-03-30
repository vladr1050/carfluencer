<?php

/**
 * One-off: convert official apkaimes KML (WGS84) to GeoJSON FeatureCollection.
 * Usage: php build-riga-apkaimes-geojson.php /path/to/apkaimes.kml > riga-apkaimes.json
 */

declare(strict_types=1);

$path = $argv[1] ?? '';
if ($path === '' || ! is_readable($path)) {
    fwrite(STDERR, "Usage: php build-riga-apkaimes-geojson.php apkaimes.kml\n");
    exit(1);
}

$kml = file_get_contents($path);
if ($kml === false) {
    fwrite(STDERR, "Cannot read file\n");
    exit(1);
}

$xml = @simplexml_load_string($kml);
if ($xml === false) {
    fwrite(STDERR, "Invalid XML\n");
    exit(1);
}

$placemarks = $xml->xpath('//*[local-name()="Placemark"]');
$features = [];

foreach ($placemarks as $pm) {
    $idAttr = (string) ($pm->attributes()->id ?? '');
    if (preg_match('/^apkaimes\.(\d+)$/', $idAttr, $m)) {
        $fid = $m[1];
    } else {
        $fid = (string) (count($features) + 1);
    }
    $name = trim((string) $pm->name);
    $desc = trim((string) $pm->description);
    $coordsEl = $pm->xpath('.//*[local-name()="coordinates"]');
    if ($coordsEl === false || $coordsEl === []) {
        continue;
    }
    $text = trim(preg_replace('/\s+/', ' ', (string) $coordsEl[0]));
    if ($text === '') {
        continue;
    }
    $ring = [];
    foreach (explode(' ', $text) as $pair) {
        $pair = trim($pair);
        if ($pair === '') {
            continue;
        }
        $parts = explode(',', $pair);
        if (count($parts) < 2) {
            continue;
        }
        $lng = round((float) $parts[0], 7);
        $lat = round((float) $parts[1], 7);
        $ring[] = [$lng, $lat];
    }
    if (count($ring) < 3) {
        continue;
    }
    $a = $ring[0];
    $b = $ring[count($ring) - 1];
    if (abs($a[0] - $b[0]) > 1e-7 || abs($a[1] - $b[1]) > 1e-7) {
        $ring[] = [$a[0], $a[1]];
    }
    $features[] = [
        'type' => 'Feature',
        'id' => $fid,
        'properties' => [
            'gid' => (int) $fid,
            'name_lv' => $name,
            'url' => $desc !== '' && str_starts_with($desc, 'http') ? $desc : null,
        ],
        'geometry' => [
            'type' => 'Polygon',
            'coordinates' => [$ring],
        ],
    ];
}

$collection = [
    'type' => 'FeatureCollection',
    'features' => $features,
];

echo json_encode($collection, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
