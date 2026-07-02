---
layout: default
title: DatePicker
parent: Fields
nav_order: 7
---

# DatePicker

A native `<input type="date">` (or `type="datetime-local"` with `withTime()`).

```php
use Yuga\Forge\Fields\DatePicker;
```

## Usage

```php
DatePicker::make('born_on')
    ->label('Date of birth'),

DatePicker::make('starts_at')
    ->label('Starts at')
    ->withTime(),
```

## Methods

| Method | Purpose |
|--------|---------|
| `withTime(): static` | Switches to `type="datetime-local"` |

All [common field methods](index#common-api) also apply.

## Notes

- An empty date input sends `''` (an empty string). `DatePicker::dehydrate()` converts that to `null` automatically, which is what `DATE`/`DATETIME` columns expect. You do not need a `dehydrateUsing()` callback for this.
- Values must be in `YYYY-MM-DD` (or `YYYY-MM-DDTHH:MM` for datetime-local) format for the native picker to pre-fill correctly. If your database stores a different format, use `hydrateUsing()` to reformat on load.
