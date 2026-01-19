<?php

declare(strict_types=1);

use Yannelli\Attempt\Facades\Attempt;

it('retries on failure', function () {
    $attempts = 0;

    $result = Attempt::try(function () use (&$attempts) {
        $attempts++;
        if ($attempts < 3) {
            throw new RuntimeException('fail');
        }

        return 'success';
    })
        ->retry(3)
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('success');
    expect($result->attempts())->toBe(3);
    expect($result->resolvedBy())->toBe('retry:3');
});

it('fails after exhausting retries', function () {
    $attempts = 0;

    $result = Attempt::try(function () use (&$attempts) {
        $attempts++;
        throw new RuntimeException('always fail');
    })
        ->retry(3)
        ->quiet()
        ->run();

    expect($result->failed())->toBeTrue();
    expect($attempts)->toBe(4); // initial + 3 retries
});

it('supports delay between retries', function () {
    $times = [];

    $result = Attempt::try(function () use (&$times) {
        $times[] = microtime(true);
        if (count($times) < 3) {
            throw new RuntimeException('fail');
        }

        return 'success';
    })
        ->retry(3)
        ->delay(50) // 50ms delay
        ->run();

    expect($result->succeeded())->toBeTrue();

    // Check that there's some delay between attempts
    if (count($times) >= 2) {
        $diff = ($times[1] - $times[0]) * 1000; // convert to ms
        expect($diff)->toBeGreaterThan(30); // Allow some tolerance
    }
});

it('supports array delays', function () {
    $attempts = 0;

    $result = Attempt::try(function () use (&$attempts) {
        $attempts++;
        if ($attempts < 3) {
            throw new RuntimeException('fail');
        }

        return 'success';
    })
        ->retry(3)
        ->delay([10, 20, 30])
        ->run();

    expect($result->succeeded())->toBeTrue();
});

it('supports exponential backoff', function () {
    $attempts = 0;

    $result = Attempt::try(function () use (&$attempts) {
        $attempts++;
        if ($attempts < 3) {
            throw new RuntimeException('fail');
        }

        return 'success';
    })
        ->retry(3)
        ->exponentialBackoff(base: 10, max: 100)
        ->run();

    expect($result->succeeded())->toBeTrue();
});

it('supports linear backoff', function () {
    $attempts = 0;

    $result = Attempt::try(function () use (&$attempts) {
        $attempts++;
        if ($attempts < 3) {
            throw new RuntimeException('fail');
        }

        return 'success';
    })
        ->retry(3)
        ->linearBackoff(base: 10, increment: 10)
        ->run();

    expect($result->succeeded())->toBeTrue();
});

it('supports jitter', function () {
    $attempts = 0;

    $result = Attempt::try(function () use (&$attempts) {
        $attempts++;
        if ($attempts < 2) {
            throw new RuntimeException('fail');
        }

        return 'success';
    })
        ->retry(2)
        ->delay(50)
        ->withJitter(0.5) // 50% jitter
        ->run();

    expect($result->succeeded())->toBeTrue();
});

it('supports retryIf condition', function () {
    $attempts = 0;

    $result = Attempt::try(function () use (&$attempts) {
        $attempts++;
        throw new RuntimeException('always fail');
    })
        ->retry(5)
        ->retryIf(fn ($e, $attempt) => $attempt < 2)
        ->quiet()
        ->run();

    expect($attempts)->toBe(2); // Only 2 attempts because condition limits it
});

it('supports retryUnless condition', function () {
    $attempts = 0;

    $result = Attempt::try(function () use (&$attempts) {
        $attempts++;
        throw new RuntimeException('always fail');
    })
        ->retry(5)
        ->retryUnless(fn ($e) => $e->getMessage() === 'always fail')
        ->quiet()
        ->run();

    expect($attempts)->toBe(1); // Only 1 attempt because exception matches
});

it('supports custom delay callback', function () {
    $delays = [];

    $result = Attempt::try(function () use (&$delays) {
        if (count($delays) < 2) {
            throw new RuntimeException('fail');
        }

        return 'success';
    })
        ->retry(3)
        ->delayUsing(function ($attempt) use (&$delays) {
            $delay = $attempt * 10;
            $delays[] = $delay;

            return $delay;
        })
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($delays)->toBe([10, 20]);
});
