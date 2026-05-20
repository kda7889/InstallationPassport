# Storage Layout

```text
/storage/
├── database.sqlite                          # БД
├── initial-admin-credentials.txt            # пароль дефолтного админа (удалить после первого входа)
├── branding/
│   └── company-<id>.{png|jpg|webp}          # логотипы арендаторов
├── tmp/                                     # mPDF temp
└── installations/
    └── {year}/{installation_number}/
        ├── common/photos/compressed/        # JPEG ≤2048 max-side
        ├── common/photos/thumbnails/        # JPEG ≤512 max-side
        └── documents/                       # PDF
```

> Подкаталоги `items/...` исторически создавались, когда фото привязывались к элементам монтажа. С момента релиза «без элементов» новые фото туда не пишутся; миграция удаляет старые item-фото вместе с записями в БД, но **сами папки `items/`** на диске не убираются — оставлены на случай ручного восстановления. При желании можно вычистить вручную:
>
> ```bash
> find storage/installations -type d -name items -empty -delete
> ```

## Naming convention

`{installation}_{item_or_common}_{photo_code}_{datetime}_{rand}.jpg`

## Principles

- Оригинал фото **не сохраняется** — конвертация в JPEG сразу при upload (см. ADR-0002).
- HEIC принимается через Imagick (если установлен) и тоже нормализуется в JPEG.
- Thumbnail обязателен для списков и customer-портала.
- Каталог `storage/` **вне webroot** (см. `docs/operations/deploy-hestia.md`). Доступ к фото для customer-портала — только через `photo_public.php`, валидирующий код.
- Логотипы компаний хранятся под `storage/branding/company-<id>.*` и отдаются через `branding_logo.php?company=<id>` — это позволяет хранить их вне webroot и переключать без рестарта.
