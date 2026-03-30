# Impression Engine — BIG DATA import (MVP v1.0)

Статус: **зафиксировано** для реализации слоя `mobility_reference_cells`.

## Источник

- Файл: `datasets/riga_mobility_bigdata_reference_dataset.xlsx`
- Читается **только** лист **`Dataset`**.

## Маппинг колонок

| Excel | Используем | Поле в БД / семантика |
|-------|------------|------------------------|
| `gps_lat` | ✅ | `lat` (WGS84) |
| `gps_lon` | ✅ | `lon` |
| `vehicle_volume_AADT_2025` | ✅ | `vehicle_aadt` (**только 2025**, без альтернатив) |
| `pedestrian_count_daily` | ✅ | `pedestrian_daily` |
| `average_speed_kmh` | ✅ | `average_speed_kmh` |
| `hourly_peak_factor` | ✅ | `hourly_peak_factor` |

### Не используем в MVP v1.0

| Excel | Причина |
|-------|---------|
| `vehicle_volume_AADT_2024` | Baseline — только 2025 |
| `heavy_vehicles_percent` | v1.1+ |
| `location_name` | Только дебаг вне БД или лог |
| `source` | Не влияет на расчёт |
| `notes` | Длинный повторяющийся текст — не импортировать |

## Геометрия

- `cell_id = H3(lat, lon, resolution = 9)` (WGS84, порядок аргументов **lat, lng** — как принято в выбранном PHP binding).

## Агрегация по `cell_id`

После чтения строк: **`GROUP BY cell_id`**, для каждой ячейки:

- `AVG(vehicle_aadt)`
- `AVG(pedestrian_daily)`
- `AVG(average_speed_kmh)`
- `AVG(hourly_peak_factor)`

**Почему AVG:** входные значения уже агрегированы по участкам; суммирование по одной H3-ячейке искажало бы смысл; AVG сглаживает шум при нескольких строках в одной ячейке.

### Рекомендуемое поле в БД

- `records_count` — число строк Excel, попавших в данный `cell_id` (плотность/шум, отладка).

## Edge cases

1. **`pedestrian_daily = 0`** — допустимо; **не** заменять на NULL и **не** подставлять минимум.
2. **Дубликаты координат / несколько участков в одной H3-ячейке** — схлопываются через `GROUP BY cell_id` + AVG.
3. **Пропуски:** нет `lat`/`lon` → пропуск строки; `vehicle_aadt` или `pedestrian_daily` **null** (после парсинга) → пропуск строки.

## `data_version`

- Текущая зафиксированная версия: **`riga_v1_2025`**
- При любом изменении файла, набора колонок или логики агрегации — **новая** версия (`riga_v2_…` и т.д.).

## Импорт: память

Файл крупный (~34MB внутренний XML листа) — **не** загружать весь лист в память.

- PHP: streaming / chunk reader (например **Spout** `openspout`, или режим чанками, совместимый с большими xlsx).
- В памяти держать **аккумулятор по `cell_id`** (суммы + счётчики для AVG), а не все сырые строки.

### Псевдокод (эквивалент AVG)

```
foreach row in stream:
    if invalid lat/lon or null aadt/pedestrian: skipped_rows++; continue
    cell_id = h3(lat, lon, 9)
    agg[cell_id].sum_aadt += vehicle_aadt
    agg[cell_id].sum_ped += pedestrian_daily
    agg[cell_id].sum_speed += average_speed_kmh
    agg[cell_id].sum_peak += hourly_peak_factor
    agg[cell_id].count += 1
    valid_rows++

foreach cell_id in agg:
    write row:
      vehicle_aadt = sum_aadt / count
      pedestrian_daily = sum_ped / count
      average_speed_kmh = sum_speed / count
      hourly_peak_factor = sum_peak / count
      records_count = count
```

## Результат в `mobility_reference_cells`

Минимальный набор (плюс PK/таймстемпы по решению миграции):

- `cell_id`
- `vehicle_aadt`, `pedestrian_daily`, `average_speed_kmh`, `hourly_peak_factor`
- `data_version`
- `records_count` (рекомендовано)

При необходимости позже: `lat_center` / `lon_center` из центроида ячейки H3 (не из AVG сырых точек), если нужно для UI.

## Реализация в репозитории

- Конфиг: `backend/config/impression_engine.php`
- Импорт: `App\Services\ImpressionEngine\MobilityReferenceDatasetImportService` (OpenSpout streaming, лист `Dataset`)
- H3: `IMPRESSION_ENGINE_H3_DRIVER=real` → `LibH3Indexer` (FFI + **libh3** на сервере); `fake` — для PHPUnit (`phpunit.xml`)
- Команда: `php artisan impression-engine:import-mobility-dataset` (`--path`, `--data-version`)

На VPS без `libh3` задайте путь к `.so` в `IMPRESSION_ENGINE_H3_LIBRARY_PATH` или установите библиотеку (см. `michaellindahl/php-h3` README).

## Контроль качества после импорта (обязательно логировать/выводить)

- `total_rows_read`, `valid_rows`, `skipped_rows`, `unique_cells`
- `avg_aadt`, `avg_pedestrian` (по всем ячейкам после агрегации)
- `min_lat`, `max_lat`, `min_lon`, `max_lon` (по валидным строкам) — sanity check «это Рига»
- **Coverage:** доля ячеек с `pedestrian_daily = 0`; доля с «низким» AADT (порог задать в конфиге, напр. `< N`)

Эти метрики — для объяснения рекламодателю и регрессии при смене файла/версии.
