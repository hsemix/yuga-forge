---
title: TextInput
sidebar_position: 1
---

# TextInput

A single-line `<input>` field. Defaults to `type="text"`; call a mode method to switch the input type.

```php
use Yuga\Forge\Fields\TextInput;
```

## Usage

```php
TextInput::make('name')
    ->label('Full name')
    ->required()
    ->minLength(2),

TextInput::make('email')
    ->email()
    ->required(),

TextInput::make('price')
    ->number(),

TextInput::make('password')
    ->password(),
```

## Methods

| Method | Effect |
|--------|--------|
| `email(): static` | Sets `type="email"` and adds the `email` validation rule |
| `number(): static` | Sets `type="number"` |
| `password(): static` | Sets `type="password"` |

All [common field methods](index#common-api) also apply.

## Notes

- `email()` adds the `email` validation rule automatically — you don't need `->rule('email')` separately.
- There is no `->placeholder()` method; browser default placeholder behaviour applies.
