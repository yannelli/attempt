<?php

declare(strict_types=1);

use Yannelli\Attempt\Strategies\ArrayDelay;

it('returns delay from array by attempt index', function () {
    $strategy = new ArrayDelay([100, 200, 500, 1000]);

    expect($strategy->getDelay(1, 0))->toBe(100);
    expect($strategy->getDelay(2, 0))->toBe(200);
    expect($strategy->getDelay(3, 0))->toBe(500);
    expect($strategy->getDelay(4, 0))->toBe(1000);
});

it('uses last delay for attempts beyond array length', function () {
    $strategy = new ArrayDelay([100, 200, 300]);

    expect($strategy->getDelay(1, 0))->toBe(100);
    expect($strategy->getDelay(3, 0))->toBe(300);
    expect($strategy->getDelay(4, 0))->toBe(300);
    expect($strategy->getDelay(10, 0))->toBe(300);
});

it('falls back to base delay when array is empty or missing', function () {
    $strategy = new ArrayDelay([]);

    expect($strategy->getDelay(1, 500))->toBe(500);
});

it('should retry when attempts remain', function () {
    $strategy = new ArrayDelay([100, 200]);

    expect($strategy->shouldRetry(new RuntimeException('test'), 1, 3))->toBeTrue();
    expect($strategy->shouldRetry(new RuntimeException('test'), 2, 3))->toBeTrue();
    expect($strategy->shouldRetry(new RuntimeException('test'), 3, 3))->toBeFalse();
});
