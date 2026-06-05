<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Feature;

use Fazzinipierluigi\LaravelRails\Jobs\AutoAdvanceWorkflow;
use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Models\State;
use Fazzinipierluigi\LaravelRails\Models\Transition;
use Fazzinipierluigi\LaravelRails\Models\Workflow;
use Fazzinipierluigi\LaravelRails\Tests\Support\WorkflowFactory;
use Fazzinipierluigi\LaravelRails\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

/**
 * Tests for the auto-advance system.
 *
 * States with no form on any outgoing transition are auto-advanced via a queued job
 * (AutoAdvanceWorkflow). The job re-checks conditions at execution time for idempotency.
 */
class AutoAdvanceTest extends TestCase
{
    // ── Dispatch conditions ────────────────────────────────────────────

    public function test_state_with_no_forms_dispatches_auto_advance_job_on_instantiate(): void
    {
        Queue::fake();

        ['workflow' => $workflow] = WorkflowFactory::twoState(); // no forms
        $order = WorkflowFactory::createOrder();

        $workflow->instantiate($order);

        Queue::assertDispatched(AutoAdvanceWorkflow::class);
    }

    public function test_state_with_form_on_transition_does_not_dispatch_job(): void
    {
        Queue::fake();

        ['workflow' => $workflow] = WorkflowFactory::withForm();
        $order = WorkflowFactory::createOrder();

        $workflow->instantiate($order);

        Queue::assertNotDispatched(AutoAdvanceWorkflow::class);
    }

    public function test_end_state_does_not_dispatch_auto_advance_job(): void
    {
        Queue::fake();

        ['workflow' => $workflow, 'end' => $end] = WorkflowFactory::twoState();
        $order = WorkflowFactory::createOrder();

        // Put instance directly at end state
        $i = new Instance();
        $i->instanceable_type = get_class($order);
        $i->instanceable_id   = $order->id;
        $i->workflow_id       = $workflow->id;
        $i->state_id          = $end->id;
        $i->save();

        $i->checkAutoAdvance();

        Queue::assertNotDispatched(AutoAdvanceWorkflow::class);
    }

    public function test_conditional_state_does_not_dispatch_auto_advance_job(): void
    {
        Queue::fake();

        ['workflow' => $workflow, 'conditional' => $conditional] = WorkflowFactory::withConditional();
        $order = WorkflowFactory::createOrder();

        $i = new Instance();
        $i->instanceable_type = get_class($order);
        $i->instanceable_id   = $order->id;
        $i->workflow_id       = $workflow->id;
        $i->state_id          = $conditional->id;
        $i->save();

        $i->checkAutoAdvance();

        Queue::assertNotDispatched(AutoAdvanceWorkflow::class);
    }

    public function test_state_without_outgoing_transitions_does_not_dispatch(): void
    {
        Queue::fake();

        $workflow = Workflow::create(['name' => 'Stuck', 'slug' => 'stuck']);
        $island   = State::create(['workflow_id' => $workflow->id, 'name' => 'Island', 'slug' => 'island', 'is_start' => true]);
        // No transitions from island

        $order = WorkflowFactory::createOrder();
        $i     = new Instance();
        $i->instanceable_type = get_class($order);
        $i->instanceable_id   = $order->id;
        $i->workflow_id       = $workflow->id;
        $i->state_id          = $island->id;
        $i->save();

        $i->checkAutoAdvance();

        Queue::assertNotDispatched(AutoAdvanceWorkflow::class);
    }

    // ── Job properties ─────────────────────────────────────────────────

    public function test_auto_advance_job_has_correct_retry_config(): void
    {
        $job = new AutoAdvanceWorkflow('dummy-id');

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(5, $job->backoff);
    }

    // ── Job handle() ───────────────────────────────────────────────────

    public function test_auto_advance_job_advances_instance_to_next_state(): void
    {
        Queue::fake();

        ['workflow' => $workflow, 'end' => $end] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        // Run the job manually (Queue::fake() prevents automatic execution)
        $job = new AutoAdvanceWorkflow($instance->id);
        $job->handle();

        $instance->refresh();
        $this->assertEquals($end->id, $instance->state_id);
    }

    public function test_auto_advance_job_skips_if_instance_not_found(): void
    {
        $job = new AutoAdvanceWorkflow('00000000-0000-0000-0000-000000000000');

        // Must not throw
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_auto_advance_job_skips_if_state_acquired_form_since_dispatch(): void
    {
        Queue::fake();

        ['workflow' => $workflow, 'start' => $start, 'end' => $end] = WorkflowFactory::twoState();

        // Add a form to the transition after dispatch to simulate race condition
        $transition = $start->transitions()->first();
        $transition->form_type = 'html';
        $transition->form_data = '<p>Form</p>';
        $transition->save();

        $order    = WorkflowFactory::createOrder();
        $instance = new Instance();
        $instance->instanceable_type = get_class($order);
        $instance->instanceable_id   = $order->id;
        $instance->workflow_id       = $workflow->id;
        $instance->state_id          = $start->id;
        $instance->save();

        $job = new AutoAdvanceWorkflow($instance->id);
        $job->handle(); // Should skip — transition now has a form

        $instance->refresh();
        $this->assertEquals($start->id, $instance->state_id); // unchanged
    }

    public function test_auto_advance_job_skips_if_instance_already_at_end(): void
    {
        Queue::fake();

        ['workflow' => $workflow, 'end' => $end] = WorkflowFactory::twoState();
        $order = WorkflowFactory::createOrder();

        $i = new Instance();
        $i->instanceable_type = get_class($order);
        $i->instanceable_id   = $order->id;
        $i->workflow_id       = $workflow->id;
        $i->state_id          = $end->id;
        $i->save();

        $job = new AutoAdvanceWorkflow($i->id);
        $job->handle(); // Should skip — already at end state

        $i->refresh();
        $this->assertEquals($end->id, $i->state_id); // unchanged
    }

    // ── Dispatch on progress ───────────────────────────────────────────

    public function test_progress_dispatches_auto_advance_for_formless_next_state(): void
    {
        // Three-state workflow: Start → Middle (no form) → End
        // After progressing from Start to Middle, a job should be dispatched
        Queue::fake();

        ['workflow' => $workflow] = WorkflowFactory::threeState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        Queue::assertDispatched(AutoAdvanceWorkflow::class); // once on instantiate (Start has no form)
        Queue::fake(); // reset

        $instance->progress(); // moves to Middle

        Queue::assertDispatched(AutoAdvanceWorkflow::class); // dispatched again for Middle
    }
}
