---
layout: default
title: SelectFilter
parent: Filters
nav_order: 1
---

# SelectFilter

A `<select>` dropdown that filters records to those matching a single value.

```php
use Yuga\Forge\Filters\SelectFilter;
```

## Usage

```php
SelectFilter::make('status')
    ->label('Status')
    ->options([
        'paid'       => 'Paid',
        'processing' => 'Processing',
        'failed'     => 'Failed',
    ])
    ->allLabel('All statuses'),
```

With a plain indexed array (value = label):

```php
SelectFilter::make('plan')
    ->options(['Starter', 'Pro', 'Enterprise'])
    ->allLabel('All plans'),
```

## Methods

| Method | Purpose |
|--------|---------|
| `options(array $options): static` | The filterable choices |
| `allLabel(string $label): static` | Label for the "show all" option (default: `'All'`) |

All [common filter methods](index#common-api) also apply.

## SQL behaviour

When active, adds `WHERE {name} = {value}` to the base query. The filter is skipped when the current value equals the declared `default` (`'all'`).
