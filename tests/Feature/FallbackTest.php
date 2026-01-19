<?php

declare(strict_types=1);

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
