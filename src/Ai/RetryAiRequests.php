<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Ai;

use Closure;
use Laravel\Ai\PendingStep;
use Yannelli\Attempt\AttemptBuilder;

/**
 * Agent middleware that retries transient provider failures.
 *
 * Agent middleware wraps each generation step, and the SDK runs every step
 * once per provider in its failover list. This middleware retries a failing
 * step on the current provider before the SDK fails over to the next one.
 * After retries are exhausted, the original exception is re-thrown so the
 * SDK's provider failover proceeds normally.
 */
class RetryAiRequests
{
    protected ?Closure $configuration = null;

    public function __construct(
        protected int $times = 2
    ) {}

    /**
     * Create middleware that retries the given number of times.
     */
    public static function times(int $times): static
    {
        return new static($times);
    }

    /**
     * Customize the underlying attempt builder before execution.
     *
     * @param  Closure(AttemptBuilder): mixed  $callback
     */
    public function configureUsing(Closure $callback): static
    {
        $this->configuration = $callback;

        return $this;
    }

    /**
     * Handle the pending generation step.
     */
    public function handle(PendingStep $step, Closure $next): mixed
    {
        $builder = AiRetryPolicy::applyTo(
            AttemptBuilder::make(fn (): mixed => $next($step))->retry($this->times)
        );

        if ($this->configuration !== null) {
            ($this->configuration)($builder);
        }

        return $builder->thenReturnOrFail();
    }
}
