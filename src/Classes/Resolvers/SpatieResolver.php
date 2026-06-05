<?php

namespace Fazzinipierluigi\LaravelRails\Classes\Resolvers;

use Fazzinipierluigi\LaravelRails\Contracts\PermissionResolverInterface;

class SpatieResolver implements PermissionResolverInterface
{
    public function check(mixed $user, array $permissions, string $operator = 'OR'): bool
    {
        if (empty($permissions)) {
            return true;
        }

        if (!$this->hasSpatieTraits($user)) {
            return (new VanillaGateResolver())->check($user, $permissions, $operator);
        }

        if (strtoupper($operator) === 'AND') {
            foreach ($permissions as $perm) {
                if (!$this->userHas($user, $perm)) {
                    return false;
                }
            }
            return true;
        }

        foreach ($permissions as $perm) {
            if ($this->userHas($user, $perm)) {
                return true;
            }
        }
        return false;
    }

    private function hasSpatieTraits(mixed $user): bool
    {
        return method_exists($user, 'hasPermissionTo') || method_exists($user, 'hasRole');
    }

    private function userHas(mixed $user, string $perm): bool
    {
        try {
            if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($perm)) {
                return true;
            }
            if (method_exists($user, 'hasRole') && $user->hasRole($perm)) {
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getDriverName(): string
    {
        return 'spatie/laravel-permission';
    }
}
