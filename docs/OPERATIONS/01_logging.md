# Логирование (Laravel / Monolog)

## Где пишутся логи

| Канал / место | Назначение |
|----------------|------------|
| **`storage/logs/laravel-YYYY-MM-DD.log`** | Основной канал **`daily`** (ротация по дням). |
| **`storage/logs/laravel-json-YYYY-MM-DD.log`** | Опционально, если в **`LOG_STACK`** добавлен **`daily_json`** — строки JSON для внешних агрегаторов. |
| **`storage/logs/worker.log`** | Вывод **`queue:work`** (Supervisor), см. `deploy/supervisor-laravel.conf.example`. |
| **Slack** | Только при настроенном **`LOG_SLACK_WEBHOOK_URL`** и уровне **`LOG_SLACK_LEVEL`**. |

## Переменные `.env`

| Переменная | Рекомендация |
|------------|----------------|
| **`LOG_CHANNEL`** | Обычно **`stack`**. |
| **`LOG_STACK`** | Локально: **`daily`** или **`single`**. Прод: **`daily`** или **`daily,daily_json`**. |
| **`LOG_LEVEL`** | Прод: **`warning`** или **`error`**; локально **`debug`**. |
| **`LOG_DAILY_DAYS`** | Сколько дней хранить файлы ротации (по умолчанию **30**). |
| **`LOG_SLACK_WEBHOOK_URL`** | Вебхук Incoming Webhook (опционально). |
| **`LOG_SLACK_LEVEL`** | Минимальный уровень в Slack (по умолчанию **`critical`**, чтобы не заспамить канал). |

## Корреляция запросов

Глобальный middleware **`AssignRequestId`**:

- кладёт **`request_id`** в контекст всех записей лога за HTTP-запрос;
- отдаёт заголовок **`X-Request-ID`** в ответе;
- если клиент прислал **`X-Request-ID`** или **`X-Correlation-ID`**, то же значение используется дальше.

## Просмотр на сервере

```bash
cd /var/www/carfluencer/backend
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log
```

После деплоя убедитесь, что у **`www-data`** (или пользователя PHP-FPM) есть запись в **`storage/logs`**.
