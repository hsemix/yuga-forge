<?php

namespace Yuga\Forge\Authorization;

/**
 * Base class for a Forge resource's authorization rules. Yuga has no
 * built-in policy/gate system to hook into, so Resource resolves and calls
 * these directly (see Resource::can()) instead of going through any
 * framework facade.
 *
 * Every ability defaults to "allow" so a resource with no declared policy
 * keeps working exactly as it did before this existed - authorization is
 * opt-in per resource, not a global gate every existing resource suddenly
 * has to satisfy.
 */
abstract class Policy
{
    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, array $record): bool
    {
        return true;
    }

    public function create(mixed $user): bool
    {
        return true;
    }

    public function update(mixed $user, array $record): bool
    {
        return true;
    }

    public function delete(mixed $user, array $record): bool
    {
        return true;
    }

    public function deleteAny(mixed $user): bool
    {
        return true;
    }
}
