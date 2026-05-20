# File Upload Policy

## Accepted formats
- `jpg`, `jpeg`, `png`, `webp` — обязательно.
- `heic` / `heif` — принимается, если расширение `Imagick` есть в системе. Иначе отказ с пояснением.

## Validation pipeline
1. Проверка размера (по `upload_max_filesize` / `post_max_size`).
2. MIME через `finfo_file` (не доверяем расширению клиента).
3. Загрузка библиотекой (`Imagick` приоритетнее `GD` для HEIC и для устойчивости к EXIF-атакам).
4. EXIF orientation fix.
5. Resize: основной кадр — ≤2048 max-side, thumbnail — ≤512.
6. Encode в JPEG, оригинал удаляется.
7. Persist record в `installation_photos` (включая `photo_stage`).

## Бренд-логотипы
- `/company_edit.php` принимает PNG / JPEG / WebP до 2 МБ.
- Сохраняются под `storage/branding/company-<id>.<ext>`.
- Старый файл удаляется при перезагрузке (включая legacy `storage/branding/logo.png` после миграции).
- Отдача через `/branding_logo.php?company=<id>` — позволяет хранить вне webroot.

## Запрет выполнения PHP в storage
- `.htaccess` запрещает `*.php`, `*.phtml`, `*.phar` в `storage/`.
- На nginx-only сетапе — `location` блок в шаблоне домена (см. `docs/operations/deploy-hestia.md` §9).
