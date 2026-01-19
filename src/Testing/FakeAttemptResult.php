<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Testing;

use Throwable;
use Yannelli\Attempt\AttemptResult;

readonly class FakeAttemptResult extends AttemptResult
{
    /**
     * Create a successful fake result.
     */
    public static function success(mixed $value = null, int $attempts = 1): static
    {
        return new static(
            value: $value,
            succeeded: true,
            exception: null,
            attempts: $attempts,
            resolvedBy: 'primary'
        );
    }

    /**
     * Create a failed fake result.
     */
    public static function failure(?Throwable $exception = null, int $attempts = 1): static
    {
        return new static(
            value: null,
            succeeded: false,
            exception: $exception ?? new \RuntimeException('Fake failure'),
            attempts: $attempts,
            resolvedBy: null
        );
    }

    /**
     * Create a fake result from a retry.
     */
    public static function fromRetry(mixed $value, int $attemptNumber): static
    {
        return new static(
            value: $value,
            succeeded: true,
            exception: null,
            attempts: $attemptNumber,
            resolvedBy: "retry:{$attemptNumber}"
        );
    }

    /**
     * Create a fake result from a fallback.
     */
    public static function fromFallback(mixed $value, string $fallbackName, int $attempts = 1): static
    {
        return new static(
            value: $value,
            succeeded: true,
            exception: null,
            attempts: $attempts,
            resolvedBy: "fallback:{$fallbackName}"
        );
    }

    /**
     * Create a skipped fake result.
     */
    public static function skipped(): static
    {
        return new static(
            value: null,
            succeeded: true,
            exception: null,
            attempts: 0,
            resolvedBy: 'skipped'
        );
    }
}
