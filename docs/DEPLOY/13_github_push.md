# Первый push в GitHub

Репозиторий проекта: **[github.com/vladr1050/carfluencer](https://github.com/vladr1050/carfluencer)**.

Локально в корне монорепо (где лежат `backend/`, `design/`, `docs/`):

```bash
cd /path/to/Evo_ad_x_crflncr   # ваш каталог проекта

git init
git add .
git commit -m "Initial commit"

git remote add origin git@github.com:vladr1050/carfluencer.git
git branch -M main
git push -u origin main
```

Если `remote origin already exists`:

```bash
git remote set-url origin git@github.com:vladr1050/carfluencer.git
git push -u origin main
```

Рекомендуется **SSH** (`git@github.com:...`). Для HTTPS GitHub попросит **Personal Access Token** вместо пароля.

После появления кода на GitHub:

1. **Settings → Secrets and variables → Actions** — секреты из `docs/DEPLOY/12_vps_production.md` §10.
2. На VPS: `git clone git@github.com:vladr1050/carfluencer.git` в ваш `DEPLOY_PATH` (и настроить deploy key **read** для `git fetch`).
3. **Actions → Deploy production** — ручной запуск или push в `main`.

Файлы **`backend/.env`** и **`backend/vendor/`** в git не попадают (см. корневой `.gitignore`). На сервере `.env` создаётся вручную, зависимости — `composer install` из `deploy/post-pull.sh`.
