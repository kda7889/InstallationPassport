# Form Contracts

## `photo_upload.php`
Required:
- `_csrf`
- `installation_id`
- `photo` (file: JPEG/PNG/WebP/HEIC)

Optional:
- `photo_code`
- `title`
- `photo_stage` (`before` | `during` | `after` | `other`, по умолчанию `other`)

Все фото привязываются к монтажу как `scope = 'common'`. Поля `scope` и `installation_item_id` из формы больше не принимаются.

## `photo_delete.php`
Required: `_csrf`, `id`.

## `installation_create.php`
Required: `_csrf`, `work_type_id`, `address`.

## `installation_edit.php`
Все поля карточки + `_csrf`. `install_date` — `YYYY-MM-DD`; гарантия в месяцах пересчитывается в `warranty_until`. На странице показывается сворачиваемый список «Что снять для этого типа работ» — он рендерится из `photo_templates`, ничего не отправляется на сервер.

## `users.php`
- Создание: `name`, `email`, `password`, `role` (`admin` | `installer`); для superadmin — также `company_id`.
- Toggle активности: `_csrf`, `action=toggle`, `id`. Защита от деактивации последнего админа в компании.
- Смена пароля: `_csrf`, `action=set_password`, `id`, `password`.

## `companies.php` (superadmin)
- Создание: `name` (required), `inn`, `phone`, `email`, `address`.
- Toggle активности: `action=toggle`, `id`.

## `company_edit.php` (admin своей / superadmin любой)
- `name`, `inn`, `phone`, `email`, `address`.
- `logo` (file, опционально) — лимит 2 МБ, MIME через `finfo`.

## `review_submit.php` (token / только access_token)
- `period_label` (`initial` | `1m` | `1y` | `2y` | `3y` | `custom`).
- `rating_<criterion>` ∈ {1..5} для каждого из 5 критериев (необязательно — если нет ни одной звезды, общая оценка по умолчанию 5/5).
- `text`, `suggestions` — свободный текст.
- `name` — необязательная подпись (в публичной проекции маскируется).

Ограничения:
- Анти-дубликат: один отзыв на `(installation, period_label ≠ 'custom')`. Защищено частичным UNIQUE-индексом + PHP-чеком.
- IP rate-limit: 5 отзывов в час на один публичный IP.

## `reviews.php` (admin)
- `action=hide` / `action=unhide`, `_csrf`, `review_id`. Опциональный `reason`.

## `login.php`
- `_csrf`, `email`, `password`.
- Принимает `?suspended=1` для показа уведомления о приостановке компании.

## `profile.php`
- `_csrf`, `current_password`, `new_password`.
