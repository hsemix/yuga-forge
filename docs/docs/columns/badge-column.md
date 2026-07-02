---
title: BadgeColumn
sidebar_position: 2
---

# BadgeColumn

Renders a cell as a coloured pill badge — ideal for status or category columns.

```php
use Yuga\Forge\Columns\BadgeColumn;
```

## Usage

```php
BadgeColumn::make('status')
    ->label('Status')
    ->colors([
        'paid'       => 'emerald',
        'processing' => 'amber',
        'failed'     => 'red',
    ])
    ->sortable(),
```

## Available colours

| Key | Appearance |
|-----|-----------|
| `slate` | Grey (default for unmapped values) |
| `emerald` | Green |
| `amber` | Yellow/orange |
| `red` | Red |
| `violet` | Purple |
| `azure` | Blue |

## Methods

| Method | Purpose |
|--------|---------|
| `colors(array $colors): static` | Maps column values to colour keys. Unmapped values fall back to `slate`. |

All [common column methods](index#common-api) also apply.
