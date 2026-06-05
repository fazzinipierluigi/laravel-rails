<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Feature;

use Fazzinipierluigi\LaravelRails\Classes\ExecutionLogger;
use Fazzinipierluigi\LaravelRails\Models\ExecutionLog;
use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Tests\Support\WorkflowFactory;
use Fazzinipierluigi\LaravelRails\Tests\TestCase;

/**
 * Verifies that ExecutionLogger writes the correct event records to execution_logs
 * and that logging failures never propagate to the calling workflow code.
 */
class ExecutionLoggerTest extends TestCase
{
    private Instance $instance;
    private $workflow;
    private $start;
    private $end;
    private $transition;

    protected function setUp(): void
    {
        parent::setUp();

        [
            'workflow'   => $this->workflow,
            'start'      => $this->start,
            'end'        => $this->end,
            'transition' => $this->transition,
        ] = WorkflowFactory::twoState();

        $order = WorkflowFactory::createOrder();

        $i = new Instance();
        $i->instanceable_type = get_class($order);
        $i->instanceable_id   = $order->id;
        $i->workflow_id       = $this->workflow->id;
        $i->state_id          = $this->start->id;
        $i->save();

        $this->instance = $i;
    }

    private function logger(string $triggeredBy = 'system'): ExecutionLogger
    {
        return new ExecutionLogger($this->instance, $triggeredBy);
    }

    // ── instanceStarted ────────────────────────────────────────────────

    public function test_instance_started_creates_log_record(): void
    {
        $this->logger()->instanceStarted();

        $log = ExecutionLog::where('instance_id', $this->instance->id)
            ->where('event', 'instance.started')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->workflow->id,   $log->data['workflow_id']);
        $this->assertEquals($this->workflow->name,  $log->data['workflow_name']);
        $this->assertEquals($this->workflow->slug,  $log->data['workflow_slug']);
    }

    public function test_instance_started_records_triggered_by(): void
    {
        $this->logger('user:42')->instanceStarted();

        $log = ExecutionLog::where('instance_id', $this->instance->id)
            ->where('event', 'instance.started')
            ->first();

        $this->assertEquals('user:42', $log->triggered_by);
    }

    // ── stateEntered ───────────────────────────────────────────────────

    public function test_state_entered_creates_log_record(): void
    {
        $this->logger()->stateEntered($this->start, 'start');

        $log = ExecutionLog::where('instance_id', $this->instance->id)
            ->where('event', 'state.entered')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('state',          $log->subject_type);
        $this->assertEquals($this->start->id, $log->subject_id);
        $this->assertEquals($this->start->id, $log->data['state_id']);
        $this->assertEquals('Start',          $log->data['state_name']);
        $this->assertEquals('start',          $log->data['mode']);
    }

    public function test_state_entered_on_end_state_also_writes_instance_completed(): void
    {
        $this->logger()->stateEntered($this->end, 'manual');

        $events = ExecutionLog::where('instance_id', $this->instance->id)
            ->pluck('event')
            ->all();

        $this->assertContains('state.entered',      $events);
        $this->assertContains('instance.completed', $events);
    }

    // ── stateExited ────────────────────────────────────────────────────

    public function test_state_exited_creates_log_record(): void
    {
        $logger = $this->logger();
        $logger->stateEntered($this->start, 'start');  // sets stateEnteredAt
        $logger->stateExited($this->start);

        $log = ExecutionLog::where('instance_id', $this->instance->id)
            ->where('event', 'state.exited')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->start->id, $log->data['state_id']);
        $this->assertArrayHasKey('time_in_state_ms', $log->data);
    }

    // ── transitionPerformed ────────────────────────────────────────────

    public function test_transition_performed_creates_log_record(): void
    {
        $this->logger()->transitionPerformed($this->transition);

        $log = ExecutionLog::where('instance_id', $this->instance->id)
            ->where('event', 'transition.performed')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('transition',          $log->subject_type);
        $this->assertEquals($this->transition->id, $log->subject_id);
        $this->assertEquals($this->start->id,      $log->data['from_state_id']);
        $this->assertEquals($this->end->id,        $log->data['to_state_id']);
    }

    // ── conditionEvaluated ─────────────────────────────────────────────

    public function test_condition_evaluated_creates_log_record(): void
    {
        $condition = ['==' => [['var' => 'variables.status'], 'approved']];
        $inputData = ['variables' => ['status' => 'approved']];

        $this->logger()->conditionEvaluated($this->transition, 'execute', $condition, $inputData, true);

        $log = ExecutionLog::where('instance_id', $this->instance->id)
            ->where('event', 'transition.condition_evaluated')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('execute',             $log->data['condition_type']);
        $this->assertTrue($log->data['result']);
        $this->assertEquals($this->transition->id, $log->data['transition_id']);
    }

    // ── actionExecuted ─────────────────────────────────────────────────

    public function test_action_executed_success_creates_log_record(): void
    {
        $this->logger()->actionExecuted(
            'SomeAction', 'pre', ['key' => 'val'], 'success', 12, null, null, 'transition', $this->transition->id
        );

        $log = ExecutionLog::where('instance_id', $this->instance->id)
            ->where('event', 'action.executed')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('success',            $log->data['result']);
        $this->assertEquals('pre',                $log->data['phase']);
        $this->assertEquals('transition',         $log->subject_type);
        $this->assertEquals($this->transition->id, $log->subject_id);
    }

    public function test_action_executed_failure_records_error_class(): void
    {
        $ex = new \RuntimeException('Something went wrong');

        $this->logger()->actionExecuted(
            'BadAction', 'on_enter', null, 'failure', 5, null, $ex, 'state', $this->start->id
        );

        $log = ExecutionLog::where('instance_id', $this->instance->id)
            ->where('event', 'action.executed')
            ->first();

        $this->assertEquals('failure',             $log->data['result']);
        $this->assertEquals('RuntimeException',    $log->data['error_class']);
        $this->assertEquals('Something went wrong',$log->data['error_message']);
    }

    public function test_action_executed_skipped_records_skipped_result(): void
    {
        $this->logger()->actionExecuted('NonExistentClass', 'post', null, 'skipped', 0);

        $log = ExecutionLog::where('instance_id', $this->instance->id)
            ->where('event', 'action.executed')
            ->first();

        $this->assertEquals('skipped', $log->data['result']);
    }

    // ── instanceCompleted ──────────────────────────────────────────────

    public function test_instance_completed_records_step_count(): void
    {
        $logger = $this->logger();
        $logger->stateEntered($this->start, 'start');
        $logger->stateEntered($this->end, 'manual');

        $log = ExecutionLog::where('instance_id', $this->instance->id)
            ->where('event', 'instance.completed')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->end->id, $log->data['final_state_id']);
        $this->assertEquals(2, $log->data['steps_taken']);
    }

    // ── permissionDenied ───────────────────────────────────────────────

    public function test_permission_denied_creates_log_record(): void
    {
        $user       = new class { public function getKey() { return 99; } };
        $logger     = $this->logger('user:99');

        $logger->permissionDenied('advance', $this->transition, $user, 'laravel-gate');

        $log = ExecutionLog::where('instance_id', $this->instance->id)
            ->where('event', 'permission.denied')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('advance',            $log->data['action']);
        $this->assertEquals($this->transition->id,$log->data['transition_id']);
        $this->assertEquals(99,                   $log->data['user_id']);
        $this->assertEquals('laravel-gate',        $log->data['driver']);
    }

    // ── executionError ─────────────────────────────────────────────────

    public function test_execution_error_creates_log_record(): void
    {
        $this->logger()->executionError(
            new \RuntimeException('Boom'),
            ['context' => 'auto-advance']
        );

        $log = ExecutionLog::where('instance_id', $this->instance->id)
            ->where('event', 'execution.error')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Boom',             $log->data['message']);
        $this->assertEquals('auto-advance',     $log->data['context']['context']);
    }

    // ── Fire-and-forget safety ─────────────────────────────────────────

    public function test_write_failure_does_not_propagate(): void
    {
        // Drop the table to force a DB error
        \Illuminate\Support\Facades\Schema::drop('execution_logs');

        // Must not throw
        $this->logger()->instanceStarted();

        $this->assertTrue(true); // Reached here = logger swallowed the error
    }
}
