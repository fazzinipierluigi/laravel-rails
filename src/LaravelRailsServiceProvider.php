<?php

namespace Fazzinipierluigi\LaravelRails;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Fazzinipierluigi\LaravelRails\Console\ExportWorkflow;
use Fazzinipierluigi\LaravelRails\Console\ImportWorkflow;
use Fazzinipierluigi\LaravelRails\Console\InstallAutomataPackage;
use Fazzinipierluigi\LaravelRails\Console\MakeAction;
use Fazzinipierluigi\LaravelRails\Console\RegisterAction;
use Fazzinipierluigi\LaravelRails\Console\RunScheduledTriggers;
use Fazzinipierluigi\LaravelRails\Contracts\PermissionResolverInterface;
use Fazzinipierluigi\LaravelRails\Classes\Resolvers\JustAGateResolver;
use Fazzinipierluigi\LaravelRails\Classes\Resolvers\SpatieResolver;
use Fazzinipierluigi\LaravelRails\Classes\Resolvers\VanillaGateResolver;

class LaravelRailsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-rails');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'laravel-rails');
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        // ── Blade directives ──────────────────────────────────────────

        Blade::directive('laravel_rail_editor', function (string $expression) {
            return "<?php echo view('laravel-rails::editor', ['workflowSlug' => {$expression}])->render(); ?>";
        });

        Blade::directive('laravel_rail_form', function (string $expression) {
            return "<?php echo \\Fazzinipierluigi\\LaravelRails\\Classes\\FormRenderer::render({$expression}); ?>";
        });

        Blade::directive('laravel_rail_trigger', function (string $expression) {
            return "<?php echo \\Fazzinipierluigi\\LaravelRails\\Classes\\TriggerRenderer::render({$expression}); ?>";
        });

        Blade::directive('laravel_rail_execution', function (string $expression) {
            return "<?php echo view('laravel-rails::execution', ['instanceId' => {$expression}])->render(); ?>";
        });

        Blade::directive('laravel_rail_actions', function (string $expression) {
            return "<?php echo \\Fazzinipierluigi\\LaravelRails\\Classes\\ActionsRenderer::render({$expression}); ?>";
        });

        // ── Entity observers for entity_event triggers ────────────────
        // Deferred to after all providers are booted to avoid DB access
        // during early service provider registration.
        $this->app->booted(function () {
            $this->registerEntityObservers();
        });

        // ── Console ───────────────────────────────────────────────────
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-rails.php', 'laravel-rails');

        $this->app->singleton('laravel-rails', function ($app) {
            return new LaravelRails;
        });

        $this->registerPermissionResolver();
    }

    private function registerPermissionResolver(): void
    {
        $this->app->singleton(PermissionResolverInterface::class, function ($app) {
            // 1. User-provided custom resolver
            $custom = config('laravel-rails.permission_resolver');
            if ($custom && class_exists($custom)) {
                return new $custom;
            }

            // 2. just-a-gate (highest priority)
            if (class_exists('Fazzinipierluigi\\JustAGate\\Facades\\JustAGate')) {
                return new JustAGateResolver();
            }

            // 3. spatie/laravel-permission
            if (class_exists('Spatie\\Permission\\PermissionServiceProvider')) {
                return new SpatieResolver();
            }

            // 4. vanilla Laravel Gate
            return new VanillaGateResolver();
        });
    }

    public function provides(): array
    {
        return ['laravel-rails'];
    }

    protected function bootForConsole(): void
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-rails.php' => config_path('laravel-rails.php'),
        ], 'laravel-rails.config');

        $this->publishes([
            __DIR__ . '/../resources/views' => base_path('resources/views/vendor/laravel-rails'),
        ], 'laravel-rails.views');

        $this->publishes([
            __DIR__ . '/../resources/assets' => public_path('vendor/laravel-rails'),
        ], 'laravel-rails.assets');

        $this->commands([
            InstallAutomataPackage::class,
            RegisterAction::class,
            ExportWorkflow::class,
            ImportWorkflow::class,
            MakeAction::class,
            RunScheduledTriggers::class,
        ]);
    }

    private function registerEntityObservers(): void
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('workflow_triggers')) {
                return;
            }

            $registered = [];

            \Fazzinipierluigi\LaravelRails\Models\WorkflowTrigger::active()
                ->ofType(\Fazzinipierluigi\LaravelRails\Models\WorkflowTrigger::TYPE_ENTITY_EVENT)
                ->get()
                ->each(function ($trigger) use (&$registered) {
                    $entityClass = $trigger->configuration['entity_class'] ?? '';
                    if (empty($entityClass) || !class_exists($entityClass)) {
                        return;
                    }
                    if (in_array($entityClass, $registered, true)) {
                        return;
                    }
                    $entityClass::observe(\Fazzinipierluigi\LaravelRails\Observers\WorkflowEntityObserver::class);
                    $registered[] = $entityClass;
                });
        } catch (\Throwable $e) {
            // Table doesn't exist yet (before migrations run) — ignore
        }
    }
}
