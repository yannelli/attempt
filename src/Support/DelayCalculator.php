<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Support;

use Closure;
use Throwable;
use Yannelli\Attempt\Contracts\RetryStrategy;

class DelayCalculator
{
    public function __construct(
        protected int|array $delay = 0,
        protected ?RetryStrategy $strategy = null,
        protected ?Closure $callback = null,
        protected float $jitter = 0.0
    ) {}

    /**
     * Calculate the delay for the given attempt.
     */
    public function calculate(int $attempt, ?Throwable $exception = null): int
    {
        $baseDelay = is_array($this->delay) ? ($this->delay[0] ?? 0) : $this->delay;

        // Custom callback takes precedence
        if ($this->callback !== null) {
            $delay = (int) ($this->callback)($attempt, $exception);

            return $this->applyJitter($delay);
        }

        // Array of explicit delays
        if (is_array($this->delay)) {
            $index = min($attempt - 1, count($this->delay) - 1);
            $delay = $this->delay[$index] ?? $baseDelay;

            return $this->applyJitter($delay);
        }

        // Strategy-based calculation
        if ($this->strategy !== null) {
            $delay = $this->strategy->getDelay($attempt, $baseDelay);

            return $this->applyJitter($delay);
        }

        // Fixed delay
        return $this->applyJitter($this->delay);
    }

    /**
     * Apply jitter to a delay value.
     */
    protected function applyJitter(int $delay): int
    {
        if ($this->jitter <= 0 || $delay <= 0) {
            return max(0, $delay);
        }

        $jitterAmount = (int) ($delay * $this->jitter);

        if ($jitterAmount <= 0) {
            return max(0, $delay);
        }

        $jitter = random_int(-$jitterAmount, $jitterAmount);

        return max(0, $delay + $jitter);
    }

    /**
     * Create a new calculator with updated parameters.
     */
    public function withDelay(int|array $delay): static
    {
        return new static($delay, $this->strategy, $this->callback, $this->jitter);
    }

    /**
     * Create a new calculator with a strategy.
     */
    public function withStrategy(RetryStrategy $strategy): static
    {
        return new static($this->delay, $strategy, $this->callback, $this->jitter);
    }

    /**
     * Create a new calculator with a callback.
     */
    public function withCallback(Closure $callback): static
    {
        return new static($this->delay, $this->strategy, $callback, $this->jitter);
    }

    /**
     * Create a new calculator with jitter.
     */
    public function withJitter(float $jitter): static
    {
        return new static($this->delay, $this->strategy, $this->callback, $jitter);
    }
}
