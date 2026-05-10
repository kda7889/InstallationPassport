# Form Contracts

## `photo_upload.php`
Required fields:
- `_csrf`
- `installation_id`
- `scope` (`common|item`)
- `photo` (file)

Optional:
- `installation_item_id` (required for `scope=item`)
- `photo_code`
- `title`

## `installation_item_edit.php`
- `title` (required)
- `location`, `brand`, `model`
- `indoor_serial`, `outdoor_serial`
