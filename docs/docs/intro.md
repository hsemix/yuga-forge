---
title: Introduction
sidebar_position: 1
slug: /
---

# Yuga Forge

Yuga Forge is a Filament-inspired admin-panel builder for the Yuga Framework. It lets you declare fully interactive resource pages — searchable/sortable tables, create/edit forms, detail views, filters, bulk actions — in pure PHP, with no JavaScript authoring required on your part.

```php
#[Live(name: 'admin.orders-resource')]
class OrdersResource extends Resource
{
    protected string $model = DashboardOrder::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('reference')->required(),
            Select::make('status')->options(['pending', 'paid', 'failed']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->searchable()->sortable(),
            BadgeColumn::make('status')->colors(['paid' => 'emerald', 'failed' => 'red']),
        ]);
    }
}
```

## How it works

Forge sits on top of **Yuga Live Components (YLC)**, which handles server-side state, DOM morphing, and two-way data binding. Every interaction (sort, search, filter, open form, save) is a lightweight AJAX round-trip that morphs only the changed parts of the page — no full reload.

YugaJS (`ys`) manages client-side reactivity (directives, headless components, morphdom diffing). You do not write any of this — Forge generates the markup automatically.

## Packages

| Package | Role |
|---------|------|
| `yuga/framework` | PHP MVVM/MVC base, ORM, DI container |
| `yuga/forge` | This package — admin builder |
| YLC (`ylc/`) | Live component runtime |
| YugaJS (`ys.js`) | Client-side reactivity |

## Next steps

- [Getting started](getting-started) — installation and your first resource
- [Resources](resources) — the `Resource` class reference
- [Fields](fields/) — form field types
- [Columns](columns/) — table column types
- [Filters](filters/) — filter types
- [Actions](actions) — row, bulk, and header action buttons
- [Schema](schema/) — Sections and Tabs
- [Widgets](widgets) — dashboard stat cards and charts
- [Relation managers](relation-managers) — inline related-record tables
- [Authorization](authorization) — Policy and RolePolicy
