<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Yannelli\Attempt\AttemptBuilder;
use Yannelli\Attempt\Contracts\Attemptable;
use Yannelli\Attempt\Contracts\ConfiguresAttempt;
use Yannelli\Attempt\Events\FallbackTriggered;
use Yannelli\Attempt\Facades\Attempt;
use Yannelli\Attempt\Tests\Fixtures\TestFallbackable;

it('uses fallback on primary failure', function () {
    $result = Attempt::try(fn () => throw new RuntimeException('primary failed'))
        ->fallback(fn () => 'fallback success')
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('fallback success');
    expect($result->resolvedBy())->toStartWith('fallback:');
});

it('chains multiple fallbacks', function () {
    $calls = [];

    $result = Attempt::try(function () use (&$calls) {
        $calls[] = 'primary';
        throw new RuntimeException('primary failed');
    })
        ->fallback([
            function () use (&$calls) {
                $calls[] = 'fallback1';
                throw new RuntimeException('fallback1 failed');
            },
            function () use (&$calls) {
                $calls[] = 'fallback2';

                return 'fallback2 success';
            },
            function () use (&$calls) {
                $calls[] = 'fallback3';

                return 'should not reach';
            },
        ])
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('fallback2 success');
    expect($calls)->toBe(['primary', 'fallback1', 'fallback2']);
});

it('reports failure when all fallbacks fail', function () {
    $result = Attempt::try(fn () => throw new RuntimeException('primary failed'))
        ->fallback([
            fn () => throw new RuntimeException('fallback1 failed'),
            fn () => throw new RuntimeException('fallback2 failed'),
        ])
        ->quiet()
        ->run();

    expect($result->failed())->toBeTrue();
});

it('tries fallback after exhausting retries', function () {
    $attempts = 0;

    $result = Attempt::try(function () use (&$attempts) {
        $attempts++;
        throw new RuntimeException('always fail');
    })
        ->retry(2)
        ->fallback(fn () => 'fallback success')
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('fallback success');
    expect($attempts)->toBe(3); // 1 primary + 2 retries
});

it('passes original exception to fallbackable', function () {
    $result = Attempt::try(fn () => throw new RuntimeException('original error'))
        ->fallback(TestFallbackable::class)
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value()['fallback'])->toBeTrue();
    expect($result->value()['originalError'])->toBe('original error');
});

it('can append fallbacks with orFallback', function () {
    $calls = [];

    $result = Attempt::try(function () use (&$calls) {
        $calls[] = 'primary';
        throw new RuntimeException('primary failed');
    })
        ->fallback(function () use (&$calls) {
            $calls[] = 'fallback1';
            throw new RuntimeException('fallback1 failed');
        })
        ->orFallback(function () use (&$calls) {
            $calls[] = 'fallback2';

            return 'success';
        })
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($calls)->toBe(['primary', 'fallback1', 'fallback2']);
});

it('uses fallback class string', function () {
    $result = Attempt::try(fn () => throw new RuntimeException('failed'))
        ->fallback(TestFallbackable::class)
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value()['fallback'])->toBeTrue();
});

it('does not trigger skipped fallbacks', function () {
    Event::fake();
    $triggered = [];

    $skipping = new class extends TestFallbackable
    {
        public function shouldSkip(Throwable $e): bool
        {
            return true;
        }
    };

    $result = Attempt::try(fn () => throw new RuntimeException('primary failed'))
        ->fallback([$skipping::class, fn () => 'used'])
        ->onFallback(function ($context, $fallback) use (&$triggered) {
            $triggered[] = $fallback;
        })
        ->run();

    expect($result->value())->toBe('used')
        ->and($triggered)->toHaveCount(1)
        ->and($triggered[0])->toBeInstanceOf(Closure::class);

    Event::assertDispatchedTimes(FallbackTriggered::class, 1);
});

it('does not duplicate list fallbacks when executed again after throwing', function () {
    $fallbackCalls = 0;

    $builder = Attempt::try([
        fn () => throw new RuntimeException('primary failed'),
        function () use (&$fallbackCalls) {
            $fallbackCalls++;

            throw new RuntimeException('fallback failed');
        },
    ])->throw();

    expect(fn () => $builder->run())->toThrow(RuntimeException::class)
        ->and(fn () => $builder->run())->toThrow(RuntimeException::class)
        ->and($fallbackCalls)->toBe(2);
});

it('does not apply self configuration from fallbacks', function () {
    $fallback = new class implements Attemptable, ConfiguresAttempt
    {
        public static int $configurations = 0;

        public function handle(mixed ...$input): mixed
        {
            return 'fallback';
        }

        public function configureAttempt(AttemptBuilder $attempt): void
        {
            self::$configurations++;
        }
    };

    $result = Attempt::try(fn () => throw new RuntimeException('primary failed'))
        ->fallback($fallback::class)
        ->run();

    expect($result->value())->toBe('fallback')
        ->and($fallback::$configurations)->toBe(0);
});
