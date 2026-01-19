<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Strategies;

use Throwable;
use Yannelli\Attempt\Contracts\RetryStrategy;

/**
 * AWS-style decorrelated jitter backoff strategy.
 *
 * @see https://aws.amazon.com/blogs/architecture/exponential-backoff-and-jitter/
 */
class DecorrelatedJitter implements RetryStrategy
{
    protected int $previousDelay;

    public function __construct(
        protected int $base = 100,
        protected int $maxDelay = 30000
    ) {
        $this->previousDelay = $this->base;
    }

    public function getDelay(int $attempt, int $baseDelay): int
    {
        $base = $this->base > 0 ? $this->base : $baseDelay;

        if ($attempt === 1) {
            $this->previousDelay = $base;

            return $base;
        }

        $min = $base;
        $max = $this->previousDelay * 3;

        $delay = random_int($min, max($min, $max));
        $delay = min($delay, $this->maxDelay);

        $this->previousDelay = $delay;

        return $delay;
    }

    public function shouldRetry(Throwable $e, int $attempt, int $maxAttempts): bool
    {
        return $attempt < $maxAttempts;
    }
}
