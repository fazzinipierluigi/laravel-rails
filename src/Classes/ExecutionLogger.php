<?php

namespace Fazzinipierluigi\LaravelRails\Classes;

use Fazzinipierluigi\LaravelRails\Models\ExecutionLog;
use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Models\State;
use Fazzinipierluigi\LaravelRails\Models\Transition;

/**
 * Records every meaningful event during workflow execution.
 * All writes are fire-and-forget: logging errors never propagate to the workflow.
 */
class ExecutionLogger
{
    private Instance $instance;
    private string   $triggeredBy;
    private ?float   $stateEnteredAt = null;
    private int      $stepCount      = 0;

    public function __construct(Instance $instance, string $triggeredBy = 'system')
    {
        $this->instance    = $instance;
        $this->triggeredBy = $triggeredBy;
    }

    public function getTriggeredBy(): string { return $this->triggeredBy; }

    // ─────────────────────────────────────────────────────────────────
    // Public event methods
    // ─────────────────────────────────────────────────────────────────

    public function instanceStarted(): void
    {
        $workflow = $this->instance->workflow;
        $entity   = $this->instance->entity;

        $this->write('instance.started', null, null, [
            'workflow_id'    => $workflow?->id,
            'workflow_name'  => $workflow?->name,
            'workflow_slug'  => $workflow?->slug,
            'entity_class'   => $this->instance->instanceable_type,
            'entity_id'      => $this->instance->instanceable_id,
            'entity_display' => $entity
                ? (method_exists($entity, '__toString')
                    ? (string) $entity
                    : class_basename(get_class($entity)) . ' #' . $entity->getKey())
                : null,
            'triggered_by_detail' => $this->formatTriggeredBy(),
        ]);
    }

    public function stateEntered(State $state, string $mode = 'manual'): void
    {
        $mode = $this->resolveMode($mode);

        $this->stateEnteredAt = microtime(true);
        $this->stepCount++;

        $this->write('state.entered', 'state', $state->id, [
            'state_id'   => $state->id,
            'state_name' => $state->name,
            'state_type' => $state->type ?? 'simple',
            'mode'       => $mode,
            'mode_label' => $this->modeLabel($mode),
            'is_start'   => $state->is_start,
            'is_end'     => $state->is_end,
        ]);

        if ($state->is_end) {
            $this->instanceCompleted($state);
        }
    }

    public function stateExited(State $state): void
    {
        $timeMs = $this->stateEnteredAt
            ? (int) ((microtime(true) - $this->stateEnteredAt) * 1000)
            : null;

        $this->write('state.exited', 'state', $state->id, [
            'state_id'         => $state->id,
            'state_name'       => $state->name,
            'time_in_state_ms' => $timeMs,
            'time_in_state'    => $timeMs !== null ? $this->formatMs($timeMs) : null,
        ]);
    }

    public function conditionEvaluated(
        Transition $transition,
        string     $type,
        array      $condition,
        array      $inputData,
        bool       $result
    ): void {
        $typeLabels = [
            'execute' => 'Condizione di esecuzione (determina se la transizione è percorribile)',
            'exit'    => 'Condizione di uscita (determina se lo stato può essere abbandonato)',
            'show'    => 'Condizione di visibilità (determina se la transizione è visibile all\'utente)',
        ];

        $this->write('transition.condition_evaluated', 'transition', $transition->id, [
            'transition_id'        => $transition->id,
            'transition_label'     => $transition->label ?? null,
            'from_state_id'        => $transition->from,
            'to_state_id'          => $transition->to,
            'condition_type'       => $type,
            'condition_type_label' => $typeLabels[$type] ?? $type,
            'condition'            => $condition,
            'input_data'           => $this->sanitizeInputData($inputData),
            'result'               => $result,
            'result_label'         => $result
                ? 'SODDISFATTA — la transizione è percorribile'
                : 'NON SODDISFATTA — la transizione non può essere percorsa',
        ]);
    }

    public function transitionPerformed(Transition $transition): void
    {
        $fromState = $transition->source;
        $toState   = $transition->destination;

        $this->write('transition.performed', 'transition', $transition->id, [
            'transition_id'    => $transition->id,
            'transition_label' => $transition->label ?? '(senza etichetta)',
            'from_state_id'    => $transition->from,
            'from_state_name'  => $fromState?->name,
            'to_state_id'      => $transition->to,
            'to_state_name'    => $toState?->name,
        ]);
    }

    public function actionExecuted(
        string      $actionClass,
        string      $phase,
        ?array      $configuration,
        string      $result,       // 'success' | 'failure' | 'skipped'
        int         $durationMs,
        ?string     $output             = null,
        ?\Throwable $error              = null,
        ?string     $contextSubjectType = null,
        ?string     $contextSubjectId   = null
    ): void {
        $phaseLabels = [
            'on_enter' => "All'ingresso dello stato",
            'on_exit'  => "All'uscita dallo stato",
            'pre'      => 'Prima della transizione (pre)',
            'post'     => 'Dopo la transizione (post)',
        ];

        $resultLabels = [
            'success' => 'Eseguita con successo',
            'failure' => 'Fallita con errore',
            'skipped' => 'Saltata (classe non trovata o non valida)',
        ];

        $displayName = null;
        if (class_exists($actionClass) && property_exists($actionClass, 'display_name')) {
            $displayName = $actionClass::$display_name;
        }

        $this->write('action.executed', $contextSubjectType ?? 'action', $contextSubjectId, [
            'action_class'   => $actionClass,
            'action_name'    => $displayName ?? class_basename($actionClass),
            'phase'          => $phase,
            'phase_label'    => $phaseLabels[$phase] ?? $phase,
            'configuration'  => $configuration,
            'result'         => $result,
            'result_label'   => $resultLabels[$result] ?? $result,
            'duration_ms'    => $durationMs,
            'duration'       => $this->formatMs($durationMs),
            'output'         => $output,
            'error_message'  => $error?->getMessage(),
            'error_class'    => $error ? get_class($error) : null,
            'error_trace'    => $error
                ? mb_substr($error->getTraceAsString(), 0, 3000)
                : null,
        ]);
    }

    public function instanceCompleted(State $finalState): void
    {
        $this->write('instance.completed', 'state', $finalState->id, [
            'final_state_id'   => $finalState->id,
            'final_state_name' => $finalState->name,
            'steps_taken'      => $this->stepCount,
            'summary'          => "Il workflow si è concluso con successo nello stato \"{$finalState->name}\" dopo {$this->stepCount} passaggio/i.",
        ]);
    }

    public function permissionDenied(
        string     $action,
        Transition $transition,
        mixed      $user,
        string     $driver
    ): void {
        $userId = method_exists($user, 'getKey') ? $user->getKey() : 'unknown';

        $actionLabel = match ($action) {
            'view'    => 'Visualizzazione transizione',
            'advance' => 'Avanzamento transizione',
            default   => $action,
        };

        $this->write('permission.denied', 'transition', $transition->id, [
            'action'           => $action,
            'action_label'     => $actionLabel,
            'transition_id'    => $transition->id,
            'transition_label' => $transition->label ?? '(senza etichetta)',
            'from_state_id'    => $transition->from,
            'to_state_id'      => $transition->to,
            'user_id'          => $userId,
            'driver'           => $driver,
            'summary'          => "Accesso negato: utente #{$userId} non ha il permesso di {$action} sulla transizione '{$transition->label}' tramite driver [{$driver}].",
        ]);
    }

    public function instanceBlocked(State $state, string $reason): void
    {
        $this->write('instance.blocked', 'state', $state->id, [
            'state_id'   => $state->id,
            'state_name' => $state->name,
            'reason'     => $reason,
            'summary'    => "Il workflow è bloccato nello stato \"{$state->name}\": {$reason}",
        ]);
    }

    public function executionError(\Throwable $e, array $context = []): void
    {
        $this->write('execution.error', null, null, [
            'message' => $e->getMessage(),
            'class'   => get_class($e),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => mb_substr($e->getTraceAsString(), 0, 4000),
            'context' => $context,
            'summary' => 'Errore durante l\'esecuzione: ' . $e->getMessage(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────

    private function write(
        string  $event,
        ?string $subjectType,
        ?string $subjectId,
        array   $data
    ): void {
        try {
            ExecutionLog::create([
                'instance_id'  => $this->instance->id,
                'event'        => $event,
                'subject_type' => $subjectType,
                'subject_id'   => $subjectId,
                'data'         => $data,
                'triggered_by' => $this->triggeredBy,
                'occurred_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Logging must never break the workflow — fail silently to Laravel log
            \Illuminate\Support\Facades\Log::error(
                '[LaravelRails] ExecutionLogger write failed: ' . $e->getMessage(),
                ['event' => $event, 'instance_id' => $this->instance->id]
            );
        }
    }

    private function resolveMode(string $mode): string
    {
        if ($mode === 'manual' && str_starts_with($this->triggeredBy, 'auto')) {
            return 'auto';
        }
        return $mode;
    }

    private function modeLabel(string $mode): string
    {
        return match($mode) {
            'manual'      => 'Avanzamento manuale (utente)',
            'auto'        => 'Auto-avanzamento (sistema automatico)',
            'conditional' => 'Routing condizionale (automatico)',
            'start'       => 'Stato iniziale (avvio workflow)',
            default       => $mode,
        };
    }

    private function formatMs(int $ms): string
    {
        if ($ms < 1000) return $ms . 'ms';
        if ($ms < 60000) return round($ms / 1000, 2) . 's';
        return round($ms / 60000, 1) . 'min';
    }

    private function formatTriggeredBy(): string
    {
        if (str_starts_with($this->triggeredBy, 'user:')) {
            return 'Utente ID ' . substr($this->triggeredBy, 5);
        }
        if (str_starts_with($this->triggeredBy, 'trigger:')) {
            return 'Trigger automatico ID ' . substr($this->triggeredBy, 8);
        }
        if (str_starts_with($this->triggeredBy, 'auto')) {
            return 'Sistema (auto-avanzamento)';
        }
        return 'Sistema';
    }

    /** Remove potentially large/sensitive data from condition input before logging */
    private function sanitizeInputData(array $data): array
    {
        $sanitized = $data;
        // Truncate large values
        array_walk_recursive($sanitized, function (&$val) {
            if (is_string($val) && mb_strlen($val) > 500) {
                $val = mb_substr($val, 0, 500) . '…';
            }
        });
        return $sanitized;
    }
}
