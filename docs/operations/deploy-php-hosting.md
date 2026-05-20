# Деплой на обычный PHP-хостинг

## 1) Требования
- PHP 8.1+
- Расширения: `pdo_sqlite`, `gd`, `fileinfo`, `mbstring` (обязательно), `exif`, `imagick` (рекомендуется — для HEIC).
- Composer (для `mpdf/mpdf` и `mpdf/qrcode` — оба нужны: без второго PDF падает с `Mpdf\QrCode package was not found`).

## 2) Установка
1. Залить проект на хостинг (через FTP / Git).
2. В корне проекта выполнить:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Webroot хостинга → папка `public/`.

## 3) Права и безопасность
- `storage/` writable для веб-пользователя.
- Запрет выполнения PHP в `storage/`:
  - стандартный Apache — `.htaccess` из репозитория уже всё закрывает;
  - nginx-only — явный `location` блок, см. §9 в `deploy-hestia.md`.
- Включить HTTPS.

## 4) ENV (опционально)
- `ADMIN_EMAIL`, `ADMIN_PASSWORD` — креды для первого админа (если не заданы, пароль сгенерится случайно и попадёт в `storage/initial-admin-credentials.txt`).

## 5) Проверка
```bash
php scripts/preflight.php
```
Должно быть `Preflight result: OK`. Затем — smoke-сценарий из `docs/operations/runbook.md`.

## 6) Cron-задачи (опционально)
```bash
# Бэкап storage (БД + фото + PDF) с глубиной 14 дней
0 3 * * * tar czf /backup/doc-$(date +\%Y\%m\%d).tar.gz storage && find /backup/ -name "doc-*.tar.gz" -mtime +14 -delete

# Очистка audit_log старше года
0 4 * * 0 php /path/to/installationpassport/scripts/cleanup.php 365
```
