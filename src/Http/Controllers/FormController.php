<?php

namespace Fazzinipierluigi\LaravelRails\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Models\Transition;

class FormController extends Controller
{
    public function execute(Request $request, string $transitionId): JsonResponse|RedirectResponse
    {
        $transition = Transition::find($transitionId);
        if (empty($transition)) {
            abort(404, 'Transition not found');
        }

        $instanceId = $request->input('instance_id');
        $instance   = Instance::find($instanceId);
        if (empty($instance)) {
            abort(404, 'Instance not found');
        }

        // Validate form data against JSON schema if present
        if ($transition->form_type === 'json' && !empty($transition->form_data)) {
            $schema = json_decode($transition->form_data, true) ?? [];
            $this->validateAgainstSchema($request, $schema);
        }

        // Merge submitted form data into instance variables
        $formData = $request->except(['_token', 'instance_id']);
        if (!empty($formData)) {
            $instance->variables = array_merge($instance->variables ?? [], $formData);
            $instance->save();
        }

        // Initialize logger with user context before perform()
        $userId      = auth()->id();
        $triggeredBy = $userId ? 'user:' . $userId : 'system';
        $instance->logger($triggeredBy);

        // Advance permission check
        $user = auth()->user();
        if (!$transition->canAdvance($user)) {
            abort(403, 'Permesso non sufficiente per eseguire questa transizione');
        }

        // Execute the specific transition, then auto-chain through any conditional states
        $transition->perform($instance);
        $instance->refresh();
        $chainLast   = $instance->resolveConditionalChain();
        $instance->checkAutoAdvance();

        $final       = $chainLast ?? $transition;
        $redirectUrl = $final->redirect
            ? route($final->redirect)
            : url()->previous();

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['redirect' => $redirectUrl]);
        }

        return redirect($redirectUrl);
    }

    private function validateAgainstSchema(Request $request, array $schema): void
    {
        $rules    = [];
        $messages = [];

        foreach ($schema as $field) {
            $name = $field['name'] ?? '';
            if (empty($name)) {
                continue;
            }

            $fieldRules = [];

            if (!empty($field['required'])) {
                $fieldRules[] = 'required';
            }

            if (!empty($field['validation'])) {
                foreach (explode('|', $field['validation']) as $rule) {
                    $rule = trim($rule);
                    if ($rule !== '') {
                        $fieldRules[] = $rule;
                    }
                }
            }

            // Type-based implicit rules
            switch ($field['type'] ?? 'text') {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
            }

            if (!empty($fieldRules)) {
                $rules[$name] = array_unique($fieldRules);
            }
        }

        if (!empty($rules)) {
            $request->validate($rules);
        }
    }
}
