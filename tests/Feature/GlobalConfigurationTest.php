<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\ServiceProvider;
use Yannelli\Attempt\AttemptManager;
use Yannelli\Attempt\AttemptServiceProvider;
use Yannelli\Attempt\Events\AttemptStarted;
use Yannelli\Attempt\Facades\Attempt;
use Yannelli\Attempt\Strategies\ExponentialBackoff;
use Yannelli\Attempt\Strategies\FixedDelay;

it('registers a deferred singleton manager', function () {
    $provider = new AttemptServiceProvider($this->app);

    expect($provider->isDeferred())->toBeTrue()
        ->and($provider->provides())->toBe([AttemptManager::class])
        ->and($this->app->make(AttemptManager::class))->toBe($this->app->make(AttemptManager::class));
});

it('keeps subclasses of the provider eager unless they opt in', function () {
    $subclass = new class($this->app) extends AttemptServiceProvider {};

    expect($subclass->isDeferred())->toBeFalse();
});

it('keeps the deprecated config publishable', function () {
    $this->app->make(AttemptManager::class);

    expect(ServiceProvider::pathsToPublish(AttemptServiceProvider::class, 'attempt-config'))->toHaveCount(1);
});

it('does not resolve the manager when configuring globally', function () {
    Attempt::clearResolvedInstances();
    $this->app->forgetInstance(AttemptManager::class);

    Attempt::neverRetry(LogicException::class);
    Attempt::defineBackoff('custom', FixedDelay::class);
    Attempt::disableEvents();

    expect($this->app->resolved(AttemptManager::class))->toBeFalse();
});

it('never retries globally configured exceptions', function () {
    Attempt::neverRetry([LogicException::class]);
    $calls = 0;

    $result = Attempt::try(function () use (&$calls) {
        $calls++;

        throw new InvalidArgumentException('permanent');
    })
        ->retry(3)
        ->retryIf(fn () => true)
        ->run();

    expect($result->failed())->toBeTrue()
        ->and($calls)->toBe(1);
});

it('always retries globally configured exceptions', function () {
    Attempt::alwaysRetry(RuntimeException::class);
    $calls = 0;

    Attempt::try(function () use (&$calls) {
        $calls++;

        throw new RuntimeException('transient');
    })
        ->retry(2)
        ->retryIf(fn () => false)
        ->run();

    expect($calls)->toBe(3);
});

it('replaces global retry rules when configured again', function () {
    Attempt::neverRetry(RuntimeException::class);
    Attempt::neverRetry([]);
    $calls = 0;

    Attempt::try(function () use (&$calls) {
        $calls++;

        throw new RuntimeException('transient');
    })->retry(1)->run();

    expect($calls)->toBe(2);
});

it('resolves registered backoff strategies with options', function () {
    Attempt::defineBackoff('aggressive', fn (int $base = 50) => new ExponentialBackoff(base: $base, multiplier: 3.0));
    Attempt::defineBackoff('steady', FixedDelay::class);

    $aggressive = Attempt::try(fn () => null)->backoff('aggressive', 10)->getConfiguration()['retryStrategy'];
    $steady = Attempt::try(fn () => null)->backoff('steady', delay: 750)->getConfiguration()['retryStrategy'];

    expect($aggressive->getDelay(3, 0))->toBe(90)
        ->and($steady->getDelay(1, 0))->toBe(750);
});

it('passes positional options to built-in backoff strategies', function () {
    $strategy = Attempt::try(fn () => null)->backoff('linear', 200, 50)->getConfiguration()['retryStrategy'];

    expect($strategy->getDelay(3, 0))->toBe(300);
});

it('reports undefined backoff strategies without changing the strategy', function () {
    Exceptions::fake();

    $strategy = Attempt::try(fn () => null)
        ->fixedDelay(10)
        ->backoff('exponetial')
        ->getConfiguration()['retryStrategy'];

    expect($strategy)->toBeInstanceOf(FixedDelay::class)
        ->and(fn () => Attempt::makeBackoff('exponetial'))
        ->toThrow(InvalidArgumentException::class, 'Backoff strategy [exponetial] is not defined.');

    Exceptions::assertReported(InvalidArgumentException::class);
});

describe('deprecated config file', function () {
    it('honors retry rules unless they are configured statically', function () {
        config(['attempt.never_retry' => [RuntimeException::class]]);
        $calls = 0;
        $attempt = function () use (&$calls) {
            Attempt::try(function () use (&$calls) {
                $calls++;

                throw new RuntimeException('fail');
            })->retry(1)->run();
        };

        $attempt();
        Attempt::neverRetry([]);
        $attempt();

        expect($calls)->toBe(3);
    });

    it('honors customized backoff strategies with named and positional options', function () {
        config(['attempt.backoff_strategies.exponential' => [
            'class' => ExponentialBackoff::class,
            'base' => 100,
            'multiplier' => 3.0,
            'maxDelay' => 30000,
        ]]);

        $configured = Attempt::makeBackoff('exponential');
        $named = Attempt::makeBackoff('exponential', ['base' => 10]);
        $positional = Attempt::makeBackoff('exponential', [20]);

        expect($configured->getDelay(2, 0))->toBe(300)
            ->and($named->getDelay(2, 0))->toBe(30)
            ->and($positional->getDelay(2, 0))->toBe(60);
    });

    it('prefers statically defined backoff strategies', function () {
        Attempt::defineBackoff('exponential', FixedDelay::class);

        expect(Attempt::makeBackoff('exponential'))->toBeInstanceOf(FixedDelay::class);
    });

    it('honors the events toggle unless configured statically', function () {
        config(['attempt.events.enabled' => false]);

        expect(Attempt::eventsDisabled())->toBeTrue();

        Attempt::disableEvents(false);

        expect(Attempt::eventsDisabled())->toBeFalse();
    });
});

it('disables events globally', function () {
    Event::fake();
    Attempt::disableEvents();

    Attempt::try(fn () => 'ok')->withEvents()->run();

    Event::assertNotDispatched(AttemptStarted::class);
});

it('flushes global state', function () {
    Attempt::neverRetry(RuntimeException::class);
    Attempt::defineBackoff('custom', FixedDelay::class);
    Attempt::disableEvents();

    Attempt::flushState();

    expect(Attempt::retryRuleFor(new RuntimeException))->toBeNull()
        ->and(Attempt::eventsDisabled())->toBeFalse()
        ->and(fn () => Attempt::makeBackoff('custom'))->toThrow(InvalidArgumentException::class);
});
