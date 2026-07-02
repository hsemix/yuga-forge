---
title: Hidden
sidebar_position: 12
---

# Hidden

A `<input type="hidden">` that carries a value through validation and save without showing any visible input or label. It does not appear in the "Details" view either.

```php
use Yuga\Forge\Fields\Hidden;
```

## Usage

```php
Hidden::make('signup_channel')
    ->default('admin-panel'),
```

## Common uses

- Hardcoding a constant value the admin-created record should always have (e.g. `source = 'admin'`).
- Carrying a parent record's ID when creating child records from a nested context.

## Notes

- `Hidden` overrides `isHiddenField()` to return `true`, which tells the form renderer to omit any wrapping `<label>` and the details view to skip it entirely.
- `default()` is the only method you normally need. Validation rules and `visible()`/`hidden()` work but are rarely useful on a hidden field.

All [common field methods](index#common-api) apply.
