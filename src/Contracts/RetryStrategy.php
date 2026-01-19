<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Contracts;

use Throwable;

interface RetryStrategy
{
    /**
     * Calculate the delay before the next retry.
     *
     * @param  int  $attempt  The current attempt number (1-based)
     * @param  int  $baseDelay  The configured base delay in milliseconds
     * @return int The delay in milliseconds
     */
    public function getDelay(int $attempt, int $baseDelay): int;

    /**
     * Determine if another retry should be attempted.
     */
    public function shouldRetry(Throwable $e, int $attempt, int $maxAttempts): bool;
}
