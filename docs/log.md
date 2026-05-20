# Documentation Log

## 2026-05-20 (2)
- Убран блок «элементы монтажа» — фото теперь только на уровне объекта.
- Добавлен сворачиваемый чек-лист рекомендуемых фото на странице монтажа (рендерится из `photo_templates` по `work_type_id`).
- Миграция `db_migrate_drop_items()` удаляет старые item-фото (файлы и записи), дропает таблицу `installation_items` и колонку `installation_photos.installation_item_id`.
- Обновлены: README, domain-model, routes, form-contracts, storage-layout, runbook, log.

## 2026-05-20
- Пересборка документации под Multi-tenant SaaS-релиз.
- Обновлены: `README.md`, `docs/index.md`, `docs/roadmap.md`, `docs/cards.md`, `docs/contradictions.md`.
- Обновлены архитектурные документы: `domain-model.md`, `data-flow.md`, `storage-layout.md` (companies, reviews, два кода, photo_stage).
- Обновлены API-документы: `routes.md` (все новые endpoints), `form-contracts.md` (review_submit, company_edit, и т.д.).
- Обновлены секьюрити-документы: `auth-and-sessions.md` (изоляция, rate-limit, X-Forwarded-For), `threat-model.md`, `file-upload-policy.md` (HEIC).
- Обновлены продуктовые документы: `mvp-scope.md`, `roles-and-access.md` (superadmin / customer).
- Обновлены операционные: `runbook.md` (новые smoke-сценарии), `deploy-php-hosting.md` (cron).
- Новые ADR: `adr-0003-multi-tenant-companies.md`, `adr-0004-customer-portal-two-codes.md`.

## 2026-05-10
- Инициализирована LLM Wiki структура `docs/`.
- Добавлены атомарные документы по архитектуре, продукту, безопасности, API, эксплуатации, ADR и тестированию.
- Зафиксированы противоречия между текущей реализацией и ТЗ (см. `contradictions.md`).
