<?php

declare(strict_types=1);

use Yannelli\Attempt\Strategies\ExponentialBackoff;

it('calculates exponential delay', function () {
    $strategy = new ExponentialBackoff(base: 100, multiplier: 2.0, maxDelay: 30000);

    expect($strategy->getDelay(1, 100))->toBe(100);
    expect($strategy->getDelay(2, 100))->toBe(200);
    expect($strategy->getDelay(3, 100))->toBe(400);
    expect($strategy->getDelay(4, 100))->toBe(800);
});

it('respects max delay', function () {
    $strategy = new ExponentialBackoff(base: 100, multiplier: 2.0, maxDelay: 500);

    expect($strategy->getDelay(1, 100))->toBe(100);
    expect($strategy->getDelay(5, 100))->toBe(500); // 1600 capped to 500
    expect($strategy->getDelay(10, 100))->toBe(500);
});

it('uses base delay when 0', function () {
    $strategy = new ExponentialBackoff(base: 0, multiplier: 2.0, maxDelay: 30000);

    expect($strategy->getDelay(1, 200))->toBe(200);
    expect($strategy->getDelay(2, 200))->toBe(400);
});

it('should retry when attempts remain', function () {
    $strategy = new ExponentialBackoff;

    expect($strategy->shouldRetry(new RuntimeException('test'), 1, 3))->toBeTrue();
    expect($strategy->shouldRetry(new RuntimeException('test'), 2, 3))->toBeTrue();
    expect($strategy->shouldRetry(new RuntimeException('test'), 3, 3))->toBeFalse();
});

it('works with custom multiplier', function () {
    $strategy = new ExponentialBackoff(base: 100, multiplier: 3.0, maxDelay: 100000);

    expect($strategy->getDelay(1, 100))->toBe(100);
    expect($strategy->getDelay(2, 100))->toBe(300);
    expect($strategy->getDelay(3, 100))->toBe(900);
});
