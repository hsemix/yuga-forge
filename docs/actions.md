---
layout: default
title: Actions
nav_order: 8
---

# Actions

`Action` is a fluent builder for buttons that appear in three places:

| Context | Hook | Record key available? |
|---------|------|-----------------------|
| Table row | `rowActions(array $record): array` | Yes |
| Bulk toolbar | `bulkActions(): array` | No (operates on `$this->selected`) |
| Page header | `headerActions(): array` | No |

```php
use Yuga\Forge\Actions\Action;
```

## Quick example

```php
protected function rowActions(array $record): array
{
    $actions = [
        Action::make('view')
            ->can('view')
            ->action('openView'),
    ];

    if ($record['status'] !== 'paid') {
        $actions[] = Action::make('markPaid')
            ->label('Mark paid')
            ->color('success')
            ->can('update')
            ->confirm('Mark this order as paid?')
            ->action('markPaid');
    } else {
        $actions[] = Action::make('settled')
            ->label('Settled')
            ->plain();
    }

    return $actions;
}
```

## Common API

### `Action::make(string $name): static`

Factory. `$name` is used as the default label (auto-formatted: `ucfirst(str_replace('_', ' ', $name))`).

### `label(string $label): static`

Override the displayed text.

### `color(string $color): static`

Available colours: `'default'`, `'primary'`, `'danger'`, `'success'`, `'warning'`.

Row actions use full-height buttons (`h-10`); bulk/header actions use compact buttons with smaller padding.

### `icon(string $icon): static`

A plain string rendered before the label — use an emoji or Unicode symbol (there is no icon library):

```php
Action::make('download')->icon('⬇')->label('Download')
```

### `action(string $method, array $params = []): static`

The resource method to call via `ylc:click`. The record key is automatically prepended as the first argument for row actions.

```php
// In your resource:
public function markPaid(string $key): void { ... }

// In rowActions():
Action::make('markPaid')->action('markPaid')
// → ylc:click="markPaid('ORD-abc123')"
```

Pass extra params after the key:

```php
Action::make('flag')->action('flagRecord', ['reason' => 'spam'])
```

### `can(string $ability): static`

The ability name Forge will check via `$this->can($ability, $record)` before rendering. If the check fails, the action is hidden.

### `visible(\Closure $callback): static`

Show this action only when the callback returns true. Receives the `$record` array:

```php
Action::make('markPaid')
    ->visible(fn($record) => $record['status'] !== 'paid')
```

### `hidden(\Closure $callback): static`

Inverse of `visible()`.

### `confirm(string|\Closure $message): static`

Show a confirmation dialog before the action fires. For bulk actions, pass a closure that receives the selection count:

```php
Action::make('delete')
    ->confirm(fn(int $count) => "Delete {$count} record(s)?")
```

### `plain(bool $plain = true): static`

Renders a non-interactive `<span>` instead of a `<button>` — for states where an action isn't available but you still want to show a label in the column (e.g. "Settled" where "Mark paid" would otherwise appear).

### `url(string|\Closure $url): static`

Renders as `<a href>` instead of `<button ylc:click>`. The closure receives `(?string $recordKey, array $record)`:

```php
Action::make('download')
    ->url(fn($key, $record) => route('reports.download', $key))
```

### `withoutRecordKey(): static`

Prevents the record key from being prepended to the `ylc:click` arguments. Use this for bulk and header actions that have no single record context.

## Full method reference

| Method | Type |
|--------|------|
| `make(string $name)` | Factory |
| `label(string $label)` | Display |
| `color(string $color)` | Display |
| `icon(string $icon)` | Display |
| `plain(bool $plain = true)` | Display |
| `action(string $method, array $params = [])` | Behaviour |
| `url(string\|\Closure $url)` | Behaviour |
| `can(string $ability)` | Auth |
| `visible(\Closure $callback)` | Visibility |
| `hidden(\Closure $callback)` | Visibility |
| `confirm(string\|\Closure $message)` | UX |
| `withoutRecordKey()` | Behaviour |
