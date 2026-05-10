# File Upload Policy

## Accepted formats (MVP)
- `jpg`, `jpeg`, `png`, `webp`.
- HEIC: reject with user guidance.

## Validation pipeline
1. Проверка размера.
2. Проверка MIME через `finfo`.
3. Decode image библиотекой.
4. EXIF orientation fix.
5. Resize to policy bounds.
6. Encode to JPEG.
7. Persist metadata in DB.
