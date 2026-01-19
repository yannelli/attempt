<?php

declare(strict_types=1);

use Yannelli\Attempt\Strategies\FixedDelay;

it('returns fixed delay', function () {
    $strategy = new FixedDelay(delay: 500);

    expect($strategy->getDelay(1, 100))->toBe(500);
    expect($strategy->getDelay(2, 100))->toBe(500);
    expect($strategy->getDelay(5, 100))->toBe(500);
    expect($strategy->getDelay(10, 100))->toBe(500);
});

it('falls back to base delay when delay is 0', function () {
    $strategy = new FixedDelay(delay: 0);

    expect($strategy->getDelay(1, 200))->toBe(200);
    expect($strategy->getDelay(5, 200))->toBe(200);
});

it('should retry when attempts remain', function () {
    $strategy = new FixedDelay();

    expect($strategy->shouldRetry(new RuntimeException('test'), 1, 3))->toBeTrue();
    expect($strategy->shouldRetry(new RuntimeException('test'), 2, 3))->toBeTrue();
    expect($strategy->shouldRetry(new RuntimeException('test'), 3, 3))->toBeFalse();
});
