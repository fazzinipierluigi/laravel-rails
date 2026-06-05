<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Unit;

use Fazzinipierluigi\LaravelRails\Classes\VariableResolver;
use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Tests\Support\WorkflowFactory;
use Fazzinipierluigi\LaravelRails\Tests\TestCase;

/**
 * Tests for VariableResolver::resolve() and ::buildContext().
 *
 * The resolver replaces {{token}} placeholders in strings using:
 * - Special tokens: now, today, instance.id, instance.workflow_id, instance.state_id
 * - Explicit namespaces: variables.key, entity.field
 * - Shorthand: {{key}} → variables first, then entity
 */
class VariableResolverTest extends TestCase
{
    private Instance $instance;
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        ['workflow' => $workflow, 'start' => $start] = WorkflowFactory::twoState();
        $this->order = WorkflowFactory::createOrder(['amount' => 250.00, 'customer_name' => 'Alice']);

        $instance = new Instance();
        $instance->instanceable_type = get_class($this->order);
        $instance->instanceable_id   = $this->order->id;
        $instance->workflow_id       = $workflow->id;
        $instance->state_id          = $start->id;
        $instance->variables         = ['price' => 99.50, 'tag' => 'urgent', 'nested.key' => 'deep'];
        $instance->save();

        $this->instance = $instance;
    }

    // ── Special tokens ─────────────────────────────────────────────────

    public function test_now_token_returns_datetime_string(): void
    {
        $result = VariableResolver::resolve('{{now}}', $this->instance);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
    }

    public function test_today_token_returns_date_string(): void
    {
        $result = VariableResolver::resolve('{{today}}', $this->instance);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);
    }

    public function test_instance_id_token(): void
    {
        $result = VariableResolver::resolve('{{instance.id}}', $this->instance);
        $this->assertEquals($this->instance->id, $result);
    }

    public function test_instance_workflow_id_token(): void
    {
        $result = VariableResolver::resolve('{{instance.workflow_id}}', $this->instance);
        $this->assertEquals($this->instance->workflow_id, $result);
    }

    public function test_instance_state_id_token(): void
    {
        $result = VariableResolver::resolve('{{instance.state_id}}', $this->instance);
        $this->assertEquals($this->instance->state_id, $result);
    }

    // ── variables.* namespace ──────────────────────────────────────────

    public function test_variables_key_resolves_from_instance_variables(): void
    {
        $result = VariableResolver::resolve('{{variables.price}}', $this->instance);
        $this->assertEquals('99.5', $result);
    }

    public function test_variables_key_returns_empty_for_missing_key(): void
    {
        $result = VariableResolver::resolve('{{variables.nonexistent}}', $this->instance);
        $this->assertEquals('', $result);
    }

    // ── entity.* namespace ─────────────────────────────────────────────

    public function test_entity_field_resolves_from_entity(): void
    {
        $result = VariableResolver::resolve('{{entity.customer_name}}', $this->instance, $this->order);
        $this->assertEquals('Alice', $result);
    }

    public function test_entity_field_resolves_numeric_value(): void
    {
        $result = VariableResolver::resolve('{{entity.amount}}', $this->instance, $this->order);
        $this->assertEquals('250', $result);
    }

    public function test_entity_field_returns_empty_when_entity_is_null(): void
    {
        $result = VariableResolver::resolve('{{entity.customer_name}}', $this->instance, null);
        $this->assertEquals('', $result);
    }

    // ── Shorthand ──────────────────────────────────────────────────────

    public function test_shorthand_resolves_variables_first(): void
    {
        // 'price' is in variables AND could match entity field too; variables wins
        $result = VariableResolver::resolve('{{price}}', $this->instance, $this->order);
        $this->assertEquals('99.5', $result);
    }

    public function test_shorthand_falls_back_to_entity_when_not_in_variables(): void
    {
        $result = VariableResolver::resolve('{{customer_name}}', $this->instance, $this->order);
        $this->assertEquals('Alice', $result);
    }

    public function test_shorthand_returns_empty_when_not_found_anywhere(): void
    {
        $result = VariableResolver::resolve('{{totally_unknown}}', $this->instance, $this->order);
        $this->assertEquals('', $result);
    }

    // ── Multiple tokens in one string ──────────────────────────────────

    public function test_multiple_tokens_in_template(): void
    {
        $template = 'Hello {{variables.tag}}, your price is {{variables.price}}';
        $result   = VariableResolver::resolve($template, $this->instance);
        $this->assertEquals('Hello urgent, your price is 99.5', $result);
    }

    public function test_non_token_text_is_preserved(): void
    {
        $result = VariableResolver::resolve('Static text', $this->instance);
        $this->assertEquals('Static text', $result);
    }

    public function test_template_with_spaces_around_token_name(): void
    {
        $result = VariableResolver::resolve('{{ variables.price }}', $this->instance);
        $this->assertEquals('99.5', $result);
    }

    // ── buildContext ───────────────────────────────────────────────────

    public function test_build_context_returns_expected_keys(): void
    {
        $context = VariableResolver::buildContext($this->instance, $this->order);

        $this->assertArrayHasKey('variables', $context);
        $this->assertArrayHasKey('entity', $context);
        $this->assertArrayHasKey('request', $context);
        $this->assertArrayHasKey('instance', $context);
    }

    public function test_build_context_variables_match_instance_variables(): void
    {
        $context = VariableResolver::buildContext($this->instance);
        $this->assertEquals(99.50, $context['variables']['price']);
        $this->assertEquals('urgent', $context['variables']['tag']);
    }

    public function test_build_context_instance_ids_are_correct(): void
    {
        $context = VariableResolver::buildContext($this->instance);
        $this->assertEquals($this->instance->id,          $context['instance']['id']);
        $this->assertEquals($this->instance->workflow_id, $context['instance']['workflow_id']);
        $this->assertEquals($this->instance->state_id,    $context['instance']['state_id']);
    }

    public function test_build_context_entity_empty_when_null(): void
    {
        $context = VariableResolver::buildContext($this->instance, null);
        $this->assertEquals([], $context['entity']);
    }
}
