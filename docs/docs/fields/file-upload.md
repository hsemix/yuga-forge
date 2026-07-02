---
title: FileUpload
sidebar_position: 11
---

# FileUpload

A file input wired to YLC's upload mechanism. Selecting a file POSTs it immediately to `/ylc/upload`; the returned metadata (token, name, type, size, preview URL) is stored in form state. On save, `dehydrate()` moves the temp file to permanent storage under `public/uploads/{directory}/` and writes the stored path to the database.

```php
use Yuga\Forge\Fields\FileUpload;
```

## Usage

```php
FileUpload::make('avatar')
    ->label('Profile photo')
    ->accept('image/*')
    ->directory('avatars'),

FileUpload::make('attachments')
    ->label('Attachments')
    ->multiple()
    ->directory('order-attachments'),
```

## Methods

| Method | Purpose |
|--------|---------|
| `accept(string $accept): static` | MIME type filter passed to the `accept` attribute (e.g. `'image/*'`, `'.pdf,.docx'`) |
| `multiple(): static` | Allow picking multiple files; stored value becomes an array of paths |
| `directory(string $directory): static` | Sub-directory under `public/uploads/` to commit files into (default: `'uploads'`) |

All [common field methods](index#common-api) also apply.

## Storage

Committed files are stored as:

```
public/uploads/{directory}/{40-char-hex-token}_{sanitized-original-name}
```

The path written to the database is:

```
/uploads/{directory}/{token}_{name}
```

Multiple files store a JSON-encoded array of paths in a `TEXT` column.

## Previews

After a file is selected (but before saving), the field shows a preview thumbnail for images or a filename badge for other file types. The same preview renders when loading a record that already has a saved file.

## Manual commit

If you need to commit an upload outside of the normal save flow (e.g. in a bulk action), call the static method directly:

```php
$path = FileUpload::commit($value, 'invoices');
```
