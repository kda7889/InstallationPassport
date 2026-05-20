# Domain Model

## Core entities

- **Company** (`companies`) — арендатор. ИНН, телефон, email, адрес, логотип, флаг `is_active`.
- **User** (`users`) — внутренний пользователь. Поля `role` (`admin` | `installer`), `is_superadmin` (0/1), `company_id`.
- **Installation** (`installations`) — объект работ. `verification_code` (6 байт, публичный QR) и `access_token` (8 байт, личный код для отзывов).
- **Installation Item** (`installation_items`) — узел/оборудование внутри объекта.
- **Photo Template** (`photo_templates`) — необязательные шаблоны рекомендуемых фото по типу работ.
- **Installation Photo** (`installation_photos`) — факт загруженного фото со стадией (`before` / `during` / `after` / `other`).
- **Generated Document** (`generated_documents`) — версия сформированного PDF.
- **Review** (`reviews`) — отзыв клиента по конкретному монтажу.
- **Review Rating** (`review_ratings`) — оценка по критерию (1..5).
- **App Setting** (`app_settings`) — глобальные настройки (унаследовано от моно-арендной версии).
- **Login Attempt / Audit Log** (`login_attempts`, `audit_log`) — телеметрия безопасности.

## Key invariants

1. Каждый `installation`, `user`, `installation_item` принадлежит ровно одной `company` через `company_id`.
2. Доступ к данным: superadmin видит всё; admin видит свою компанию; installer — только свои монтажи в своей компании. Реализовано в `app/permissions.php::can_access_installation()`.
3. Один `installation` имеет 0..N `installation_items`.
4. Фото имеет `scope`:
   - `common` (на уровне объекта),
   - `item` (на уровне элемента, требует `installation_item_id`).
5. Фото имеет `photo_stage` ∈ {`before`, `during`, `after`, `other`}.
6. PDF и customer-портал агрегируют фото только из фактических записей `installation_photos`.
7. Customer-портал разрешает два уровня доступа:
   - **public** (по `verification_code`): ФИО / телефон / адрес маскируются по 152-ФЗ, отзыв оставить **нельзя**.
   - **personal** (по `access_token`): полный доступ + право оставить отзыв.
8. Отзыв уникален в рамках `(installation_id, period_label)`, кроме `period_label = 'custom'` — для свободных follow-up. Защищено частичным UNIQUE-индексом `ux_reviews_installation_period`.
9. Деактивация компании (`is_active = 0`):
   - блокирует логин её пользователей;
   - убивает их активные сессии в `require_auth()`;
   - убирает компанию из топа на лендинге;
   - superadmin не подпадает под это правило.

## Review criteria

Критерии в `app/settings.php::review_criteria()`:
- `punctuality` — Пунктуальность
- `quality` — Качество монтажа
- `cleanliness` — Чистота
- `communication` — Общение
- `price_transparency` — Прозрачное ценообразование
