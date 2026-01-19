<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Strategies;

use Throwable;
use Yannelli\Attempt\Contracts\RetryStrategy;

class ExponentialBackoff implements RetryStrategy
{
    public function __construct(
        protected int $base = 100,
        protected float $multiplier = 2.0,
        protected int $maxDelay = 30000
    ) {}

    public function getDelay(int $attempt, int $baseDelay): int
    {
        $base = $this->base > 0 ? $this->base : $baseDelay;
        $delay = (int) ($base * pow($this->multiplier, $attempt - 1));

        return min($delay, $this->maxDelay);
    }

    public function shouldRetry(Throwable $e, int $attempt, int $maxAttempts): bool
    {
        return $attempt < $maxAttempts;
    }
}
