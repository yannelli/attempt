<?php

namespace Yannelli\Attempt\Tests;

use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Yannelli\Attempt\AttemptServiceProvider;
use Yannelli\Attempt\Facades\Attempt;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            AttemptServiceProvider::class,
            AiServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('ai.providers.openai.key', 'fake-key');
    }

    protected function tearDown(): void
    {
        Attempt::flushState();

        parent::tearDown();
    }
}
