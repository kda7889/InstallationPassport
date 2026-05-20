# Threat Model

## Угрозы

| ID | Угроза | Контроль |
|----|--------|----------|
| T-01 | IDOR на чужие монтажи / фото / PDF | `can_access_installation()` на каждом ACL-чувствительном endpoint. |
| T-02 | Кросс-арендный доступ (admin компании А → данные Б) | Сессия хранит `company_id`; SQL-запросы фильтруют по нему. |
| T-03 | CSRF на изменяющих действиях | `csrf_validate()` обязателен на POST. |
| T-04 | Malicious upload (PHP-шелл под `.jpg`) | MIME через `finfo` + decode библиотекой + re-encode в JPEG. Storage вне webroot. |
| T-05 | Brute force login | `login_rate_limit_block()` — 10 неудач / 5 минут / IP. |
| T-06 | Утечка ПДн через публичную страницу талона | `accessLevel = 'public'` → `mask_name`, `mask_phone`, `mask_address`. Только `verification_code` даёт публичный доступ. |
| T-07 | Подделка отзыва клиентом / спам | Отзыв требует `access_token` (личный код из PDF). UNIQUE на `(installation, period)` для не-custom. IP rate-limit 5/час. |
| T-08 | Спам атаки через X-Forwarded-For | `client_ip()` доверяет XFF **только** если `REMOTE_ADDR` — loopback. На прод-сетапе с nginx-фронтом это безопасно. |
| T-09 | Активная сессия после приостановки компании | `require_auth()` перепроверяет `companies.is_active` на каждом запросе и убивает сессию. |
| T-10 | Расползание `audit_log` без ограничений | TTL 365 дней: ленивая чистка (1/1000 на каждый insert) + cron `scripts/cleanup.php`. |
| T-11 | Подделка PDF | На talon-странице (`customer.php`) показывается отметка «Документ подлинный ✓» только после проверки `hash_equals` по коду из БД. |
| T-12 | Утечка `access_token` через распечатанный QR | QR кодирует **только** публичную ссылку (`verification_code`). Личный URL печатается отдельной строкой — клиент сам решает, кому давать. |

## Контрольные точки

- Уровни доступа: `require_auth`, `require_admin`, `require_superadmin`.
- Per-tenant изоляция: `$user['company_id']` в session + SQL where.
- Маски ПДн: `app/privacy.php` (`mask_name`, `mask_phone`, `mask_address`, `mask_email`).
- Constant-time проверка кодов: `hash_equals`.
- Нормализация номера документа: `mb_strtoupper(trim(...))` для совпадения с DB-форматом.
