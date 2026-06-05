<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Feature;

use Fazzinipierluigi\LaravelRails\Models\ExecutionLog;
use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Models\Workflow;
use Fazzinipierluigi\LaravelRails\Tests\Support\WorkflowFactory;
use Fazzinipierluigi\LaravelRails\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

/**
 * Integration tests for the core workflow lifecycle:
 * instantiate → progress → reach end state.
 */
class WorkflowLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // Prevent auto-advance jobs from running during lifecycle tests
    }

    // ── Workflow::getBySlug ────────────────────────────────────────────

    public function test_get_by_slug_returns_workflow(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::twoState('my-wf');

        $found = Workflow::getBySlug('my-wf');

        $this->assertNotNull($found);
        $this->assertEquals($workflow->id, $found->id);
    }

    public function test_get_by_slug_is_case_insensitive(): void
    {
        WorkflowFactory::twoState('slug-test');

        $this->assertNotNull(Workflow::getBySlug('SLUG-TEST'));
        $this->assertNotNull(Workflow::getBySlug('Slug-Test'));
    }

    public function test_get_by_slug_returns_null_for_missing_workflow(): void
    {
        $this->assertNull(Workflow::getBySlug('does-not-exist'));
    }

    public function test_get_by_slug_returns_null_for_empty_input(): void
    {
        $this->assertNull(Workflow::getBySlug(''));
        $this->assertNull(Workflow::getBySlug(null));
    }

    // ── Workflow::instantiate ──────────────────────────────────────────

    public function test_instantiate_creates_instance_at_start_state(): void
    {
        ['workflow' => $workflow, 'start' => $start] = WorkflowFactory::twoState();
        $order = WorkflowFactory::createOrder();

        $instance = $workflow->instantiate($order);

        $this->assertDatabaseHas('instances', ['id' => $instance->id]);
        $this->assertEquals($start->id,                $instance->state_id);
        $this->assertEquals($order->id,                $instance->instanceable_id);
        $this->assertEquals(get_class($order),         $instance->instanceable_type);
        $this->assertEquals($workflow->id,             $instance->workflow_id);
    }

    public function test_instantiate_logs_instance_started_and_state_entered(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::twoState();
        $order = WorkflowFactory::createOrder();

        $instance = $workflow->instantiate($order);

        $events = ExecutionLog::where('instance_id', $instance->id)->pluck('event')->all();
        $this->assertContains('instance.started', $events);
        $this->assertContains('state.entered',    $events);
    }

    public function test_instantiate_throws_when_workflow_has_no_start_state(): void
    {
        $workflow = Workflow::create(['name' => 'Empty', 'slug' => 'empty']);
        $order    = WorkflowFactory::createOrder();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/no start state/i');

        $workflow->instantiate($order);
    }

    public function test_instantiate_throws_when_entity_already_has_instance(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::twoState();
        $order = WorkflowFactory::createOrder();

        $workflow->instantiate($order);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/already instantiated/i');

        $workflow->instantiate($order);
    }

    // ── Instance::progress ─────────────────────────────────────────────

    public function test_progress_advances_instance_to_next_state(): void
    {
        ['workflow' => $workflow, 'end' => $end] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $instance->progress();

        $instance->refresh();
        $this->assertEquals($end->id, $instance->state_id);
    }

    public function test_progress_logs_transition_performed_and_state_entered(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $instance->progress();

        $events = ExecutionLog::where('instance_id', $instance->id)->pluck('event')->all();
        $this->assertContains('transition.performed', $events);
        $this->assertContains('state.exited',         $events);
    }

    public function test_progress_returns_non_empty_string(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $result = $instance->progress();

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_progress_throws_when_no_applicable_transition(): void
    {
        ['workflow' => $workflow, 'end' => $end] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();

        // Create instance directly at end state (no outgoing transitions)
        $i = new Instance();
        $i->instanceable_type = get_class($order);
        $i->instanceable_id   = $order->id;
        $i->workflow_id       = $workflow->id;
        $i->state_id          = $end->id;
        $i->save();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/no applicable transition/i');

        $i->progress();
    }

    public function test_three_state_workflow_advances_step_by_step(): void
    {
        ['workflow' => $workflow, 'middle' => $middle, 'end' => $end] = WorkflowFactory::threeState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $instance->progress();
        $instance->refresh();
        $this->assertEquals($middle->id, $instance->state_id);

        $instance->progress();
        $instance->refresh();
        $this->assertEquals($end->id, $instance->state_id);
    }

    public function test_reaching_end_state_logs_instance_completed(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $instance->progress();

        $completedLog = ExecutionLog::where('instance_id', $instance->id)
            ->where('event', 'instance.completed')
            ->first();

        $this->assertNotNull($completedLog);
    }

    // ── Variable helpers ───────────────────────────────────────────────

    public function test_get_variable_returns_value_via_dot_notation(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->variables = ['price' => 50, 'nested' => ['key' => 'val']];
        $instance->save();

        $this->assertEquals(50,    $instance->getVariable('price'));
        $this->assertEquals('val', $instance->getVariable('nested.key'));
        $this->assertEquals('x',   $instance->getVariable('missing', 'x'));
    }

    public function test_set_variable_persists_via_dot_notation(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $instance->setVariable('total', 99);

        $instance->refresh();
        $this->assertEquals(99, $instance->getVariable('total'));
    }

    public function test_merge_variables_merges_without_overwriting_others(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->variables = ['a' => 1];
        $instance->save();

        $instance->mergeVariables(['b' => 2]);

        $instance->refresh();
        $this->assertEquals(1, $instance->getVariable('a'));
        $this->assertEquals(2, $instance->getVariable('b'));
    }

    public function test_has_variable_returns_correct_boolean(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->variables = ['exists' => 'yes'];
        $instance->save();

        $this->assertTrue($instance->hasVariable('exists'));
        $this->assertFalse($instance->hasVariable('not_there'));
    }

    // ── Transition conditions ──────────────────────────────────────────

    public function test_transition_with_execute_condition_blocks_when_condition_false(): void
    {
        ['workflow' => $workflow, 'start' => $start, 'end' => $end] = WorkflowFactory::twoState();

        // Replace the transition with one that requires amount > 1000
        \Fazzinipierluigi\LaravelRails\Models\Transition::where('from', $start->id)->delete();
        \Fazzinipierluigi\LaravelRails\Models\Transition::create([
            'from'              => $start->id,
            'to'                => $end->id,
            'sort'              => 0,
            'execute_condition' => ['>' => [['var' => 'variables.amount'], 1000]],
        ]);

        $order    = WorkflowFactory::createOrder(['amount' => 50]);
        $instance = $workflow->instantiate($order);
        $instance->variables = ['amount' => 50];
        $instance->save();

        $this->expectException(\Exception::class);
        $instance->progress();
    }

    public function test_transition_with_execute_condition_passes_when_condition_true(): void
    {
        ['workflow' => $workflow, 'start' => $start, 'end' => $end] = WorkflowFactory::twoState();

        \Fazzinipierluigi\LaravelRails\Models\Transition::where('from', $start->id)->delete();
        \Fazzinipierluigi\LaravelRails\Models\Transition::create([
            'from'              => $start->id,
            'to'                => $end->id,
            'sort'              => 0,
            'execute_condition' => ['>' => [['var' => 'variables.amount'], 100]],
        ]);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->variables = ['amount' => 500];
        $instance->save();

        $instance->progress();
        $instance->refresh();

        $this->assertEquals($end->id, $instance->state_id);
    }
}
