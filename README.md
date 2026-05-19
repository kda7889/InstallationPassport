# МонтажПаспорт (MVP)

Веб-сервис на PHP 8 + SQLite для мобильной фотофиксации монтажных работ и формирования гарантийного PDF.

## Что уже реализовано

- Модель данных: **монтаж → элементы → фото (common/item)**.
- Роли и доступ: `admin`, `installer`.
- CRUD-поток: логин, список монтажей, создание монтажа, добавление элементов.
- Загрузка фото: MIME-проверка, ресайз, JPEG-конвертация, thumbnail.
- Генерация PDF (через `mpdf/mpdf`).
- Админ-страница управления пользователями.

## Быстрый запуск (локально/на хостинге)

1. Установите PHP 8.1+ и расширения:
   - `pdo_sqlite`, `gd`, `fileinfo`, `mbstring` (обязательно)
   - `exif` (рекомендуется)
2. Установите зависимости:
   - `composer require mpdf/mpdf`
3. Проверьте готовность окружения:
   - `php scripts/preflight.php`
4. Убедитесь, что web-root указывает на `public/`.
5. Убедитесь, что `storage/` доступна на запись веб-пользователю.
6. Откройте `/login.php`.

> При первом запуске БД создаётся автоматически и создаётся дефолтный админ `admin@example.com` со случайным паролем — пароль будет записан в `storage/initial-admin-credentials.txt` (зайдите, смените пароль и удалите файл). Можно заранее задать креды через переменные окружения `ADMIN_EMAIL` и `ADMIN_PASSWORD`.

## Проверка перед запуском в прод

- Пройти smoke-сценарий из `docs/operations/runbook.md`.
- Проверить безопасность storage из `docs/operations/deploy-php-hosting.md`.
- Убедиться, что preflight проходит без FAIL.

## Документация

- Главный индекс: `docs/index.md`
- Деплой на HestiaCP: `docs/operations/deploy-hestia.md`
- Деплой на обычный PHP-хостинг: `docs/operations/deploy-php-hosting.md`
- Runbook: `docs/operations/runbook.md`
- Тест-стратегия: `docs/testing/test-strategy.md`
