# Roadmap

## Phase 0 — Stabilization (1-2 итерации)
- Убрать HTML fallback для PDF.
- Перевести destructive actions на POST+CSRF.
- Довести валидацию входных данных и ошибки UX.

## Phase 1 — MVP completion
- Пользователи и роли (полный admin CRUD).
- Полные формы монтажа + элементов по типам работ.
- Полный checklist фото для кондиционеров.
- Предупреждение о недостающих важных фото.
- История PDF версий + страница просмотра.

## Phase 2 — Production hardening
- Логирование событий безопасности.
- Rate limiting на login/upload.
- Резервные копии SQLite + storage.
- Набор smoke/integration тестов.

## Phase 3 — Extensions
- Публичная ссылка на PDF.
- QR в талоне.
- PWA/offline draft.
- HEIC pipeline (опционально).
