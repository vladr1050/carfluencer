# Перенос машин (Vehicles) между локальной БД и сервером

> Нужна **вся база целиком** (как у тебя локально), а не только машины — см. **[Полная копия БД](03_full_database_sync.md)**.

На сервере у каждой машины есть **`media_owner_id`** → пользователь в **`users`**. Импорт сопоставляет владельца по **email** (поле `media_owner_email` в JSON).

## 1. Локально: экспорт

Из каталога **`backend/`**:

```bash
php artisan vehicles:export
```

По умолчанию файл: **`storage/app/vehicles-export.json`**.

Свой путь:

```bash
php artisan vehicles:export --path=storage/app/my-fleet.json
```

## 2. На сервере: пользователь-владелец

Убедитесь, что на **сервере** есть пользователь с **тем же email**, что у медиа-владельца на локали (роль `media_owner`, статус `active`).  
Если email другой — либо создайте пользователя с нужным email в админке, либо отредактируйте JSON (`media_owner_email` у каждой строки), либо импортируйте с запасным email:

```bash
php artisan vehicles:import storage/app/vehicles-export.json --default-media-owner-email=media@yourcompany.com
```

(`--default-media-owner-email` подставляется только там, где в JSON пустой `media_owner_email`.)

## 3. На сервере: копирование файла

С локальной машины (пример):

```bash
scp backend/storage/app/vehicles-export.json user@YOUR_SERVER:/var/www/carfluencer/backend/storage/app/
```

## 4. На сервере: импорт

```bash
cd /var/www/carfluencer/backend
sudo -u www-data php artisan vehicles:import storage/app/vehicles-export.json
```

Проверка без записи:

```bash
sudo -u www-data php artisan vehicles:import storage/app/vehicles-export.json --dry-run
```

Импорт идёт через **`updateOrCreate` по `imei`**: повторный запуск обновит поля, не создаст дубликатов по IMEI.

## 5. Картинки (`image_path`)

В JSON попадает только **путь** в storage. Сами файлы из **`storage/app/public/...`** на сервер нужно скопировать отдельно (`rsync`/`scp`), либо заново загрузить изображения в админке.

## 6. Телеметрия

Поля вроде `telemetry_last_*` в экспорт **не входят** — на новом окружении курсоры синка начнутся «с чистого листа» для этих записей (после импорта при необходимости настройте pull в карточке ТС).

## 7. Цвет и статус флота

В JSON используется **`color_key`** (ключ из `config/vehicle.php`, например `white`, `black`). Старое поле **`color`** в импорте по-прежнему распознаётся и маппится в ближайший ключ или `other`.

Статус машины — один из: `active`, `booked`, `in_campaign`, `not_available`. После массового импорта или ручных правок в БД можно выровнять статусы относительно связей с кампаниями:

```bash
php artisan vehicles:reconcile-fleet-status
```
