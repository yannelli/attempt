<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Concerns;

use Closure;
use Yannelli\Attempt\Contracts\RetryStrategy;
use Yannelli\Attempt\Strategies\DecorrelatedJitter;
use Yannelli\Attempt\Strategies\ExponentialBackoff;
use Yannelli\Attempt\Strategies\FibonacciBackoff;
use Yannelli\Attempt\Strategies\FixedDelay;
use Yannelli\Attempt\Strategies\LinearBackoff;

trait HasDelayConfiguration
{
    protected int|array $delay = 0;

    protected ?RetryStrategy $retryStrategy = null;

    protected ?Closure $delayCallback = null;

    protected float $jitter = 0.0;

    /**
     * Set delay(s) between retries.
     */
    public function delay(int|array $milliseconds): static
    {
        $this->delay = $milliseconds;

        return $this;
    }

    /**
     * Use a named backoff strategy.
     */
    public function backoff(string $strategy, mixed ...$options): static
    {
        $config = config("attempt.backoff_strategies.{$strategy}", []);

        if (! empty($config['class'])) {
            $this->retryStrategy = app($config['class'], array_merge($config, $options));
        }

        return $this;
    }

    /**
     * Use exponential backoff strategy.
     */
    public function exponentialBackoff(int $base = 100, int $max = 30000, float $multiplier = 2.0): static
    {
        $this->retryStrategy = new ExponentialBackoff($base, $multiplier, $max);

        return $this;
    }

    /**
     * Use linear backoff strategy.
     */
    public function linearBackoff(int $base = 100, int $increment = 100, int $max = 30000): static
    {
        $this->retryStrategy = new LinearBackoff($base, $increment, $max);

        return $this;
    }

    /**
     * Use fibonacci backoff strategy.
     */
    public function fibonacciBackoff(int $base = 100, int $max = 30000): static
    {
        $this->retryStrategy = new FibonacciBackoff($base, $max);

        return $this;
    }

    /**
     * Use decorrelated jitter backoff strategy.
     */
    public function decorrelatedJitter(int $base = 100, int $max = 30000): static
    {
        $this->retryStrategy = new DecorrelatedJitter($base, $max);

        return $this;
    }

    /**
     * Use fixed delay strategy.
     */
    public function fixedDelay(int $delay = 100): static
    {
        $this->retryStrategy = new FixedDelay($delay);

        return $this;
    }

    /**
     * Set a retry strategy instance.
     */
    public function usingStrategy(RetryStrategy $strategy): static
    {
        $this->retryStrategy = $strategy;

        return $this;
    }

    /**
     * Add jitter (randomization) to delays.
     */
    public function withJitter(float $factor = 0.1): static
    {
        $this->jitter = max(0, min(1, $factor));

        return $this;
    }

    /**
     * Set a custom delay calculation callback.
     */
    public function delayUsing(Closure $callback): static
    {
        $this->delayCallback = $callback;

        return $this;
    }
}
