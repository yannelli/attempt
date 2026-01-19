<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Builders;

use Closure;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Yannelli\Attempt\AttemptBuilder;
use Yannelli\Attempt\AttemptResult;
use Yannelli\Attempt\Concerns\HasDelayConfiguration;
use Yannelli\Attempt\Concerns\HasExceptionHandling;
use Yannelli\Attempt\Concerns\HasFallbacks;
use Yannelli\Attempt\Concerns\HasLifecycleHooks;

class PipelineAttemptBuilder
{
    use HasDelayConfiguration;
    use HasExceptionHandling;
    use HasFallbacks;
    use HasLifecycleHooks;

    protected array $pipes = [];

    protected mixed $passable = null;

    protected string $method = 'handle';

    protected int $maxRetries = 0;

    public function __construct(array|Collection $pipes = [])
    {
        $this->pipes = $pipes instanceof Collection ? $pipes->all() : $pipes;
    }

    /**
     * Set the data to send through the pipeline.
     */
    public function send(mixed $passable): static
    {
        $this->passable = $passable;

        return $this;
    }

    /**
     * Set the pipes.
     */
    public function through(array|Collection $pipes): static
    {
        $this->pipes = $pipes instanceof Collection ? $pipes->all() : $pipes;

        return $this;
    }

    /**
     * Append pipes.
     */
    public function pipe(array|Collection $pipes): static
    {
        $pipes = $pipes instanceof Collection ? $pipes->all() : $pipes;
        $this->pipes = array_merge($this->pipes, $pipes);

        return $this;
    }

    /**
     * Set the handler method.
     */
    public function via(string $method): static
    {
        $this->method = $method;

        return $this;
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
     * Execute and transform result.
     */
    public function then(Closure $callback): mixed
    {
        $result = $this->execute();

        if ($result->succeeded()) {
            return $callback($result->value());
        }

        return $result->value();
    }

    /**
     * Execute and return value directly.
     */
    public function thenReturn(): mixed
    {
        return $this->execute()->value();
    }

    /**
     * Execute and return full result.
     */
    public function run(): AttemptResult
    {
        return $this->execute();
    }

    /**
     * Execute the pipeline with attempt logic.
     */
    protected function execute(): AttemptResult
    {
        $callable = function () {
            return app(Pipeline::class)
                ->send($this->passable)
                ->through($this->pipes)
                ->via($this->method)
                ->thenReturn();
        };

        $builder = new AttemptBuilder($callable);

        if ($this->maxRetries > 0) {
            $builder->retry($this->maxRetries);
        }

        // Apply delay configuration
        if (! empty($this->delay)) {
            $builder->delay($this->delay);
        }

        if ($this->retryStrategy !== null) {
            $builder->usingStrategy($this->retryStrategy);
        }

        if ($this->jitter > 0) {
            $builder->withJitter($this->jitter);
        }

        // Apply fallbacks
        if (! empty($this->fallbacks)) {
            $builder->fallback($this->fallbacks);
        }

        // Apply lifecycle hooks
        foreach ($this->finallyCallbacks as $callback) {
            $builder->finally($callback);
        }

        foreach ($this->deferCallbacks as $callback) {
            $builder->defer($callback);
        }

        foreach ($this->onSuccessCallbacks as $callback) {
            $builder->onSuccess($callback);
        }

        foreach ($this->onFailureCallbacks as $callback) {
            $builder->onFailure($callback);
        }

        if (! $this->eventsEnabled) {
            $builder->withoutEvents();
        }

        return $builder->run();
    }
}
