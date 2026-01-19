<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Yannelli\Attempt\Events\AllAttemptsFailed;
use Yannelli\Attempt\Events\AttemptFailed;
use Yannelli\Attempt\Events\AttemptStarted;
use Yannelli\Attempt\Events\AttemptSucceeded;
use Yannelli\Attempt\Events\FallbackTriggered;
use Yannelli\Attempt\Events\RetryAttempted;
use Yannelli\Attempt\Facades\Attempt;

it('fires AttemptStarted event', function () {
    Event::fake();

    Attempt::try(fn () => 'test')->thenReturn();

    Event::assertDispatched(AttemptStarted::class);
});

it('fires AttemptSucceeded on success', function () {
    Event::fake();

    Attempt::try(fn () => 'test')->thenReturn();

    Event::assertDispatched(AttemptSucceeded::class, function ($event) {
        return $event->result === 'test';
    });
});

it('fires AttemptFailed on failure', function () {
    Event::fake();

    Attempt::try(fn () => throw new RuntimeException('error'))
        ->quiet()
        ->run();

    Event::assertDispatched(AttemptFailed::class, function ($event) {
        return $event->exception->getMessage() === 'error';
    });
});

it('fires RetryAttempted on retry', function () {
    Event::fake();

    $attempts = 0;
    Attempt::try(function () use (&$attempts) {
        $attempts++;
        if ($attempts < 2) {
            throw new RuntimeException('fail');
        }

        return 'success';
    })
        ->retry(2)
        ->run();

    Event::assertDispatched(RetryAttempted::class, function ($event) {
        return $event->attemptNumber === 2;
    });
});

it('fires FallbackTriggered when fallback is used', function () {
    Event::fake();

    Attempt::try(fn () => throw new RuntimeException('primary failed'))
        ->fallback(fn () => 'fallback')
        ->run();

    Event::assertDispatched(FallbackTriggered::class);
});

it('fires AllAttemptsFailed when everything fails', function () {
    Event::fake();

    Attempt::try(fn () => throw new RuntimeException('primary failed'))
        ->fallback(fn () => throw new RuntimeException('fallback failed'))
        ->quiet()
        ->run();

    Event::assertDispatched(AllAttemptsFailed::class);
});

it('can disable events with withoutEvents', function () {
    Event::fake();

    Attempt::try(fn () => 'test')
        ->withoutEvents()
        ->run();

    Event::assertNotDispatched(AttemptStarted::class);
    Event::assertNotDispatched(AttemptSucceeded::class);
});

it('can re-enable events with withEvents', function () {
    Event::fake();

    Attempt::try(fn () => 'test')
        ->withoutEvents()
        ->withEvents()
        ->run();

    Event::assertDispatched(AttemptStarted::class);
});
