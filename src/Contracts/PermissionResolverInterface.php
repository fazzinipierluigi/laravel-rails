<?php

namespace Fazzinipierluigi\LaravelRails\Contracts;

interface PermissionResolverInterface
{
    /**
     * Check if $user has the required permissions.
     *
     * @param  mixed   $user        Authenticated user model (or null for system/auto)
     * @param  array   $permissions List of permission, role, or ability names
     * @param  string  $operator    'OR' = any one sufficient; 'AND' = all required
     */
    public function check(mixed $user, array $permissions, string $operator = 'OR'): bool;

    /** Human-readable driver name for logging (e.g. 'just-a-gate', 'spatie', 'laravel-gate') */
    public function getDriverName(): string;
}
