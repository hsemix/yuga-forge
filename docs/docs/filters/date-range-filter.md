---
title: DateRangeFilter
sidebar_position: 2
---

# DateRangeFilter

Two date pickers (from / to) that add `>=` / `<=` clauses to the query. Either end is optional.

```php
use Yuga\Forge\Filters\DateRangeFilter;
```

## Usage

```php
DateRangeFilter::make('ordered_at')
    ->label('Date range'),
```

## SQL behaviour

- `from` set, `to` empty → `WHERE ordered_at >= 'from-date'`
- `to` set, `from` empty → `WHERE ordered_at <= 'to-date'`
- Both set → both clauses combined
- Neither set → no constraint (filter considered inactive)

All [common filter methods](index#common-api) also apply.

## Notes

The filter state is an associative array `['from' => '', 'to' => '']`, bound via `ylc:model="filters.ordered_at.from"` / `ylc:model="filters.ordered_at.to"`. The Resource's array-bucket handling walks the dot path automatically.
