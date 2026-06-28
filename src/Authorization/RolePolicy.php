<?php

namespace Yuga\Forge\Authorization;

/**
 * A generic Policy backed by a single app-wide config instead of a bespoke
 * PHP class per resource. Resource falls back to this automatically (see
 * Resource::resolvePolicyClass()) once config('permissions.roles') is
 * defined - no per-resource wiring needed, and no risk of a one-off Policy
 * class quietly being left in place (or forgotten/removed) the way a
 * hand-written one would be.
 *
 * Expected shape of config/permissions.php:
 *
 *     return [
 *         'roles' => [
 *             'admin'  => ['*'],                  // '*' = every ability
 *             'viewer' => ['viewAny', 'view'],
 *         ],
 *         'resources' => [
 *             // Optional per-resource override, keyed by Resource FQCN -
 *             // replaces (not merges with) that role's global abilities
 *             // for this resource only.
 *             // \App\Live\Admin\OrdersResource::class => [
 *             //     'viewer' => ['viewAny', 'view', 'markPaid'],
 *             // ],
 *         ],
 *     ];
 *
 * The current user's role is read from $user->role - if your User model
 * uses a different column/relationship, override roleFor() in a subclass
 * and point Resource::$policy at it instead.
 */
class RolePolicy extends Policy
{
    public function __construct(protected ?string $resourceClass = null)
    {
    }

    public function viewAny(mixed $user): bool
    {
        return $this->allows($user, 'viewAny');
    }

    public function view(mixed $user, array $record): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(mixed $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(mixed $user, array $record): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(mixed $user, array $record): bool
    {
        return $this->allows($user, 'delete');
    }

    public function deleteAny(mixed $user): bool
    {
        return $this->allows($user, 'deleteAny');
    }

    public function allows(mixed $user, string $ability): bool
    {
        $role = $this->roleFor($user);

        if ($role === null) {
            return false;
        }

        $abilities = $this->abilitiesFor($role);

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    protected function roleFor(mixed $user): ?string
    {
        return is_object($user) ? ($user->role ?? null) : null;
    }

    /**
     * config('permissions', []) - the top-level key with no dotted sub-path
     * - does not return a plain array to index into; only a fully dotted
     * path (config('permissions.roles'), etc.) drills down to one. Fetch
     * each piece directly rather than fetching the whole file and indexing
     * into it.
     */
    protected function abilitiesFor(string $role): array
    {
        if ($this->resourceClass !== null) {
            $override = config("permissions.resources.{$this->resourceClass}.{$role}", null);

            if ($override !== null) {
                return $override;
            }
        }

        return (array) config("permissions.roles.{$role}", []);
    }
}
