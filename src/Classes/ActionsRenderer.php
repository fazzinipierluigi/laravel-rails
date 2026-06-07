<?php

namespace Fazzinipierluigi\LaravelRails\Classes;

use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Models\Transition;

class ActionsRenderer
{
    /**
     * Render the available transition actions for a workflow instance.
     *
     * If no transitions are visible to the current user: returns empty string.
     * If exactly one transition is visible: renders its form (or a plain button)
     *   directly, with no select.
     * If multiple transitions are visible: renders a <select> to choose among
     *   them, and one form panel per transition (only the selected one visible).
     *
     * Styling is intentionally left to the host application.
     * See documentation for available CSS hooks and HTML structure.
     *
     * @param  string  $instanceId   UUID of the Instance to act on.
     * @param  array   $options {
     *     @type string $wrapper_class   CSS class on the outer wrapper div (default: 'lr-actions').
     *     @type string $select_class    CSS class on the <select>             (default: 'lr-actions-select').
     *     @type string $form_class      CSS class forwarded to FormRenderer    (default: 'lr-form').
     *     @type string $btn_class       CSS class on plain-advance buttons     (default: 'lr-action-btn').
     *     @type string $btn_label       Override label for all plain buttons   (default: transition label or 'Avanza').
     * }
     */
    public static function render(string $instanceId, array $options = []): string
    {
        $instance = Instance::find($instanceId);
        if (!$instance || !$instance->state) {
            return '';
        }

        $transitions = $instance->availableTransitions();

        if ($transitions->isEmpty()) {
            return '';
        }

        $wrapperClass = $options['wrapper_class'] ?? 'lr-actions';
        $selectClass  = $options['select_class']  ?? 'lr-actions-select';
        $formClass    = $options['form_class']     ?? 'lr-form';
        $btnClass     = $options['btn_class']      ?? 'lr-action-btn';
        $uid          = 'lr-' . substr(str_replace('-', '', $instanceId), 0, 12);

        $html = "<div class=\"{$wrapperClass}\" id=\"{$uid}\">";

        if ($transitions->count() === 1) {
            $t     = $transitions->first();
            $html .= self::renderPanel($t, $instance->id, $formClass, $btnClass, true);
        } else {
            // Select
            $html .= "<select class=\"{$selectClass}\" id=\"{$uid}-select\" "
                   . "onchange=\"_lrSwitch('{$uid}',this.value)\">";
            foreach ($transitions as $t) {
                $label = htmlspecialchars($t->label ?: $t->id, ENT_QUOTES);
                $html .= "<option value=\"{$t->id}\">{$label}</option>";
            }
            $html .= '</select>';

            // Panels (first visible, rest hidden)
            foreach ($transitions as $idx => $t) {
                $visible = $idx === 0 ? '' : ' style="display:none"';
                $html   .= "<div class=\"lr-action\" id=\"{$uid}-{$t->id}\" "
                         . "data-transition=\"{$t->id}\"{$visible}>";
                $html   .= self::renderPanel($t, $instance->id, $formClass, $btnClass, false);
                $html   .= '</div>';
            }

            // Minimal inline JS (one function, scoped by uid)
            $html .= <<<JS
<script>
function _lrSwitch(uid,tid){
  document.querySelectorAll('#'+uid+' .lr-action').forEach(function(el){
    el.style.display = el.dataset.transition===tid ? '' : 'none';
  });
}
</script>
JS;
        }

        $html .= '</div>';
        return $html;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private static function renderPanel(
        Transition $transition,
        string     $instanceId,
        string     $formClass,
        string     $btnClass,
        bool       $unwrapped
    ): string {
        $wrap  = $unwrapped ? '' : '';   // outer div already added by caller when multiple
        $inner = '';

        if ($transition->hasForm()) {
            $inner = FormRenderer::render($transition->id, $instanceId, [
                'form_class' => $formClass,
            ]);
        } else {
            $action = route('laravel-rails.transition.execute', ['transitionId' => $transition->id]);
            $csrf   = csrf_field();
            $label  = htmlspecialchars($transition->label ?: 'Avanza', ENT_QUOTES);
            $tid    = $transition->id;
            $inner  = <<<HTML
<form method="POST" action="{$action}" class="lr-action-form">
    {$csrf}
    <input type="hidden" name="instance_id" value="{$instanceId}">
    <button type="submit" class="{$btnClass}" data-transition="{$tid}">{$label}</button>
</form>
HTML;
        }

        if ($unwrapped) {
            return "<div class=\"lr-action\" data-transition=\"{$transition->id}\">{$inner}</div>";
        }

        return $inner;
    }
}
