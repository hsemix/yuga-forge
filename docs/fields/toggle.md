---
layout: default
title: Toggle
parent: Fields
nav_order: 6
---

# Toggle

A checkbox rendered as `<input type="checkbox">` for boolean columns.

```php
use Yuga\Forge\Fields\Toggle;
```

## Usage

```php
Toggle::make('is_active')
    ->label('Active')
    ->default(true),
```

## Notes

- The label renders to the **right** of the checkbox (unlike most fields where it's above).
- Stores `true`/`false` (PHP bool), cast to `1`/`0` by the ORM on save.
- No extra options — it's a boolean.

All [common field methods](index#common-api) apply.
