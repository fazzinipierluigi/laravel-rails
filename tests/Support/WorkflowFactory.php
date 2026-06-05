<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Support;

use Fazzinipierluigi\LaravelRails\Models\Action;
use Fazzinipierluigi\LaravelRails\Models\State;
use Fazzinipierluigi\LaravelRails\Models\Transition;
use Fazzinipierluigi\LaravelRails\Models\Workflow;
use Fazzinipierluigi\LaravelRails\Tests\Support\Models\Order;

/**
 * Factory helpers for building workflow structures in tests.
 *
 * All returned arrays use the same key names for predictability.
 * The workflow is not instantiated — callers do $workflow->instantiate($entity).
 */
class WorkflowFactory
{
    /**
     * Two-state workflow: Start → End (no intermediate state).
     * Start has is_start=true, End has is_end=true.
     * Transition has no form, no conditions.
     */
    public static function twoState(string $slug = 'two-state'): array
    {
        $workflow = Workflow::create(['name' => 'Two State', 'slug' => $slug]);

        $start = State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'Start',
            'slug'        => 'start',
            'is_start'    => true,
            'is_end'      => false,
        ]);

        $end = State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'End',
            'slug'        => 'end',
            'is_start'    => false,
            'is_end'      => true,
        ]);

        $transition = Transition::create([
            'from' => $start->id,
            'to'   => $end->id,
            'sort' => 0,
        ]);

        return compact('workflow', 'start', 'end', 'transition');
    }

    /**
     * Three-state workflow: Start → Middle → End.
     * Useful when tests need a state to progress through before reaching End.
     */
    public static function threeState(string $slug = 'three-state'): array
    {
        $workflow = Workflow::create(['name' => 'Three State', 'slug' => $slug]);

        $start = State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'Start',
            'slug'        => 'start',
            'is_start'    => true,
        ]);

        $middle = State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'Middle',
            'slug'        => 'middle',
        ]);

        $end = State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'End',
            'slug'        => 'end',
            'is_end'      => true,
        ]);

        $t1 = Transition::create(['from' => $start->id,  'to' => $middle->id, 'sort' => 0]);
        $t2 = Transition::create(['from' => $middle->id, 'to' => $end->id,    'sort' => 0]);

        return compact('workflow', 'start', 'middle', 'end', 't1', 't2');
    }

    /**
     * Workflow where the Start→Middle transition requires a form submission.
     * Start -[form]-> Middle -> End
     */
    public static function withForm(string $slug = 'with-form'): array
    {
        $workflow = Workflow::create(['name' => 'With Form', 'slug' => $slug]);

        $start = State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'Start',
            'slug'        => 'start',
            'is_start'    => true,
        ]);

        $review = State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'Review',
            'slug'        => 'review',
        ]);

        $end = State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'End',
            'slug'        => 'end',
            'is_end'      => true,
        ]);

        $formData = json_encode([
            ['name' => 'notes', 'type' => 'text', 'label' => 'Notes', 'required' => false],
            ['name' => 'approved', 'type' => 'checkbox', 'label' => 'Approve', 'required' => true],
        ]);

        $t1 = Transition::create([
            'from'      => $start->id,
            'to'        => $review->id,
            'sort'      => 0,
            'form_type' => 'json',
            'form_data' => $formData,
        ]);

        $t2 = Transition::create(['from' => $review->id, 'to' => $end->id, 'sort' => 0]);

        return compact('workflow', 'start', 'review', 'end', 't1', 't2', 'formData');
    }

    /**
     * Workflow with a conditional routing node.
     *
     * Start → [Conditional] → Branch A (amount > 100) → End
     *                       → Branch B (else)          → End
     */
    public static function withConditional(string $slug = 'with-conditional'): array
    {
        $workflow = Workflow::create(['name' => 'With Conditional', 'slug' => $slug]);

        $start = State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'Start',
            'slug'        => 'start',
            'is_start'    => true,
        ]);

        $conditional = State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'Router',
            'slug'        => 'router',
            'type'        => State::TYPE_CONDITIONAL,
        ]);

        $branchA = State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'Branch A',
            'slug'        => 'branch-a',
        ]);

        $branchB = State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'Branch B',
            'slug'        => 'branch-b',
        ]);

        $end = State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'End',
            'slug'        => 'end',
            'is_end'      => true,
        ]);

        $toConditional = Transition::create(['from' => $start->id, 'to' => $conditional->id, 'sort' => 0]);

        // Branch A: amount > 100
        $toA = Transition::create([
            'from'              => $conditional->id,
            'to'                => $branchA->id,
            'sort'              => 0,
            'execute_condition' => ['>' => [['var' => 'variables.amount'], 100]],
        ]);

        // Branch B: no condition = else/default
        $toB = Transition::create([
            'from' => $conditional->id,
            'to'   => $branchB->id,
            'sort' => 1,
        ]);

        $endA = Transition::create(['from' => $branchA->id, 'to' => $end->id, 'sort' => 0]);
        $endB = Transition::create(['from' => $branchB->id, 'to' => $end->id, 'sort' => 0]);

        return compact(
            'workflow', 'start', 'conditional', 'branchA', 'branchB', 'end',
            'toConditional', 'toA', 'toB', 'endA', 'endB'
        );
    }

    /**
     * Create and persist a test Order entity.
     */
    public static function createOrder(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'status'        => 'pending',
            'amount'        => 100.00,
            'customer_name' => 'Test Customer',
        ], $attrs));
    }

    /**
     * Attach an action record to a state or transition.
     */
    public static function addAction(
        mixed  $actionable,
        string $phase,
        string $actionClass,
        array  $configuration = []
    ): Action {
        return Action::create([
            'actionable_type' => get_class($actionable),
            'actionable_id'   => $actionable->id,
            'phase'           => $phase,
            'sort'            => 0,
            'action'          => $actionClass,
            'configuration'   => !empty($configuration) ? $configuration : null,
        ]);
    }
}
