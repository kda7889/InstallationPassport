# Deploy на обычный PHP-хостинг

## 1) Требования

- PHP 8.1+
- Расширения: `pdo_sqlite`, `gd`, `fileinfo`, `mbstring` (обязательно), `exif` (рекомендуется)
- Composer (для `mpdf/mpdf`)

## 2) Установка

1. Загрузить проект на хостинг.
2. Выполнить в корне проекта:
   - `composer require mpdf/mpdf`
3. Настроить web-root на папку `public/`.

## 3) Права и безопасность

- `storage/` должна быть writable веб-пользователем.
- Запретить выполнение PHP-файлов в `storage/` через настройки сервера.
- Включить HTTPS.

## 4) Проверка перед запуском

- `php scripts/preflight.php`
- Пройти smoke-сценарий из `docs/operations/runbook.md`.
