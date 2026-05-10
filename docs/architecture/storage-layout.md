# Storage Layout

```text
/storage/installations/{year}/{installation_number}/
  common/photos/compressed/
  common/photos/thumbnails/
  items/{item_number}/photos/compressed/
  items/{item_number}/photos/thumbnails/
  documents/
```

## Naming convention
`{installation}_{item_or_common}_{photo_code}_{datetime}_{rand}.jpg`

## Principles
- Не хранить оригиналы в MVP.
- Нормализовать в JPEG.
- Поддерживать thumbnail для списков/предпросмотра.
