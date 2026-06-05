<?php

namespace Fazzinipierluigi\LaravelRails\Actions;

use Fazzinipierluigi\LaravelRails\Classes\VariableResolver;
use Fazzinipierluigi\LaravelRails\Interfaces\ActionInterface;

class WriteEntity implements ActionInterface
{
    public static string $display_name = 'Write data to entity';

    /**
     * Configuration example:
     * {
     *   "mappings": [
     *     {"field": "status",      "value": "{{variables.new_status}}"},
     *     {"field": "approved_at", "value": "{{now}}"},
     *     {"field": "approved_by", "value": "{{variables.user_id}}"},
     *     {"field": "notes",       "value": "Approvato automaticamente"}
     *   ]
     * }
     *
     * Values support {{placeholder}} syntax.
     * Special tokens: {{now}}, {{today}}, {{variables.X}}, {{entity.X}}, {{instance.id}}
     *
     * After all fields are set, save() is called once on the entity.
     */
    public static array $configuration_schema = [
        [
            'name'        => 'mappings',
            'type'        => 'textarea',
            'label'       => 'Mapping JSON (array di {field, value})',
            'required'    => true,
            'placeholder' => '[{"field":"status","value":"{{variables.new_status}}"},{"field":"updated_at","value":"{{now}}"}]',
        ],
    ];

    public function execute($instance, $entity, ?array $configuration, $destination_state): bool
    {
        $mappings = $configuration['mappings'] ?? [];

        if (is_string($mappings)) {
            $mappings = json_decode($mappings, true) ?? [];
        }

        if (empty($mappings) || empty($entity)) {
            return true;
        }

        foreach ($mappings as $mapping) {
            $mapping = (array) $mapping;

            $field = $mapping['field'] ?? null;
            $value = (string) ($mapping['value'] ?? '');

            if (empty($field)) {
                continue;
            }

            $resolved      = VariableResolver::resolve($value, $instance, $entity);
            $entity->$field = $resolved;
        }

        $entity->save();

        return true;
    }
}
