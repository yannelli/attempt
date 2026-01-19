<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Pipes;

use Closure;
use Yannelli\Attempt\AttemptBuilder;
use Yannelli\Attempt\Concerns\HasDelayConfiguration;
use Yannelli\Attempt\Concerns\HasFallbacks;

class AttemptPipe
{
    use HasDelayConfiguration;
    use HasFallbacks;

    protected AttemptBuilder $builder;

    protected int $maxRetries = 0;

    public function __construct(
        protected string|Closure $callable
    ) {
        $this->builder = new AttemptBuilder($this->callable);
    }

    /**
     * Wrap a callable in an AttemptPipe.
     */
    public static function wrap(string|Closure $callable): static
    {
        return new static($callable);
    }

    /**
     * Set retry count.
     */
    public function retry(int $times): static
    {
        $this->maxRetries = max(0, $times);

        return $this;
    }

    /**
     * Handle the pipe (called by Laravel Pipeline).
     */
    public function handle(mixed $passable, Closure $next): mixed
    {
        $builder = new AttemptBuilder($this->callable);
        $builder->with($passable);

        if ($this->maxRetries > 0) {
            $builder->retry($this->maxRetries);
        }

        if (! empty($this->delay)) {
            $builder->delay($this->delay);
        }

        if ($this->retryStrategy !== null) {
            $builder->usingStrategy($this->retryStrategy);
        }

        if ($this->jitter > 0) {
            $builder->withJitter($this->jitter);
        }

        if (! empty($this->fallbacks)) {
            $builder->fallback($this->fallbacks);
        }

        $result = $builder->thenReturn();

        return $next($result);
    }

    /**
     * Allow the pipe to be invoked directly.
     */
    public function __invoke(mixed $passable, Closure $next): mixed
    {
        return $this->handle($passable, $next);
    }
}
