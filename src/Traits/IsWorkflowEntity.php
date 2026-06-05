<?php

namespace Fazzinipierluigi\LaravelRails\Traits;

use Fazzinipierluigi\LaravelRails\Classes\TriggerService;
use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Models\Workflow;
use Fazzinipierluigi\LaravelRails\Models\WorkflowTrigger;

trait IsWorkflowEntity
{
    public function workflowInstances()
    {
        return $this->morphMany(Instance::class, 'instanceable');
    }

    public function startWorkflow(string $workflowSlug): Instance
    {
        $workflow = Workflow::getBySlug($workflowSlug);

        if (empty($workflow)) {
            throw new \Exception("Workflow '{$workflowSlug}' not found");
        }

        $userId      = auth()->id();
        $triggeredBy = $userId ? 'user:' . $userId : 'system';

        return $workflow->instantiate($this, $triggeredBy);
    }

    public function getWorkflowInstance(string $workflowSlug): ?Instance
    {
        $workflow = Workflow::getBySlug($workflowSlug);

        if (empty($workflow)) {
            return null;
        }

        return $this->workflowInstances()->where('workflow_id', $workflow->id)->first();
    }

    public function progressWorkflow(string $workflowSlug): string
    {
        $instance = $this->getWorkflowInstance($workflowSlug);

        if (empty($instance)) {
            throw new \Exception("No active instance found for workflow '{$workflowSlug}'");
        }

        return $instance->progress();
    }

    /**
     * Fire a manual trigger by its name or UUID for this entity.
     * Throws if trigger not found, not manual, not active, or permission denied.
     */
    public function fireManualTrigger(string $triggerNameOrId): Instance
    {
        $trigger = WorkflowTrigger::active()
            ->ofType(WorkflowTrigger::TYPE_MANUAL)
            ->where(function ($q) use ($triggerNameOrId) {
                $q->where('id', $triggerNameOrId)->orWhere('name', $triggerNameOrId);
            })
            ->first();

        if (empty($trigger)) {
            throw new \Exception("Manual trigger '{$triggerNameOrId}' not found");
        }

        return TriggerService::fireManual($trigger, $this);
    }
}
