<?php

namespace Fazzinipierluigi\LaravelRails\Classes\Resolvers;

use Fazzinipierluigi\LaravelRails\Contracts\PermissionResolverInterface;

/**
 * Resolver for fazzinipierluigi/just-a-gate.
 *
 * Expects the package to expose a facade at Fazzinipierluigi\JustAGate\Facades\JustAGate
 * with a fluent forUser($user)->can($ability) API.
 * Falls back to VanillaGateResolver if the API is incompatible.
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
            $gate   = method_exists($facade, 'forUser') ? $facade::forUser($user) : null;

            if (strtoupper($operator) === 'AND') {
                foreach ($permissions as $perm) {
                    $result = $gate
                        ? $gate->can($perm)
                        : $facade::check($user, $perm);
                    if (!$result) {
                        return false;
                    }
                }
                return true;
            }

            foreach ($permissions as $perm) {
                $result = $gate
                    ? $gate->can($perm)
                    : $facade::check($user, $perm);
                if ($result) {
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
