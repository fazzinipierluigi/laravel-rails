<?php

namespace Fazzinipierluigi\LaravelRails\Classes;

use Fazzinipierluigi\LaravelRails\Models\Instance;

/**
 * Resolves {{placeholder}} templates using instance variables, entity fields, and special tokens.
 *
 * Supported tokens:
 *   {{now}}                  → current datetime (Y-m-d H:i:s)
 *   {{today}}                → current date (Y-m-d)
 *   {{instance.id}}          → instance UUID
 *   {{instance.state_id}}    → current state UUID
 *   {{instance.workflow_id}} → workflow UUID
 *   {{variables.key}}        → instance variable (dot-notation supported)
 *   {{entity.field}}         → entity attribute (dot-notation supported)
 *   {{key}}                  → shorthand: checks variables first, then entity
 */
class VariableResolver
{
    public static function resolve(string $template, Instance $instance, $entity = null): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([^}]+?)\s*\}\}/',
            fn ($m) => (string) self::resolveKey(trim($m[1]), $instance, $entity),
            $template
        );
    }

    public static function resolveKey(string $key, Instance $instance, $entity = null): mixed
    {
        // Special tokens
        switch ($key) {
            case 'now':
                return now()->toDateTimeString();
            case 'today':
                return now()->toDateString();
            case 'instance.id':
                return $instance->id;
            case 'instance.workflow_id':
                return $instance->workflow_id;
            case 'instance.state_id':
                return $instance->state_id;
        }

        // Explicit namespace: variables.*
        if (str_starts_with($key, 'variables.')) {
            return data_get($instance->variables ?? [], substr($key, 10), '');
        }

        // Explicit namespace: entity.*
        if (str_starts_with($key, 'entity.') && $entity !== null) {
            return data_get($entity->toArray(), substr($key, 7), '');
        }

        // Shorthand: check variables first, then entity attributes
        $fromVars = data_get($instance->variables ?? [], $key, null);
        if ($fromVars !== null) {
            return $fromVars;
        }

        if ($entity !== null) {
            $fromEntity = data_get($entity->toArray(), $key, null);
            if ($fromEntity !== null) {
                return $fromEntity;
            }
        }

        return '';
    }

    /**
     * Build a flat data array suitable for passing to JsonLogic evaluations.
     * Keys: 'variables', 'entity', 'request', 'instance'
     */
    public static function buildContext(Instance $instance, $entity = null): array
    {
        return [
            'variables' => $instance->variables ?? [],
            'entity'    => $entity ? $entity->toArray() : [],
            'request'   => request()->all(),
            'instance'  => [
                'id'          => $instance->id,
                'workflow_id' => $instance->workflow_id,
                'state_id'    => $instance->state_id,
            ],
        ];
    }
}
