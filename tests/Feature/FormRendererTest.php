<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Feature;

use Fazzinipierluigi\LaravelRails\Classes\FormRenderer;
use Fazzinipierluigi\LaravelRails\Classes\Resolvers\VanillaGateResolver;
use Fazzinipierluigi\LaravelRails\Contracts\PermissionResolverInterface;
use Fazzinipierluigi\LaravelRails\Tests\Support\Models\User;
use Fazzinipierluigi\LaravelRails\Tests\Support\WorkflowFactory;
use Fazzinipierluigi\LaravelRails\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;

/**
 * Tests for FormRenderer::render().
 *
 * FormRenderer converts JSON schema or raw HTML into an HTML <form> element
 * and applies view permission checks.
 */
class FormRendererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Reset static style-emission state between tests
        $ref  = new \ReflectionClass(FormRenderer::class);
        $prop = $ref->getProperty('stylesEmitted');
        $prop->setAccessible(true);
        $prop->setValue(null, false);

        $this->app->instance(PermissionResolverInterface::class, new VanillaGateResolver());
    }

    // ── No form ────────────────────────────────────────────────────────

    public function test_returns_empty_string_when_transition_has_no_form(): void
    {
        ['transition' => $transition] = WorkflowFactory::twoState();

        // Transition has no form_type / form_data by default
        $result = FormRenderer::render($transition->id, 'dummy-instance-id');

        $this->assertSame('', $result);
    }

    public function test_returns_empty_string_for_unknown_transition(): void
    {
        $result = FormRenderer::render('00000000-0000-0000-0000-000000000000', 'dummy');
        $this->assertSame('', $result);
    }

    // ── HTML form type ─────────────────────────────────────────────────

    public function test_renders_raw_html_form_type(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type = 'html';
        $t->form_data = '<input type="text" name="raw_field">';
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $html = FormRenderer::render($t->id, $instance->id);

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('raw_field', $html);
        $this->assertStringContainsString($instance->id, $html);
    }

    // ── JSON schema rendering ──────────────────────────────────────────

    public function test_renders_text_input_from_json_schema(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type = 'json';
        $t->form_data = json_encode([
            ['name' => 'full_name', 'type' => 'text', 'label' => 'Full Name', 'required' => true],
        ]);
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $html = FormRenderer::render($t->id, $instance->id);

        $this->assertStringContainsString('type="text"',   $html);
        $this->assertStringContainsString('name="full_name"', $html);
        $this->assertStringContainsString('Full Name',     $html);
        $this->assertStringContainsString('required',      $html);
    }

    public function test_renders_select_with_options_from_json_schema(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type = 'json';
        $t->form_data = json_encode([
            [
                'name'    => 'priority',
                'type'    => 'select',
                'label'   => 'Priority',
                'options' => [
                    ['value' => 'low',  'label' => 'Low'],
                    ['value' => 'high', 'label' => 'High'],
                ],
            ],
        ]);
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $html = FormRenderer::render($t->id, $instance->id);

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('value="low"',  $html);
        $this->assertStringContainsString('value="high"', $html);
        $this->assertStringContainsString('Low',          $html);
        $this->assertStringContainsString('High',         $html);
    }

    public function test_renders_textarea_from_json_schema(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type = 'json';
        $t->form_data = json_encode([
            ['name' => 'notes', 'type' => 'textarea', 'label' => 'Notes'],
        ]);
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $html = FormRenderer::render($t->id, $instance->id);

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('name="notes"', $html);
    }

    public function test_renders_checkbox_from_json_schema(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type = 'json';
        $t->form_data = json_encode([
            ['name' => 'agree', 'type' => 'checkbox', 'label' => 'Agree?'],
        ]);
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $html = FormRenderer::render($t->id, $instance->id);

        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('name="agree"',    $html);
    }

    public function test_renders_radio_buttons_from_json_schema(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type = 'json';
        $t->form_data = json_encode([
            [
                'name'    => 'choice',
                'type'    => 'radio',
                'label'   => 'Choice',
                'options' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                    ['value' => 'no',  'label' => 'No'],
                ],
            ],
        ]);
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $html = FormRenderer::render($t->id, $instance->id);

        $this->assertStringContainsString('type="radio"', $html);
        $this->assertStringContainsString('value="yes"',  $html);
        $this->assertStringContainsString('value="no"',   $html);
    }

    public function test_hidden_input_renders_without_label(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type = 'json';
        $t->form_data = json_encode([
            ['name' => 'workflow_step', 'type' => 'hidden', 'value' => 'step_1'],
        ]);
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $html = FormRenderer::render($t->id, $instance->id);

        $this->assertStringContainsString('type="hidden"',    $html);
        $this->assertStringContainsString('value="step_1"',   $html);
        $this->assertStringNotContainsString('<label', $html);
    }

    // ── CSS deduplication ──────────────────────────────────────────────

    public function test_styles_emitted_only_once_across_multiple_renders(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type = 'html';
        $t->form_data = '<input type="text" name="field">';
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $html1 = FormRenderer::render($t->id, $instance->id);
        $html2 = FormRenderer::render($t->id, $instance->id);

        // <style> appears once across both renders
        $totalStyleTags = substr_count($html1 . $html2, '<style>');
        $this->assertEquals(1, $totalStyleTags);
    }

    // ── Permission-based visibility ────────────────────────────────────

    public function test_returns_empty_string_when_no_user_but_view_permissions_set(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type        = 'html';
        $t->form_data        = '<input name="x">';
        $t->view_permissions = ['members-only'];
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        // No authenticated user
        $result = FormRenderer::render($t->id, $instance->id);

        $this->assertSame('', $result);
    }

    public function test_returns_empty_string_when_user_lacks_view_permission(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type        = 'html';
        $t->form_data        = '<input name="x">';
        $t->view_permissions = ['restricted'];
        $t->save();

        $user = User::create(['name' => 'Joe', 'email' => 'joe@example.com']);
        Gate::define('restricted', fn($u) => false);
        $this->actingAs($user);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $result = FormRenderer::render($t->id, $instance->id);

        $this->assertSame('', $result);
    }

    public function test_renders_form_when_user_has_view_permission(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type        = 'html';
        $t->form_data        = '<input name="allowed_field">';
        $t->view_permissions = ['can-view'];
        $t->save();

        $user = User::create(['name' => 'Joe', 'email' => 'joe@example.com']);
        Gate::define('can-view', fn($u) => true);
        $this->actingAs($user);

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $html = FormRenderer::render($t->id, $instance->id);

        $this->assertStringContainsString('allowed_field', $html);
    }

    // ── Form structure ─────────────────────────────────────────────────

    public function test_rendered_form_contains_csrf_token_field(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type = 'html';
        $t->form_data = '<input name="x">';
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $html = FormRenderer::render($t->id, $instance->id);

        $this->assertStringContainsString('_token', $html);
    }

    public function test_rendered_form_contains_instance_id_hidden_field(): void
    {
        ['workflow' => $workflow, 'transition' => $t] = WorkflowFactory::twoState();
        $t->form_type = 'html';
        $t->form_data = '<input name="x">';
        $t->save();

        $order    = WorkflowFactory::createOrder();
        $instance = $workflow->instantiate($order);

        $html = FormRenderer::render($t->id, $instance->id);

        $this->assertStringContainsString('name="instance_id"', $html);
        $this->assertStringContainsString($instance->id,        $html);
    }
}
