<?php

namespace Fazzinipierluigi\LaravelRails\Classes;

use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Models\WorkflowTrigger;

class TriggerService
{
    /**
     * Run all active scheduled triggers whose cron expression is currently due.
     * Returns the total number of workflow instances created.
     */
    public static function fireScheduled(?\DateTimeInterface $now = null): int
    {
        $count = 0;

        WorkflowTrigger::active()
            ->ofType(WorkflowTrigger::TYPE_SCHEDULED)
            ->with('workflow')
            ->get()
            ->each(function (WorkflowTrigger $trigger) use ($now, &$count) {
                if (!$trigger->isDue($now)) {
                    return;
                }

                // Idempotence: skip if already fired in this minute
                if ($trigger->last_run_at && $trigger->last_run_at->format('Y-m-d H:i') === now()->format('Y-m-d H:i')) {
                    return;
                }

                $cfg         = $trigger->configuration ?? [];
                $entityClass = $cfg['entity_class'] ?? null;

                if (empty($entityClass) || !class_exists($entityClass)) {
                    return;
                }

                $workflow = $trigger->workflow;
                if (empty($workflow)) {
                    return;
                }

                // Build entity query — exclude entities that already have an instance for this workflow
                $existingIds = Instance::where('workflow_id', $workflow->id)
                                       ->where('instanceable_type', $entityClass)
                                       ->pluck('instanceable_id')
                                       ->toArray();

                $query = $entityClass::query()->whereNotIn('id', $existingIds);

                // Apply optional named scope
                if (!empty($cfg['entity_scope'])) {
                    $query = $query->{$cfg['entity_scope']}();
                }

                // Apply optional jsonLogic entity-level conditions
                $conditions = $cfg['entity_condition'] ?? null;

                $triggerId = $trigger->id;
                $query->each(function ($entity) use ($workflow, $conditions, $triggerId, &$count) {
                    if ($conditions) {
                        try {
                            $data = ['entity' => $entity->toArray()];
                            if (!\JWadhams\JsonLogic::apply($conditions, $data)) {
                                return;
                            }
                        } catch (\Throwable $e) {
                            return;
                        }
                    }

                    try {
                        $workflow->instantiate($entity, 'trigger:' . $triggerId);
                        $count++;
                    } catch (\Throwable $e) {
                        // Instance already exists or other error — skip
                    }
                });

                $trigger->update(['last_run_at' => now()]);
            });

        return $count;
    }

    /**
     * Handle a model lifecycle event (created / updated).
     * Finds matching entity_event triggers, checks conditions, instantiates workflows.
     */
    public static function handleEntityEvent(string $event, $entity): void
    {
        $entityClass = get_class($entity);

        WorkflowTrigger::active()
            ->ofType(WorkflowTrigger::TYPE_ENTITY_EVENT)
            ->with('workflow')
            ->get()
            ->each(function (WorkflowTrigger $trigger) use ($entityClass, $event, $entity) {
                $cfg = $trigger->configuration ?? [];

                if (($cfg['entity_class'] ?? '') !== $entityClass) {
                    return;
                }

                $triggerEvent = $cfg['event'] ?? 'created';
                if ($triggerEvent !== 'created_or_updated' && $triggerEvent !== $event) {
                    return;
                }

                if (!$trigger->matchesConditions($entity)) {
                    return;
                }

                $workflow = $trigger->workflow;
                if (empty($workflow)) {
                    return;
                }

                try {
                    $workflow->instantiate($entity, 'trigger:' . $trigger->id);
                } catch (\Throwable $e) {
                    // Instance already exists — skip silently
                }
            });
    }

    /**
     * Fire a manual trigger for the given entity.
     * Throws if the trigger is not active, not manual, or permission denied.
     */
    public static function fireManual(WorkflowTrigger $trigger, $entity): Instance
    {
        if (!$trigger->isManual()) {
            throw new \Exception('Trigger "' . $trigger->name . '" is not a manual trigger');
        }

        if (!$trigger->is_active) {
            throw new \Exception('Trigger "' . $trigger->name . '" is not active');
        }

        $permission = $trigger->getPermission();
        if ($permission && auth()->check() && !auth()->user()->can($permission)) {
            throw new \Exception('Permission denied for trigger "' . $trigger->name . '"');
        }

        return $trigger->workflow->instantiate($entity, 'trigger:' . $trigger->id);
    }
}
