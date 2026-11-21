<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Builders;

use Closure;
use Illuminate\Contracts\Bus\Dispatcher;
use Yannelli\Attempt\AttemptBuilder;
use Yannelli\Attempt\AttemptResult;
use Yannelli\Attempt\Facades\Attempt;

class AsyncAttemptBuilder
{
    protected ?string $connection = null;

    protected ?string $queue = null;

    protected ?int $timeout = null;

    protected ?Closure $thenCallback = null;

    protected ?Closure $catchCallback = null;

    public function __construct(
        protected AttemptBuilder $builder
    ) {}

    /**
     * Set the queue connection.
     */
    public function onConnection(?string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Set the queue name.
     */
    public function onQueue(?string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Set the job timeout in seconds, overriding the worker's timeout.
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = max(1, $seconds);

        return $this;
    }

    /**
     * Set success callback.
     */
    public function then(Closure $callback): static
    {
        $this->thenCallback = $callback;

        return $this;
    }

    /**
     * Set failure callback.
     */
    public function catch(Closure $callback): static
    {
        $this->catchCallback = $callback;

        return $this;
    }

    /**
     * Dispatch the attempt to the queue.
     *
     * Unset connections and queues defer to Laravel's queue routing
     * (Queue::route) and then to the application's queue defaults.
     */
    public function dispatch(): void
    {
        $class = Attempt::asyncJob();

        $job = new $class(
            $this->builder->getConfiguration(),
            $this->thenCallback,
            $this->catchCallback,
            $this->timeout
        );

        // Deprecated: the attempt.async config applies only to the default job and will be removed in 2.0.
        if ($class === AsyncAttemptJob::class) {
            $timeout = config('attempt.async.timeout', 60);

            $job->timeout ??= $timeout === null ? null : (int) $timeout;
            $job->connection ??= config('attempt.async.connection');
            $job->queue ??= config('attempt.async.queue');
        }

        // Only explicit values are applied, so a custom job's own defaults and queue routes still apply.
        if ($this->connection !== null) {
            $job->onConnection($this->connection);
        }

        if ($this->queue !== null) {
            $job->onQueue($this->queue);
        }

        app(Dispatcher::class)->dispatch($job);
    }

    /**
     * Execute synchronously and await result (blocking).
     */
    public function await(): AttemptResult
    {
        return $this->builder->run();
    }

    /**
     * Get the underlying builder.
     */
    public function getBuilder(): AttemptBuilder
    {
        return $this->builder;
    }
}
