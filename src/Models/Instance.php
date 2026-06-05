<?php

namespace Fazzinipierluigi\LaravelRails\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Fazzinipierluigi\LaravelRails\Classes\ExecutionLogger;
use Fazzinipierluigi\LaravelRails\Jobs\AutoAdvanceWorkflow;

class Instance extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'variables' => 'array',
    ];

    private ?ExecutionLogger $_logger = null;

    public static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function entity()
    {
        return $this->morphTo('instanceable');
    }

    // ── Logger ────────────────────────────────────────────────────────

    public function logger(string $triggeredBy = 'system'): ExecutionLogger
    {
        if ($this->_logger === null) {
            $this->_logger = new ExecutionLogger($this, $triggeredBy);
        }
        return $this->_logger;
    }

    // ── Auto-advance ──────────────────────────────────────────────────

    public function checkAutoAdvance(): void
    {
        $this->refresh();
        $state = $this->state;

        if (!$state || $state->is_end || $state->isConditional()) {
            return;
        }
        if ($state->transitions()->count() === 0) {
            return;
        }
        if ($state->transitions()->get()->contains(fn($t) => $t->hasForm())) {
            return;
        }

        AutoAdvanceWorkflow::dispatch($this->id);
    }

    // ── Variable helpers ──────────────────────────────────────────────

    public function getVariable(string $key, mixed $default = null): mixed
    {
        return data_get($this->variables ?? [], $key, $default);
    }

    public function setVariable(string $key, mixed $value): void
    {
        $vars = $this->variables ?? [];
        data_set($vars, $key, $value);
        $this->variables = $vars;
        $this->save();
    }

    public function mergeVariables(array $vars): void
    {
        $this->variables = array_merge($this->variables ?? [], $vars);
        $this->save();
    }

    public function hasVariable(string $key): bool
    {
        return data_get($this->variables ?? [], $key) !== null;
    }

    // ─────────────────────────────────────────────────────────────────

    public function progress(string $triggeredBy = 'system'): string
    {
        $this->logger($triggeredBy);

        $transition = $this->state->next($this);

        if (empty($transition)) {
            throw new \Exception('No applicable transition was found');
        }

        $transition->perform($this);
        $this->refresh();

        $chainLast = $this->resolveConditionalChain();

        $this->checkAutoAdvance();

        $final = $chainLast ?? $transition;
        return $final->redirect ? route($final->redirect) : url()->previous();
    }

    /**
     * Automatically advance through consecutive conditional states.
     * Stops when the instance lands on a non-conditional state (or an end state).
     * Throws on infinite loops (revisited conditional state within the same chain).
     */
    public function resolveConditionalChain(int $maxHops = 50): ?Transition
    {
        $lastTransition = null;
        $visited        = [];

        while (true) {
            $state = $this->state;

            if (!$state || !$state->isConditional()) {
                break;
            }

            if (in_array($state->id, $visited, true)) {
                throw new \Exception(
                    "Infinite loop detected: conditional state \"{$state->name}\" revisited in the same chain"
                );
            }
            $visited[] = $state->id;

            if (count($visited) > $maxHops) {
                throw new \Exception('Maximum conditional chain length exceeded (possible loop)');
            }

            $transition = $state->next($this);

            if (empty($transition)) {
                throw new \Exception(
                    "Conditional state \"{$state->name}\" has no applicable transition — deadlock"
                );
            }

            $transition->perform($this);
            $lastTransition = $transition;
            $this->refresh();
        }

        return $lastTransition;
    }
}
