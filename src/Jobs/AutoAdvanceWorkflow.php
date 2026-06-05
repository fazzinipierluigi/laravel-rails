<?php

namespace Fazzinipierluigi\LaravelRails\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Fazzinipierluigi\LaravelRails\Models\Instance;

class AutoAdvanceWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 5;

    public function __construct(private readonly string $instanceId) {}

    public function handle(): void
    {
        $instance = Instance::find($this->instanceId);
        if (!$instance) {
            return;
        }

        $instance->refresh();
        $state = $instance->state;

        // Re-check: conditions may have changed since the job was dispatched
        if (!$state || $state->is_end || $state->isConditional()) {
            return;
        }
        if ($state->transitions()->count() === 0) {
            return;
        }
        if ($state->transitions()->get()->contains(fn($t) => $t->hasForm())) {
            return;
        }

        try {
            $instance->progress('auto');
        } catch (\Throwable $e) {
            $instance->logger('auto')->executionError($e, [
                'context'  => 'auto-advance job',
                'state_id' => $instance->state_id,
            ]);
            throw $e;
        }
    }
}
