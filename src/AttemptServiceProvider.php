<?php

declare(strict_types=1);

namespace Yannelli\Attempt;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AttemptServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('attempt')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(AttemptManager::class, function ($app) {
            return new AttemptManager($app);
        });
    }
}
