<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Strategies;

use Throwable;
use Yannelli\Attempt\Contracts\RetryStrategy;

class ArrayDelay implements RetryStrategy
{
    protected array $delays;

    public function __construct(array $delays = [100, 200, 400])
    {
        $this->delays = array_values($delays);
    }

    public function getDelay(int $attempt, int $baseDelay): int
    {
        $index = min($attempt - 1, count($this->delays) - 1);

        return $this->delays[$index] ?? $baseDelay;
    }

    public function shouldRetry(Throwable $e, int $attempt, int $maxAttempts): bool
    {
        return $attempt < $maxAttempts;
    }
}
