<?php

namespace Fazzinipierluigi\LaravelRails\Actions;

use Fazzinipierluigi\LaravelRails\Interfaces\ActionInterface;

class SetVariableWithEntity implements ActionInterface
{
    public static string $display_name = "Set variables from entity data";

    /**
     * Configuration example:
     * {
     *   "mappings": [
     *     {"variable": "customer_name",  "entity_field": "name"},
     *     {"variable": "customer_email", "entity_field": "email"},
     *     {"variable": "address.city",   "entity_field": "address.city"}
     *   ]
     * }
     *
     * Supports dot-notation for both source (entity_field) and destination (variable).
     */
    public static array $configuration_schema = [
        [
            'name'        => 'mappings',
            'type'        => 'textarea',
            'label'       => 'Mapping JSON (array di {variable, entity_field})',
            'required'    => true,
            'placeholder' => '[{"variable":"name","entity_field":"name"},{"variable":"email","entity_field":"email"}]',
        ],
    ];

    public function execute($instance, $entity, ?array $configuration, $destination_state): bool
    {
        $mappings = $configuration['mappings'] ?? [];

        // Support mappings stored as JSON string inside the array
        if (is_string($mappings)) {
            $mappings = json_decode($mappings, true) ?? [];
        }

        if (empty($mappings) || !is_array($mappings)) {
            return true;
        }

        $vars = $instance->variables ?? [];

        foreach ($mappings as $mapping) {
            $mapping = (array) $mapping;

            $varName     = $mapping['variable']     ?? null;
            $entityField = $mapping['entity_field'] ?? null;

            if (empty($varName) || empty($entityField)) {
                continue;
            }

            $value = $entity ? data_get($entity->toArray(), $entityField) : null;
            data_set($vars, $varName, $value);
        }

        $instance->variables = $vars;
        $instance->save();

        return true;
    }
}
