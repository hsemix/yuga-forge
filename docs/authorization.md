---
layout: default
title: Authorization
nav_order: 11
---

# Authorization

Forge has its own lightweight authorization system — no framework Gate, no facades. Attach a policy class to a resource and Forge will check it before every operation.

## Attaching a policy

```php
class CustomersResource extends Resource
{
    protected string $policy = CustomerPolicy::class;
    // ...
}
```

If `$policy` is not set, **all abilities default to `true`** (allow everything). Authorization is opt-in.

## Writing a policy

Extend `Yuga\Forge\Authorization\Policy` and override only the abilities you need to restrict:

```php
use Yuga\Forge\Authorization\Policy;

class CustomerPolicy extends Policy
{
    public function create(mixed $user): bool
    {
        return $user->role === 'admin';
    }

    public function delete(mixed $user, array $record): bool
    {
        return $user->role === 'admin';
    }

    // viewAny, view, update, deleteAny all default to true via parent
}
```

### Ability signatures

| Ability | Signature |
|---------|-----------|
| `viewAny` | `viewAny(mixed $user): bool` |
| `view` | `view(mixed $user, array $record): bool` |
| `create` | `create(mixed $user): bool` |
| `update` | `update(mixed $user, array $record): bool` |
| `delete` | `delete(mixed $user, array $record): bool` |
| `deleteAny` | `deleteAny(mixed $user): bool` |

Custom ability names (for `Action::can('myAbility')`) work by convention — add them as methods on your Policy class with the same signature as the closest matching built-in.

## Role-based policy (no per-resource classes)

`RolePolicy` reads permissions from `config/permissions.php` instead of a bespoke class per resource. Point any resource at it:

```php
protected string $policy = \Yuga\Forge\Authorization\RolePolicy::class;
```

### `config/permissions.php`

```php
return [
    'roles' => [
        'admin'  => ['*'],                       // '*' = every ability
        'editor' => ['viewAny', 'view', 'create', 'update'],
        'viewer' => ['viewAny', 'view'],
    ],

    'resources' => [
        // Optional per-resource override (replaces, not merges, global abilities for that role)
        \App\Live\Admin\OrdersResource::class => [
            'editor' => ['viewAny', 'view', 'update'],
        ],
    ],
];
```

`RolePolicy` reads `$user->role` to determine the current user's role. Override `roleFor()` in a subclass if your user model uses a different attribute.

## Checking authorization in actions

Call `$this->can(string $ability, mixed $record = [])` inside any resource method:

```php
public function markPaid(string $key): void
{
    $order = $this->findRecord($key);

    if (!$order || !$this->can('update', $order)) {
        return;
    }

    // ...
}
```

`Action::can('update')` on a row action does the same check automatically — Forge won't render the button if it returns false.
