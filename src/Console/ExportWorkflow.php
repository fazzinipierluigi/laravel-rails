<?php

namespace Fazzinipierluigi\LaravelRails\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Fazzinipierluigi\LaravelRails\Models\State;
use Fazzinipierluigi\LaravelRails\Models\Transition;
use Fazzinipierluigi\LaravelRails\Models\Workflow;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\error;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;

class ExportWorkflow extends Command
{
    protected $signature = 'automata:workflow:export
                            {--w|workflow= : Slug of the workflow to export}
                            {--d|destination= : Destination filename (without .json)}
                            {--stdout : Print JSON to stdout instead of writing to file}';

    protected $description = 'Export a workflow structure to a JSON file for later import';

    // Format version — bump when the schema changes incompatibly
    private const FORMAT_VERSION = '2';

    public function handle(): int
    {
        $workflowSlug = trim($this->option('workflow') ?? '');
        $destination  = trim($this->option('destination') ?? '');
        $toStdout     = (bool) $this->option('stdout');

        $storage = Storage::build([
            'driver' => 'local',
            'root'   => storage_path('workflows'),
        ]);

        // ── Choose workflow ───────────────────────────────────────────
        if (empty($workflowSlug)) {
            $slugs = Workflow::orderBy('name')->pluck('slug')->toArray();
            if (empty($slugs)) {
                error('No workflows found in the database.');
                return Command::FAILURE;
            }
            $workflowSlug = select(label: 'Choose the workflow to export', options: $slugs);
        }

        $workflow = Workflow::where('slug', $workflowSlug)->first();
        if (empty($workflow)) {
            error('No workflow found with slug "' . $workflowSlug . '".');
            return Command::FAILURE;
        }

        // ── Choose destination ────────────────────────────────────────
        if (!$toStdout) {
            if (empty($destination)) {
                $destination = trim(text(
                    label   : 'Destination filename (without .json)',
                    default : $workflowSlug,
                    required: true,
                ));
            }

            if (!Str::endsWith($destination, '.json')) {
                $destination .= '.json';
            }

            if ($storage->exists($destination)) {
                if (!confirm('File "' . $destination . '" already exists. Overwrite?', default: true)) {
                    return Command::SUCCESS;
                }
            }
        }

        // ── Build structure ───────────────────────────────────────────
        info('▶ Building export structure for "' . $workflowSlug . '"…');
        $structure = $this->buildStructure($workflow);
        $json      = json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // ── Write output ──────────────────────────────────────────────
        if ($toStdout) {
            $this->line($json);
        } else {
            $storage->put($destination, $json);
            info('✓ Written to storage/workflows/' . $destination);
        }

        $stateCount      = count($structure['states'] ?? []);
        $transitionCount = array_sum(array_map(fn ($s) => count($s['transitions'] ?? []), $structure['states'] ?? []));
        note("  {$stateCount} states, {$transitionCount} transitions exported.");

        return Command::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────

    private function buildStructure(Workflow $workflow): array
    {
        // Build states reference map: id → slug-or-name
        $states = State::where('workflow_id', $workflow->id)
                       ->orderBy('code')
                       ->orderBy('name')
                       ->get();

        $statesMap = [];
        foreach ($states as $state) {
            $ref               = !empty($state->slug) ? $state->slug : Str::slug($state->name);
            $statesMap[$state->id] = $ref;
        }

        // Detect code prefix
        $prefix = '';
        foreach ($states as $state) {
            if (!empty($state->code) && str_contains($state->code, '_')) {
                $segments = explode('_', $state->code);
                if (count($segments) >= 2) {
                    $prefix = $segments[0];
                    note('Code prefix detected: ' . $prefix);
                    break;
                }
            }
        }

        // Build result
        $result = [
            'version'   => self::FORMAT_VERSION,
            'name'      => $workflow->name,
            'slug'      => $workflow->slug,
            'variables' => $workflow->variables ?? [],
        ];

        if (!empty($prefix)) {
            $result['prefix'] = $prefix;
        }

        $result['states'] = $states->map(
            fn ($state) => $this->serializeState($state, $prefix, $statesMap)
        )->values()->all();

        return $result;
    }

    private function serializeState(State $state, string $prefix, array $statesMap): array
    {
        // Strip prefix from code if present
        $code = $state->code ?? '';
        $prefixedCode = $prefix ? ($prefix . '_') : '';
        if ($prefixedCode && str_starts_with($code, $prefixedCode)) {
            $code = substr($code, strlen($prefixedCode));
        }

        return [
            'type'             => $state->type ?? 'simple',
            'name'             => $state->name,
            'code'             => $code ?: null,
            'slug'             => $state->slug ?? Str::slug($state->name),
            'is_start'         => $state->is_start ? 1 : 0,
            'is_end'           => $state->is_end   ? 1 : 0,
            'x'                => (float) ($state->x ?? 0),
            'y'                => (float) ($state->y ?? 0),
            'on_enter_actions' => $this->serializeStateActions($state, 'on_enter'),
            'on_exit_actions'  => $this->serializeStateActions($state, 'on_exit'),
            'transitions'      => $this->serializeTransitions($state, $statesMap),
        ];
    }

    private function serializeStateActions(State $state, string $phase): array
    {
        return $state->actions()
                     ->where('phase', $phase)
                     ->orderBy('sort')
                     ->get()
                     ->map(fn ($a) => [
                         'sort'          => $a->sort,
                         'action'        => $a->action,
                         'configuration' => $a->configuration ?? null,
                     ])
                     ->values()
                     ->all();
    }

    private function serializeTransitions(State $state, array $statesMap): array
    {
        return Transition::where('from', $state->id)
                         ->orderBy('sort')
                         ->get()
                         ->map(function (Transition $t) use ($statesMap) {
                             $toRef = $statesMap[$t->to] ?? null;
                             if (empty($toRef)) {
                                 $this->warn('  Transition from "' . $t->from . '" to unknown state "' . $t->to . '" — skipping.');
                                 return null;
                             }

                             return [
                                 'to'                => $toRef,
                                 'sort'              => $t->sort,
                                 'label'             => $t->label,
                                 'permission'        => $t->permission,
                                 'redirect'          => $t->redirect,
                                 'show_condition'    => $t->show_condition,
                                 'execute_condition' => $t->execute_condition,
                                 'exit_condition'    => $t->exit_condition,
                                 'form_type'         => $t->form_type,
                                 'form_data'         => $t->form_data,
                                 'actions'           => $this->serializeTransitionActions($t),
                             ];
                         })
                         ->filter()
                         ->values()
                         ->all();
    }

    private function serializeTransitionActions(Transition $transition): array
    {
        return $transition->actions()
                          ->orderBy('sort')
                          ->get()
                          ->map(fn ($a) => [
                              'sort'          => $a->sort,
                              'phase'         => $a->phase,
                              'action'        => $a->action,
                              'configuration' => $a->configuration ?? null,
                          ])
                          ->values()
                          ->all();
    }
}
