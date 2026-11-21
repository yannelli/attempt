<?php

declare(strict_types=1);

use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Yannelli\Attempt\Builders\AsyncAttemptJob;
use Yannelli\Attempt\Facades\Attempt;

class RoutedAttemptJob extends AsyncAttemptJob
{
    public $connection = 'redis';

    public $queue = 'attempts';

    public ?int $timeout = 120;
}

it('dispatches through the bus with the configured connection, queue, and timeout', function () {
    Bus::fake();

    Attempt::try(fn () => 'done')
        ->async()
        ->onConnection('redis')
        ->onQueue('attempts')
        ->timeout(30)
        ->dispatch();

    Bus::assertDispatched(AsyncAttemptJob::class, fn (AsyncAttemptJob $job) => $job->connection === 'redis'
        && $job->queue === 'attempts'
        && $job->timeout === 30);
});

it('leaves connection and queue to the application by default', function () {
    Bus::fake();

    Attempt::try(fn () => 'done')->async()->dispatch();

    Bus::assertDispatched(AsyncAttemptJob::class, fn (AsyncAttemptJob $job) => $job->connection === null
        && $job->queue === null
        && $job->timeout === 60);
});

it('falls back to the deprecated async config', function () {
    Bus::fake();
    config([
        'attempt.async.connection' => 'redis',
        'attempt.async.queue' => 'legacy',
        'attempt.async.timeout' => null,
    ]);

    Attempt::try(fn () => 'done')->async()->onQueue('explicit')->dispatch();

    Bus::assertDispatched(AsyncAttemptJob::class, fn (AsyncAttemptJob $job) => $job->connection === 'redis'
        && $job->queue === 'explicit'
        && $job->timeout === null);
});

it('honors laravel queue routes', function () {
    Queue::fake();
    Queue::route(AsyncAttemptJob::class, queue: 'attempts');

    Attempt::try(fn () => 'done')->async()->dispatch();

    Queue::assertPushedOn('attempts', AsyncAttemptJob::class);
})->skip(! method_exists(QueueManager::class, 'route'), 'Queue routing requires Laravel 13.');

it('dispatches a custom job class with its own defaults', function () {
    Bus::fake();
    config(['attempt.async.connection' => 'legacy', 'attempt.async.queue' => 'legacy']);
    Attempt::useAsyncJob(RoutedAttemptJob::class);

    Attempt::try(fn () => 'done')->async()->dispatch();

    Bus::assertDispatched(RoutedAttemptJob::class, fn (RoutedAttemptJob $job) => $job->connection === 'redis'
        && $job->queue === 'attempts'
        && $job->timeout === 120);
});

it('lets dispatch options override a custom job class', function () {
    Bus::fake();
    Attempt::useAsyncJob(RoutedAttemptJob::class);

    Attempt::try(fn () => 'done')->async()->onConnection('sqs')->onQueue('high')->timeout(5)->dispatch();

    Bus::assertDispatched(RoutedAttemptJob::class, fn (RoutedAttemptJob $job) => $job->connection === 'sqs'
        && $job->queue === 'high'
        && $job->timeout === 5);
});

it('rejects async job classes that do not extend the attempt job', function () {
    Attempt::useAsyncJob(stdClass::class);
})->throws(InvalidArgumentException::class, 'must extend');
