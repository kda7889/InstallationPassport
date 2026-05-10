# Data Flow

## Основной поток
1. Login -> session user.
2. Создание installation (draft).
3. Добавление N items.
4. Upload фото по item/common.
5. Проверка полноты важных фото.
6. Генерация PDF.
7. Скачивание PDF с ACL-проверкой.

## Security gates
- Auth check на каждом приватном endpoint.
- ACL check (admin/all vs installer/own).
- CSRF на state-changing запросах.
- MIME/size validation на upload.
