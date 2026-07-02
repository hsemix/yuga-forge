---
layout: default
title: Radio
parent: Fields
nav_order: 5
---

# Radio

A group of radio buttons. All options are always visible — unlike `Select`, there's no dropdown to open. Good for 2–5 options where seeing all choices at once matters more than saving vertical space.

```php
use Yuga\Forge\Fields\Radio;
```

## Usage

```php
Radio::make('referral_source')
    ->label('How did they find us?')
    ->options([
        'friend' => 'Friend or colleague',
        'search' => 'Search engine',
        'ad'     => 'Advertisement',
        'other'  => 'Other',
    ]),
```

If option keys and labels are the same, pass a plain indexed array:

```php
Radio::make('size')->options(['S', 'M', 'L', 'XL']),
```

## Methods

| Method | Purpose |
|--------|---------|
| `options(array $options): static` | The choices. Keys are stored values; values are display labels. For an indexed array, the element itself is both. |

All [common field methods](index#common-api) also apply.

## Conditional fields based on radio selection

Combine `Radio` with `visible()` on sibling fields:

```php
Radio::make('referral_source')->options([
    'friend' => 'Friend',
    'other'  => 'Other',
]),
TextInput::make('referral_detail')
    ->label('Please specify')
    ->visible(fn(array $data) => ($data['referral_source'] ?? null) === 'other'),
```

The detail field appears/disappears on the next server render (debounced `ylc:model` change) — no JavaScript required.
