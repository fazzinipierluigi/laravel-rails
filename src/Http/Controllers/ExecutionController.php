<?php

namespace Fazzinipierluigi\LaravelRails\Http\Controllers;

use Illuminate\Routing\Controller;
use Fazzinipierluigi\LaravelRails\Models\ExecutionLog;
use Fazzinipierluigi\LaravelRails\Models\Instance;

class ExecutionController extends Controller
{
    public function data(string $instanceId)
    {
        $instance = Instance::with(['workflow', 'workflow.states', 'workflow.states.transitions'])
            ->find($instanceId);

        if (!$instance) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $logs = ExecutionLog::forInstance($instanceId)->orderBy('occurred_at')->get();

        $visitedStates      = $logs->where('event', 'state.entered')
            ->pluck('subject_id')->filter()->unique()->values()->all();
        $visitedTransitions = $logs->where('event', 'transition.performed')
            ->pluck('subject_id')->filter()->unique()->values()->all();

        $erroredStates      = [];
        $erroredTransitions = [];

        foreach ($logs as $log) {
            if ($log->event === 'action.executed' && ($log->data['result'] ?? '') === 'failure') {
                if ($log->subject_type === 'state' && $log->subject_id) {
                    $erroredStates[] = $log->subject_id;
                } elseif ($log->subject_type === 'transition' && $log->subject_id) {
                    $erroredTransitions[] = $log->subject_id;
                }
            }
            if ($log->event === 'execution.error') {
                $lastEnteredState = $logs
                    ->where('event', 'state.entered')
                    ->filter(fn($l) => $l->occurred_at <= $log->occurred_at)
                    ->last();
                if ($lastEnteredState?->subject_id) {
                    $erroredStates[] = $lastEnteredState->subject_id;
                }
            }
        }

        return response()->json([
            'instance' => [
                'id'                => $instance->id,
                'workflow_id'       => $instance->workflow_id,
                'current_state_id'  => $instance->state_id,
                'instanceable_type' => $instance->instanceable_type,
                'instanceable_id'   => $instance->instanceable_id,
            ],
            'workflow' => [
                'id'     => $instance->workflow->id,
                'name'   => $instance->workflow->name,
                'states' => $instance->workflow->states->map(fn($s) => [
                    'id'          => $s->id,
                    'name'        => $s->name,
                    'type'        => $s->type ?? 'simple',
                    'is_start'    => $s->is_start,
                    'is_end'      => $s->is_end,
                    'code'        => $s->code,
                    'x'           => $s->x ?? 0,
                    'y'           => $s->y ?? 0,
                    'transitions' => $s->transitions->map(fn($t) => [
                        'id'    => $t->id,
                        'from'  => $t->from,
                        'to'    => $t->to,
                        'label' => $t->label,
                        'sort'  => $t->sort,
                    ]),
                ]),
            ],
            'traversed' => [
                'states'      => $visitedStates,
                'transitions' => $visitedTransitions,
                'errors'      => [
                    'states'      => array_unique($erroredStates),
                    'transitions' => array_unique($erroredTransitions),
                ],
            ],
        ]);
    }

    public function nodeLogs(string $instanceId, string $type, string $subjectId)
    {
        if (!in_array($type, ['state', 'transition'], true)) {
            abort(400);
        }

        $logs = ExecutionLog::forInstance($instanceId)
            ->where('subject_type', $type)
            ->where('subject_id', $subjectId)
            ->orderBy('occurred_at')
            ->get();

        return response()->json($logs);
    }
}
