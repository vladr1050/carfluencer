# Database snapshots (`db:export-snapshot`)

Файлы в этой папке **не коммитятся** (кроме этого README). Снимок создаётся командой:

```bash
cd backend
php artisan db:export-snapshot
```

Дальше: **`docs/OPERATIONS/03_full_database_sync.md`** — копирование на сервер и восстановление.
