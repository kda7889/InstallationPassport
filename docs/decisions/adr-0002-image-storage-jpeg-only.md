# ADR-0002: JPEG-only storage in MVP

## Status
Accepted

## Context
Ограниченный shared-hosting ресурс и потребность в быстрых PDF.

## Decision
После upload конвертировать изображения в JPEG и не хранить оригинал в MVP.

## Consequences
- Экономия диска и ускорение рендера.
- Потеря оригинала как артефакта; можно расширить в future version.
