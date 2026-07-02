---
layout: default
title: RichEditor
parent: Fields
nav_order: 4
---

# RichEditor

A markdown-flavoured textarea with a minimal toolbar (bold, italic, list item, link). The stored value is always plain text/Markdown — no HTML is written to the database.

```php
use Yuga\Forge\Fields\RichEditor;
```

## Usage

```php
RichEditor::make('body')
    ->label('Description')
    ->rows(10),
```

## Methods

| Method | Purpose |
|--------|---------|
| `rows(int $rows): static` | Textarea height in rows (default: 6) |

All [common field methods](index#common-api) also apply.

## Toolbar

The toolbar inserts standard Markdown tokens around the current selection:

| Button | Inserts |
|--------|---------|
| **B** | `**selection**` |
| _I_ | `*selection*` |
| • | `- selection` |
| 🔗 | `[selection](https://)` |

The toolbar works by mutating the textarea's value directly and dispatching an `input` event, which YLC's `ylc:model` binding picks up through the normal debounced flow.

## Notes

- The stored value is Markdown, not HTML. Your application is responsible for rendering it (e.g. via a Markdown parser) on the front-end.
- There is no image-upload integration in the toolbar; use [FileUpload](file-upload) as a separate field if you need it.
