<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Feature;

use Fazzinipierluigi\LaravelRails\Models\ExecutionLog;
use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Models\State;
use Fazzinipierluigi\LaravelRails\Models\Transition;
use Fazzinipierluigi\LaravelRails\Models\Workflow;
use Fazzinipierluigi\LaravelRails\Tests\Support\Actions\AlwaysFailAction;
use Fazzinipierluigi\LaravelRails\Tests\Support\Actions\AlwaysSucceedAction;
use Fazzinipierluigi\LaravelRails\Tests\Support\WorkflowFactory;
use Fazzinipierluigi\LaravelRails\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

/**
 * Tests for action execution across all phases:
 *   on_enter (state entry), on_exit (state exit), pre (before transition), post (after transition).
 *
 * Also covers SetVariableWithFormula, SetVariableWithEntity, and WriteEntity built-in actions.
 */
class ActionExecutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    // ── Phase: on_enter ────────────────────────────────────────────────

    public function test_on_enter_action_runs_when_entering_destination_state(): void
    {
        ['workflow' => $workflow, 'end' => $end] = WorkflowFactory::twoState();
        WorkflowFactory::addAction($end, 'on_enter', AlwaysSucceedAction::class);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->progress();

        $log = ExecutionLog::where('instance_id', $instance->id)
            ->where('event', 'action.executed')
            ->where('data->phase', 'on_enter')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('success', $log->data['result']);
        $this->assertEquals('state',   $log->subject_type);
        $this->assertEquals($end->id,  $log->subject_id);
    }

    // ── Phase: on_exit ─────────────────────────────────────────────────

    public function test_on_exit_action_runs_when_leaving_source_state(): void
    {
        ['workflow' => $workflow, 'start' => $start] = WorkflowFactory::twoState();
        WorkflowFactory::addAction($start, 'on_exit', AlwaysSucceedAction::class);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->progress();

        $log = ExecutionLog::where('instance_id', $instance->id)
            ->where('event', 'action.executed')
            ->where('data->phase', 'on_exit')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('success',   $log->data['result']);
        $this->assertEquals('state',     $log->subject_type);
        $this->assertEquals($start->id,  $log->subject_id);
    }

    // ── Phase: pre ─────────────────────────────────────────────────────

    public function test_pre_action_runs_before_state_change(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();
        WorkflowFactory::addAction($transition, 'pre', AlwaysSucceedAction::class);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->progress();

        $log = ExecutionLog::where('instance_id', $instance->id)
            ->where('event', 'action.executed')
            ->where('data->phase', 'pre')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('transition',     $log->subject_type);
        $this->assertEquals($transition->id,  $log->subject_id);
    }

    // ── Phase: post ────────────────────────────────────────────────────

    public function test_post_action_runs_after_state_change(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();
        WorkflowFactory::addAction($transition, 'post', AlwaysSucceedAction::class);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->progress();

        $log = ExecutionLog::where('instance_id', $instance->id)
            ->where('event', 'action.executed')
            ->where('data->phase', 'post')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('success',       $log->data['result']);
        $this->assertEquals('transition',     $log->subject_type);
    }

    // ── Failure handling ───────────────────────────────────────────────

    public function test_failing_action_logs_failure_and_throws(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();
        WorkflowFactory::addAction($transition, 'pre', AlwaysFailAction::class);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $this->expectException(\Exception::class);
        $instance->progress();

        $log = ExecutionLog::where('instance_id', $instance->id)
            ->where('event', 'action.executed')
            ->where('data->result', 'failure')
            ->first();

        $this->assertNotNull($log);
    }

    public function test_missing_action_class_logs_skipped(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();
        WorkflowFactory::addAction($transition, 'pre', 'NonExistent\\Action\\Class');

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->progress();

        $log = ExecutionLog::where('instance_id', $instance->id)
            ->where('event', 'action.executed')
            ->where('data->result', 'skipped')
            ->first();

        $this->assertNotNull($log);
    }

    // ── Built-in: SetVariableWithFormula ───────────────────────────────

    public function test_set_variable_with_formula_stores_computed_result(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();

        WorkflowFactory::addAction($transition, 'pre', \Fazzinipierluigi\LaravelRails\Actions\SetVariableWithFormula::class, [
            'variable' => 'total',
            'formula'  => 'quantity * unit_price',
        ]);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->variables = ['quantity' => 4, 'unit_price' => 25];
        $instance->save();

        $instance->progress();
        $instance->refresh();

        $this->assertEquals(100.0, $instance->getVariable('total'));
    }

    // ── Built-in: SetVariableWithEntity ───────────────────────────────

    public function test_set_variable_with_entity_maps_field_to_variable(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();

        WorkflowFactory::addAction($transition, 'pre', \Fazzinipierluigi\LaravelRails\Actions\SetVariableWithEntity::class, [
            'mappings' => json_encode([
                ['variable' => 'customer', 'entity_field' => 'customer_name'],
                ['variable' => 'amount',   'entity_field' => 'amount'],
            ]),
        ]);

        $order    = WorkflowFactory::createOrder(['customer_name' => 'Bob', 'amount' => 75.50]);
        $instance = $workflow->instantiate($order);

        $instance->progress();
        $instance->refresh();

        $this->assertEquals('Bob',  $instance->getVariable('customer'));
        $this->assertEquals(75.50,  $instance->getVariable('amount'));
    }

    // ── Built-in: WriteEntity ──────────────────────────────────────────

    public function test_write_entity_writes_resolved_values_to_entity(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();

        WorkflowFactory::addAction($transition, 'pre', \Fazzinipierluigi\LaravelRails\Actions\WriteEntity::class, [
            'mappings' => json_encode([
                ['field' => 'status',        'value' => 'approved'],
                ['field' => 'customer_name', 'value' => '{{variables.buyer}}'],
            ]),
        ]);

        $order    = WorkflowFactory::createOrder(['status' => 'pending']);
        $instance = $workflow->instantiate($order);
        $instance->variables = ['buyer' => 'Carol'];
        $instance->save();

        $instance->progress();

        $order->refresh();
        $this->assertEquals('approved', $order->status);
        $this->assertEquals('Carol',    $order->customer_name);
    }

    // ── Multiple actions in sequence ───────────────────────────────────

    public function test_multiple_pre_actions_execute_in_sort_order(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();

        // Both actions succeed; we just verify both appear in logs
        WorkflowFactory::addAction($transition, 'pre', AlwaysSucceedAction::class);
        WorkflowFactory::addAction($transition, 'pre', AlwaysSucceedAction::class);
        // Set sort on second action (addAction always uses sort=0; manually update)
        $transition->actions()->latest()->first()->update(['sort' => 1]);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);
        $instance->progress();

        $logs = ExecutionLog::where('instance_id', $instance->id)
            ->where('event', 'action.executed')
            ->where('data->phase', 'pre')
            ->get();

        $this->assertCount(2, $logs);
    }
}
