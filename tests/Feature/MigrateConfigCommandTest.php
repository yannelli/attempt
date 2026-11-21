<?php

declare(strict_types=1);

use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->sandbox = sys_get_temp_dir().'/attempt-migrate-'.Str::random(8);

    File::ensureDirectoryExists("{$this->sandbox}/config");
    File::ensureDirectoryExists("{$this->sandbox}/bootstrap");
    File::put("{$this->sandbox}/bootstrap/providers.php", "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n];\n");

    $this->app->useConfigPath("{$this->sandbox}/config");
    $this->app->useAppPath("{$this->sandbox}/app");
    $this->app->useBootstrapPath("{$this->sandbox}/bootstrap");

    $this->publishConfig = function (Closure $customize): void {
        $config = $customize(require __DIR__.'/../../config/attempt.php');

        File::put("{$this->sandbox}/config/attempt.php", '<?php return '.var_export($config, true).';');
    };
});

afterEach(function () {
    File::deleteDirectory($this->sandbox);
});

it('does nothing without a published config file', function () {
    $this->artisan('attempt:migrate-config')
        ->expectsOutputToContain('Nothing to migrate')
        ->assertSuccessful();
});

it('generates and registers a provider for customized settings', function () {
    ($this->publishConfig)(function (array $config): array {
        $config['never_retry'] = [LogicException::class];
        $config['always_retry'] = [RuntimeException::class];
        $config['backoff_strategies']['exponential']['base'] = 250;
        $config['events']['enabled'] = false;
        $config['async']['queue'] = 'attempts';

        return $config;
    });

    $this->artisan('attempt:migrate-config', ['--delete' => true])->assertSuccessful();

    $provider = File::get("{$this->sandbox}/app/Providers/AttemptServiceProvider.php");

    expect($provider)
        ->toContain("Attempt::neverRetry([\n            \\LogicException::class,\n        ]);")
        ->toContain("Attempt::alwaysRetry([\n            \\RuntimeException::class,\n        ]);")
        ->toContain("Attempt::defineBackoff('exponential', fn (mixed ...\$options) => new \\Yannelli\\Attempt\\Strategies\\ExponentialBackoff(...[")
        ->toContain("'base' => 250,")
        ->toContain('Attempt::disableEvents();')
        ->not->toContain("'linear'")
        ->and(File::get("{$this->sandbox}/bootstrap/providers.php"))->toContain('App\Providers\AttemptServiceProvider::class')
        ->and(File::exists("{$this->sandbox}/config/attempt.php"))->toBeFalse();

    if (method_exists(QueueManager::class, 'route')) {
        expect($provider)->toContain("Queue::route(\\Yannelli\\Attempt\\Builders\\AsyncAttemptJob::class, queue: 'attempts');")
            ->and(File::exists("{$this->sandbox}/app/Jobs/ProcessAttempt.php"))->toBeFalse();
    } else {
        expect($provider)->toContain('Attempt::useAsyncJob(\\App\\Jobs\\ProcessAttempt::class);')
            ->and(File::get("{$this->sandbox}/app/Jobs/ProcessAttempt.php"))->toContain("public \$queue = 'attempts';");
    }

    $file = tempnam(sys_get_temp_dir(), 'attempt-provider');
    File::put($file, str_replace('namespace App\Providers;', 'namespace Attempt\Generated'.Str::random(8).';', $provider));

    expect(shell_exec('php -l '.escapeshellarg($file)))->toContain('No syntax errors');

    File::delete($file);
});

it('generates a job class for a customized timeout', function () {
    ($this->publishConfig)(function (array $config): array {
        $config['async']['connection'] = 'redis';
        $config['async']['timeout'] = 300;

        return $config;
    });

    $this->artisan('attempt:migrate-config', ['--delete' => true])->assertSuccessful();

    $job = File::get("{$this->sandbox}/app/Jobs/ProcessAttempt.php");

    expect($job)
        ->toContain('class ProcessAttempt extends AsyncAttemptJob')
        ->toContain("public \$connection = 'redis';")
        ->toContain('public ?int $timeout = 300;')
        ->and(File::get("{$this->sandbox}/app/Providers/AttemptServiceProvider.php"))
        ->toContain('Attempt::useAsyncJob(\\App\\Jobs\\ProcessAttempt::class);')
        ->not->toContain('Queue::route')
        ->and(File::exists("{$this->sandbox}/config/attempt.php"))->toBeFalse();

    $file = tempnam(sys_get_temp_dir(), 'attempt-job');
    File::put($file, $job);

    expect(shell_exec('php -l '.escapeshellarg($file)))->toContain('No syntax errors');

    File::delete($file);
});

it('keeps the config file when async settings come from the environment', function () {
    $_SERVER['ATTEMPT_QUEUE'] = 'from-env';

    try {
        ($this->publishConfig)(function (array $config): array {
            $config['never_retry'] = [LogicException::class];
            $config['async']['queue'] = 'from-env';

            return $config;
        });

        $this->artisan('attempt:migrate-config', ['--delete' => true])
            ->expectsOutputToContain('async.queue is set by ATTEMPT_QUEUE')
            ->assertSuccessful();
    } finally {
        unset($_SERVER['ATTEMPT_QUEUE']);
    }

    expect(File::exists("{$this->sandbox}/config/attempt.php"))->toBeTrue()
        ->and(File::get("{$this->sandbox}/app/Providers/AttemptServiceProvider.php"))->not->toContain('from-env');
});

it('prints the code instead of overwriting an existing provider', function () {
    ($this->publishConfig)(function (array $config): array {
        $config['never_retry'] = [LogicException::class];

        return $config;
    });

    File::ensureDirectoryExists("{$this->sandbox}/app/Providers");
    File::put("{$this->sandbox}/app/Providers/AttemptServiceProvider.php", 'existing');

    $this->artisan('attempt:migrate-config', ['--delete' => true])
        ->expectsOutputToContain('Attempt::neverRetry([')
        ->assertSuccessful();

    expect(File::get("{$this->sandbox}/app/Providers/AttemptServiceProvider.php"))->toBe('existing')
        ->and(File::exists("{$this->sandbox}/config/attempt.php"))->toBeTrue();
});

it('deletes a default config file after confirmation', function () {
    ($this->publishConfig)(fn (array $config): array => $config);

    $this->artisan('attempt:migrate-config')
        ->expectsConfirmation('Delete config/attempt.php?', 'yes')
        ->assertSuccessful();

    expect(File::exists("{$this->sandbox}/config/attempt.php"))->toBeFalse()
        ->and(File::exists("{$this->sandbox}/app/Providers/AttemptServiceProvider.php"))->toBeFalse();
});

it('refuses to run in production without force', function () {
    $this->app['env'] = 'production';
    ($this->publishConfig)(fn (array $config): array => $config);

    $this->artisan('attempt:migrate-config', ['--no-interaction' => true, '--delete' => true])->assertFailed();

    expect(File::exists("{$this->sandbox}/config/attempt.php"))->toBeTrue();

    $this->artisan('attempt:migrate-config', ['--force' => true, '--delete' => true])->assertSuccessful();

    expect(File::exists("{$this->sandbox}/config/attempt.php"))->toBeFalse();
});
