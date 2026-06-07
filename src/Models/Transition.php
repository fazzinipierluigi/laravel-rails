<?php

namespace Fazzinipierluigi\LaravelRails\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Fazzinipierluigi\LaravelRails\Classes\VariableResolver;
use Fazzinipierluigi\LaravelRails\Contracts\PermissionResolverInterface;

class Transition extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $keyType    = 'string';
    public $incrementing  = false;

    protected $casts = [
        'show_condition'      => 'array',
        'execute_condition'   => 'array',
        'exit_condition'      => 'array',
        'view_permissions'    => 'array',
        'advance_permissions' => 'array',
        'waypoints'           => 'array',
    ];

    public static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });

        static::deleting(function ($model) {
            $model->actions()->delete();
        });
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function source()
    {
        return $this->belongsTo(State::class, 'from');
    }

    public function destination()
    {
        return $this->belongsTo(State::class, 'to');
    }

    public function actions()
    {
        return $this->morphMany(Action::class, 'actionable');
    }

    // ── Helpers ────────────────────────────────────────────────────────

    public function getFormSchema(): array
    {
        if ($this->form_type !== 'json' || empty($this->form_data)) {
            return [];
        }
        return json_decode($this->form_data, true) ?? [];
    }

    public function hasForm(): bool
    {
        return !empty($this->form_type) && !empty($this->form_data);
    }

    public static function nextOrder(string $from): int
    {
        $last = static::where('from', $from)->orderBy('sort', 'DESC')->first();
        return $last ? ($last->sort + 1) : 0;
    }

    // ── Condition checks ───────────────────────────────────────────────

    public function can(Instance $instance): bool
    {
        if ($instance->state_id !== $this->from) {
            return false;
        }

        // Advance permission check — system/auto (no user) bypasses
        $user = auth()->user();
        if ($user !== null) {
            $advPerms = $this->advance_permissions ?? [];
            if (!empty($advPerms)) {
                $resolver = app(PermissionResolverInterface::class);
                if (!$resolver->check($user, $advPerms, $this->advance_operator ?? 'OR')) {
                    $instance->logger()->permissionDenied('advance', $this, $user, $resolver->getDriverName());
                    return false;
                }
            }
        }

        if (empty($this->execute_condition)) {
            return true;
        }
        return $this->evaluateCondition($this->execute_condition, $instance, 'execute');
    }

    public function can_exit(Instance $instance): bool
    {
        if (empty($this->exit_condition)) {
            return true;
        }
        return $this->evaluateCondition($this->exit_condition, $instance, 'exit');
    }

    public function can_show(Instance $instance): bool
    {
        if ($instance->state_id !== $this->from) {
            return false;
        }

        // View permission check
        $user     = auth()->user();
        $viewPerms = $this->view_permissions ?? [];
        if (!empty($viewPerms)) {
            if ($user === null) {
                return false;
            }
            $resolver = app(PermissionResolverInterface::class);
            if (!$resolver->check($user, $viewPerms, $this->view_operator ?? 'OR')) {
                $instance->logger()->permissionDenied('view', $this, $user, $resolver->getDriverName());
                return false;
            }
        }

        if (empty($this->show_condition)) {
            return true;
        }
        return $this->evaluateCondition($this->show_condition, $instance, 'show');
    }

    /** Check advance permissions without an instance (e.g. for FormRenderer pre-check). */
    public function canAdvance(mixed $user = null): bool
    {
        $permissions = $this->advance_permissions ?? [];
        if (empty($permissions)) {
            return true;
        }
        if ($user === null) {
            return true; // system/auto bypass
        }
        return app(PermissionResolverInterface::class)
            ->check($user, $permissions, $this->advance_operator ?? 'OR');
    }

    // ── Perform ────────────────────────────────────────────────────────

    /**
     * Execute this transition for the given instance.
     *
     * @param  string  $entryMode  How the destination state is being entered:
     *                             'manual'|'auto'|'conditional'|'start'
     */
    public function perform(Instance $instance, string $entryMode = 'manual'): void
    {
        $entity = $instance->entity;
        $logger = $instance->logger();

        if (!$this->can($instance)) {
            throw new \Exception('Transition not applicable');
        }

        // Pre-transition actions
        foreach ($this->actions()->where('phase', 'pre')->orderBy('sort')->get() as $action) {
            $this->executeAction($action->action, $instance, $entity, $action->configuration, 'pre', 'Error during pre-transition action');
        }

        if ($this->can_exit($instance)) {
            // Exit current state
            $logger->stateExited($this->source);

            foreach ($this->source->actions()->where('phase', 'on_exit')->orderBy('sort')->get() as $action) {
                $this->executeAction($action->action, $instance, $entity, $action->configuration, 'on_exit', 'Error during on_exit action');
            }

            // Move to destination
            $logger->transitionPerformed($this);
            $instance->state_id = $this->to;
            $instance->save();

            // Enter destination state
            $logger->stateEntered($this->destination, $entryMode);

            foreach ($this->destination->actions()->where('phase', 'on_enter')->orderBy('sort')->get() as $action) {
                $this->executeAction($action->action, $instance, $entity, $action->configuration, 'on_enter', 'Error during on_enter action');
            }

            // Post-transition actions
            foreach ($this->actions()->where('phase', 'post')->orderBy('sort')->get() as $action) {
                $this->executeAction($action->action, $instance, $entity, $action->configuration, 'post', 'Error during post-transition action');
            }
        }
    }

    // ── Private helpers ────────────────────────────────────────────────

    private function executeAction(
        string   $actionClass,
        Instance $instance,
        $entity,
        ?array   $configuration,
        string   $phase,
        string   $errorMessage = ''
    ): void {
        $logger = $instance->logger();
        $start  = microtime(true);

        // Associate action logs with the relevant state or transition for the execution viewer
        $contextType = match ($phase) {
            'on_enter'     => 'state',
            'on_exit'      => 'state',
            'pre', 'post'  => 'transition',
            default        => null,
        };
        $contextId = match ($phase) {
            'on_enter'     => $this->to,
            'on_exit'      => $this->from,
            'pre', 'post'  => $this->id,
            default        => null,
        };

        if (empty($actionClass) || !class_exists($actionClass)) {
            $logger->actionExecuted($actionClass, $phase, $configuration, 'skipped',
                (int)((microtime(true) - $start) * 1000),
                null, null, $contextType, $contextId);
            return;
        }

        try {
            $handler    = new $actionClass;
            $result     = $handler->execute($instance, $entity, $configuration, $this->to);
            $durationMs = (int)((microtime(true) - $start) * 1000);

            if ($result !== true) {
                $logger->actionExecuted($actionClass, $phase, $configuration, 'failure',
                    $durationMs, null, new \RuntimeException($errorMessage), $contextType, $contextId);
                throw new \Exception($errorMessage);
            }

            $logger->actionExecuted($actionClass, $phase, $configuration, 'success',
                $durationMs, null, null, $contextType, $contextId);
        } catch (\Exception $ex) {
            $durationMs = (int)((microtime(true) - $start) * 1000);
            $logger->actionExecuted($actionClass, $phase, $configuration, 'failure',
                $durationMs, null, $ex, $contextType, $contextId);
            throw $ex;
        }
    }

    private function evaluateCondition(array $condition, Instance $instance, string $type): bool
    {
        $data   = VariableResolver::buildContext($instance, $instance->entity);
        $result = (bool) \JWadhams\JsonLogic::apply($condition, $data);

        $instance->logger()->conditionEvaluated($this, $type, $condition, $data, $result);

        return $result;
    }
}
