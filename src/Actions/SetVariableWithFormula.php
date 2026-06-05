<?php

namespace Fazzinipierluigi\LaravelRails\Actions;

use Fazzinipierluigi\LaravelRails\Classes\Expression\Parser;
use Fazzinipierluigi\LaravelRails\Interfaces\ActionInterface;

class SetVariableWithFormula implements ActionInterface
{
    public static string $display_name = 'Set variable with formula';

    /**
     * Configuration example:
     * {
     *   "variable": "total_price",
     *   "formula":  "quantity * unit_price * (1 + tax_rate)"
     * }
     *
     * Formula tokens:
     *  - Numeric literals:   42, 3.14
     *  - Operators:          + - * / ^ %
     *  - Variable names:     quantity (maps to instance.variables['quantity'])
     *                        entity_price (maps to entity['price'] if no var found)
     *  - String literals:    "hello"  (concatenate with +)
     *  - Parentheses:        (a + b) * c
     *
     * Note: variable names are case-insensitive (lowercased by the parser).
     * Dot-notation is NOT supported in formula variable names — use
     * SetVariableWithEntity first to flatten nested values if needed.
     */
    public static array $configuration_schema = [
        [
            'name'        => 'variable',
            'type'        => 'text',
            'label'       => 'Nome variabile di destinazione',
            'required'    => true,
            'placeholder' => 'total_price',
        ],
        [
            'name'        => 'formula',
            'type'        => 'text',
            'label'       => 'Formula (es. quantity * unit_price * (1 + tax_rate))',
            'required'    => true,
            'placeholder' => 'quantity * unit_price',
        ],
    ];

    public function execute($instance, $entity, ?array $configuration, $destination_state): bool
    {
        $varName = $configuration['variable'] ?? null;
        $formula = $configuration['formula']  ?? null;

        if (empty($varName) || empty($formula)) {
            return true;
        }

        $parser = new Parser();

        // Wire parser variable resolution to instance variables and entity fields
        $parser->onVariable = function (string $name, &$value) use ($instance, $entity): void {
            // Instance variables (lowercased keys since parser lowercases identifiers)
            $instanceVars = array_change_key_case($instance->variables ?? [], CASE_LOWER);
            if (array_key_exists($name, $instanceVars)) {
                $raw   = $instanceVars[$name];
                $value = is_numeric($raw) ? (float) $raw : $raw;
                return;
            }

            // Entity attributes as fallback
            if ($entity !== null) {
                $entityArr = array_change_key_case($entity->toArray(), CASE_LOWER);
                if (array_key_exists($name, $entityArr)) {
                    $raw   = $entityArr[$name];
                    $value = is_numeric($raw) ? (float) $raw : $raw;
                    return;
                }
            }

            // Default to 0 so formula doesn't throw on unknown vars
            $value = 0;
        };

        // Register common math functions
        $parser->functions['abs']   = ['arc' => 1, 'ref' => 'abs'];
        $parser->functions['round'] = ['arc' => 2, 'ref' => 'round'];
        $parser->functions['floor'] = ['arc' => 1, 'ref' => 'floor'];
        $parser->functions['ceil']  = ['arc' => 1, 'ref' => 'ceil'];
        $parser->functions['max']   = ['ref' => fn (...$a) => max($a)];
        $parser->functions['min']   = ['ref' => fn (...$a) => min($a)];
        $parser->functions['sqrt']  = ['arc' => 1, 'ref' => 'sqrt'];
        $parser->functions['pow']   = ['arc' => 2, 'ref' => 'pow'];

        $result = $parser->execute($formula);

        $vars = $instance->variables ?? [];
        data_set($vars, $varName, $result);
        $instance->variables = $vars;
        $instance->save();

        return true;
    }
}
