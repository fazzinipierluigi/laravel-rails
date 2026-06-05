<?php

namespace Fazzinipierluigi\LaravelRails\Classes\Resolvers;

use Illuminate\Support\Facades\Gate;
use Fazzinipierluigi\LaravelRails\Contracts\PermissionResolverInterface;

class VanillaGateResolver implements PermissionResolverInterface
{
    public function check(mixed $user, array $permissions, string $operator = 'OR'): bool
    {
        if (empty($permissions)) {
            return true;
        }

        $gate = Gate::forUser($user);

        if (strtoupper($operator) === 'AND') {
            foreach ($permissions as $ability) {
                if (!$gate->check($ability)) {
                    return false;
                }
            }
            return true;
        }

        foreach ($permissions as $ability) {
            if ($gate->check($ability)) {
                return true;
            }
        }
        return false;
    }

    public function getDriverName(): string
    {
        return 'laravel-gate';
    }
}
