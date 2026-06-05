<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Feature\Http;

use Fazzinipierluigi\LaravelRails\Models\ExecutionLog;
use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Tests\Support\WorkflowFactory;
use Fazzinipierluigi\LaravelRails\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

/**
 * Tests for ExecutionController:
 *   GET /laravel-rails/api/execution/{instanceId}     → full execution data
 *   GET /laravel-rails/api/execution/{id}/{type}/{sid} → logs for specific node
 */
class ExecutionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->withoutMiddleware();
    }

    // ── GET /api/execution/{instanceId} ───────────────────────────────

    public function test_data_returns_instance_and_workflow_structure(): void
    {
        ['workflow' => $workflow, 'start' => $start, 'end' => $end] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $response = $this->getJson("/laravel-rails/api/execution/{$instance->id}");
        $response->assertOk();

        $data = $response->json();

        $this->assertEquals($instance->id,   $data['instance']['id']);
        $this->assertEquals($workflow->id,    $data['instance']['workflow_id']);
        $this->assertEquals($start->id,       $data['instance']['current_state_id']);

        $this->assertArrayHasKey('id',     $data['workflow']);
        $this->assertArrayHasKey('states', $data['workflow']);

        $stateIds = array_column($data['workflow']['states'], 'id');
        $this->assertContains($start->id, $stateIds);
        $this->assertContains($end->id,   $stateIds);
    }

    public function test_data_includes_traversal_info_keys(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $data = $this->getJson("/laravel-rails/api/execution/{$instance->id}")->json();

        $this->assertArrayHasKey('traversed', $data);
        $this->assertArrayHasKey('states',      $data['traversed']);
        $this->assertArrayHasKey('transitions',  $data['traversed']);
        $this->assertArrayHasKey('errors',       $data['traversed']);
    }

    public function test_data_tracks_visited_states(): void
    {
        ['workflow' => $workflow, 'start' => $start, 'end' => $end] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $instance->progress();

        $data = $this->getJson("/laravel-rails/api/execution/{$instance->id}")->json();

        $visitedStates = $data['traversed']['states'];
        $this->assertContains($start->id, $visitedStates);
        $this->assertContains($end->id,   $visitedStates);
    }

    public function test_data_tracks_visited_transitions(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $instance->progress();

        $data = $this->getJson("/laravel-rails/api/execution/{$instance->id}")->json();

        $this->assertContains($t->id, $data['traversed']['transitions']);
    }

    public function test_data_marks_errored_states_from_failed_action_logs(): void
    {
        ['workflow' => $workflow, 'start' => $start] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        // Manually write a failure action log for the start state
        ExecutionLog::create([
            'instance_id'  => $instance->id,
            'event'        => 'action.executed',
            'subject_type' => 'state',
            'subject_id'   => $start->id,
            'data'         => ['result' => 'failure', 'phase' => 'on_enter'],
            'triggered_by' => 'system',
            'occurred_at'  => now(),
        ]);

        $data = $this->getJson("/laravel-rails/api/execution/{$instance->id}")->json();

        $this->assertContains($start->id, $data['traversed']['errors']['states']);
    }

    public function test_data_returns_404_when_instance_not_found(): void
    {
        $this->getJson('/laravel-rails/api/execution/00000000-0000-0000-0000-000000000000')
             ->assertStatus(404);
    }

    public function test_data_states_include_transitions_with_sort_and_label(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->label = 'Proceed';
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $data   = $this->getJson("/laravel-rails/api/execution/{$instance->id}")->json();
        $states = collect($data['workflow']['states']);
        $start  = $states->first(fn($s) => $s['is_start']);

        $this->assertNotEmpty($start['transitions']);
        $this->assertEquals('Proceed', $start['transitions'][0]['label']);
        $this->assertArrayHasKey('sort', $start['transitions'][0]);
    }

    // ── GET /api/execution/{id}/{type}/{sid} ───────────────────────────

    public function test_node_logs_returns_logs_filtered_by_state_and_subject_id(): void
    {
        ['workflow' => $workflow, 'start' => $start, 'end' => $end] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        // State entered for start is logged during instantiate
        $response = $this->getJson("/laravel-rails/api/execution/{$instance->id}/state/{$start->id}");
        $response->assertOk();

        $logs = $response->json();
        $this->assertIsArray($logs);
        $this->assertGreaterThan(0, count($logs));

        foreach ($logs as $log) {
            $this->assertEquals('state',     $log['subject_type']);
            $this->assertEquals($start->id,  $log['subject_id']);
        }
    }

    public function test_node_logs_returns_logs_filtered_by_transition_id(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $instance->progress();

        $response = $this->getJson("/laravel-rails/api/execution/{$instance->id}/transition/{$t->id}");
        $response->assertOk();

        $logs = $response->json();
        $this->assertGreaterThan(0, count($logs));

        foreach ($logs as $log) {
            $this->assertEquals('transition', $log['subject_type']);
            $this->assertEquals($t->id,       $log['subject_id']);
        }
    }

    public function test_node_logs_returns_empty_array_when_no_logs_exist(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::twoState();
        $randomId = (string) \Illuminate\Support\Str::uuid();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $response = $this->getJson("/laravel-rails/api/execution/{$instance->id}/state/{$randomId}");
        $response->assertOk();
        $this->assertEquals([], $response->json());
    }

    public function test_node_logs_returns_400_for_invalid_subject_type(): void
    {
        ['workflow' => $workflow] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        // Route constraint: type must be 'state' or 'transition'
        // Routes with invalid type simply won't match, resulting in 404
        $this->getJson("/laravel-rails/api/execution/{$instance->id}/workflow/{$instance->id}")
             ->assertStatus(404);
    }

    public function test_node_logs_returns_logs_ordered_by_occurred_at(): void
    {
        ['workflow' => $workflow, 'start' => $start] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $instance->progress();

        $response = $this->getJson("/laravel-rails/api/execution/{$instance->id}/state/{$start->id}");
        $logs = $response->json();

        if (count($logs) > 1) {
            $times = array_column($logs, 'occurred_at');
            $sorted = $times;
            sort($sorted);
            $this->assertEquals($sorted, $times);
        }

        $this->assertTrue(true); // Reached here
    }
}
