# Test strategy (MVP)

## Автопроверки

- `find app public -name '*.php' -print0 | xargs -0 -n1 php -l`
- `php scripts/preflight.php`

## Smoke (ручной)

- Сценарий из `docs/operations/runbook.md`.

## Что добавить следующим шагом

- PHPStan/Psalm для статанализа.
- Минимальные feature-тесты (login, create installation, upload photo, generate pdf).
- CI pipeline с запуском lint + preflight в тестовом контейнере.
