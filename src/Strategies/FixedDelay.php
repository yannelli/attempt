<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Strategies;

use Throwable;
use Yannelli\Attempt\Contracts\RetryStrategy;

class FixedDelay implements RetryStrategy
{
    public function __construct(
        protected int $delay = 100
    ) {}

    public function getDelay(int $attempt, int $baseDelay): int
    {
        return $this->delay > 0 ? $this->delay : $baseDelay;
    }

    public function shouldRetry(Throwable $e, int $attempt, int $maxAttempts): bool
    {
        return $attempt < $maxAttempts;
    }
}
