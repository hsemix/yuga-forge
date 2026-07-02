---
title: Getting started
sidebar_position: 2
---

# Getting started

## Requirements

- PHP 8.2+
- Yuga Framework application
- YLC (Yuga Live Components) loaded in your app

## Installation

Add the path repository to your app's `composer.json` (or use Packagist once the package is published):

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../yuga-forge",
            "options": { "symlink": true }
        }
    ],
    "require": {
        "yuga/forge": "*"
    }
}
```

Then run:

```bash
composer update
```

Forge self-registers via its `ForgeServiceProvider`, discovered automatically by Yuga's package system.

## Creating a resource

A resource is a Yuga Live Component that extends `Yuga\Forge\Resource`. Create one with the Forge generator:

```bash
php yuga forge:resource ProductsResource
```

Or create the file manually in `app/Live/Admin/`:

```php
<?php

namespace App\Live\Admin;

use App\Models\Product;
use Yuga\Forge\Columns\TextColumn;
use Yuga\Forge\Fields\TextInput;
use Yuga\Forge\Resource;
use Yuga\Forge\Schema\Form;
use Yuga\Forge\Schema\Table;
use Yuga\Live\Attributes\Live;

#[Live(name: 'admin.products-resource')]
class ProductsResource extends Resource
{
    protected string $model = Product::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
        ]);
    }
}
```

## Mounting the resource

Register the component in a Yuga view using the `ylc:mount` tag:

```html
<ylc:mount component="admin.products-resource" />
```

## Key conventions

| Property | Default | Purpose |
|----------|---------|---------|
| `$model` | _(required)_ | The Eloquent model class to read/write |
| `$recordKey` | `'id'` | Column used as the record identifier |
| `$keyPrefix` | `''` | Display prefix prepended to keys (e.g. `'PRD'` → `'PRD-1'`) |
| `$softDeletes` | `false` | Enables a Trash view when true |
| `$with` | `[]` | Relations to eager-load, same as `Model::with()` |

## What you get for free

Once `form()` and `table()` are declared, Forge handles:

- Paginated, searchable, sortable table with column toggle
- Create/edit form in a modal or slide-over (see `panelDisplay()`)
- Read-only details view with relation managers
- Soft-delete trash view (when `$softDeletes = true`)
- Bulk selection with bulk-delete and custom bulk actions
- Filter bar
- Notifications on save/delete
