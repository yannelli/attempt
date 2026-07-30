<?php

declare(strict_types=1);

use Yannelli\Attempt\AttemptResult;
use Yannelli\Attempt\Builders\AsyncAttemptJob;
use Yannelli\Attempt\Facades\Attempt;
use Yannelli\Attempt\Strategies\DecorrelatedJitter;
use Yannelli\Attempt\Strategies\ExponentialBackoff;
use Yannelli\Attempt\Strategies\FibonacciBackoff;
use Yannelli\Attempt\Strategies\LinearBackoff;
use Yannelli\Attempt\Tests\Fixtures\InvokableCallable;
use Yannelli\Attempt\Tests\Fixtures\TestAttemptable;

it('can execute via facade', function () {
    $result = Attempt::try(fn () => 'hello world')
        ->thenReturn();

    expect($result)->toBe('hello world');
});

it('can execute closure with input', function () {
    $result = Attempt::try(fn ($a, $b) => $a.' '.$b, 'hello', 'world')
        ->thenReturn();

    expect($result)->toBe('hello world');
});

it('can execute class string', function () {
    $result = Attempt::try(TestAttemptable::class)
        ->thenReturn();

    expect($result)->toBeArray();
    expect($result['handled'])->toBeTrue();
});

it('can execute invokable class', function () {
    $result = Attempt::try(InvokableCallable::class, 'test')
        ->thenReturn();

    expect($result)->toBeArray();
    expect($result['invoked'])->toBeTrue();
    expect($result['input'])->toBe(['test']);
});

it('supports then() for transforming results', function () {
    $result = Attempt::try(fn () => 5)
        ->then(fn ($value) => $value * 2);

    expect($result)->toBe(10);
});

it('supports run() to get full result object', function () {
    $result = Attempt::try(fn () => 'test')
        ->run();

    expect($result)->toBeInstanceOf(AttemptResult::class);
    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('test');
    expect($result->attempts())->toBe(1);
    expect($result->resolvedBy())->toBe('primary');
});

it('tracks attempt count on success', function () {
    $result = Attempt::try(fn () => 'success')
        ->run();

    expect($result->attempts())->toBe(1);
});

it('supports quiet mode', function () {
    $result = Attempt::try(fn () => throw new RuntimeException('error'))
        ->quiet()
        ->run();

    expect($result->failed())->toBeTrue();
    expect($result->value())->toBeNull();
    expect($result->exception())->toBeInstanceOf(RuntimeException::class);
});

it('throws with thenReturnOrFail on failure', function () {
    expect(fn () => Attempt::try(fn () => throw new RuntimeException('boom'))
        ->thenReturnOrFail()
    )->toThrow(RuntimeException::class, 'boom');
});

it('returns value with thenReturnOrFail on success', function () {
    $result = Attempt::try(fn () => 'success')
        ->thenReturnOrFail();

    expect($result)->toBe('success');
});

it('supports array of callables as primary', function () {
    $calls = [];

    $result = Attempt::try([
        function () use (&$calls) {
            $calls[] = 'first';
            throw new RuntimeException('first failed');
        },
        function () use (&$calls) {
            $calls[] = 'second';

            return 'second success';
        },
    ])->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('second success');
    expect($calls)->toBe(['first', 'second']);
});

it('supports facade assertions on a fake', function () {
    Attempt::fake()->forceResult(TestAttemptable::class, 'fake result');

    Attempt::try(TestAttemptable::class)->run();

    Attempt::assertAttemptedTimes(TestAttemptable::class, 1);
    Attempt::assertSucceeded(TestAttemptable::class);
});

it('does not count an unexecuted fake builder as an attempt', function () {
    Attempt::fake();
    Attempt::try(TestAttemptable::class);

    Attempt::assertNeverAttempted(TestAttemptable::class);
    Attempt::assertNothingAttempted();
    Attempt::assertAttemptCount(0);
});

it('preserves async timeout and callback failures', function () {
    $job = new AsyncAttemptJob(
        Attempt::try(fn () => 'success')->getConfiguration(),
        fn () => throw new RuntimeException('callback failed'),
        fn () => null,
        15
    );

    expect($job->timeout)->toBe(15)
        ->and(fn () => $job->handle())
        ->toThrow(RuntimeException::class, 'callback failed');
});

it('resolves configured backoff strategies', function (string $name, string $strategy) {
    $configuration = Attempt::try(fn () => null)
        ->backoff($name)
        ->getConfiguration();

    expect($configuration['retryStrategy'])->toBeInstanceOf($strategy);
})->with([
    'exponential' => ['exponential', ExponentialBackoff::class],
    'linear' => ['linear', LinearBackoff::class],
    'fibonacci' => ['fibonacci', FibonacciBackoff::class],
    'decorrelated jitter' => ['decorrelated_jitter', DecorrelatedJitter::class],
]);
