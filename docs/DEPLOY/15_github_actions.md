# GitHub Actions: CI и деплой на VPS

В репозитории два основных workflow:

| Файл | Назначение |
|------|------------|
| `.github/workflows/ci.yml` | **CI** — тесты Laravel (`backend/`), сборка `frontend/` |
| `.github/workflows/deploy-production.yml` | **Deploy production** — сборка **`frontend/dist`** на runner, SSH (`git` + `post-pull.sh`), **`rsync`** статики на VPS |

---

## 1. CI (всегда включён)

Срабатывает на **push** и **pull request** в ветки `main` / `master`.

Ничего настраивать не нужно — при зелёном CI можно включать автодеплой (см. ниже).

---

## 2. Деплой на сервер

### Когда запускается

1. **Вручную:** **Actions** → **Deploy production** → **Run workflow**.
2. **Автоматически:** после **успешного** завершения **CI**, если CI был запущен событием **`push`** в **`main`** или **`master`**.

Если CI упал — деплой **не** стартует.

### Окружение `production` (рекомендуется)

В workflow указано `environment: production`. В GitHub:

**Settings → Environments → New environment →** имя **`production`**

Там можно:

- включить **Required reviewers** (одобрение перед деплоем);
- ограничить ветки;
- завести **environment secrets** вместо repository secrets (если нужно разделение).

Пока окружения нет, GitHub создаст его при первом запуске workflow (без правил).

### Секреты (repository или environment)

**Settings → Secrets and variables → Actions** (или секреты окружения **production**):

| Секрет | Описание |
|--------|----------|
| `DEPLOY_HOST` | IP или домен VPS. Нестандартный SSH-порт: **`host:2222`** |
| `DEPLOY_USER` | Пользователь Linux для SSH. У вас: **`root`** (в секрете GitHub буквально `root`). Рекомендуем для продакшена отдельного пользователя без полного sudo — но workflow с `root` работает. |
| `DEPLOY_SSH_PRIVATE_KEY` | Полный текст приватного ключа (от `-----BEGIN` до `END-----`) |
| `DEPLOY_PATH` | Абсолютный путь к **корню git** на сервере, например `/var/www/carfluencer` (рядом каталоги `backend/` и `deploy/`) |
| `FRONTEND_VITE_API_URL` | **Необязательно.** Полный origin API для сборки SPA, если фронт открывается с **другого** домена, чем API. Если SPA и `/api` на одном хосте (типовой Nginx) — **не задавайте**: в бандле будет относительный `/api`. |

На сервере **публичный** ключ (от этой пары) должен лежать в **`authorized_keys`**:

- если `DEPLOY_USER` = **`root`** → файл **`/root/.ssh/authorized_keys`** (права: каталог `700`, файл `600`);
- если отдельный пользователь → **`/home/ИМЯ/.ssh/authorized_keys`**.

> **Безопасность:** вход по ключу под `root` удобен для старта, но при компрометации ключа открыт весь сервер. Позже лучше перейти на пользователя вроде `evoad` + нужные права на `DEPLOY_PATH`.

Отдельно для **`git fetch`** на сервере нужен доступ к GitHub (deploy key read-only или другой способ) — см. `docs/DEPLOY/12_vps_production.md` §10.

### Что делает workflow **Deploy production**

1. **Checkout** репозитория на runner.
2. **`npm ci && npm run build`** в **`frontend/`** → свежий **`frontend/dist`** (Advertiser / Media owner SPA).
3. **SSH:** `cd DEPLOY_PATH` → `git fetch` / **`git clean -fd -- deploy/`** → **`git reset --hard origin/main`** (или `master`) → **`./deploy/post-pull.sh`** (Composer, migrate, кэши Laravel и т.д.).
4. **`ssh mkdir -p`** для **`$DEPLOY_PATH/frontend/dist`**, затем **`rsync`** туда содержимого **`frontend/dist/`** (нужны права на запись в `DEPLOY_PATH`).

Так после **`git push`** в `main` при успешном CI на прод попадает и бэкенд, и актуальная статика порталов.

---

## 3. Деплой без Docker на хосте

Сервер должен иметь установленные **PHP 8.4** (см. `deploy/setup-ubuntu-server.sh`), **Composer**, права на запись в `backend/storage` и `bootstrap/cache`. Вариант полностью на Docker — `docs/DEPLOY/14_docker.md` (тогда этот SSH-деплой можно заменить скриптом с `docker compose`).

---

## 4. Отключить автодеплой после CI

Оставьте только ручной запуск: в `.github/workflows/deploy-production.yml` удалите блок `workflow_run:` и условие `if:` у job (или упростите `if` до `always()` для ручного сценария — проще удалить `workflow_run` и оставить `workflow_dispatch` + `if: true` не нужен).

---

## 5. Проверка

После первого успешного деплоя откройте сайт и **`/admin`**, на сервере при необходимости смотрите логи очереди и `storage/logs/laravel.log`.
