<?php

declare(strict_types=1);

use Yannelli\Attempt\Strategies\DecorrelatedJitter;

it('returns base delay on first attempt', function () {
    $strategy = new DecorrelatedJitter(base: 100, maxDelay: 30000);

    expect($strategy->getDelay(1, 100))->toBe(100);
});

it('returns delay within expected range on subsequent attempts', function () {
    $strategy = new DecorrelatedJitter(base: 100, maxDelay: 30000);

    // First attempt sets previousDelay to 100
    $delay1 = $strategy->getDelay(1, 100);
    expect($delay1)->toBe(100);

    // Second attempt: random between base (100) and previousDelay * 3 (300)
    $delay2 = $strategy->getDelay(2, 100);
    expect($delay2)->toBeGreaterThanOrEqual(100);
    expect($delay2)->toBeLessThanOrEqual(300);
});

it('respects max delay', function () {
    $strategy = new DecorrelatedJitter(base: 100, maxDelay: 150);

    $strategy->getDelay(1, 100); // Set up first attempt

    // All subsequent attempts should be capped at maxDelay
    for ($i = 0; $i < 10; $i++) {
        $delay = $strategy->getDelay(2 + $i, 100);
        expect($delay)->toBeLessThanOrEqual(150);
    }
});

it('should retry when attempts remain', function () {
    $strategy = new DecorrelatedJitter();

    expect($strategy->shouldRetry(new RuntimeException('test'), 1, 3))->toBeTrue();
    expect($strategy->shouldRetry(new RuntimeException('test'), 2, 3))->toBeTrue();
    expect($strategy->shouldRetry(new RuntimeException('test'), 3, 3))->toBeFalse();
});
