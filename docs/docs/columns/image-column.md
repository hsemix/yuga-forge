---
title: ImageColumn
sidebar_position: 3
---

# ImageColumn

Renders a thumbnail `<img>` from a stored file path — the same format that [FileUpload](../fields/file-upload) commits to the database.

```php
use Yuga\Forge\Columns\ImageColumn;
```

## Usage

```php
ImageColumn::make('avatar')
    ->label('Photo')
    ->size('md')
    ->circular(),
```

## Methods

| Method | Purpose |
|--------|---------|
| `size(string $size): static` | `'sm'` (32px), `'md'` (40px, default), `'lg'` (64px) |
| `circular(): static` | Renders with `border-radius: 50%` (round instead of rounded square) |

All [common column methods](index#common-api) also apply.

## Notes

- If the column value is null or empty, a placeholder dash (`—`) renders instead of a broken image.
- The stored value must be a URL or absolute path the browser can reach (e.g. `/uploads/avatars/token_photo.jpg`). Fresh-upload metadata shapes (`{token, name, ...}`) only live in form state and never reach a persisted record's columns.
