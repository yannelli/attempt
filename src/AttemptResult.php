<?php

declare(strict_types=1);

namespace Yannelli\Attempt;

use Closure;
use Throwable;
use Yannelli\Attempt\Exceptions\AllFallbacksFailed;

readonly class AttemptResult
{
    public function __construct(
        public mixed $value,
        public bool $succeeded,
        public ?Throwable $exception,
        public int $attempts,
        public ?string $resolvedBy = null,
        public array $attemptLog = []
    ) {}

    /**
     * Get the result value.
     */
    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * Check if the attempt succeeded.
     */
    public function succeeded(): bool
    {
        return $this->succeeded;
    }

    /**
     * Check if the attempt failed.
     */
    public function failed(): bool
    {
        return ! $this->succeeded;
    }

    /**
     * Get the exception if failed.
     */
    public function exception(): ?Throwable
    {
        return $this->exception;
    }

    /**
     * Get the number of attempts made.
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * Get what resolved the attempt.
     * Returns: 'primary', 'retry:2', 'fallback:ClassName', 'skipped'
     */
    public function resolvedBy(): ?string
    {
        return $this->resolvedBy;
    }

    /**
     * Transform the value if succeeded.
     */
    public function map(Closure $fn): static
    {
        if ($this->failed()) {
            return $this;
        }

        return new static(
            value: $fn($this->value),
            succeeded: $this->succeeded,
            exception: $this->exception,
            attempts: $this->attempts,
            resolvedBy: $this->resolvedBy,
            attemptLog: $this->attemptLog
        );
    }

    /**
     * Get value or return default.
     */
    public function getOrElse(mixed $default): mixed
    {
        return $this->succeeded ? $this->value : value($default);
    }

    /**
     * Get value or throw exception.
     */
    public function getOrThrow(): mixed
    {
        if ($this->failed()) {
            throw $this->exception ?? new AllFallbacksFailed(
                'Attempt failed with no exception'
            );
        }

        return $this->value;
    }

    /**
     * Execute callback if succeeded.
     */
    public function onSuccess(Closure $fn): static
    {
        if ($this->succeeded) {
            $fn($this->value);
        }

        return $this;
    }

    /**
     * Execute callback if failed.
     */
    public function onFailure(Closure $fn): static
    {
        if ($this->failed()) {
            $fn($this->exception);
        }

        return $this;
    }
}
