<?php

declare(strict_types=1);

namespace Yannelli\Attempt;

use Carbon\Carbon;
use Throwable;

class AttemptContext
{
    public readonly Carbon $startedAt;

    public int $attemptNumber = 0;

    public bool $succeeded = false;

    public ?Throwable $lastException = null;

    public ?string $resolvedBy = null;

    public array $attemptLog = [];

    public function __construct(
        public readonly int $maxAttempts,
        public readonly array $input
    ) {
        $this->startedAt = Carbon::now();
    }

    /**
     * Get elapsed time in milliseconds.
     */
    public function elapsed(): float
    {
        return $this->startedAt->diffInMilliseconds(Carbon::now());
    }

    /**
     * Check if this is a retry attempt.
     */
    public function isRetry(): bool
    {
        return $this->attemptNumber > 1;
    }

    /**
     * Check if we're in fallback mode.
     */
    public function isFallback(): bool
    {
        return str_starts_with($this->resolvedBy ?? '', 'fallback:');
    }

    /**
     * Record an attempt.
     */
    public function recordAttempt(string $stage, bool $success, ?Throwable $e = null): void
    {
        $this->attemptLog[] = [
            'stage' => $stage,
            'success' => $success,
            'exception' => $e?->getMessage(),
            'timestamp' => Carbon::now()->toIso8601String(),
        ];
    }
}
