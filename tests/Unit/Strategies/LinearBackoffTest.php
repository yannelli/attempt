<?php

declare(strict_types=1);

use Yannelli\Attempt\Strategies\LinearBackoff;

it('calculates linear delay', function () {
    $strategy = new LinearBackoff(base: 100, increment: 100, maxDelay: 30000);

    expect($strategy->getDelay(1, 100))->toBe(100);
    expect($strategy->getDelay(2, 100))->toBe(200);
    expect($strategy->getDelay(3, 100))->toBe(300);
    expect($strategy->getDelay(4, 100))->toBe(400);
});

it('respects max delay', function () {
    $strategy = new LinearBackoff(base: 100, increment: 100, maxDelay: 250);

    expect($strategy->getDelay(1, 100))->toBe(100);
    expect($strategy->getDelay(2, 100))->toBe(200);
    expect($strategy->getDelay(3, 100))->toBe(250); // Capped
    expect($strategy->getDelay(10, 100))->toBe(250);
});

it('uses base delay when 0', function () {
    $strategy = new LinearBackoff(base: 0, increment: 50, maxDelay: 30000);

    expect($strategy->getDelay(1, 200))->toBe(200);
    expect($strategy->getDelay(2, 200))->toBe(250);
});

it('should retry when attempts remain', function () {
    $strategy = new LinearBackoff;

    expect($strategy->shouldRetry(new RuntimeException('test'), 1, 3))->toBeTrue();
    expect($strategy->shouldRetry(new RuntimeException('test'), 2, 3))->toBeTrue();
    expect($strategy->shouldRetry(new RuntimeException('test'), 3, 3))->toBeFalse();
});
