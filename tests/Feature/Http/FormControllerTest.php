<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Feature\Http;

use Fazzinipierluigi\LaravelRails\Classes\Resolvers\VanillaGateResolver;
use Fazzinipierluigi\LaravelRails\Contracts\PermissionResolverInterface;
use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Tests\Support\Models\User;
use Fazzinipierluigi\LaravelRails\Tests\Support\WorkflowFactory;
use Fazzinipierluigi\LaravelRails\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;

/**
 * Tests for FormController::execute() — POST /laravel-rails/transition/{id}/execute.
 *
 * This endpoint validates the form submission, merges data into instance variables,
 * advances the transition, and redirects (or returns JSON).
 */
class FormControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->withoutMiddleware();
        $this->app->instance(PermissionResolverInterface::class, new VanillaGateResolver());
    }

    private function executeUrl(string $transitionId): string
    {
        return "/laravel-rails/transition/{$transitionId}/execute";
    }

    // ── 404 cases ──────────────────────────────────────────────────────

    public function test_returns_404_when_transition_not_found(): void
    {
        $this->post($this->executeUrl('00000000-0000-0000-0000-000000000000'), [
            'instance_id' => 'dummy',
        ])->assertStatus(404);
    }

    public function test_returns_404_when_instance_not_found(): void
    {
        ['transition' => $t] = WorkflowFactory::twoState();

        $this->post($this->executeUrl($t->id), [
            'instance_id' => '00000000-0000-0000-0000-000000000000',
        ])->assertStatus(404);
    }

    // ── Successful execution ───────────────────────────────────────────

    public function test_execute_advances_instance_to_next_state(): void
    {
        ['workflow' => $workflow, 'end' => $end, 'transition' => $t] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $this->post($this->executeUrl($t->id), ['instance_id' => $instance->id])
             ->assertRedirect();

        $instance->refresh();
        $this->assertEquals($end->id, $instance->state_id);
    }

    public function test_execute_merges_form_data_into_instance_variables(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $this->post($this->executeUrl($t->id), [
            'instance_id' => $instance->id,
            'notes'       => 'Approved by manager',
            'priority'    => 'high',
        ]);

        $instance->refresh();
        $this->assertEquals('Approved by manager', $instance->getVariable('notes'));
        $this->assertEquals('high',               $instance->getVariable('priority'));
    }

    public function test_execute_returns_json_when_json_accepted(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $response = $this->postJson($this->executeUrl($t->id), [
            'instance_id' => $instance->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['redirect']);
    }

    // ── Form validation ────────────────────────────────────────────────

    public function test_execute_validates_required_fields_from_json_schema(): void
    {
        ['workflow' => $workflow, 't1' => $t1] = WorkflowFactory::withForm();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        // 'approved' is required in the form schema
        $response = $this->postJson($this->executeUrl($t1->id), [
            'instance_id' => $instance->id,
            // 'approved' not provided
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['approved']);
    }

    public function test_execute_validates_email_type_field(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type = 'json';
        $t->form_data = json_encode([
            ['name' => 'contact', 'type' => 'email', 'label' => 'Email', 'required' => true],
        ]);
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $this->postJson($this->executeUrl($t->id), [
            'instance_id' => $instance->id,
            'contact'     => 'not-an-email',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['contact']);
    }

    public function test_execute_validates_number_type_field(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type = 'json';
        $t->form_data = json_encode([
            ['name' => 'quantity', 'type' => 'number', 'label' => 'Qty', 'required' => true],
        ]);
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $this->postJson($this->executeUrl($t->id), [
            'instance_id' => $instance->id,
            'quantity'    => 'not-a-number',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['quantity']);
    }

    // ── Permission enforcement ─────────────────────────────────────────

    public function test_execute_returns_403_when_user_lacks_advance_permission(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->advance_permissions = ['manager-role'];
        $t->save();

        $user = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);
        Gate::define('manager-role', fn($u) => false);
        $this->actingAs($user);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $this->post($this->executeUrl($t->id), ['instance_id' => $instance->id])
             ->assertStatus(403);
    }

    public function test_execute_succeeds_when_user_has_advance_permission(): void
    {
        ['workflow' => $workflow, 'transition' => $t, 'end' => $end] = WorkflowFactory::twoState();
        $t->advance_permissions = ['can-advance'];
        $t->save();

        $user = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        Gate::define('can-advance', fn($u) => true);
        $this->actingAs($user);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $this->post($this->executeUrl($t->id), ['instance_id' => $instance->id])
             ->assertRedirect();

        $instance->refresh();
        $this->assertEquals($end->id, $instance->state_id);
    }

    // ── Conditional chain ──────────────────────────────────────────────

    public function test_execute_follows_conditional_chain_after_form_submission(): void
    {
        // Build: Start -[form]-> Conditional -[always]-> End
        ['workflow' => $workflow, 'start' => $start] = WorkflowFactory::withForm();
        $formTransition = $start->transitions()->first();

        // Change destination of form transition to a conditional node
        $conditionalState = \Fazzinipierluigi\LaravelRails\Models\State::create([
            'workflow_id' => $workflow->id,
            'name'        => 'Cond',
            'slug'        => 'cond',
            'type'        => 'conditional',
        ]);
        $endState = \Fazzinipierluigi\LaravelRails\Models\State::where('workflow_id', $workflow->id)
            ->where('is_end', true)->first();

        // Redirect form transition to conditional
        $formTransition->to = $conditionalState->id;
        $formTransition->save();

        // Conditional goes to end
        \Fazzinipierluigi\LaravelRails\Models\Transition::create([
            'from' => $conditionalState->id,
            'to'   => $endState->id,
            'sort' => 0,
        ]);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $this->postJson($this->executeUrl($formTransition->id), [
            'instance_id' => $instance->id,
            'notes'       => 'ok',
            'approved'    => '1',
        ]);

        $instance->refresh();
        $this->assertEquals($endState->id, $instance->state_id);
    }
}
