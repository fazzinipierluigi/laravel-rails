<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Feature;

use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Models\State;
use Fazzinipierluigi\LaravelRails\Models\Transition;
use Fazzinipierluigi\LaravelRails\Models\Workflow;
use Fazzinipierluigi\LaravelRails\Tests\Support\WorkflowFactory;
use Fazzinipierluigi\LaravelRails\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

/**
 * Tests for conditional state routing and the resolveConditionalChain() method.
 *
 * Conditional states are traversed automatically (no human input) in order of
 * their sorted transitions. The first matching execute_condition wins.
 * An unconditional transition (no execute_condition) acts as the else/default branch.
 */
class ConditionalStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_conditional_state_routes_to_branch_a_when_amount_high(): void
    {
        [
            'workflow' => $workflow,
            'branchA'  => $branchA,
        ] = WorkflowFactory::withConditional();

        $order    = WorkflowFactory::createOrder(['amount' => 200]);
        $instance = $workflow->instantiate($order);
        $instance->variables = ['amount' => 200];
        $instance->save();

        $instance->progress(); // Start → Conditional → auto-chains to Branch A
        $instance->refresh();

        $this->assertEquals($branchA->id, $instance->state_id);
    }

    public function test_conditional_state_routes_to_branch_b_when_condition_false(): void
    {
        [
            'workflow' => $workflow,
            'branchB'  => $branchB,
        ] = WorkflowFactory::withConditional();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->variables = ['amount' => 50]; // 50 is NOT > 100 → Branch B
        $instance->save();

        $instance->progress();
        $instance->refresh();

        $this->assertEquals($branchB->id, $instance->state_id);
    }

    public function test_conditional_state_uses_else_branch_for_missing_condition(): void
    {
        // Branch B has no execute_condition = always true (else branch)
        [
            'workflow' => $workflow,
            'branchB'  => $branchB,
            'toA'      => $toA,
        ] = WorkflowFactory::withConditional();

        // Make Branch A condition impossible
        $toA->execute_condition = ['==' => [1, 0]]; // always false
        $toA->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->variables = ['amount' => 0];
        $instance->save();

        $instance->progress();
        $instance->refresh();

        $this->assertEquals($branchB->id, $instance->state_id);
    }

    public function test_conditional_chain_resolves_multiple_hops(): void
    {
        // Build: Start → Cond1 → Cond2 → End
        $workflow = Workflow::create(['name' => 'Multi-Hop', 'slug' => 'multi-hop']);

        $start = State::create(['workflow_id' => $workflow->id, 'name' => 'Start', 'slug' => 'start', 'is_start' => true]);
        $cond1 = State::create(['workflow_id' => $workflow->id, 'name' => 'Cond1', 'slug' => 'cond1', 'type' => 'conditional']);
        $cond2 = State::create(['workflow_id' => $workflow->id, 'name' => 'Cond2', 'slug' => 'cond2', 'type' => 'conditional']);
        $end   = State::create(['workflow_id' => $workflow->id, 'name' => 'End',   'slug' => 'end',   'is_end' => true]);

        Transition::create(['from' => $start->id, 'to' => $cond1->id, 'sort' => 0]);
        Transition::create(['from' => $cond1->id, 'to' => $cond2->id, 'sort' => 0]);
        Transition::create(['from' => $cond2->id, 'to' => $end->id,   'sort' => 0]);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $instance->progress(); // Start → Cond1 → Cond2 → End (all auto-chained)
        $instance->refresh();

        $this->assertEquals($end->id, $instance->state_id);
    }

    public function test_conditional_chain_detects_self_loop_and_throws(): void
    {
        $workflow = Workflow::create(['name' => 'Loopy', 'slug' => 'loopy']);

        $start = State::create(['workflow_id' => $workflow->id, 'name' => 'Start', 'slug' => 'start', 'is_start' => true]);
        $cond  = State::create(['workflow_id' => $workflow->id, 'name' => 'Cond',  'slug' => 'cond',  'type' => 'conditional']);

        Transition::create(['from' => $start->id, 'to' => $cond->id, 'sort' => 0]);
        Transition::create(['from' => $cond->id,  'to' => $cond->id, 'sort' => 0]); // self-loop

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/infinite loop/i');

        $instance->progress();
    }

    public function test_conditional_chain_throws_when_no_matching_transition(): void
    {
        $workflow = Workflow::create(['name' => 'Dead-End', 'slug' => 'dead-end']);

        $start = State::create(['workflow_id' => $workflow->id, 'name' => 'Start', 'slug' => 'start', 'is_start' => true]);
        $cond  = State::create(['workflow_id' => $workflow->id, 'name' => 'Cond',  'slug' => 'cond',  'type' => 'conditional']);

        Transition::create(['from' => $start->id, 'to' => $cond->id, 'sort' => 0]);
        // Conditional node has a transition that never matches
        Transition::create([
            'from'              => $cond->id,
            'to'                => $start->id, // arbitrary target
            'sort'              => 0,
            'execute_condition' => ['==' => [1, 0]], // always false, no else branch
        ]);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/deadlock/i');

        $instance->progress();
    }

    public function test_simple_state_does_not_auto_advance_through_chain(): void
    {
        ['workflow' => $workflow, 'start' => $start, 'middle' => $middle] = WorkflowFactory::threeState();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        // After instantiation, instance is at Start (simple state = no auto-chaining)
        $this->assertEquals($start->id, $instance->state_id);

        $instance->progress();
        $instance->refresh();

        // After one explicit progress(), should be at Middle (not auto-chained to End)
        $this->assertEquals($middle->id, $instance->state_id);
    }

    public function test_conditional_state_logs_condition_evaluation(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::withConditional();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->variables = ['amount' => 200];
        $instance->save();

        $instance->progress();

        $condLogs = \Fazzinipierluigi\LaravelRails\Models\ExecutionLog::where('instance_id', $instance->id)
            ->where('event', 'transition.condition_evaluated')
            ->get();

        $this->assertGreaterThan(0, $condLogs->count());
    }
}
