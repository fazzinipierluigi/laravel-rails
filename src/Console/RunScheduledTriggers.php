<?php

namespace Fazzinipierluigi\LaravelRails\Console;

use Illuminate\Console\Command;
use Fazzinipierluigi\LaravelRails\Classes\TriggerService;

class RunScheduledTriggers extends Command
{
    protected $signature = 'rail:trigger:scheduled
                            {--dry-run : Check which triggers are due without firing them}';

    protected $description = 'Fire all active scheduled workflow triggers whose cron expression is currently due';

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            return $this->dryRun();
        }

        $this->info('Running scheduled workflow triggers...');

        $count = TriggerService::fireScheduled();

        $this->info("Done. {$count} workflow instance(s) created.");

        return Command::SUCCESS;
    }

    private function dryRun(): int
    {
        $this->line('<fg=yellow>DRY RUN — no workflows will be started</>');

        $triggers = \Fazzinipierluigi\LaravelRails\Models\WorkflowTrigger::active()
            ->ofType(\Fazzinipierluigi\LaravelRails\Models\WorkflowTrigger::TYPE_SCHEDULED)
            ->with('workflow')
            ->get();

        if ($triggers->isEmpty()) {
            $this->line('No active scheduled triggers found.');
            return Command::SUCCESS;
        }

        $headers = ['ID', 'Name', 'Workflow', 'Cron', 'Due now?', 'Last run'];
        $rows    = $triggers->map(function ($t) {
            return [
                $t->id,
                $t->name,
                $t->workflow?->slug ?? '—',
                $t->configuration['cron'] ?? '—',
                $t->isDue() ? '<fg=green>YES</>' : 'no',
                $t->last_run_at?->format('Y-m-d H:i:s') ?? 'never',
            ];
        })->toArray();

        $this->table($headers, $rows);

        return Command::SUCCESS;
    }
}
