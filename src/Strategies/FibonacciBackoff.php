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
        $fibonacci = $this->fibonacci($attempt);
        $delay = $base * $fibonacci;

        return min($delay, $this->maxDelay);
    }

    public function shouldRetry(Throwable $e, int $attempt, int $maxAttempts): bool
    {
        return $attempt < $maxAttempts;
    }

    protected function fibonacci(int $n): int
    {
        if ($n <= 1) {
            return 1;
        }

        $fib = [1, 1];
        for ($i = 2; $i < $n; $i++) {
            $fib[$i] = $fib[$i - 1] + $fib[$i - 2];
        }

        return $fib[$n - 1];
    }
}
