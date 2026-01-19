<?php

declare(strict_types=1);

use Yannelli\Attempt\Facades\Attempt;

it('runs finally callbacks on success', function () {
    $finallyCalled = false;

    Attempt::try(fn () => 'test')
        ->finally(function () use (&$finallyCalled) {
            $finallyCalled = true;
        })
        ->thenReturn();

    expect($finallyCalled)->toBeTrue();
});

it('runs finally callbacks on failure', function () {
    $finallyCalled = false;

    Attempt::try(fn () => throw new RuntimeException('error'))
        ->finally(function () use (&$finallyCalled) {
            $finallyCalled = true;
        })
        ->quiet()
        ->run();

    expect($finallyCalled)->toBeTrue();
});

it('runs multiple finally callbacks', function () {
    $calls = [];

    Attempt::try(fn () => 'test')
        ->finally(function () use (&$calls) {
            $calls[] = 'first';
        })
        ->finally(function () use (&$calls) {
            $calls[] = 'second';
        })
        ->thenReturn();

    expect($calls)->toBe(['first', 'second']);
});

it('runs onSuccess callback on success', function () {
    $value = null;

    Attempt::try(fn () => 'success value')
        ->onSuccess(function ($ctx, $result) use (&$value) {
            $value = $result;
        })
        ->thenReturn();

    expect($value)->toBe('success value');
});

it('does not run onSuccess callback on failure', function () {
    $called = false;

    Attempt::try(fn () => throw new RuntimeException('error'))
        ->onSuccess(function () use (&$called) {
            $called = true;
        })
        ->quiet()
        ->run();

    expect($called)->toBeFalse();
});

it('runs onFailure callback on failure', function () {
    $exception = null;

    Attempt::try(fn () => throw new RuntimeException('test error'))
        ->onFailure(function ($ctx, $e) use (&$exception) {
            $exception = $e;
        })
        ->quiet()
        ->run();

    expect($exception)->toBeInstanceOf(RuntimeException::class);
    expect($exception->getMessage())->toBe('test error');
});

it('does not run onFailure callback on success', function () {
    $called = false;

    Attempt::try(fn () => 'success')
        ->onFailure(function () use (&$called) {
            $called = true;
        })
        ->thenReturn();

    expect($called)->toBeFalse();
});

it('runs onRetry callback when retrying', function () {
    $retryAttempts = [];

    Attempt::try(function () use (&$retryAttempts) {
        if (count($retryAttempts) < 2) {
            throw new RuntimeException('fail');
        }

        return 'success';
    })
        ->retry(3)
        ->onRetry(function ($ctx) use (&$retryAttempts) {
            $retryAttempts[] = $ctx->attemptNumber;
        })
        ->run();

    expect($retryAttempts)->toBe([2, 3]);
});

it('runs onFallback callback when using fallback', function () {
    $fallbackUsed = null;

    Attempt::try(fn () => throw new RuntimeException('primary failed'))
        ->fallback(fn () => 'fallback result')
        ->onFallback(function ($ctx, $fallback) use (&$fallbackUsed) {
            $fallbackUsed = $fallback;
        })
        ->run();

    expect($fallbackUsed)->toBeInstanceOf(Closure::class);
});

it('runs catch handlers for exceptions', function () {
    $caughtMessage = null;

    Attempt::try(fn () => throw new RuntimeException('test error'))
        ->catch(function ($e) use (&$caughtMessage) {
            $caughtMessage = $e->getMessage();
        })
        ->quiet()
        ->run();

    expect($caughtMessage)->toBe('test error');
});

it('runs catch handlers for specific exception types', function () {
    $invalidArgCaught = false;
    $runtimeCaught = false;

    Attempt::try(fn () => throw new InvalidArgumentException('invalid'))
        ->catch(RuntimeException::class, function () use (&$runtimeCaught) {
            $runtimeCaught = true;
        })
        ->catch(InvalidArgumentException::class, function () use (&$invalidArgCaught) {
            $invalidArgCaught = true;
        })
        ->quiet()
        ->run();

    expect($invalidArgCaught)->toBeTrue();
    expect($runtimeCaught)->toBeFalse();
});
