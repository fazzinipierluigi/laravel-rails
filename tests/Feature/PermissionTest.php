<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Feature;

use Fazzinipierluigi\LaravelRails\Classes\Resolvers\VanillaGateResolver;
use Fazzinipierluigi\LaravelRails\Contracts\PermissionResolverInterface;
use Fazzinipierluigi\LaravelRails\Models\ExecutionLog;
use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Tests\Support\Models\User;
use Fazzinipierluigi\LaravelRails\Tests\Support\WorkflowFactory;
use Fazzinipierluigi\LaravelRails\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;

/**
 * Tests for the cascading permission system.
 *
 * Covers State::canView(), Transition::can(), Transition::can_show(),
 * Transition::canAdvance(), and VanillaGateResolver.
 */
class PermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Always use VanillaGateResolver in tests (deterministic, no external deps)
        $this->app->instance(PermissionResolverInterface::class, new VanillaGateResolver());
    }

    private function createUser(): User
    {
        return User::create(['name' => 'Test', 'email' => 'test@example.com']);
    }

    // ── VanillaGateResolver ────────────────────────────────────────────

    public function test_vanilla_gate_resolver_driver_name(): void
    {
        $resolver = new VanillaGateResolver();
        $this->assertEquals('laravel-gate', $resolver->getDriverName());
    }

    public function test_vanilla_gate_resolver_allows_when_no_permissions(): void
    {
        $resolver = new VanillaGateResolver();
        $user     = $this->createUser();

        $this->assertTrue($resolver->check($user, []));
    }

    public function test_vanilla_gate_resolver_or_logic_allows_if_any_permission_granted(): void
    {
        $user = $this->createUser();
        Gate::define('can-view', fn($u) => true);
        Gate::define('can-edit', fn($u) => false);

        $resolver = new VanillaGateResolver();

        $this->assertTrue($resolver->check($user, ['can-view', 'can-edit'], 'OR'));
    }

    public function test_vanilla_gate_resolver_or_logic_denies_if_no_permission_granted(): void
    {
        $user = $this->createUser();
        Gate::define('can-delete', fn($u) => false);

        $resolver = new VanillaGateResolver();

        $this->assertFalse($resolver->check($user, ['can-delete'], 'OR'));
    }

    public function test_vanilla_gate_resolver_and_logic_allows_if_all_permissions_granted(): void
    {
        $user = $this->createUser();
        Gate::define('perm-a', fn($u) => true);
        Gate::define('perm-b', fn($u) => true);

        $resolver = new VanillaGateResolver();

        $this->assertTrue($resolver->check($user, ['perm-a', 'perm-b'], 'AND'));
    }

    public function test_vanilla_gate_resolver_and_logic_denies_if_one_permission_missing(): void
    {
        $user = $this->createUser();
        Gate::define('perm-x', fn($u) => true);
        Gate::define('perm-y', fn($u) => false);

        $resolver = new VanillaGateResolver();

        $this->assertFalse($resolver->check($user, ['perm-x', 'perm-y'], 'AND'));
    }

    // ── State::canView ─────────────────────────────────────────────────

    public function test_state_without_permissions_is_always_viewable(): void
    {
        ['start' => $start] = WorkflowFactory::twoState();

        $this->assertTrue($start->canView());
        $this->assertTrue($start->canView(null));
    }

    public function test_state_with_permissions_requires_authenticated_user(): void
    {
        ['start' => $start] = WorkflowFactory::twoState();
        $start->view_permissions = ['some-ability'];
        $start->save();

        $this->assertFalse($start->canView(null));
    }

    public function test_state_with_permissions_allows_when_user_has_ability(): void
    {
        ['start' => $start] = WorkflowFactory::twoState();
        $start->view_permissions = ['view-state'];
        $start->save();

        $user = $this->createUser();
        Gate::define('view-state', fn($u) => true);

        $this->assertTrue($start->canView($user));
    }

    public function test_state_with_permissions_denies_when_user_lacks_ability(): void
    {
        ['start' => $start] = WorkflowFactory::twoState();
        $start->view_permissions = ['admin-only'];
        $start->save();

        $user = $this->createUser();
        Gate::define('admin-only', fn($u) => false);

        $this->assertFalse($start->canView($user));
    }

    public function test_state_view_or_operator_allows_when_any_match(): void
    {
        ['start' => $start] = WorkflowFactory::twoState();
        $start->view_permissions = ['role-a', 'role-b'];
        $start->view_operator    = 'OR';
        $start->save();

        $user = $this->createUser();
        Gate::define('role-a', fn($u) => false);
        Gate::define('role-b', fn($u) => true);

        $this->assertTrue($start->canView($user));
    }

    public function test_state_view_and_operator_requires_all(): void
    {
        ['start' => $start] = WorkflowFactory::twoState();
        $start->view_permissions = ['perm-1', 'perm-2'];
        $start->view_operator    = 'AND';
        $start->save();

        $user = $this->createUser();
        Gate::define('perm-1', fn($u) => true);
        Gate::define('perm-2', fn($u) => false);

        $this->assertFalse($start->canView($user));
    }

    // ── Transition::can_show ───────────────────────────────────────────

    public function test_transition_can_show_true_when_no_permissions(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $this->assertTrue($transition->can_show($instance));
    }

    public function test_transition_can_show_false_when_no_user_but_permissions_set(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();
        $transition->view_permissions = ['some-perm'];
        $transition->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        // No authenticated user
        $this->assertFalse($transition->can_show($instance));
    }

    public function test_transition_can_show_true_when_user_has_view_permission(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();
        $transition->view_permissions = ['view-transition'];
        $transition->save();

        $user = $this->createUser();
        Gate::define('view-transition', fn($u) => true);
        $this->actingAs($user);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $this->assertTrue($transition->can_show($instance));
    }

    public function test_transition_can_show_logs_permission_denied(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();
        $transition->view_permissions = ['secret-perm'];
        $transition->save();

        $user = $this->createUser();
        Gate::define('secret-perm', fn($u) => false);
        $this->actingAs($user);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $transition->can_show($instance);

        $denied = ExecutionLog::where('instance_id', $instance->id)
            ->where('event', 'permission.denied')
            ->first();

        $this->assertNotNull($denied);
        $this->assertEquals('view', $denied->data['action']);
    }

    // ── Transition::canAdvance ─────────────────────────────────────────

    public function test_can_advance_true_when_no_permissions(): void
    {
        ['transition' => $transition] = WorkflowFactory::twoState();

        $this->assertTrue($transition->canAdvance(null));
        $this->assertTrue($transition->canAdvance($this->createUser()));
    }

    public function test_can_advance_true_for_null_user_regardless_of_permissions(): void
    {
        ['transition' => $transition] = WorkflowFactory::twoState();
        $transition->advance_permissions = ['admin'];
        $transition->save();

        // null user = system/auto bypass
        $this->assertTrue($transition->canAdvance(null));
    }

    public function test_can_advance_true_when_user_has_permission(): void
    {
        ['transition' => $transition] = WorkflowFactory::twoState();
        $transition->advance_permissions = ['do-advance'];
        $transition->save();

        $user = $this->createUser();
        Gate::define('do-advance', fn($u) => true);

        $this->assertTrue($transition->canAdvance($user));
    }

    public function test_can_advance_false_when_user_lacks_permission(): void
    {
        ['transition' => $transition] = WorkflowFactory::twoState();
        $transition->advance_permissions = ['manager-only'];
        $transition->save();

        $user = $this->createUser();
        Gate::define('manager-only', fn($u) => false);

        $this->assertFalse($transition->canAdvance($user));
    }

    // ── Transition::can (advance + state check) ────────────────────────

    public function test_transition_can_returns_false_for_wrong_instance_state(): void
    {
        ['workflow' => $workflow, 'middle' => $middle, 'transition' => $t1] = WorkflowFactory::threeState();
        // t1 goes from Start to Middle; an instance at Middle should return false for t1

        $order = WorkflowFactory::createOrder();

        $i = new Instance();
        $i->instanceable_type = get_class($order);
        $i->instanceable_id   = $order->id;
        $i->workflow_id       = $workflow->id;
        $i->state_id          = $middle->id;
        $i->save();

        $this->assertFalse($t1->can($i));
    }

    public function test_transition_can_logs_permission_denied_when_advance_blocked(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();
        $transition->advance_permissions = ['blocked-perm'];
        $transition->save();

        $user = $this->createUser();
        Gate::define('blocked-perm', fn($u) => false);
        $this->actingAs($user);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $transition->can($instance);

        $denied = ExecutionLog::where('instance_id', $instance->id)
            ->where('event', 'permission.denied')
            ->first();

        $this->assertNotNull($denied);
        $this->assertEquals('advance', $denied->data['action']);
    }

    public function test_system_auto_bypasses_advance_permission_check(): void
    {
        ['workflow' => $workflow, 'transition' => $transition] = WorkflowFactory::twoState();
        $transition->advance_permissions = ['requires-human'];
        $transition->save();

        // No authenticated user (system context) — Gate would deny, but bypass applies
        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        // With no auth user, can() should bypass the permission check
        $this->assertTrue($transition->can($instance));
    }
}
