# Threat Model (MVP)

## Основные угрозы
- IDOR (доступ к чужим монтажам/фото/PDF).
- CSRF на изменяющих действиях.
- Malicious upload (polyglot, oversized, script payload).
- Brute force login.

## Controls
- Централизованные ACL-проверки.
- CSRF token в POST формах.
- MIME+size+re-encode в JPEG.
- Ограничение попыток входа (planned).
