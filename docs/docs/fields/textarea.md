---
title: Textarea
sidebar_position: 2
---

# Textarea

A multi-line `<textarea>` for plain text.

```php
use Yuga\Forge\Fields\Textarea;
```

## Usage

```php
Textarea::make('notes')
    ->label('Notes')
    ->rows(5),
```

## Methods

| Method | Purpose |
|--------|---------|
| `rows(int $rows): static` | Number of visible rows (default: 3) |

All [common field methods](index#common-api) also apply.

## Notes

For markdown-flavoured text with a bold/italic/link toolbar, use [RichEditor](rich-editor) instead.
