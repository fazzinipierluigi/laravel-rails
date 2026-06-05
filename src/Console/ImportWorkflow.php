<?php

namespace Fazzinipierluigi\LaravelRails\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Fazzinipierluigi\LaravelRails\Interfaces\ActionInterface;
use Fazzinipierluigi\LaravelRails\Models\Action;
use Fazzinipierluigi\LaravelRails\Models\RegisteredAction;
use Fazzinipierluigi\LaravelRails\Models\State;
use Fazzinipierluigi\LaravelRails\Models\Transition;
use Fazzinipierluigi\LaravelRails\Models\Workflow;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\error;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\progress;

class ImportWorkflow extends Command
{
    protected $signature = 'automata:workflow:update
                            {source? : Filename without .json (e.g. "my-flow" for "my-flow.json")}
                            {--a|all : Import all JSON files in storage/workflows/}
                            {--dry-run : Validate structure and show a summary without writing to DB}
                            {--force : Skip unregistered-action warnings and import anyway}';

    protected $description = 'Create or update workflows from JSON files in storage/workflows/';

    private array $sources         = [];
    private array $workflowData    = [];
    private $storage;

    public function handle(): int
    {
        $this->storage = Storage::build([
            'driver' => 'local',
            'root'   => storage_path('workflows'),
        ]);

        // ── Collect source files ──────────────────────────────────────
        info('▶ Resolving source files…');
        $result = $this->resolveSources();
        if ($result !== Command::SUCCESS) {
            return $result;
        }

        // ── Parse + validate ──────────────────────────────────────────
        $failures = 0;
        progress(
            label: '▶ Validating sources',
            steps: $this->sources,
            callback: function (string $source) use (&$failures) {
                if (!$this->parseSource($source)) {
                    $failures++;
                }
            },
        );

        if ($failures > 0) {
            error("{$failures} source(s) failed validation. Aborting.");
            return Command::FAILURE;
        }

        // ── Dry-run summary ───────────────────────────────────────────
        if ($this->option('dry-run')) {
            $this->printDryRunSummary();
            return Command::SUCCESS;
        }

        // ── Write workflows ───────────────────────────────────────────
        info('▶ Writing workflows…');
        $written = 0;
        foreach ($this->workflowData as $data) {
            try {
                DB::transaction(function () use ($data) {
                    $this->writeWorkflow($data);
                });
                $written++;
            } catch (\Throwable $e) {
                error('Failed to import "' . ($data->slug ?? $data->name) . '": ' . $e->getMessage());
            }
        }

        info("✓ {$written}/" . count($this->workflowData) . ' workflow(s) imported.');

        return Command::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────
    // Source resolution
    // ─────────────────────────────────────────────────────────────────

    private function resolveSources(): int
    {
        if ($this->option('all')) {
            foreach ($this->storage->files('/') as $file) {
                $info = pathinfo($file);
                if (!empty($info['filename']) && strtolower($info['extension'] ?? '') === 'json') {
                    $this->sources[] = $info['filename'];
                }
            }

            if (empty($this->sources)) {
                error('No JSON files found in storage/workflows/.');
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        }

        $source = $this->argument('source');

        if (!empty($source)) {
            if (!$this->storage->exists($source . '.json')) {
                error('File "' . $source . '.json" not found in storage/workflows/.');
                return Command::INVALID;
            }
            $this->sources[] = $source;
            return Command::SUCCESS;
        }

        // Interactive selection
        $files = collect($this->storage->files('/'))->filter(
            fn ($f) => strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'json'
        )->values()->all();

        if (empty($files)) {
            error('No JSON files found in storage/workflows/.');
            return Command::FAILURE;
        }

        $chosen = multiselect(
            label   : 'Select source file(s)',
            options : $files,
            required: true,
        );

        foreach ($chosen as $file) {
            $info = pathinfo($file);
            if (!empty($info['filename'])) {
                $this->sources[] = $info['filename'];
            }
        }

        return Command::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────
    // Parse + validate
    // ─────────────────────────────────────────────────────────────────

    private function parseSource(string $source): bool
    {
        $raw = $this->storage->get($source . '.json');
        if (empty($raw)) {
            error('[' . $source . '] File is empty.');
            return false;
        }

        $data = json_decode($raw);
        if (!is_object($data)) {
            error('[' . $source . '] Invalid JSON.');
            return false;
        }

        // Required top-level fields
        if (empty(trim($data->name ?? ''))) {
            error('[' . $source . '] Missing "name" field.');
            return false;
        }
        if (empty($data->states) || !is_array($data->states)) {
            error('[' . $source . '] No states defined.');
            return false;
        }

        // Validate states
        $hasStart  = false;
        $hasEnd    = false;
        $usedCodes = [];
        $usedNames = [];

        foreach ($data->states as $i => $state) {
            $name = trim($state->name ?? '');
            $code = trim($state->code ?? '');
            $type = $state->type ?? 'simple';

            if (empty($name)) {
                error('[' . $source . '] State #' . $i . ' has no name.');
                return false;
            }

            if (in_array($name, $usedNames, true)) {
                error('[' . $source . '] Duplicate state name "' . $name . '".');
                return false;
            }
            $usedNames[] = $name;

            // Code is optional; validate uniqueness only if provided
            if ($code !== '') {
                if (in_array($code, $usedCodes, true)) {
                    error('[' . $source . '] Duplicate state code "' . $code . '".');
                    return false;
                }
                $usedCodes[] = $code;
            }

            if (!empty($state->is_start)) {
                if ($hasStart) {
                    error('[' . $source . '] More than one start state.');
                    return false;
                }
                $hasStart = true;
            }

            if (!empty($state->is_end)) {
                $hasEnd = true;
            }

            // Validate transition destinations exist in the same file
            foreach ($state->transitions ?? [] as $j => $t) {
                if (empty($t->to)) {
                    error('[' . $source . '] Transition #' . $j . ' of state "' . $name . '" has no "to" field.');
                    return false;
                }
            }
        }

        if (!$hasStart) {
            error('[' . $source . '] No start state defined.');
            return false;
        }
        if (!$hasEnd) {
            error('[' . $source . '] No end state defined.');
            return false;
        }

        $this->workflowData[] = $data;
        return true;
    }

    // ─────────────────────────────────────────────────────────────────
    // Dry-run
    // ─────────────────────────────────────────────────────────────────

    private function printDryRunSummary(): void
    {
        $this->line('');
        $this->line('<fg=yellow>DRY RUN — no data will be written.</>');
        $this->line('');

        foreach ($this->workflowData as $data) {
            $slug          = !empty($data->slug) ? $data->slug : Str::slug($data->name);
            $exists        = Workflow::where('slug', $slug)->exists();
            $stateCount    = count($data->states ?? []);
            $transCount    = array_sum(array_map(fn ($s) => count($s->transitions ?? []), $data->states ?? []));
            $conditionals  = count(array_filter($data->states ?? [], fn ($s) => ($s->type ?? 'simple') === 'conditional'));

            $this->table(
                ['Field', 'Value'],
                [
                    ['Workflow',    $data->name . ' (' . $slug . ')'],
                    ['Action',      $exists ? '<fg=yellow>UPDATE</>' : '<fg=green>CREATE</>'],
                    ['States',      $stateCount . ($conditionals ? " ({$conditionals} conditional)" : '')],
                    ['Transitions', $transCount],
                    ['Format ver.', $data->version ?? '1 (legacy)'],
                ]
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Write workflow (inside DB::transaction)
    // ─────────────────────────────────────────────────────────────────

    private function writeWorkflow(object $data): void
    {
        $slug = !empty($data->slug) ? $data->slug : Str::slug($data->name);

        // Upsert workflow
        $workflow = Workflow::where('slug', $slug)->first()
            ?? tap(new Workflow(), function ($w) use ($data, $slug) {
                $w->name = $data->name;
                $w->slug = $slug;
            });

        $workflow->name      = $data->name;
        $workflow->variables = !empty($data->variables) ? (array) $data->variables : null;
        $workflow->save();

        note('  Workflow "' . $slug . '" ' . ($workflow->wasRecentlyCreated ? 'created' : 'updated') . '.');

        // ── States ────────────────────────────────────────────────────
        $statePreserve = [];
        $statesMap     = [];  // ref (slug) → State uuid
        $transitions   = [];

        $prefix = $data->prefix ?? '';
        $bar    = $this->output->createProgressBar(count($data->states ?? []));

        foreach ($data->states as $sData) {
            $stateSlug = Str::slug($sData->name);
            $stateCode = ($prefix ? $prefix . '_' : '') . ($sData->code ?? '');
            $stateCode = rtrim($stateCode, '_') ?: null; // remove trailing underscore if code is empty

            $state = State::where('workflow_id', $workflow->id)
                          ->where('slug', $stateSlug)
                          ->first()
                ?? tap(new State(), function ($s) use ($workflow, $stateSlug) {
                    $s->workflow_id = $workflow->id;
                    $s->slug        = $stateSlug;
                });

            $state->type     = $sData->type     ?? 'simple';
            $state->name     = $sData->name;
            $state->code     = $stateCode;
            $state->is_start = !empty($sData->is_start);
            $state->is_end   = !empty($sData->is_end);
            $state->x        = (float) ($sData->x ?? 0);
            $state->y        = (float) ($sData->y ?? 0);
            $state->save();

            $statePreserve[]   = $state->id;
            $statesMap[$stateSlug]    = $state->id;
            $statesMap[$state->id]    = $state->id;  // allow uuid refs
            if (!empty($sData->slug)) {
                $statesMap[$sData->slug] = $state->id;
            }

            // Replace state actions
            $state->actions()->delete();
            $this->saveStateActions($state, 'on_enter', (array) ($sData->on_enter_actions ?? []));
            $this->saveStateActions($state, 'on_exit',  (array) ($sData->on_exit_actions  ?? []));

            // Queue transitions (need full statesMap resolved first)
            foreach ($sData->transitions ?? [] as $order => $t) {
                $t        = (object) $t;
                $t->_from = $state->id;
                $t->_sort = $t->sort ?? $order;
                $transitions[] = $t;
            }

            $bar->advance();
        }
        $bar->finish();
        $this->line('');

        // Remove orphaned states
        $orphans = State::where('workflow_id', $workflow->id)->whereNotIn('id', $statePreserve)->get();
        if ($orphans->isNotEmpty()) {
            note('  Removing ' . $orphans->count() . ' orphaned state(s).');
            $orphans->each(fn ($s) => $s->delete());
        }

        // ── Transitions ───────────────────────────────────────────────
        // Delete all existing transitions (cascade deletes their actions)
        $oldIds = Transition::join('states', 'states.id', '=', 'transitions.from')
                             ->where('states.workflow_id', $workflow->id)
                             ->select('transitions.id')
                             ->pluck('transitions.id');
        Transition::whereIn('id', $oldIds)->each(fn ($t) => $t->delete());

        $tBar = $this->output->createProgressBar(count($transitions));

        // Sort by sort field so conditional node branches are in the right order
        usort($transitions, fn ($a, $b) => ($a->_sort ?? 0) <=> ($b->_sort ?? 0));

        foreach ($transitions as $t) {
            $toRef = $t->to ?? null;
            $toId  = $statesMap[$toRef] ?? $statesMap[Str::slug($toRef ?? '')] ?? null;

            if (empty($toId)) {
                warning('  Transition to "' . $toRef . '" could not be resolved — skipping.');
                $tBar->advance();
                continue;
            }

            $transition                    = new Transition();
            $transition->from              = $t->_from;
            $transition->to                = $toId;
            $transition->sort              = $t->_sort;
            $transition->label             = $t->label ?? null;
            $transition->permission        = $t->permission ?? null;
            $transition->redirect          = $t->redirect ?? null;
            $transition->form_type         = $t->form_type ?? null;
            $transition->form_data         = $t->form_data ?? null;
            $transition->show_condition    = $this->toArray($t->show_condition    ?? null);
            $transition->execute_condition = $this->toArray($t->execute_condition ?? null);
            $transition->exit_condition    = $this->toArray($t->exit_condition    ?? null);
            $transition->save();

            $this->saveTransitionActions($transition, (array) ($t->actions ?? []));

            $tBar->advance();
        }
        $tBar->finish();
        $this->line('');
    }

    // ─────────────────────────────────────────────────────────────────
    // Action helpers
    // ─────────────────────────────────────────────────────────────────

    private function saveStateActions(State $state, string $phase, array $actions): void
    {
        foreach ($actions as $i => $raw) {
            $a = (object) $raw;
            if (empty($a->action)) {
                continue;
            }

            if (!class_exists($a->action)) {
                warning('  Action class "' . $a->action . '" not found — skipping.');
                continue;
            }

            if (!in_array(ActionInterface::class, class_implements($a->action) ?: [])) {
                warning('  "' . $a->action . '" does not implement ActionInterface — skipping.');
                continue;
            }

            $action                  = new Action();
            $action->actionable_type = State::class;
            $action->actionable_id   = $state->id;
            $action->sort            = $a->sort ?? $i;
            $action->phase           = $phase;
            $action->action          = $a->action;
            $action->configuration   = $this->toArray($a->configuration ?? null);
            $action->save();
        }
    }

    private function saveTransitionActions(Transition $transition, array $actions): void
    {
        $force = (bool) $this->option('force');

        foreach ($actions as $i => $raw) {
            $a = (object) $raw;
            if (empty($a->action)) {
                continue;
            }

            if (!class_exists($a->action)) {
                warning('  Action class "' . $a->action . '" not found — skipping.');
                continue;
            }

            if (!in_array(ActionInterface::class, class_implements($a->action) ?: [])) {
                warning('  "' . $a->action . '" does not implement ActionInterface — skipping.');
                continue;
            }

            $registered = RegisteredAction::where('action', $a->action)->exists();
            if (!$registered && !$force) {
                warning('  Action "' . $a->action . '" not registered (use --force to import anyway) — skipping.');
                continue;
            }

            $action                  = new Action();
            $action->actionable_type = Transition::class;
            $action->actionable_id   = $transition->id;
            $action->sort            = $a->sort ?? $i;
            $action->phase           = $a->phase ?? 'pre';
            $action->action          = $a->action;
            $action->configuration   = $this->toArray($a->configuration ?? null);
            $action->save();
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Utilities
    // ─────────────────────────────────────────────────────────────────

    /**
     * Convert any value to array|null — handles JSON string, stdClass, array, null.
     */
    private function toArray(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return json_decode(json_encode($value), true);
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }
}
