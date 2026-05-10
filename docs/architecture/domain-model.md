# Domain Model

## Core entities
- **Installation** (`installations`) — объект работ.
- **Installation Item** (`installation_items`) — узел/оборудование внутри объекта.
- **Photo Template** (`photo_templates`) — требования к фото.
- **Installation Photo** (`installation_photos`) — факт загруженного фото.
- **Generated Document** (`generated_documents`) — версия сформированного документа.

## Key invariants
1. Один `installation` имеет 0..N `installation_items`.
2. Фото имеет `scope`:
   - `common` (на уровне объекта),
   - `item` (на уровне элемента).
3. Для `scope=item` требуется `installation_item_id`.
4. PDF агрегирует фото только из фактических записей `installation_photos`.
