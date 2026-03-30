<?php

namespace App\Services\GeoZones;

use App\Models\GeoZone;
use App\Support\Geo\RigaApkaimes;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates one {@see GeoZone} per official Riga neighbourhood (apkaime).
 *
 * Codes: {@code RIGA-APKAIME-{nn}} (zero-padded id from open data, e.g. RIGA-APKAIME-01).
 * Geometry/names come from {@see RigaApkaimes} (data.gov.lv “Rīgas apkaimes”, CC BY 4.0).
 */
final class RigaApkaimesGeoZoneSync
{
    public const CODE_PREFIX = 'RIGA-APKAIME-';

    /**
     * @return array{processed: int, created: int, updated: int, skipped: int}
     */
    public function sync(bool $dryRun = false): array
    {
        $fc = RigaApkaimes::featureCollection();
        $features = $fc['features'] ?? [];
        if (! is_array($features)) {
            return ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $work = function () use ($features, $dryRun, &$created, &$updated, &$skipped): void {
            foreach ($features as $feature) {
                if (! is_array($feature)) {
                    $skipped++;

                    continue;
                }
                $idRaw = $feature['id'] ?? null;
                if ($idRaw === null || $idRaw === '') {
                    $skipped++;

                    continue;
                }
                $id = (int) $idRaw;
                if ($id < 1) {
                    $skipped++;

                    continue;
                }

                $geom = $feature['geometry'] ?? null;
                if (! is_array($geom) || ($geom['type'] ?? '') !== 'Polygon') {
                    $skipped++;

                    continue;
                }

                $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
                $name = isset($props['name_lv']) && is_string($props['name_lv']) && $props['name_lv'] !== ''
                    ? $props['name_lv']
                    : 'Apkaime '.$id;

                $code = self::codeForApkaimeId($id);

                $normalized = GeoZone::normalizeGeometryFields([
                    'polygon_geojson' => [
                        'type' => 'Polygon',
                        'coordinates' => $geom['coordinates'],
                    ],
                    'min_lat' => 0.0,
                    'max_lat' => 1.0,
                    'min_lng' => 0.0,
                    'max_lng' => 1.0,
                ]);

                if ($dryRun) {
                    $exists = GeoZone::query()->where('code', $code)->exists();
                    $exists ? $updated++ : $created++;

                    continue;
                }

                /** @var GeoZone $zone */
                $zone = GeoZone::query()->firstOrNew(['code' => $code]);
                $wasExisting = $zone->exists;

                $zone->fill([
                    'name' => $name,
                    'min_lat' => $normalized['min_lat'],
                    'max_lat' => $normalized['max_lat'],
                    'min_lng' => $normalized['min_lng'],
                    'max_lng' => $normalized['max_lng'],
                    'polygon_geojson' => $normalized['polygon_geojson'],
                    'active' => true,
                    'metadata' => [
                        'source' => 'riga_apkaimes',
                        'data_gov_lv_dataset' => 'rigas_apkaimes',
                        'apkaime_id' => $id,
                    ],
                ]);
                $zone->save();

                $wasExisting ? $updated++ : $created++;
            }
        };

        if ($dryRun) {
            $work();
        } else {
            DB::transaction($work);
        }

        return [
            'processed' => $created + $updated,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    public static function codeForApkaimeId(int $id): string
    {
        return self::CODE_PREFIX.str_pad((string) $id, 2, '0', STR_PAD_LEFT);
    }
}
