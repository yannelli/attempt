<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Builders;

use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;
use Yannelli\Attempt\AttemptBuilder;
use Yannelli\Attempt\AttemptResult;

class AsyncAttemptBuilder implements ShouldQueue
{
    protected ?string $connection = null;

    protected ?string $queue = null;

    protected int $timeout = 60;

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
     * Set the timeout.
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

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
     */
    public function dispatch(): void
    {
        $connection = $this->connection
            ?? config('attempt.async.connection')
            ?? config('queue.default');

        $queue = $this->queue
            ?? config('attempt.async.queue')
            ?? config("queue.connections.{$connection}.queue", 'default');

        $job = new AsyncAttemptJob(
            $this->builder->getConfiguration(),
            $this->thenCallback,
            $this->catchCallback
        );

        Queue::connection($connection)
            ->pushOn($queue, $job);
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
