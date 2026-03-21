# Полная копия БД: локально = сервер

Цель — на сервере **та же PostgreSQL/SQLite**, что и у тебя локально: все таблицы, пользователи, сессии, очереди, телеметрия и т.д.

**Важно**

- Это **перезаписывает** данные на сервере (или целевой БД). Сначала сделай **бэкап прода**, если там что-то нужно сохранить.
- **`storage/`** (картинки, документы) в дамп БД **не входят** — их копируй отдельно (`rsync`/`scp` каталога `backend/storage/app/public`).
- После восстановления на сервере: **`php artisan config:cache`**, при необходимости **`php artisan storage:link`**, проверь **`.env`** (тот же `APP_KEY` не обязателен, но `DB_*` должны указывать на восстановленную БД).

---

## Вариант A (рекомендуется): и локально, и прод — **PostgreSQL**

Тогда «один в один» делается стандартным дампом.

### 1) Локально — экспорт

Подставь свои `USER`, `DBNAME`, хост:

```bash
pg_dump -h 127.0.0.1 -p 5432 -U USER -d DBNAME \
  -Fc --no-owner --no-acl \
  -f carfluencer-full.dump
```

Файл `carfluencer-full.dump` перенеси на сервер (`scp`).

### 2) На сервере — перед заливкой

Останови воркеры очереди (опционально, чтобы не писали в БД во время замены):

```bash
sudo supervisorctl stop carfluencer-queue:*   # имя программы как у тебя в Supervisor
```

Сделай **бэкап** текущей БД на сервере:

```bash
pg_dump -h 127.0.0.1 -U SERVER_USER -d SERVER_DB -Fc -f ~/prod-before-clone.dump
```

### 3) На сервере — восстановление

Целевая БД должна существовать (пустая или её можно очистить). Частый способ — восстановить в **пустую** базу:

```bash
# создать пустую БД (пример; имена свои)
sudo -u postgres psql -c "DROP DATABASE IF EXISTS evo WITH (FORCE);"
sudo -u postgres psql -c "CREATE DATABASE evo OWNER evo;"

pg_restore --no-owner --no-acl -h 127.0.0.1 -U evo -d evo carfluencer-full.dump
```

Если `pg_restore` ругается на существующие объекты, используют связку **`--clean --if-exists`** (осторожно: удаляет объекты в целевой БД перед созданием):

```bash
pg_restore --clean --if-exists --no-owner --no-acl -h 127.0.0.1 -U evo -d evo carfluencer-full.dump
```

### 4) Laravel на сервере

```bash
cd /var/www/carfluencer/backend
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan queue:restart
sudo supervisorctl start carfluencer-queue:*
```

`migrate` подтянет только недостающие миграции, если схема чуть разошлась.

---

## Вариант B: локально **SQLite**, на сервере **PostgreSQL**

Один файл `.sqlite` на Postgres **напрямую** не кладётся. Варианты:

1. **Перейти локально на PostgreSQL** (как на проде) → один раз залить данные → дальше пользоваться **вариантом A**. Самый предсказуемый путь.
2. Утилита **`pgloader`** (SQLite → PostgreSQL), например:
   ```bash
   pgloader sqlite:///path/to/database.sqlite postgresql://USER:PASS@127.0.0.1/DBNAME
   ```
   После загрузки проверь миграции Laravel и уникальные ограничения; при необходимости прогон **`php artisan migrate --force`** на сервере.

Пока локально остаёшься на SQLite, «абсолютно та же» база на Postgres = либо pgloader, либо дублирование окружения на Postgres локально.

---

## Вариант C: и локально, и сервер — **SQLite** (не для типичного прода)

Если бы оба были на SQLite (обычно только для экспериментов):

1. Останови приложение на сервере.
2. Скопируй файл, например **`backend/database/database.sqlite`**, на сервер поверх существующего.
3. Права на файл: пользователь PHP-FPM должен читать/писать.

На проде Laravel обычно с **PostgreSQL**, этот вариант редко подходит.

---

## Что не копируется дампом БД

| Что | Действие |
|-----|----------|
| Файлы в **`storage/`** | Отдельный `scp`/`rsync` |
| Переменные **`.env`** | Вручную (URL, ключи, `TELEMETRY_*`) |
| **Redis** / кэш | Не в БД Laravel по умолчанию — сбросится |

---

## Кратко

- **Postgres + Postgres** → `pg_dump -Fc` → `pg_restore` — это и есть «та же база целиком».
- **SQLite → Postgres** → лучше выровнять локаль на Postgres или **pgloader**.
- Только машины без остальной БД → по-прежнему **[02_sync_vehicles_between_envs.md](02_sync_vehicles_between_envs.md)** и `vehicles:export` / `vehicles:import`.
