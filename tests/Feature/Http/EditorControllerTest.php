<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Feature\Http;

use Fazzinipierluigi\LaravelRails\Models\RegisteredAction;
use Fazzinipierluigi\LaravelRails\Models\State;
use Fazzinipierluigi\LaravelRails\Models\Transition;
use Fazzinipierluigi\LaravelRails\Models\Workflow;
use Fazzinipierluigi\LaravelRails\Tests\Support\Actions\AlwaysSucceedAction;
use Fazzinipierluigi\LaravelRails\Tests\Support\WorkflowFactory;
use Fazzinipierluigi\LaravelRails\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

/**
 * Tests for EditorController: GET/PUT workflow JSON and GET registered actions.
 *
 * These are the API endpoints used by the litegraph.js editor to load and save workflows.
 */
class EditorControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->withoutMiddleware(); // Disable CSRF and other middleware for API tests
    }

    // ── GET /laravel-rails/api/workflow/{slug} ─────────────────────────

    public function test_show_returns_workflow_with_states_and_transitions(): void
    {
        ['workflow' => $workflow, 'start' => $start, 'end' => $end] = WorkflowFactory::twoState('show-test');

        $response = $this->getJson('/laravel-rails/api/workflow/show-test');

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals($workflow->id,   $data['id']);
        $this->assertEquals('show-test',     $data['slug']);
        $this->assertCount(2, $data['states']);

        $stateIds = array_column($data['states'], 'id');
        $this->assertContains($start->id, $stateIds);
        $this->assertContains($end->id,   $stateIds);
    }

    public function test_show_returns_transitions_nested_under_states(): void
    {
        WorkflowFactory::twoState('nest-test');

        $response = $this->getJson('/laravel-rails/api/workflow/nest-test');
        $response->assertOk();

        $states = $response->json('states');
        $startState = collect($states)->first(fn($s) => $s['is_start']);

        $this->assertNotEmpty($startState['transitions']);
        $this->assertArrayHasKey('to_id',    $startState['transitions'][0]);
        $this->assertArrayHasKey('form_type',$startState['transitions'][0]);
    }

    public function test_show_returns_permission_fields(): void
    {
        ['start' => $start, 'transition' => $t] = WorkflowFactory::twoState('perm-show');
        $start->view_permissions = ['view-state'];
        $start->save();
        $t->advance_permissions = ['do-advance'];
        $t->save();

        $response = $this->getJson('/laravel-rails/api/workflow/perm-show');
        $response->assertOk();

        $states = $response->json('states');
        $s = collect($states)->first(fn($s) => $s['is_start']);

        $this->assertEquals(['view-state'], $s['view_permissions']);
        $this->assertEquals(['do-advance'], $s['transitions'][0]['advance_permissions']);
    }

    public function test_show_returns_404_for_unknown_workflow(): void
    {
        $this->getJson('/laravel-rails/api/workflow/nonexistent')
             ->assertStatus(404);
    }

    // ── PUT /laravel-rails/api/workflow/{slug} ─────────────────────────

    public function test_update_creates_states_and_transitions(): void
    {
        $workflow = Workflow::create(['name' => 'Edit Me', 'slug' => 'edit-me']);

        $payload = [
            'states' => [
                [
                    'name'             => 'Start',
                    'type'             => 'simple',
                    'is_start'         => true,
                    'is_end'           => false,
                    'x'                => 10,
                    'y'                => 20,
                    'on_enter_actions' => [],
                    'on_exit_actions'  => [],
                    'transitions'      => [
                        [
                            'to_id'               => 'End',
                            'actions'             => [],
                            'form_type'           => null,
                            'form_data'           => null,
                            'view_permissions'    => [],
                            'view_operator'       => 'OR',
                            'advance_permissions' => [],
                            'advance_operator'    => 'OR',
                        ],
                    ],
                ],
                [
                    'name'             => 'End',
                    'type'             => 'simple',
                    'is_start'         => false,
                    'is_end'           => true,
                    'x'                => 200,
                    'y'                => 20,
                    'on_enter_actions' => [],
                    'on_exit_actions'  => [],
                    'transitions'      => [],
                ],
            ],
        ];

        $this->putJson('/laravel-rails/api/workflow/edit-me', $payload)
             ->assertOk();

        $this->assertDatabaseHas('states', ['workflow_id' => $workflow->id, 'name' => 'Start', 'is_start' => true]);
        $this->assertDatabaseHas('states', ['workflow_id' => $workflow->id, 'name' => 'End',   'is_end'   => true]);

        $start = State::where('workflow_id', $workflow->id)->where('name', 'Start')->first();
        $this->assertNotNull($start->transitions()->first());
    }

    public function test_update_removes_orphaned_states(): void
    {
        ['workflow' => $workflow, 'middle' => $middle] = WorkflowFactory::threeState('orphan-test');
        $middleId = $middle->id;

        // Update workflow removing Middle state (only Start and End remain)
        $start = State::where('workflow_id', $workflow->id)->where('name', 'Start')->first();
        $end   = State::where('workflow_id', $workflow->id)->where('name', 'End')->first();

        $payload = [
            'states' => [
                [
                    'id'               => $start->id,
                    'name'             => 'Start',
                    'type'             => 'simple',
                    'is_start'         => true,
                    'is_end'           => false,
                    'x'                => 0, 'y' => 0,
                    'on_enter_actions' => [],
                    'on_exit_actions'  => [],
                    'transitions'      => [['to_id' => $end->id, 'actions' => []]],
                ],
                [
                    'id'               => $end->id,
                    'name'             => 'End',
                    'type'             => 'simple',
                    'is_start'         => false,
                    'is_end'           => true,
                    'x'                => 200, 'y' => 0,
                    'on_enter_actions' => [],
                    'on_exit_actions'  => [],
                    'transitions'      => [],
                ],
            ],
        ];

        $this->putJson('/laravel-rails/api/workflow/orphan-test', $payload)
             ->assertOk();

        $this->assertDatabaseMissing('states', ['id' => $middleId]);
    }

    public function test_update_persists_permission_fields(): void
    {
        $workflow = Workflow::create(['name' => 'Perms', 'slug' => 'perms-test']);

        $payload = [
            'states' => [
                [
                    'name'             => 'Restricted',
                    'type'             => 'simple',
                    'is_start'         => true,
                    'is_end'           => false,
                    'x'                => 0, 'y' => 0,
                    'view_permissions' => ['admins'],
                    'view_operator'    => 'AND',
                    'on_enter_actions' => [],
                    'on_exit_actions'  => [],
                    'transitions'      => [],
                ],
            ],
        ];

        $this->putJson('/laravel-rails/api/workflow/perms-test', $payload)
             ->assertOk();

        $state = State::where('workflow_id', $workflow->id)->first();
        $this->assertEquals(['admins'], $state->view_permissions);
        $this->assertEquals('AND',      $state->view_operator);
    }

    public function test_update_returns_404_for_unknown_workflow(): void
    {
        $this->putJson('/laravel-rails/api/workflow/ghost', ['states' => []])
             ->assertStatus(404);
    }

    public function test_update_validates_required_state_name(): void
    {
        Workflow::create(['name' => 'Validate', 'slug' => 'validate']);

        $this->putJson('/laravel-rails/api/workflow/validate', [
            'states' => [['name' => '']], // empty name should fail
        ])->assertStatus(422);
    }

    // ── GET /laravel-rails/api/registered-actions ──────────────────────

    public function test_registered_actions_returns_json_array(): void
    {
        RegisteredAction::create([
            'display_name' => AlwaysSucceedAction::$display_name,
            'action'       => AlwaysSucceedAction::class,
        ]);

        $response = $this->getJson('/laravel-rails/api/registered-actions');
        $response->assertOk();

        $data = $response->json();
        $this->assertIsArray($data);

        $found = collect($data)->first(fn($a) => $a['action'] === AlwaysSucceedAction::class);
        $this->assertNotNull($found);
        $this->assertEquals(AlwaysSucceedAction::$display_name, $found['display_name']);
    }

    public function test_registered_actions_returns_empty_array_when_none_registered(): void
    {
        $response = $this->getJson('/laravel-rails/api/registered-actions');
        $response->assertOk();
        $this->assertEquals([], $response->json());
    }
}
