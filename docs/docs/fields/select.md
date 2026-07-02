---
title: Select
sidebar_position: 3
---

# Select

A dropdown field with two modes:

- **Static options** — a fixed `<select>` populated from an array.
- **Relationship combobox** — a server-side search-as-you-type combobox backed by a related model's table, for picking one record from a large dataset.

```php
use Yuga\Forge\Fields\Select;
```

## Static options

```php
Select::make('plan')
    ->label('Plan')
    ->options(['Starter', 'Pro', 'Enterprise'])
    ->default('Starter'),
```

If the option array uses string keys, the key is the stored value and the value is the display label:

```php
Select::make('status')
    ->options([
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'banned'   => 'Banned',
    ]),
```

## Relationship combobox

For a `BelongsTo` or `HasOne` relation, call `relationship()` to enable a search-as-you-type combobox instead:

```php
Select::make('customer_id')
    ->label('Customer')
    ->relationship('customer', 'name'),
```

`'customer'` is the relation method name on the resource's model. `'name'` is the column to search and display.

### Optional third argument: result limit

```php
Select::make('customer_id')
    ->relationship('customer', 'name', limit: 30),  // default: 20
```

### Behaviour

- Opening the combobox with no search text shows the first `$limit` rows.
- Typing narrows results with a server-side `WHERE name LIKE '%term%'` query (debounced 300 ms).
- Selecting a result saves the FK id and shows the resolved label.
- The `Resource` owns all DB access and rendering for this mode; `Select` itself only stores the config.
- `->options()` is ignored when `relationship()` is set.

## Methods

| Method | Purpose |
|--------|---------|
| `options(array $options): static` | Static option list |
| `relationship(string $relation, string $titleColumn, int $limit = 20): static` | Enable relationship combobox mode |
| `default(mixed $value): static` | Pre-selected value for new records |

All [common field methods](index#common-api) also apply.
