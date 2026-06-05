<?php

namespace Fazzinipierluigi\LaravelRails\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Fazzinipierluigi\LaravelRails\Classes\TriggerService;
use Fazzinipierluigi\LaravelRails\Models\WorkflowTrigger;

class TriggerController extends Controller
{
    public function fire(Request $request, string $triggerId): JsonResponse|RedirectResponse
    {
        $trigger = WorkflowTrigger::findOrFail($triggerId);

        $entityClass = $trigger->getEntityClass();
        if (empty($entityClass) || !class_exists($entityClass)) {
            abort(422, 'Trigger entity_class is missing or invalid');
        }

        $entityId = $request->input('entity_id');
        if (empty($entityId)) {
            abort(422, 'entity_id is required');
        }

        $entity = $entityClass::findOrFail($entityId);

        try {
            $instance = TriggerService::fireManual($trigger, $entity);
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return redirect()->back()->withErrors(['workflow' => $e->getMessage()]);
        }

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'instance_id' => $instance->id,
                'state_id'    => $instance->state_id,
            ]);
        }

        return redirect()->back()->with('rail_instance_id', $instance->id);
    }
}
