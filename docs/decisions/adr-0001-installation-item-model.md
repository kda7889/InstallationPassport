# ADR-0001: Installation -> Items model

## Status
Accepted

## Context
На объекте может быть несколько единиц оборудования (например, 2-10 кондиционеров).

## Decision
Принять модель `installation` как контейнер, `installation_items` как отдельные узлы.

## Consequences
- Правильная группировка фото и PDF.
- Простое расширение на новые типы работ.
