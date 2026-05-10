# Auth & Sessions

- Пароли храним только через `password_hash`.
- Проверка через `password_verify`.
- Session cookie с `httponly`, `secure` (в production), `samesite=lax/strict`.
- Ротация session id после логина (recommended next step).
