# Auth & Sessions

## Хранение и проверка паролей
- Только `password_hash(PASSWORD_DEFAULT)` (bcrypt по умолчанию).
- Проверка через `password_verify`.
- Смена пароля: `/profile.php` (своего) или `/users.php` (для admin).

## Cookie / сессии
- `HttpOnly`, `SameSite=Lax`.
- `Secure` — выставляется автоматически, если HTTPS (`$_SERVER['HTTPS']` либо `X-Forwarded-Proto: https`).
- Ротация session id после успешного логина (`session_regenerate_id(true)`).

## Изоляция арендаторов
- `attempt_login()` отказывает пользователю, чья компания приостановлена (`companies.is_active = 0`), кроме `is_superadmin`.
- `require_auth()` при каждом авторизованном запросе перепроверяет статус компании и убивает сессию, если её приостановили после логина.
- ACL в `app/permissions.php::can_access_installation()`:
  - superadmin → доступ ко всему;
  - admin → доступ к данным своей `company_id`;
  - installer → доступ только к своим монтажам в своей компании.

## Rate-limit и аудит
- `login_attempts` хранит попытки. `login_rate_limit_block($ip)` блокирует, если ≥10 неудач за 5 минут с одного IP.
- `audit_log` пишется на ключевые действия (login.*, installation.*, photo.*, review.*, company.*, user.*).
- TTL: `audit_log_cleanup($daysToKeep = 365)` лениво вызывается из `audit_log()` с вероятностью 0.1%; `scripts/cleanup.php` — для cron.

## Получение IP за прокси
- `client_ip()` отдаёт `REMOTE_ADDR`. Если `REMOTE_ADDR` ∈ {`127.0.0.1`, `::1`} и есть `X-Forwarded-For` — берёт первый валидный IP из заголовка. Это критично для per-IP rate-limit за nginx-фронтом.

## Customer-portal токены
- `installations.verification_code` — 12 hex, для публичной проверки талона. В QR.
- `installations.access_token` — 16 hex, для личного доступа клиента (включая возможность оставить отзыв). Печатается в PDF отдельной строкой.
- Сравнение через `hash_equals` (constant-time).
- Случай конфликта длин невозможен (12 ↔ 16 hex), коллизия в пределах одной installation также проверяется до сравнения.
