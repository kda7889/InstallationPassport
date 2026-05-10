# Runbook

## Smoke checks
- Login под admin.
- Создать монтаж.
- Добавить 2 элемента.
- Загрузить фото по каждому элементу.
- Сгенерировать PDF.
- Скачать PDF.

## Incident hints
- Upload fails -> проверить `upload_max_filesize`, `post_max_size`, права на storage.
- PDF fails -> проверить установлен ли mPDF и write permissions в `storage`.
