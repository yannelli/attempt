<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Ai;

use Closure;
use Laravel\Ai\Prompts\AgentPrompt;
use Yannelli\Attempt\AttemptBuilder;

/**
 * Agent middleware that retries transient provider failures.
 *
 * Because agent middleware runs once per provider in the SDK's failover
 * list, this middleware retries each provider before the SDK fails over
 * to the next one. After retries are exhausted, the original exception
 * is re-thrown so the SDK's provider failover proceeds normally.
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
     * Handle the agent prompt.
     */
    public function __invoke(AgentPrompt $prompt, Closure $next): mixed
    {
        $builder = AiRetryPolicy::applyTo(
            AttemptBuilder::make(fn (): mixed => $next($prompt))->retry($this->times)
        );

        if ($this->configuration !== null) {
            ($this->configuration)($builder);
        }

        return $builder->thenReturnOrFail();
    }
}
