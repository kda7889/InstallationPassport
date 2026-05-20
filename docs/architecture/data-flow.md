# Data Flow

## Внутренний поток (admin / installer)

1. Login → `attempt_login` → `is_active=1` + пароль + `companies.is_active=1` (для не-superadmin) → ротация session id → `$_SESSION['user']`.
2. Создание `installation` (status `draft`): автоматически выдаются `verification_code` (12 hex) и `access_token` (16 hex), номер вида `MP-20260520-A3F4E2`.
3. Добавление N `installation_items`.
4. Upload фото по `scope=item` или `scope=common`, с тегом `photo_stage`. JPEG-конвертация и thumbnail сразу.
5. Опционально — генерация PDF: фотоотчёт + QR на `verification_code` + распечатанная отдельной строкой ссылка с `access_token`.
6. Скачивание PDF с ACL-проверкой через `can_access_installation()`.
7. Модерация отзывов в `/reviews.php` (admin своей компании, superadmin — всё).

## Customer-portal поток (без аккаунта)

1. Клиент сканирует QR из PDF → `customer.php?n=<номер>&c=<verification_code>`.
2. Запрос проверяет `hash_equals` сначала с `access_token`, потом с `verification_code` — выставляет `accessLevel` (`personal` / `public` / `none`).
3. **public**: показ страницы с маской на ФИО (`mask_name`), телефон (`mask_phone`), адрес (`mask_address`). Кнопка «оставить отзыв» скрыта.
4. **personal**: всё открыто, доступна кнопка `/review_submit.php`.
5. Отправка отзыва (`POST /review_submit.php`): проверка access_token, проверка анти-дубликата `(installation_id, period_label)`, IP rate-limit (5 / час по `audit_log`), вставка `reviews` + `review_ratings` в транзакции, лог `review.submitted`, redirect обратно в `customer.php`.

## Public verification поток

- Лендинг `/index.php` — топ компаний по среднему рейтингу + поиск гарантийника.
- Карточка из топа ведёт на `customer.php` с `verification_code` (всегда маскированный режим).
- `photo_public.php?n=…&c=…&p=<id>` — отдаёт фото из защищённого `storage/installations/...`, валидируя оба кода.

## Security gates

- Auth check на каждом приватном endpoint (`require_auth` / `require_admin` / `require_superadmin`).
- ACL check (superadmin / admin-в-своей-компании / installer-владелец).
- CSRF на state-changing запросах (`csrf_validate(post('_csrf'))`).
- MIME + size validation на upload + re-encode в JPEG.
- Login rate-limit: 10 неудач за 5 минут по IP блокируют логин (см. `app/audit.php::login_rate_limit_block`).
- IP rate-limit на customer-отзывы: 5 / час.
- `client_ip()` уважает `X-Forwarded-For`, если `REMOTE_ADDR` — loopback.
