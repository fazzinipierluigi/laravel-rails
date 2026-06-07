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
        if (empty($transition) || $transition->form_type !== 'json' || empty($transition->form_data)) {
            return '';
        }

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

        $instance    = Instance::find($instanceId);
        $entity      = $instance?->entity;

        $schema      = json_decode($transition->form_data, true) ?? [];
        $actionUrl   = route('laravel-rails.transition.execute', ['transitionId' => $transitionId]);
        $submitLabel = $options['submit_label'] ?? 'Continua';
        $formClass   = $options['form_class'] ?? 'rail-form';

        $styles      = self::$stylesEmitted ? '' : ('<style>' . self::styles() . '</style>');
        self::$stylesEmitted = true;

        $formContent = self::renderSchema($schema, $entity, $instance);
        $csrf        = csrf_field();

        return <<<HTML
        {$styles}
        <form method="POST" action="{$actionUrl}" class="{$formClass}">
            {$csrf}
            <input type="hidden" name="instance_id" value="{$instanceId}">
            <div class="rail-grid">
                {$formContent}
            </div>
            <div class="rail-form-footer">
                <button type="submit" class="rail-form-submit">{$submitLabel}</button>
            </div>
        </form>
        HTML;
    }

    public static function renderSchema(array $schema, mixed $entity = null, mixed $instance = null): string
    {
        return implode("\n", array_map(
            fn($field) => self::renderField($field, $entity, $instance),
            $schema
        ));
    }

    private static function renderField(array $field, mixed $entity, mixed $instance): string
    {
        $name        = htmlspecialchars($field['name'] ?? '', ENT_QUOTES);
        $label       = htmlspecialchars($field['label'] ?? $name, ENT_QUOTES);
        $type        = $field['type'] ?? 'text';
        $required    = !empty($field['required']);
        $placeholder = htmlspecialchars($field['placeholder'] ?? '', ENT_QUOTES);
        $reqAttr     = $required ? ' required' : '';
        $reqBadge    = $required ? ' <span class="rail-required">*</span>' : '';
        $cols        = max(1, min(12, (int) ($field['cols'] ?? 12)));
        $defaultVal  = self::resolveDefault($field['default_value'] ?? null, $entity, $instance);

        $colStyle = "grid-column: span {$cols};";

        if ($type === 'hidden') {
            $value = htmlspecialchars($defaultVal ?? ($field['value'] ?? ''), ENT_QUOTES);
            return "<input type=\"hidden\" name=\"{$name}\" value=\"{$value}\">";
        }

        $labelHtml = "<label class=\"rail-label\" for=\"rail-field-{$name}\">{$label}{$reqBadge}</label>";

        $valAttr = $defaultVal !== null ? ' value="' . htmlspecialchars($defaultVal, ENT_QUOTES) . '"' : '';

        switch ($type) {
            case 'textarea':
                $rows    = (int) ($field['rows'] ?? 4);
                $content = htmlspecialchars($defaultVal ?? '', ENT_QUOTES);
                $input   = "<textarea class=\"rail-input\" id=\"rail-field-{$name}\" name=\"{$name}\" "
                         . "placeholder=\"{$placeholder}\" rows=\"{$rows}\"{$reqAttr}>{$content}</textarea>";
                break;

            case 'select':
                $opts  = self::renderSelectOptions($field['options'] ?? [], $defaultVal);
                $input = "<select class=\"rail-input\" id=\"rail-field-{$name}\" name=\"{$name}\"{$reqAttr}>"
                       . "<option value=\"\">— seleziona —</option>{$opts}</select>";
                break;

            case 'radio':
                $input = '<div class="rail-radio-group">';
                foreach ($field['options'] ?? [] as $opt) {
                    $v       = htmlspecialchars($opt['value'] ?? '', ENT_QUOTES);
                    $l       = htmlspecialchars($opt['label'] ?? $v, ENT_QUOTES);
                    $checked = ($defaultVal !== null && $defaultVal === ($opt['value'] ?? '')) ? ' checked' : '';
                    $input  .= "<label class=\"rail-radio\"><input type=\"radio\" name=\"{$name}\" value=\"{$v}\"{$reqAttr}{$checked}> {$l}</label>";
                }
                $input .= '</div>';
                break;

            case 'checkbox':
                $checkLabel = htmlspecialchars($field['check_label'] ?? $label, ENT_QUOTES);
                $checked    = $defaultVal ? ' checked' : '';
                return "<div class=\"rail-field\" style=\"{$colStyle}\">"
                     . "<label class=\"rail-checkbox\">"
                     . "<input type=\"checkbox\" id=\"rail-field-{$name}\" name=\"{$name}\" value=\"1\"{$reqAttr}{$checked}>"
                     . " {$checkLabel}</label></div>\n";

            default:
                $input = "<input class=\"rail-input\" type=\"{$type}\" id=\"rail-field-{$name}\" name=\"{$name}\" "
                       . "placeholder=\"{$placeholder}\"{$valAttr}{$reqAttr}>";
        }

        return "<div class=\"rail-field\" style=\"{$colStyle}\">{$labelHtml}{$input}</div>\n";
    }

    /**
     * Resolve a field's default_value config into a scalar string (or null).
     *
     * Config shape:
     *   { source: 'none' }
     *   { source: 'literal', value: '...' }
     *   { source: 'entity_field', field: 'title' }
     *   { source: 'relation_field', relation: 'submitter', field: 'name' }
     *   { source: 'variable', key: 'my_var' }
     */
    private static function resolveDefault(?array $config, mixed $entity, mixed $instance): ?string
    {
        if (empty($config) || ($config['source'] ?? 'none') === 'none') {
            return null;
        }

        try {
            switch ($config['source']) {
                case 'literal':
                    return (string) ($config['value'] ?? '');

                case 'entity_field':
                    $fieldName = $config['field'] ?? null;
                    if ($entity && $fieldName && isset($entity->$fieldName)) {
                        return (string) $entity->$fieldName;
                    }
                    return null;

                case 'relation_field':
                    $rel   = $config['relation'] ?? null;
                    $field = $config['field'] ?? null;
                    if ($entity && $rel && $field) {
                        $related = $entity->$rel;
                        if ($related && isset($related->$field)) {
                            return (string) $related->$field;
                        }
                    }
                    return null;

                case 'variable':
                    $key = $config['key'] ?? null;
                    if ($instance && $key && method_exists($instance, 'getVariable')) {
                        $val = $instance->getVariable($key);
                        return $val !== null ? (string) $val : null;
                    }
                    return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private static function renderSelectOptions(array $options, ?string $selected): string
    {
        return implode('', array_map(function ($opt) use ($selected) {
            $v        = htmlspecialchars($opt['value'] ?? '', ENT_QUOTES);
            $l        = htmlspecialchars($opt['label'] ?? $v, ENT_QUOTES);
            $sel      = ($selected !== null && $selected === ($opt['value'] ?? '')) ? ' selected' : '';
            return "<option value=\"{$v}\"{$sel}>{$l}</option>";
        }, $options));
    }

    private static function styles(): string
    {
        return <<<CSS
        .rail-form { max-width: 900px; padding: 24px 0; }
        .rail-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 16px; }
        .rail-field { }
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
