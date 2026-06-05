<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Unit;

use Fazzinipierluigi\LaravelRails\Classes\Expression\Parser;
use Fazzinipierluigi\LaravelRails\Tests\TestCase;

/**
 * Tests for the math expression parser used by SetVariableWithFormula.
 *
 * The parser supports: + - * / ^ % operators, parentheses, string literals,
 * numeric literals, variables resolved via onVariable callback, and named functions.
 */
class ExpressionParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new Parser();
    }

    // ── Arithmetic ─────────────────────────────────────────────────────

    public function test_simple_addition(): void
    {
        $this->assertEquals(5.0, $this->parser->execute('2 + 3'));
    }

    public function test_simple_subtraction(): void
    {
        $this->assertEquals(1.0, $this->parser->execute('3 - 2'));
    }

    public function test_multiplication(): void
    {
        $this->assertEquals(12.0, $this->parser->execute('3 * 4'));
    }

    public function test_division(): void
    {
        $this->assertEquals(2.5, $this->parser->execute('5 / 2'));
    }

    public function test_power_operator(): void
    {
        $this->assertEquals(8.0, $this->parser->execute('2 ^ 3'));
    }

    public function test_modulo(): void
    {
        $this->assertEquals(1.0, $this->parser->execute('7 % 3'));
    }

    public function test_parentheses_change_precedence(): void
    {
        $this->assertEquals(10.0, $this->parser->execute('(2 + 3) * 2'));
        $this->assertEquals(8.0,  $this->parser->execute('2 + 3 * 2'));
    }

    public function test_negative_number(): void
    {
        $this->assertEquals(-5.0, $this->parser->execute('-5'));
    }

    public function test_double_negative_is_positive(): void
    {
        $this->assertEquals(5.0, $this->parser->execute('--5'));
    }

    // ── Variables ──────────────────────────────────────────────────────

    public function test_variable_resolved_via_on_variable_callback(): void
    {
        $this->parser->onVariable = function (string $name, &$value): void {
            $value = match ($name) {
                'quantity'   => 5.0,
                'unit_price' => 20.0,
                default      => 0,
            };
        };

        $this->assertEquals(100.0, $this->parser->execute('quantity * unit_price'));
    }

    public function test_complex_formula_with_multiple_variables(): void
    {
        $this->parser->onVariable = function (string $name, &$value): void {
            $value = match ($name) {
                'quantity'   => 10.0,
                'unit_price' => 25.0,
                'tax_rate'   => 0.1,
                default      => 0,
            };
        };

        // 10 * 25 * (1 + 0.1) = 275
        $result = $this->parser->execute('quantity * unit_price * (1 + tax_rate)');
        $this->assertEqualsWithDelta(275.0, $result, 0.001);
    }

    public function test_unknown_variable_defaults_to_zero(): void
    {
        $this->parser->onVariable = function (string $name, &$value): void {
            $value = 0;
        };

        $this->assertEquals(0.0, $this->parser->execute('unknown_var * 5'));
    }

    // ── String literals ────────────────────────────────────────────────

    public function test_string_concatenation_with_plus(): void
    {
        $this->assertEquals('helloworld', $this->parser->execute('"hello" + "world"'));
    }

    // ── Built-in functions ─────────────────────────────────────────────

    public function test_abs_function(): void
    {
        $this->parser->functions['abs'] = ['arc' => 1, 'ref' => 'abs'];
        $this->assertEquals(5.0, $this->parser->execute('abs(-5)'));
    }

    public function test_round_function(): void
    {
        $this->parser->functions['round'] = ['arc' => 2, 'ref' => 'round'];
        $this->assertEquals(3.0, $this->parser->execute('round(3.4, 0)'));
        $this->assertEquals(4.0, $this->parser->execute('round(3.6, 0)'));
    }

    public function test_floor_function(): void
    {
        $this->parser->functions['floor'] = ['arc' => 1, 'ref' => 'floor'];
        $this->assertEquals(3.0, $this->parser->execute('floor(3.9)'));
    }

    public function test_ceil_function(): void
    {
        $this->parser->functions['ceil'] = ['arc' => 1, 'ref' => 'ceil'];
        $this->assertEquals(4.0, $this->parser->execute('ceil(3.1)'));
    }

    public function test_sqrt_function(): void
    {
        $this->parser->functions['sqrt'] = ['arc' => 1, 'ref' => 'sqrt'];
        $this->assertEquals(3.0, $this->parser->execute('sqrt(9)'));
    }

    public function test_max_function(): void
    {
        $this->parser->functions['max'] = ['ref' => fn(...$a) => max($a)];
        $this->assertEquals(10.0, $this->parser->execute('max(3, 10, 7)'));
    }

    public function test_min_function(): void
    {
        $this->parser->functions['min'] = ['ref' => fn(...$a) => min($a)];
        $this->assertEquals(3.0, $this->parser->execute('min(3, 10, 7)'));
    }

    // ── Errors ─────────────────────────────────────────────────────────

    public function test_division_by_zero_throws(): void
    {
        $this->expectException(\Exception::class);
        $this->parser->execute('10 / 0');
    }

    public function test_unmatched_opening_bracket_throws(): void
    {
        $this->expectException(\Exception::class);
        $this->parser->execute('(2 + 3');
    }

    public function test_unmatched_closing_bracket_throws(): void
    {
        $this->expectException(\Exception::class);
        $this->parser->execute('2 + 3)');
    }
}
