# МонтажПаспорт — документация

## Старт

- `README.md` — быстрый запуск и обзор фич.
- `docs/operations/deploy-hestia.md` — деплой на HestiaCP (рекомендуемый прод-сценарий).
- `docs/operations/deploy-php-hosting.md` — деплой на обычный PHP-хостинг.
- `docs/operations/runbook.md` — smoke и типичные инциденты.
- `docs/testing/test-strategy.md` — как проверять проект.

## Архитектура

- `docs/architecture/domain-model.md` — сущности и инварианты.
- `docs/architecture/data-flow.md` — основные потоки (внутренний + customer-portal).
- `docs/architecture/storage-layout.md` — раскладка `storage/`.

## API / страницы

- `docs/api/routes.md` — все endpoints с уровнями доступа.
- `docs/api/form-contracts.md` — контракты POST-форм.

## Безопасность

- `docs/security/auth-and-sessions.md` — auth, изоляция компаний, rate-limit.
- `docs/security/file-upload-policy.md` — pipeline загрузки фото.
- `docs/security/threat-model.md` — угрозы и защиты.

## Продукт

- `docs/product/mvp-scope.md` — что считается выпущенным MVP.
- `docs/product/roles-and-access.md` — superadmin / admin / installer / customer.
- `docs/product/mobile-first-ux.md` — правила UI для телефона.
- `docs/product/photo-checklist.md` — политика отображения фото в PDF.

## Решения

- `docs/decisions/adr-0001-installation-item-model.md`
- `docs/decisions/adr-0002-image-storage-jpeg-only.md`
- `docs/decisions/adr-0003-multi-tenant-companies.md`
- `docs/decisions/adr-0004-customer-portal-two-codes.md`

## Эксплуатация

- `scripts/preflight.php` — автоматическая проверка готовности окружения.
- `scripts/cleanup.php [days]` — обрезка `audit_log` (для cron).

## Прочее

- `docs/roadmap.md` — что уже сделано и что дальше.
- `docs/log.md` — журнал изменений документации.
- `docs/contradictions.md` — известные несоответствия (исторически).
- `docs/cards.md` — knowledge cards (ключевые тезисы).
