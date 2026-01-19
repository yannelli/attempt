<?php

declare(strict_types=1);

use Yannelli\Attempt\Strategies\FibonacciBackoff;

it('calculates fibonacci delay', function () {
    $strategy = new FibonacciBackoff(base: 100, maxDelay: 100000);

    // Fibonacci: 1, 1, 2, 3, 5, 8, 13...
    expect($strategy->getDelay(1, 100))->toBe(100);   // 1 * 100
    expect($strategy->getDelay(2, 100))->toBe(100);   // 1 * 100
    expect($strategy->getDelay(3, 100))->toBe(200);   // 2 * 100
    expect($strategy->getDelay(4, 100))->toBe(300);   // 3 * 100
    expect($strategy->getDelay(5, 100))->toBe(500);   // 5 * 100
    expect($strategy->getDelay(6, 100))->toBe(800);   // 8 * 100
});

it('respects max delay', function () {
    $strategy = new FibonacciBackoff(base: 100, maxDelay: 400);

    expect($strategy->getDelay(1, 100))->toBe(100);
    expect($strategy->getDelay(4, 100))->toBe(300);
    expect($strategy->getDelay(5, 100))->toBe(400);  // Capped
    expect($strategy->getDelay(10, 100))->toBe(400);
});

it('should retry when attempts remain', function () {
    $strategy = new FibonacciBackoff();

    expect($strategy->shouldRetry(new RuntimeException('test'), 1, 3))->toBeTrue();
    expect($strategy->shouldRetry(new RuntimeException('test'), 2, 3))->toBeTrue();
    expect($strategy->shouldRetry(new RuntimeException('test'), 3, 3))->toBeFalse();
});
