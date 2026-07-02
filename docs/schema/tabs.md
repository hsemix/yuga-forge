---
layout: default
title: Tabs
parent: Schema
nav_order: 2
---

# Tabs

`Tabs` splits a long form into named tab panels. Drop it directly into `Form::schema()`:

```php
use Yuga\Forge\Schema\Tabs;
use Yuga\Forge\Schema\Tab;
```

## Usage

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Tabs::make([
            Tab::make('General')->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->required(),
            ]),
            Tab::make('Pricing')->schema([
                TextInput::make('price')->number(),
                Select::make('currency')->options(['USD', 'EUR', 'GBP']),
            ]),
            Tab::make('Inventory')->schema([
                TextInput::make('sku'),
                Toggle::make('track_stock'),
            ]),
        ]),
    ]);
}
```

## How it works

Tab switching is purely client-side (no server round-trip, nothing persists about which tab is open). The active tab is tracked via YugaJS's `headless-tabs` component — a reactive index that drives `ys-show` on each panel.

Validation, save, and the "Details" view see all fields from all tabs regardless of which is currently active.

## Classes

### `Tabs`

| Method | Purpose |
|--------|---------|
| `make(array $tabs): static` | Create with an array of `Tab` instances |

### `Tab`

| Method | Purpose |
|--------|---------|
| `make(string $label): static` | Tab heading |
| `schema(array $fields): static` | Fields in this tab panel |

## Notes

- Bare `Field` instances and `Section` instances can be mixed inside a `Tab::schema()`.
- `Tabs` itself cannot be nested inside a `Tab`.
