<?php

namespace Fazzinipierluigi\LaravelRails\Actions;

use Illuminate\Support\Facades\Mail;
use Fazzinipierluigi\LaravelRails\Classes\VariableResolver;
use Fazzinipierluigi\LaravelRails\Interfaces\ActionInterface;
use Fazzinipierluigi\LaravelRails\Models\Instance;

class SendEmail implements ActionInterface
{
    public static string $display_name = 'Send an E-Mail';

    /**
     * Configuration schema (matches form field format for the visual editor).
     *
     * @var array[]
     */
    public static array $configuration_schema = [
        [
            'name'        => 'to',
            'type'        => 'text',
            'label'       => 'Destinatario (es. {{entity.email}})',
            'required'    => true,
            'placeholder' => '{{entity.email}}',
        ],
        [
            'name'        => 'subject',
            'type'        => 'text',
            'label'       => 'Oggetto',
            'required'    => true,
            'placeholder' => 'Aggiornamento pratica #{{variables.number}}',
        ],
        [
            'name'        => 'body',
            'type'        => 'textarea',
            'label'       => 'Corpo (HTML, supporta {{placeholder}})',
            'required'    => false,
            'placeholder' => '<p>Gentile {{entity.name}},<br>...</p>',
        ],
        [
            'name'        => 'template',
            'type'        => 'text',
            'label'       => 'Template Blade (opzionale, sostituisce body)',
            'required'    => false,
            'placeholder' => 'emails.workflow-notification',
        ],
        [
            'name'        => 'cc',
            'type'        => 'text',
            'label'       => 'CC (opzionale)',
            'required'    => false,
            'placeholder' => 'manager@example.com',
        ],
    ];

    public function execute($instance, $entity, ?array $configuration, $destination_state): bool
    {
        $configuration = $configuration ?? [];

        $to      = VariableResolver::resolve($configuration['to']      ?? '', $instance, $entity);
        $subject = VariableResolver::resolve($configuration['subject'] ?? '', $instance, $entity);

        if (empty(trim($to))) {
            throw new \Exception('SendEmail: recipient address is empty');
        }
        if (empty(trim($subject))) {
            throw new \Exception('SendEmail: subject is empty');
        }

        $cc = !empty($configuration['cc'])
            ? VariableResolver::resolve($configuration['cc'], $instance, $entity)
            : null;

        if (!empty($configuration['template'])) {
            $data = [
                'instance'  => $instance,
                'entity'    => $entity,
                'variables' => $instance->variables ?? [],
            ];
            Mail::send(
                $configuration['template'],
                $data,
                function ($message) use ($to, $subject, $cc) {
                    $message->to($to)->subject($subject);
                    if ($cc) {
                        $message->cc($cc);
                    }
                }
            );
        } else {
            $body = VariableResolver::resolve($configuration['body'] ?? '', $instance, $entity);
            Mail::html(
                $body ?: ' ',
                function ($message) use ($to, $subject, $cc) {
                    $message->to($to)->subject($subject);
                    if ($cc) {
                        $message->cc($cc);
                    }
                }
            );
        }

        return true;
    }
}
