---
layout: default
title: TextColumn
parent: Columns
nav_order: 1
---

# TextColumn

Renders a cell as bold primary text with an optional smaller description line below it.

```php
use Yuga\Forge\Columns\TextColumn;
```

## Usage

```php
TextColumn::make('name')
    ->label('Name')
    ->description('email')
    ->searchable(['email'])
    ->sortable(),
```

`description()` accepts a field name (or dotted relation path) to read and render as a sub-line below the primary value.

## Relation columns

```php
TextColumn::make('customer.name')
    ->label('Customer')
    ->description('customer.email')
    ->searchable(['customer.email'])
    ->sortable(),
```

For `BelongsTo`/`HasOne` relations, Forge automatically builds a SQL `LEFT JOIN` so sort and search run in the database.

## Methods

| Method | Purpose |
|--------|---------|
| `description(string $field): static` | Field name (or dotted path) to show as a sub-line |

All [common column methods](index#common-api) also apply.
