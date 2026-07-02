---
title: Filters
sidebar_position: 6
---

# Filters

Filters appear in the collapsible filter bar above the table. They narrow the record set with server-side SQL queries — no client-side filtering.

Declare them in `table()`:

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([...])
        ->filters([
            SelectFilter::make('status')
                ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                ->allLabel('All statuses'),
            DateRangeFilter::make('created_at')
                ->label('Created'),
        ]);
}
```

## Common API

### `make(string $name): static`

The name must match the database column to filter on.

### `label(string $label): static`

Overrides the auto-generated label.

### `default(mixed $value): static`

The initial value when the filter is not set (default: `'all'` for `SelectFilter`, `['from' => '', 'to' => '']` for `DateRangeFilter`).

## Filters reference

| Class | Use for |
|-------|---------|
| [SelectFilter](select-filter) | Single-value dropdown filter |
| [DateRangeFilter](date-range-filter) | From/to date range filter |
