# Полная копия БД: локально → прод (готовый сценарий)

Цель: на сервере **та же база**, что и локально (все таблицы, пользователи, сессии, очереди и т.д.).

---

## Что уже подготовлено в репозитории

| Что | Назначение |
|-----|------------|
| **`php artisan db:export-snapshot`** | Снимок текущей БД в **`backend/storage/app/db-snapshots/`** |
| **`deploy/restore-postgres-snapshot.sh.example`** | Шаблон **`pg_restore`** на сервере (PostgreSQL) |
| **`vehicles:export` / `vehicles:import`** | Только машины, если полный дамп не нужен |

**Не входит в дамп:** файлы в **`storage/`** (картинки и т.п.) — копируй отдельно.

---

## Сценарий 1: локально PostgreSQL → прод PostgreSQL (рекомендуется)

### Локально

1. В **`.env`**: `DB_CONNECTION=pgsql` и корректные `DB_*`.
2. Установи клиент **`pg_dump`** (macOS: `brew install libpq`, в PATH добавь `$(brew --prefix libpq)/bin`).
3. Экспорт:

```bash
cd backend
php artisan db:export-snapshot
```

В консоли будет путь к файлу вида **`storage/app/db-snapshots/snapshot-pgsql_YYYY-MM-DD_HHMMSS.dump`**.

4. На сервер:

```bash
scp backend/storage/app/db-snapshots/snapshot-pgsql_*.dump user@SERVER:/tmp/
```

### На сервере

1. Сделай **бэкап** текущей БД прода (`pg_dump`), если там есть ценные данные.
2. Скопируй и отредактируй скрипт:

```bash
cd /var/www/carfluencer   # корень клона
cp deploy/restore-postgres-snapshot.sh.example deploy/restore-postgres-snapshot.sh
nano deploy/restore-postgres-snapshot.sh   # DUMP_FILE=..., PGUSER, PGDATABASE
export PGPASSWORD='...'
chmod +x deploy/restore-postgres-snapshot.sh
sudo ./deploy/restore-postgres-snapshot.sh
```

3. Laravel:

```bash
cd backend
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan queue:restart
```

4. **Storage:** при необходимости синхронизируй `backend/storage/app/public` с локали (`rsync`/`scp`).

---

## Сценарий 2: локально SQLite (как в `.env.example`) → прод PostgreSQL

`db:export-snapshot` создаст файл **`snapshot-sqlite_*.sqlite`**. Его **нельзя** отдать в `pg_restore`.

Варианты:

### A) Один раз перейти на PostgreSQL локально

1. Подними локальный Postgres, пропиши `DB_CONNECTION=pgsql` в `.env`.
2. `php artisan migrate:fresh` (или миграции + импорт) — перенеси данные через **`pgloader`** из старого SQLite в локальный Postgres (см. ниже).
3. Дальше **сценарий 1** (`db:export-snapshot` → `.dump` → сервер).

### B) Оставить SQLite и залить на прод через pgloader (на сервере или на машине с доступом к обоим)

Пример (на сервере или промежуточном хосте, где есть оба драйвера):

```bash
pgloader sqlite:///path/to/snapshot-sqlite_*.sqlite postgresql://USER:PASS@127.0.0.1/evo
```

После загрузки на сервере: **`php artisan migrate --force`**, **`config:cache`**, проверка приложения.

---

## Сценарий 3: только SQLite → SQLite (не типичный прод)

Если бы на сервере тоже был SQLite:

```bash
php artisan db:export-snapshot
scp backend/storage/app/db-snapshots/snapshot-sqlite_*.sqlite user@SERVER:/var/www/carfluencer/backend/database/database.sqlite
# права: пользователь PHP-FPM должен писать файл
```

---

## Без Artisan (вручную, PostgreSQL)

Локально:

```bash
pg_dump -h 127.0.0.1 -U USER -d DBNAME -Fc --no-owner --no-acl -f carfluencer.dump
```

Дальше как в **сценарии 1**, шаг «На сервере».

---

## Чеклист перед продом

- [ ] Снимок создан (`db:export-snapshot` или `pg_dump`).
- [ ] На проде сделан **бэкап** старой БД (если нужна откатка).
- [ ] Остановлена очередь на время restore (скрипт пример это делает).
- [ ] Восстановлен дамп / выполнен pgloader.
- [ ] `migrate --force`, `config:cache`, `queue:restart`.
- [ ] Скопирован **`storage`** при необходимости.
- [ ] `.env` на сервере указывает на эту БД (`DB_*`).

---

## Связанные документы

- [Перенос только Vehicles](02_sync_vehicles_between_envs.md)
