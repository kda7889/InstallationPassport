# Test Strategy

## Layers
- **Lint/Syntax:** `php -l` по всем файлам.
- **Feature smoke:** ручной сценарий login -> create -> items -> upload -> generate/download pdf.
- **Security checks:** ACL отрицательные сценарии, CSRF negative tests, upload negative tests.

## Minimum CI checks (planned)
- php syntax check
- static analysis
- route smoke via HTTP client
