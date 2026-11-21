<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Env;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'attempt:migrate-config')]
class MigrateConfigCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attempt:migrate-config
                            {--delete : Delete config/attempt.php once it has been fully migrated}
                            {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert the deprecated config/attempt.php file into an Attempt service provider';

    /**
     * Settings that could not be migrated automatically.
     *
     * @var array<int, string>
     */
    protected array $unmigrated = [];

    /**
     * The async job properties that need a generated job class.
     *
     * @var array<string, mixed>
     */
    protected array $jobProperties = [];

    /**
     * Execute the console command.
     */
    public function handle(Filesystem $files): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $configPath = $this->laravel->configPath('attempt.php');

        if (! $files->exists($configPath)) {
            $this->components->info('No published config/attempt.php file was found. Nothing to migrate.');

            return self::SUCCESS;
        }

        $namespace = $this->appNamespace();
        $statements = $this->statements((array) require $configPath, require __DIR__.'/../../config/attempt.php');

        if ($this->jobProperties !== [] && $this->writeJob($files, $namespace)) {
            $statements[] = "Attempt::useAsyncJob(\\{$namespace}Jobs\\ProcessAttempt::class);";
        }

        if ($statements === []) {
            $this->components->info('config/attempt.php only contains default values, so no code is needed.');
        } else {
            $this->writeProvider($files, $namespace, $statements);
        }

        foreach ($this->unmigrated as $message) {
            $this->components->warn($message);
        }

        if ($this->unmigrated !== []) {
            $this->components->info('config/attempt.php was kept because some settings still depend on it.');
        } elseif ($this->option('delete') || ($this->input->isInteractive() && $this->confirm('Delete config/attempt.php?', true))) {
            $files->delete($configPath);

            $this->components->info('config/attempt.php deleted.');
        }

        return self::SUCCESS;
    }

    /**
     * Build the static configuration statements for the customized settings.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $defaults
     * @return array<int, string>
     */
    protected function statements(array $config, array $defaults): array
    {
        $statements = [];

        foreach (['never_retry' => 'neverRetry', 'always_retry' => 'alwaysRetry'] as $key => $method) {
            if (! empty($config[$key])) {
                $classes = array_map(fn (string $class): string => '    \\'.ltrim($class, '\\').'::class,', $config[$key]);

                $statements[] = "Attempt::{$method}([\n".implode("\n", $classes)."\n]);";
            }
        }

        foreach ($config['backoff_strategies'] ?? [] as $name => $strategy) {
            if ($strategy === ($defaults['backoff_strategies'][$name] ?? null)) {
                continue;
            }

            if (! isset($strategy['class'])) {
                $this->unmigrated[] = "Backoff strategy [{$name}] has no class, so it was not migrated.";

                continue;
            }

            $class = ltrim($strategy['class'], '\\');
            unset($strategy['class']);

            $arguments = array_map(
                fn (string $parameter, mixed $value): string => "    '{$parameter}' => {$this->export($value)},",
                array_keys($strategy),
                $strategy
            );

            $statements[] = 'Attempt::defineBackoff('.$this->export((string) $name).", fn (mixed ...\$options) => new \\{$class}(...[\n"
                .implode("\n", [...$arguments, '    ...$options,'])
                ."\n]));";
        }

        if (! ($config['events']['enabled'] ?? true)) {
            $statements[] = 'Attempt::disableEvents();';
        }

        $route = array_filter([
            'connection' => $this->literalAsyncValue($config, 'connection', 'ATTEMPT_QUEUE_CONNECTION'),
            'queue' => $this->literalAsyncValue($config, 'queue', 'ATTEMPT_QUEUE'),
        ], fn (mixed $value): bool => $value !== null);

        $customTimeout = array_key_exists('timeout', $config['async'] ?? [])
            && $config['async']['timeout'] !== $defaults['async']['timeout'];

        // Queue routes cover the connection and queue on Laravel 13; anything else needs a job subclass
        if ($customTimeout || ($route !== [] && ! method_exists(QueueManager::class, 'route'))) {
            $this->jobProperties = $route + ($customTimeout ? ['timeout' => $config['async']['timeout']] : []);
        } elseif ($route !== []) {
            $arguments = array_map(
                fn (string $parameter, mixed $value): string => "{$parameter}: {$this->export($value)}",
                array_keys($route),
                $route
            );

            $statements[] = '\\Illuminate\\Support\\Facades\\Queue::route(\\Yannelli\\Attempt\\Builders\\AsyncAttemptJob::class, '.implode(', ', $arguments).');';
        }

        return $statements;
    }

    /**
     * Get an async setting that can be migrated as a literal value.
     *
     * Values supplied by environment variables may differ between environments,
     * so they are reported rather than hard-coded from the current environment.
     *
     * @param  array<string, mixed>  $config
     */
    protected function literalAsyncValue(array $config, string $key, string $variable): mixed
    {
        $value = $config['async'][$key] ?? null;

        if ($value !== null && Env::get($variable) !== null) {
            $this->unmigrated[] = "async.{$key} is set by {$variable}, which may differ between environments, so it was not migrated. Route the job with Queue::route() or Attempt::useAsyncJob() using a value from your own configuration.";

            return null;
        }

        return $value;
    }

    /**
     * Write a job subclass that carries the customized async settings.
     */
    protected function writeJob(Filesystem $files, string $namespace): bool
    {
        $path = $this->laravel->path('Jobs/ProcessAttempt.php');

        $properties = implode("\n\n", array_map(
            fn (string $property, mixed $value): string => ($property === 'timeout' ? '    public ?int $timeout' : "    public \${$property}")." = {$this->export($value)};",
            array_keys($this->jobProperties),
            $this->jobProperties
        ));

        $code = <<<PHP
            <?php

            namespace {$namespace}Jobs;

            use Yannelli\Attempt\Builders\AsyncAttemptJob;

            class ProcessAttempt extends AsyncAttemptJob
            {
            {$properties}
            }

            PHP;

        if ($files->exists($path)) {
            $this->components->warn("[{$path}] already exists. Create a job that extends AsyncAttemptJob, then register it with Attempt::useAsyncJob():");
            $this->line($code);

            $this->unmigrated[] = 'The async settings were printed above instead of being written to a job class.';

            return false;
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $code);

        $this->components->info("Async attempt settings written to [{$path}].");

        return true;
    }

    /**
     * Write the generated service provider and register it with the application.
     *
     * @param  array<int, string>  $statements
     */
    protected function writeProvider(Filesystem $files, string $namespace, array $statements): void
    {
        $namespace .= 'Providers';

        $path = $this->laravel->path('Providers/AttemptServiceProvider.php');
        $body = implode("\n\n", array_map(fn (string $statement): string => preg_replace('/^/m', '        ', $statement), $statements));

        if ($files->exists($path)) {
            $this->components->warn("[{$path}] already exists. Add the following to its boot method:");
            $this->line($body);
            $this->newLine();

            $this->unmigrated[] = "Add the code above to [{$path}] before deleting config/attempt.php.";

            return;
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, <<<PHP
            <?php

            namespace {$namespace};

            use Illuminate\Support\ServiceProvider;
            use Yannelli\Attempt\Facades\Attempt;

            class AttemptServiceProvider extends ServiceProvider
            {
                /**
                 * Bootstrap Attempt's global configuration.
                 */
                public function boot(): void
                {
            {$body}
                }
            }

            PHP);

        $this->components->info("Attempt configuration written to [{$path}].");

        if (! ServiceProvider::addProviderToBootstrapFile("{$namespace}\\AttemptServiceProvider")) {
            $this->unmigrated[] = "Register [{$namespace}\\AttemptServiceProvider] in your application's service providers.";
        }
    }

    /**
     * Get the application's root namespace.
     */
    protected function appNamespace(): string
    {
        try {
            return $this->laravel->getNamespace();
        } catch (RuntimeException) {
            return 'App\\';
        }
    }

    /**
     * Export a configuration value as PHP code.
     */
    protected function export(mixed $value): string
    {
        if (is_array($value)) {
            $items = array_map(
                fn (int|string $key, mixed $item): string => (array_is_list($value) ? '' : $this->export($key).' => ').$this->export($item),
                array_keys($value),
                $value
            );

            return '['.implode(', ', $items).']';
        }

        return $value === null ? 'null' : var_export($value, true);
    }
}
