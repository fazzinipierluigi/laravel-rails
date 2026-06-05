<?php

namespace Fazzinipierluigi\LaravelRails\Observers;

use Fazzinipierluigi\LaravelRails\Classes\TriggerService;

class WorkflowEntityObserver
{
    public function created($model): void
    {
        TriggerService::handleEntityEvent('created', $model);
    }

    public function updated($model): void
    {
        TriggerService::handleEntityEvent('updated', $model);
    }
}
