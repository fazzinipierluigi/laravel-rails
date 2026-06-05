<?php

namespace Fazzinipierluigi\LaravelRails\Actions;

use Fazzinipierluigi\LaravelRails\Classes\VariableResolver;
use Fazzinipierluigi\LaravelRails\Interfaces\ActionInterface;
use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Models\Workflow;

class StartSubprocess implements ActionInterface
{
    public static string $display_name = 'Start a sub-workflow';

    /**
     * Configuration example:
     * {
     *   "workflow_slug":          "approval-process",
     *   "store_instance_id_as":   "approval_instance_id",   // optional
     *   "copy_variables":         ["order_id", "amount"],    // optional
     *   "initial_variables":      {"source": "parent"}       // optional
     * }
     *
     * The sub-workflow is instantiated on the SAME entity as the parent.
     * If an instance already exists for that workflow+entity, this action
     * succeeds silently (idempotent).
     */
    public static array $configuration_schema = [
        [
            'name'        => 'workflow_slug',
            'type'        => 'text',
            'label'       => 'Slug del workflow da avviare',
            'required'    => true,
            'placeholder' => 'approval-process',
        ],
        [
            'name'        => 'store_instance_id_as',
            'type'        => 'text',
            'label'       => 'Salva ID istanza figlio nella variabile (opzionale)',
            'required'    => false,
            'placeholder' => 'approval_instance_id',
        ],
        [
            'name'        => 'copy_variables',
            'type'        => 'text',
            'label'       => 'Variabili da copiare nel figlio (JSON array, opzionale)',
            'required'    => false,
            'placeholder' => '["order_id","amount"]',
        ],
        [
            'name'        => 'initial_variables',
            'type'        => 'textarea',
            'label'       => 'Variabili iniziali per il figlio (JSON object, opzionale)',
            'required'    => false,
            'placeholder' => '{"source":"parent"}',
        ],
    ];

    public function execute($instance, $entity, ?array $configuration, $destination_state): bool
    {
        $configuration = $configuration ?? [];

        $workflowSlug = VariableResolver::resolve(
            $configuration['workflow_slug'] ?? '',
            $instance,
            $entity
        );

        if (empty(trim($workflowSlug))) {
            throw new \Exception('StartSubprocess: workflow_slug is required');
        }

        $workflow = Workflow::getBySlug($workflowSlug);
        if (empty($workflow)) {
            throw new \Exception("StartSubprocess: workflow '{$workflowSlug}' not found");
        }

        // Build initial variables for the child instance
        $childVars = [];

        // Copy specified variables from parent
        $copyKeys = $configuration['copy_variables'] ?? [];
        if (is_string($copyKeys)) {
            $copyKeys = json_decode($copyKeys, true) ?? [];
        }
        foreach ((array) $copyKeys as $key) {
            $val = $instance->getVariable($key);
            if ($val !== null) {
                $childVars[$key] = $val;
            }
        }

        // Merge explicit initial variables
        $initialVars = $configuration['initial_variables'] ?? [];
        if (is_string($initialVars)) {
            $initialVars = json_decode($initialVars, true) ?? [];
        }
        foreach ((array) $initialVars as $k => $v) {
            $childVars[$k] = VariableResolver::resolve((string) $v, $instance, $entity);
        }

        // Instantiate (idempotent: skip if already running)
        try {
            $subInstance = $workflow->instantiate($entity);
        } catch (\Exception $e) {
            $subInstance = Instance::where('instanceable_type', get_class($entity))
                                   ->where('instanceable_id', (string) $entity->getKey())
                                   ->where('workflow_id', $workflow->id)
                                   ->first();
            if (empty($subInstance)) {
                throw $e;
            }
        }

        // Apply initial variables to child
        if (!empty($childVars)) {
            $subInstance->mergeVariables($childVars);
        }

        // Store child instance ID in parent variables if requested
        $storeAs = $configuration['store_instance_id_as'] ?? null;
        if (!empty($storeAs)) {
            $instance->setVariable($storeAs, $subInstance->id);
        }

        return true;
    }
}
