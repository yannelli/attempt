<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Contracts;

use Throwable;

interface Fallbackable
{
    /**
     * Handle as a fallback, receiving the original exception.
     */
    public function handleFallback(Throwable $e, mixed ...$input): mixed;

    /**
     * Determine if this fallback should be skipped for the given exception.
     */
    public function shouldSkip(Throwable $e): bool;
}
