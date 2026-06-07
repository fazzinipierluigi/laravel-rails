<?php

namespace Fazzinipierluigi\LaravelRails\Classes\Resolvers;

use Fazzinipierluigi\LaravelRails\Contracts\PermissionResolverInterface;

/**
 * Resolver for fazzinipierluigi/just-a-gate.
 *
 * Uses JustAGate::userCan($user, $ability) when the facade supports it,
 * then falls back to $user->can($ability) (Authorizable trait), then to VanillaGateResolver.
 */
class JustAGateResolver implements PermissionResolverInterface
{
    private const FACADE = 'Fazzinipierluigi\\JustAGate\\Facades\\JustAGate';

    public function check(mixed $user, array $permissions, string $operator = 'OR'): bool
    {
        if (empty($permissions)) {
            return true;
        }

        try {
            $facade = self::FACADE;

            $checkOne = function (string $perm) use ($user, $facade): bool {
                if (method_exists($facade, 'userCan')) {
                    return (bool) $facade::userCan($user, $perm);
                }
                if (method_exists($user, 'can')) {
                    return (bool) $user->can($perm);
                }
                return false;
            };

            if (strtoupper($operator) === 'AND') {
                foreach ($permissions as $perm) {
                    if (!$checkOne($perm)) {
                        return false;
                    }
                }
                return true;
            }

            foreach ($permissions as $perm) {
                if ($checkOne($perm)) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            return (new VanillaGateResolver())->check($user, $permissions, $operator);
        }
    }

    public function getDriverName(): string
    {
        return 'just-a-gate';
    }
}
