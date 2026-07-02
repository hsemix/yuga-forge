---
layout: default
title: MultiSelect
parent: Fields
nav_order: 8
---

# MultiSelect

A native `<select multiple>` for picking multiple values from a predefined list.

```php
use Yuga\Forge\Fields\MultiSelect;
```

## Usage

```php
MultiSelect::make('roles')
    ->label('Roles')
    ->options([
        'editor'  => 'Editor',
        'viewer'  => 'Viewer',
        'billing' => 'Billing',
    ]),
```

With a simple indexed array (key = value = label):

```php
MultiSelect::make('tags')->options(['php', 'js', 'css']),
```

## Methods

| Method | Purpose |
|--------|---------|
| `options(array $options): static` | Available choices |

All [common field methods](index#common-api) also apply.

## Storage

Selected values are stored as a JSON array in a `TEXT` column:

```json
["editor", "billing"]
```

`dehydrate()` and `hydrate()` handle serialization automatically. Migration:

```php
$table->text('roles')->nullable();
```

## Notes

YLC's `ylc:model` binding on a `<select multiple>` returns `Array.from(el.selectedOptions).map(o => o.value)` automatically — no custom JS needed.
