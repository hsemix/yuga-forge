---
title: Relation managers
sidebar_position: 10
---

# Relation managers

A `RelationManager` declares a related model's records to show as an inline table inside a resource's "Details" slide-over — for example, a Customer's Orders.

```php
use Yuga\Forge\Relations\RelationManager;
```

## Usage

Override `relationManagers()` in your resource:

```php
protected function relationManagers(): array
{
    return [
        RelationManager::make('orders')
            ->label('Orders')
            ->columns([
                TextColumn::make('public_id')->label('Order'),
                BadgeColumn::make('status')
                    ->colors(['paid' => 'emerald', 'processing' => 'amber', 'failed' => 'red']),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatUsing(fn($v) => '$' . number_format((int) $v)),
                TextColumn::make('ordered_at')->label('Date'),
            ]),
    ];
}
```

`'orders'` is the name of a **to-many relation method** on the resource's model (e.g. `Model::orders(): HasMany`). Forge calls `$record->orders()` to fetch the related rows.

## Methods

| Method | Purpose |
|--------|---------|
| `make(string $relation): static` | Factory. Pass the relation method name. |
| `label(string $label): static` | Heading shown above the inline table (defaults to `ucfirst($relation)`) |
| `columns(array $columns): static` | Same `Column` instances as a resource's `table()->columns()` |
| `limit(int $limit): static` | Cap the number of related rows shown (no limit by default) |

## Notes

- Columns in a relation manager support `formatUsing()` but not `sortable()` or `searchable()` — those apply to the parent resource's main table, not the inline related-record list.
- The relation manager only renders in the **Details** ("view") panel. It has no effect on the form or the main table.
- Multiple relation managers are supported — each renders as a separate labeled section.
