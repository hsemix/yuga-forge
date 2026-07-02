---
layout: default
title: Repeater
parent: Fields
nav_order: 10
---

# Repeater

A dynamic list of repeated field-group rows — for things like product variants, order line items, or any one-to-many data that lives inline in a single form (rather than as a separate model with its own resource).

```php
use Yuga\Forge\Fields\Repeater;
```

## Usage

```php
Repeater::make('variants')
    ->label('Variants')
    ->schema([
        TextInput::make('label')->label('Label')->required(),
        TextInput::make('sku')->label('SKU'),
        TextInput::make('price')->label('Price')->number(),
    ]),
```

## Adding and removing rows

Forge renders **+ Add row** and **Remove** buttons automatically. Clicking them calls `Resource::addRepeaterRow($name)` / `removeRepeaterRow($name, $index)` on the server, which splices `$this->data[$name]` and re-renders — a full server round-trip rather than inline JS, because adding a row changes the structure of the form itself (new inputs appear, not just new values).

## Methods

| Method | Purpose |
|--------|---------|
| `schema(array $fields): static` | The sub-fields that appear in every row |

All [common field methods](index#common-api) also apply. Note that `required()` / `rule()` validate the **outer** array (e.g. "must have at least one row"). Sub-field validation is not yet wired per-row.

## Storage

Rows are stored as a JSON array of associative arrays in a `TEXT` column:

```json
[
    {"label": "Small", "sku": "SKU-S", "price": "19"},
    {"label": "Large", "sku": "SKU-L", "price": "29"}
]
```

`dehydrate()` and `hydrate()` handle serialization automatically. Migration:

```php
$table->text('variants')->nullable();
```

## Limitations

- Using a `Repeater` inside another `Repeater` is not supported.
- `Select::relationship()` (the combobox mode) cannot be used as a sub-field inside a `Repeater` row in v1 — the nested dot-path binding isn't wired to the relationship combobox renderer.
