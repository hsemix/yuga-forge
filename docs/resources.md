---
layout: default
title: Resources
nav_order: 3
---

# Resources

A **Resource** is the central class in Forge. It combines a model, a form schema, and a table schema into a fully interactive admin page, rendered as a Yuga Live Component.

## Class anatomy

```php
#[Live(name: 'admin.customers-resource')]
class CustomersResource extends Resource
{
    // --- Required ---
    protected string $model = Customer::class;

    // --- Common options ---
    protected string $recordKey = 'public_id';   // column used as the PK in the UI
    protected string $keyPrefix = 'CUS';          // display prefix (e.g. "CUS-abc123")
    protected bool $softDeletes = true;           // enables Trash tab
    protected array $with = ['team'];             // eager-load relations

    public static function form(Form $form): Form { ... }
    public static function table(Table $table): Table { ... }
}
```

## Overridable hooks

### Sorting

```php
protected function defaultSort(): ?string  { return 'created_at'; }
protected function defaultDirection(): string { return 'desc'; }
```

### Panel display

Controls whether the create/edit form and details view open as a **modal** (default for short forms) or a **slide-over** (better for tall forms):

```php
protected function panelDisplay(): string
{
    return 'modal';       // 'modal' | 'full-height'
}
```

### Form and view titles

Override the heading shown inside the open panel:

```php
protected function formTitle(): string
{
    // Called with $this->editingKey set (edit) or null (create).
    return $this->editingKey ? 'Edit customer' : 'New customer';
}

protected function viewTitle(): string
{
    return 'Customer details';
}
```

### After-save hook

Called after every successful save, with `$created = true` on insert, `false` on update:

```php
protected function afterSave(bool $created, string $key): void
{
    if ($created) {
        $this->notify('New customer', "Welcome, {$this->data['name']}!");
    }
}
```

### Actions

Return an array of [`Action`](actions) objects. Forge renders them automatically in the right context.

```php
/** Row-level actions (one call per visible row). */
protected function rowActions(array $record): array
{
    return [
        Action::make('view')->can('view')->action('openView'),
        Action::make('edit')->can('update')->action('openEdit'),
    ];
}

/** Shown in the bulk toolbar when rows are selected. */
protected function bulkActions(): array
{
    return array_merge([
        Action::make('archive')->action('bulkArchive'),
    ], parent::bulkActions());    // parent adds bulk-delete
}

/** Shown in the page header (no record context). */
protected function headerActions(): array
{
    return [
        Action::make('export')->color('primary')->action('exportAll'),
    ];
}
```

### Relation managers

Inline related-record tables shown in the details view:

```php
protected function relationManagers(): array
{
    return [
        RelationManager::make('orders')
            ->label('Orders')
            ->columns([
                TextColumn::make('public_id')->label('Order'),
                BadgeColumn::make('status')->colors(['paid' => 'emerald']),
            ]),
    ];
}
```

See [Relation managers](relation-managers) for full reference.

### Authorization

Attach a policy class to gate every operation:

```php
protected string $policy = CustomerPolicy::class;
```

See [Authorization](authorization).

## Utility methods available in the component

These are `public` or `protected` methods you can call from action handlers inside your resource subclass:

| Method | Purpose |
|--------|---------|
| `findRecord(string $key)` | Fetch one record by its `$recordKey` value |
| `can(string $ability, mixed $record = [])` | Check authorization via the attached policy |
| `notify(string $title, string $body)` | Push a notification to the bell icon |
| `toast(string $message)` | Show a transient toast |
| `now()` | Current UTC timestamp string for `updated_at` columns |

## Extra view content

To inject arbitrary HTML below the read-only field list in the details slide-over (e.g. a call-to-action button), override:

```php
protected function renderViewExtra(array $record): string
{
    if ($record['status'] === 'paid') {
        return '';
    }

    $key = htmlspecialchars($record[$this->recordKey], ENT_QUOTES, 'UTF-8');
    return '<button ylc:click="markPaid(\'' . $key . '\')">Mark as paid</button>';
}
```
