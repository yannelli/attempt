<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Strategies;

use Throwable;
use Yannelli\Attempt\Contracts\RetryStrategy;

class FibonacciBackoff implements RetryStrategy
{
    public function __construct(
        protected int $base = 100,
        protected int $maxDelay = 30000
    ) {}

    public function getDelay(int $attempt, int $baseDelay): int
    {
        $base = $this->base > 0 ? $this->base : $baseDelay;

        if ($base <= 0) {
            return 0;
        }

        // Stop advancing once the cap is reached so large attempt numbers cannot overflow
        [$previous, $current] = [0, 1];

        for ($i = 1; $i < $attempt && $base * $current < $this->maxDelay; $i++) {
            [$previous, $current] = [$current, $previous + $current];
        }

        return min($base * $current, $this->maxDelay);
    }

    public function shouldRetry(Throwable $e, int $attempt, int $maxAttempts): bool
    {
        return $attempt < $maxAttempts;
    }
}
