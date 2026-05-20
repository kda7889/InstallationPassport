# Routes

Уровни доступа:
- **public** — без аутентификации.
- **auth** — нужен `$_SESSION['user']` (admin или installer).
- **admin** — `role=admin` ИЛИ `is_superadmin=1`.
- **superadmin** — `is_superadmin=1`.
- **token** — без сессии, но требует валидной пары (`n`, `c`).

## Public / landing

- `GET /` (`index.php`) — public. Лендинг: топ компаний, топ монтажников, поиск гарантийника.

## Auth

- `GET/POST /login.php` — public. Принимает `?suspended=1`, чтобы показать уведомление о приостановке компании.
- `GET /logout.php` — auth.

## Profile

- `GET/POST /profile.php` — auth. Смена своего пароля.

## Installations

- `GET /dashboard.php` — auth. Список монтажей (admin видит свою компанию, installer — свои; superadmin — все, с фильтром по компании).
- `GET/POST /installation_create.php` — auth.
- `GET/POST /installation_edit.php?id=` — auth + ACL.

## Photos (internal)

- `POST /photo_upload.php` — auth + CSRF.
- `POST /photo_delete.php` — auth + CSRF.
- `GET /download_photo.php?id=` — auth + ACL.

## PDF

- `POST /generate_pdf.php?id=` — auth + ACL + CSRF.
- `GET /download_pdf.php?id=` — auth + ACL.

## Users / Companies / Reviews (админка)

- `GET/POST /users.php` — admin. Управление пользователями своей компании (или всех — для superadmin).
- `GET/POST /reviews.php` — admin. Модерация отзывов (скрыть / показать).
- `GET/POST /companies.php` — **superadmin**. Создание / включение / отключение арендаторов.
- `GET/POST /company_edit.php?id=` — admin (свою) / superadmin (любую). Бренд + логотип.
- `GET /settings.php` — redirect на `company_edit.php` (legacy-совместимость).
- `GET /audit.php` — admin / superadmin. Просмотр `audit_log`.

## Customer portal (token)

- `GET /customer.php?n=<номер>&c=<код>` — token. Талон в публичной (verification_code) или личной (access_token) проекции.
- `GET /photo_public.php?n=…&c=…&p=<id>` — token. Отдача фото из защищённого storage.
- `GET/POST /review_submit.php?n=…&c=…` — token (только access_token). Форма отзыва.
- `GET /verify.php?n=…&c=…` — token. Минималистичная проверка подлинности (без UI customer-портала).

## Branding

- `GET /branding_logo.php?company=<id>` — public. Логотип компании по id.
