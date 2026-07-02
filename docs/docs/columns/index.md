---
title: Columns
sidebar_position: 5
---

# Columns

Columns define the cells that appear in a resource's data table. Every column type extends `Yuga\Forge\Columns\Column`.

## Common API

### `make(string $name): static`

Static factory. `$name` is the model attribute to read. Supports dotted relation paths:

```php
TextColumn::make('customer.name')
```

### `label(string $label): static`

Overrides the auto-generated column heading.

### `sortable(): static`

Adds a sort button to the column header. For dotted relation columns, Forge automatically joins the relation (for `BelongsTo`/`HasOne`) so the sort runs in SQL.

### `searchable(array $extraColumns = []): static`

Includes this column in the global search. Pass extra column names to also search (e.g. an email address shown as a description below the primary column):

```php
TextColumn::make('name')
    ->searchable(['email']),
```

Dotted relation columns are joined automatically for searchable too.

### `formatUsing(\Closure $callback): static`

Transform the raw value before rendering. The callback receives `($value, $record)`:

```php
TextColumn::make('amount')
    ->formatUsing(fn($value) => '$' . number_format((int) $value)),
```

### `toggleable(bool $toggleable = true): static`

By default all columns appear in the **Columns** toggle panel and can be hidden by the user. Pass `false` to pin a column as always-visible:

```php
TextColumn::make('name')->toggleable(false),
```

## Columns reference

| Class | Use for |
|-------|---------|
| [TextColumn](text-column) | Plain text, with optional sub-description |
| [BadgeColumn](badge-column) | Coloured pill badge for status-like values |
| [ImageColumn](image-column) | Thumbnail for stored file paths |
