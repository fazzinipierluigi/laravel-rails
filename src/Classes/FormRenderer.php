<?php

namespace Fazzinipierluigi\LaravelRails\Classes;

use Fazzinipierluigi\LaravelRails\Contracts\PermissionResolverInterface;
use Fazzinipierluigi\LaravelRails\Models\Instance;
use Fazzinipierluigi\LaravelRails\Models\Transition;

class FormRenderer
{
    private static bool $stylesEmitted = false;

    /**
     * Render the form associated with $transitionId for a given $instanceId.
     * Returns empty string if the transition has no form.
     */
    public static function render(string $transitionId, string $instanceId, array $options = []): string
    {
        $transition = Transition::find($transitionId);
        if (empty($transition) || empty($transition->form_type) || empty($transition->form_data)) {
            return '';
        }

        // View permission check — hide form entirely if user can't see this transition
        $user      = auth()->user();
        $viewPerms = $transition->view_permissions ?? [];
        if (!empty($viewPerms)) {
            if ($user === null) {
                return '';
            }
            if (!app(PermissionResolverInterface::class)->check($user, $viewPerms, $transition->view_operator ?? 'OR')) {
                return '';
            }
        }

        $actionUrl   = route('laravel-rails.transition.execute', ['transitionId' => $transitionId]);
        $submitLabel = $options['submit_label'] ?? 'Continua';
        $formClass   = $options['form_class'] ?? 'rail-form';

        $styles      = self::$stylesEmitted ? '' : ('<style>' . self::styles() . '</style>');
        self::$stylesEmitted = true;

        if ($transition->form_type === 'html') {
            $formContent = $transition->form_data;
        } elseif ($transition->form_type === 'json') {
            $schema      = json_decode($transition->form_data, true) ?? [];
            $formContent = self::renderSchema($schema);
        } else {
            return '';
        }

        $csrf = csrf_field();

        return <<<HTML
        {$styles}
        <form method="POST" action="{$actionUrl}" class="{$formClass}">
            {$csrf}
            <input type="hidden" name="instance_id" value="{$instanceId}">
            {$formContent}
            <div class="rail-form-footer">
                <button type="submit" class="rail-form-submit">{$submitLabel}</button>
            </div>
        </form>
        HTML;
    }

    public static function renderSchema(array $schema): string
    {
        return implode("\n", array_map([self::class, 'renderField'], $schema));
    }

    private static function renderField(array $field): string
    {
        $name        = htmlspecialchars($field['name'] ?? '', ENT_QUOTES);
        $label       = htmlspecialchars($field['label'] ?? $name, ENT_QUOTES);
        $type        = $field['type'] ?? 'text';
        $required    = !empty($field['required']);
        $placeholder = htmlspecialchars($field['placeholder'] ?? '', ENT_QUOTES);
        $reqAttr     = $required ? ' required' : '';
        $reqBadge    = $required ? ' <span class="rail-required">*</span>' : '';

        if ($type === 'hidden') {
            $value = htmlspecialchars($field['value'] ?? '', ENT_QUOTES);
            return "<input type=\"hidden\" name=\"{$name}\" value=\"{$value}\">";
        }

        $labelHtml = "<label class=\"rail-label\" for=\"rail-field-{$name}\">{$label}{$reqBadge}</label>";

        switch ($type) {
            case 'textarea':
                $rows  = (int) ($field['rows'] ?? 4);
                $input = "<textarea class=\"rail-input\" id=\"rail-field-{$name}\" name=\"{$name}\" "
                       . "placeholder=\"{$placeholder}\" rows=\"{$rows}\"{$reqAttr}></textarea>";
                break;

            case 'select':
                $opts  = self::renderSelectOptions($field['options'] ?? []);
                $input = "<select class=\"rail-input\" id=\"rail-field-{$name}\" name=\"{$name}\"{$reqAttr}>"
                       . "<option value=\"\">— seleziona —</option>{$opts}</select>";
                break;

            case 'radio':
                $input = '<div class="rail-radio-group">';
                foreach ($field['options'] ?? [] as $opt) {
                    $v     = htmlspecialchars($opt['value'] ?? '', ENT_QUOTES);
                    $l     = htmlspecialchars($opt['label'] ?? $v, ENT_QUOTES);
                    $input .= "<label class=\"rail-radio\"><input type=\"radio\" name=\"{$name}\" value=\"{$v}\"{$reqAttr}> {$l}</label>";
                }
                $input .= '</div>';
                break;

            case 'checkbox':
                $checkLabel = htmlspecialchars($field['check_label'] ?? $label, ENT_QUOTES);
                return "<div class=\"rail-field\">"
                     . "<label class=\"rail-checkbox\">"
                     . "<input type=\"checkbox\" id=\"rail-field-{$name}\" name=\"{$name}\" value=\"1\"{$reqAttr}>"
                     . " {$checkLabel}</label></div>\n";

            default:
                $input = "<input class=\"rail-input\" type=\"{$type}\" id=\"rail-field-{$name}\" name=\"{$name}\" "
                       . "placeholder=\"{$placeholder}\"{$reqAttr}>";
        }

        return "<div class=\"rail-field\">{$labelHtml}{$input}</div>\n";
    }

    private static function renderSelectOptions(array $options): string
    {
        return implode('', array_map(function ($opt) {
            $v = htmlspecialchars($opt['value'] ?? '', ENT_QUOTES);
            $l = htmlspecialchars($opt['label'] ?? $v, ENT_QUOTES);
            return "<option value=\"{$v}\">{$l}</option>";
        }, $options));
    }

    private static function styles(): string
    {
        return <<<CSS
        .rail-form { max-width: 600px; padding: 24px 0; }
        .rail-field { margin-bottom: 18px; }
        .rail-label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; color: #374151; }
        .rail-required { color: #ef4444; }
        .rail-input { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; color: #111827; background: #fff; transition: border-color .15s; }
        .rail-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        textarea.rail-input { resize: vertical; }
        .rail-radio-group { display: flex; flex-direction: column; gap: 8px; }
        .rail-radio { display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; }
        .rail-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; }
        .rail-form-footer { margin-top: 24px; }
        .rail-form-submit { padding: 10px 24px; background: #3b82f6; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background .15s; }
        .rail-form-submit:hover { background: #2563eb; }
        CSS;
    }
}
