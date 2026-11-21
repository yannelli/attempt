<?php

declare(strict_types=1);

namespace Yannelli\Attempt;

use Illuminate\Contracts\Support\DeferrableProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Yannelli\Attempt\Console\MigrateConfigCommand;

class AttemptServiceProvider extends PackageServiceProvider implements DeferrableProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('attempt')
            // Deprecated: the config file is merged for backwards compatibility and will be removed in 2.0.
            ->hasConfigFile()
            ->hasConsoleCommand(MigrateConfigCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(AttemptManager::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [AttemptManager::class];
    }

    /**
     * Determine if the provider is deferred.
     *
     * The console kernel loads deferred providers, so the command and the
     * publishable config remain available to Artisan. Subclasses stay eager
     * so lifecycle hooks they add keep running on every request; override
     * this method to opt a subclass in to deferral.
     */
    public function isDeferred(): bool
    {
        return static::class === self::class;
    }
}
