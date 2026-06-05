<?php

namespace Fazzinipierluigi\LaravelRails\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;
use function Laravel\Prompts\text;
use function Laravel\Prompts\confirm;

class MakeAction extends Command
{
    protected $signature = 'rail:make-action
                            {name : The class name of the action (e.g. SendInvoice)}
                            {--namespace=App\\Rails\\Actions : Namespace for the generated class}
                            {--register : Also register the action in the database after creation}';

    protected $description = 'Generate a new workflow action class';

    public function handle(): int
    {
        $name      = Str::studly(trim($this->argument('name')));
        $namespace = trim($this->option('namespace'));

        if (empty($name)) {
            error('Action name cannot be empty');
            return Command::INVALID;
        }

        $relativePath = str_replace('\\', '/', ltrim(str_replace('App\\', '', $namespace), '\\'));
        $outputDir    = app_path($relativePath);
        $outputFile   = $outputDir . '/' . $name . '.php';

        if (file_exists($outputFile)) {
            if (!confirm("File {$outputFile} already exists. Overwrite?", false)) {
                return Command::SUCCESS;
            }
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $displayName = text(
            label: 'Display name for this action?',
            default: Str::headline($name),
            required: true
        );

        $stub = file_get_contents(__DIR__ . '/../../stubs/action.stub');
        $stub = str_replace(
            ['{{ NAMESPACE }}', '{{ CLASS_NAME }}', '{{ DISPLAY_NAME }}'],
            [$namespace, $name, $displayName],
            $stub
        );

        file_put_contents($outputFile, $stub);
        info('Action created: ' . $outputFile);

        if ($this->option('register')) {
            $fqcn = $namespace . '\\' . $name;
            $this->call('automata:action:register', ['class_name' => $fqcn]);
        }

        return Command::SUCCESS;
    }
}
