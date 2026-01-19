<?php

namespace Yannelli\Attempt\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Yannelli\Attempt\AttemptServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            AttemptServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
