<?php

namespace Fazzinipierluigi\LaravelRails\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Fazzinipierluigi\LaravelRails\Models\Action;
use Fazzinipierluigi\LaravelRails\Models\RegisteredAction;
use Fazzinipierluigi\LaravelRails\Models\State;
use Fazzinipierluigi\LaravelRails\Models\Transition;
use Fazzinipierluigi\LaravelRails\Models\Workflow;

class EditorController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $workflow = Workflow::getBySlug($slug);

        if (empty($workflow)) {
            return response()->json(['error' => 'Workflow not found'], 404);
        }

        return response()->json($this->serializeWorkflow($workflow));
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $workflow = Workflow::getBySlug($slug);

        if (empty($workflow)) {
            return response()->json(['error' => 'Workflow not found'], 404);
        }

        $data = $request->validate([
            'states'                               => 'required|array',
            'states.*.name'                        => 'required|string|max:255',
            'states.*.type'                        => 'nullable|in:simple,conditional',
            'states.*.code'                        => 'nullable|string|max:100',
            'states.*.is_start'                    => 'boolean',
            'states.*.is_end'                      => 'boolean',
            'states.*.x'                           => 'numeric',
            'states.*.y'                           => 'numeric',
            'states.*.on_enter_actions'            => 'array',
            'states.*.on_exit_actions'             => 'array',
            'states.*.transitions'                 => 'array',
            'states.*.transitions.*.to_id'         => 'required|string',
            'states.*.transitions.*.sort'          => 'nullable|integer',
            'states.*.transitions.*.label'         => 'nullable|string|max:255',
            'states.*.transitions.*.actions'       => 'array',
            'states.*.transitions.*.form_type'     => 'nullable|in:json',
            'states.*.transitions.*.form_data'     => 'nullable|string',
            'states.*.transitions.*.waypoints'     => 'nullable|array',
            'variables'                            => 'nullable|array',
            'variables.*.name'                     => 'required_with:variables|string|max:100',
            'variables.*.type'                     => 'nullable|in:string,number,boolean,date',
            'variables.*.default'                  => 'nullable',
            'variables.*.label'                    => 'nullable|string|max:255',
        ]);

        // Validate: at most one initial state
        $startCount = count(array_filter($data['states'], fn($s) => !empty($s['is_start'])));
        if ($startCount > 1) {
            return response()->json([
                'message' => trans('laravel-rails::editor.single_start_err'),
                'errors'  => ['states' => [trans('laravel-rails::editor.single_start_err')]],
            ], 422);
        }

        DB::transaction(function () use ($workflow, $data) {
            // Save workflow-level variables
            if (isset($data['variables'])) {
                $workflow->variables = $data['variables'];
                $workflow->save();
            }

            $statePreserve = [];
            $statesMap     = [];

            foreach ($data['states'] as $sData) {
                $stateSlug = Str::slug($sData['name']);

                $state = null;
                if (!empty($sData['id'])) {
                    $state = State::where('id', $sData['id'])
                                  ->where('workflow_id', $workflow->id)
                                  ->first();
                }
                if (empty($state)) {
                    $state = State::where('workflow_id', $workflow->id)
                                  ->where('slug', $stateSlug)
                                  ->first();
                }
                if (empty($state)) {
                    $state              = new State();
                    $state->workflow_id = $workflow->id;
                }

                $state->name             = $sData['name'];
                $state->type             = $sData['type'] ?? 'simple';
                $state->slug             = $stateSlug;
                $state->code             = $sData['code'] ?? null;
                $state->is_start         = !empty($sData['is_start']);
                $state->is_end           = !empty($sData['is_end']);
                $state->x                = $sData['x'] ?? 0;
                $state->y                = $sData['y'] ?? 0;
                $state->view_permissions = !empty($sData['view_permissions']) ? $sData['view_permissions'] : null;
                $state->view_operator    = $sData['view_operator'] ?? 'OR';
                $state->save();

                $statePreserve[] = $state->id;
                $statesMap[Str::slug($sData['name'])] = $state->id;
                $statesMap[$sData['name']]             = $state->id;
                if (!empty($sData['id'])) {
                    $statesMap[$sData['id']] = $state->id;
                }
                $statesMap[$state->id] = $state->id;

                $state->actions()->delete();
                $this->saveStateActions($state, 'on_enter', $sData['on_enter_actions'] ?? []);
                $this->saveStateActions($state, 'on_exit', $sData['on_exit_actions'] ?? []);
            }

            State::where('workflow_id', $workflow->id)
                 ->whereNotIn('id', $statePreserve)
                 ->each(fn ($s) => $s->delete());

            Transition::whereIn('from', $statePreserve)->each(fn ($t) => $t->delete());

            foreach ($data['states'] as $sData) {
                $fromId = $statesMap[$sData['name']] ?? $statesMap[Str::slug($sData['name'])] ?? null;
                if (empty($fromId)) {
                    continue;
                }

                foreach ($sData['transitions'] ?? [] as $i => $tData) {
                    $toId = $statesMap[$tData['to_id']] ?? null;
                    if (empty($toId)) {
                        continue;
                    }

                    $transition                      = new Transition();
                    $transition->from                = $fromId;
                    $transition->to                  = $toId;
                    $transition->sort                = $tData['sort'] ?? $i;
                    $transition->label               = $tData['label'] ?? null;
                    $transition->show_condition      = $tData['show_condition'] ?? null;
                    $transition->execute_condition   = $tData['execute_condition'] ?? null;
                    $transition->exit_condition      = $tData['exit_condition'] ?? null;
                    $transition->permission          = $tData['permission'] ?? null;
                    $transition->redirect            = $tData['redirect'] ?? null;
                    $transition->form_type           = $tData['form_type'] ?? null;
                    $transition->form_data           = !empty($tData['form_data']) ? $tData['form_data'] : null;
                    $transition->view_permissions    = !empty($tData['view_permissions']) ? $tData['view_permissions'] : null;
                    $transition->view_operator       = $tData['view_operator'] ?? 'OR';
                    $transition->advance_permissions = !empty($tData['advance_permissions']) ? $tData['advance_permissions'] : null;
                    $transition->advance_operator    = $tData['advance_operator'] ?? 'OR';
                    $transition->waypoints           = !empty($tData['waypoints']) ? $tData['waypoints'] : null;
                    $transition->save();

                    foreach ($tData['actions'] ?? [] as $j => $aData) {
                        $action                  = new Action();
                        $action->actionable_type = Transition::class;
                        $action->actionable_id   = $transition->id;
                        $action->sort            = $j;
                        $action->phase           = $aData['phase'] ?? 'pre';
                        $action->action          = $aData['action'];
                        $action->configuration   = !empty($aData['configuration']) ? $aData['configuration'] : null;
                        $action->save();
                    }
                }
            }
        });

        return $this->show($slug);
    }

    public function registeredActions(): JsonResponse
    {
        $actions = RegisteredAction::all()->map(fn ($a) => [
            'action'               => $a->action,
            'display_name'         => $a->display_name,
            'configuration_schema' => $a->getConfigurationSchema(),
        ]);

        return response()->json($actions);
    }

    private function serializeWorkflow(Workflow $workflow): array
    {
        return [
            'id'        => $workflow->id,
            'name'      => $workflow->name,
            'slug'      => $workflow->slug,
            'variables' => $workflow->variables ?? [],
            'states'    => $workflow->states()->orderBy('code')->get()->map(function (State $state) {
                return [
                    'id'               => $state->id,
                    'name'             => $state->name,
                    'type'             => $state->type ?? 'simple',
                    'code'             => $state->code,
                    'slug'             => $state->slug,
                    'is_start'         => $state->is_start,
                    'is_end'           => $state->is_end,
                    'x'                => (float) $state->x,
                    'y'                => (float) $state->y,
                    'view_permissions' => $state->view_permissions ?? [],
                    'view_operator'    => $state->view_operator ?? 'OR',
                    'on_enter_actions' => $this->serializeActions($state->actions()->where('phase', 'on_enter')->orderBy('sort')->get()),
                    'on_exit_actions'  => $this->serializeActions($state->actions()->where('phase', 'on_exit')->orderBy('sort')->get()),
                    'transitions'      => $state->transitions()->orderBy('sort')->get()->map(fn (Transition $t) => [
                        'id'                  => $t->id,
                        'to_id'               => $t->to,
                        'sort'                => $t->sort,
                        'label'               => $t->label,
                        'show_condition'      => $t->show_condition,
                        'execute_condition'   => $t->execute_condition,
                        'exit_condition'      => $t->exit_condition,
                        'permission'          => $t->permission,
                        'redirect'            => $t->redirect,
                        'form_type'           => $t->form_type,
                        'form_data'           => $t->form_data,
                        'view_permissions'    => $t->view_permissions ?? [],
                        'view_operator'       => $t->view_operator ?? 'OR',
                        'advance_permissions' => $t->advance_permissions ?? [],
                        'advance_operator'    => $t->advance_operator ?? 'OR',
                        'waypoints'           => $t->waypoints ?? [],
                        'actions'             => $this->serializeActions($t->actions()->orderBy('sort')->get()),
                    ]),
                ];
            }),
        ];
    }

    private function serializeActions($actions): array
    {
        return $actions->map(fn (Action $a) => [
            'sort'          => $a->sort,
            'phase'         => $a->phase,
            'action'        => $a->action,
            'configuration' => $a->configuration,
        ])->values()->all();
    }

    private function saveStateActions(State $state, string $phase, array $actions): void
    {
        foreach ($actions as $i => $aData) {
            if (empty($aData['action'])) {
                continue;
            }

            $action                  = new Action();
            $action->actionable_type = State::class;
            $action->actionable_id   = $state->id;
            $action->sort            = $aData['sort'] ?? $i;
            $action->phase           = $phase;
            $action->action          = $aData['action'];
            $action->configuration   = !empty($aData['configuration']) ? $aData['configuration'] : null;
            $action->save();
        }
    }
}
