<?php

namespace Fazzinipierluigi\LaravelRails\Classes;

use Fazzinipierluigi\LaravelRails\Models\WorkflowTrigger;

class TriggerRenderer
{
    private static bool $stylesEmitted = false;

    /**
     * Render a button/form that fires a manual trigger for the given entity.
     *
     * Usage in Blade:
     *   @laravel_rail_trigger($trigger->id, $entity->id)
     *   @laravel_rail_trigger($trigger->id, $entity->id, ['class' => 'btn btn-success', 'label' => 'Avvia'])
     */
    public static function render(string $triggerId, $entityId, array $options = []): string
    {
        $trigger = WorkflowTrigger::find($triggerId);

        if (empty($trigger) || !$trigger->isManual() || !$trigger->is_active) {
            return '';
        }

        $permission = $trigger->getPermission();
        if ($permission && auth()->check() && !auth()->user()->can($permission)) {
            return '';
        }

        $actionUrl   = route('laravel-rails.trigger.fire', ['triggerId' => $triggerId]);
        $label       = $options['label']       ?? $trigger->getLabel();
        $buttonClass = $options['class']       ?? ($trigger->configuration['button_class'] ?? 'rail-trigger-btn');
        $formClass   = $options['form_class']  ?? 'rail-trigger-form';

        $styles = self::$stylesEmitted ? '' : ('<style>' . self::styles() . '</style>');
        self::$stylesEmitted = true;

        $csrf = csrf_field();

        return <<<HTML
        {$styles}
        <form method="POST" action="{$actionUrl}" class="{$formClass}">
            {$csrf}
            <input type="hidden" name="entity_id" value="{$entityId}">
            <button type="submit" class="{$buttonClass}">{$label}</button>
        </form>
        HTML;
    }

    private static function styles(): string
    {
        return <<<CSS
        .rail-trigger-form { display: inline-block; }
        .rail-trigger-btn {
            padding: 9px 20px;
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }
        .rail-trigger-btn:hover { background: #2563eb; }
        CSS;
    }
}
