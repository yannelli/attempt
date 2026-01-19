<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Concerns;

use Closure;

trait HasLifecycleHooks
{
    protected array $finallyCallbacks = [];

    protected array $deferCallbacks = [];

    protected array $onRetryCallbacks = [];

    protected array $onFallbackCallbacks = [];

    protected array $onSuccessCallbacks = [];

    protected array $onFailureCallbacks = [];

    protected bool $eventsEnabled = true;

    /**
     * Run callback after attempt completes (before return).
     */
    public function finally(Closure $callback): static
    {
        $this->finallyCallbacks[] = $callback;

        return $this;
    }

    /**
     * Run callback after response is sent (uses Laravel's defer).
     */
    public function defer(Closure $callback): static
    {
        $this->deferCallbacks[] = $callback;

        return $this;
    }

    /**
     * Called when a retry is attempted.
     */
    public function onRetry(Closure $callback): static
    {
        $this->onRetryCallbacks[] = $callback;

        return $this;
    }

    /**
     * Called when a fallback is triggered.
     */
    public function onFallback(Closure $callback): static
    {
        $this->onFallbackCallbacks[] = $callback;

        return $this;
    }

    /**
     * Called on success.
     */
    public function onSuccess(Closure $callback): static
    {
        $this->onSuccessCallbacks[] = $callback;

        return $this;
    }

    /**
     * Called on failure.
     */
    public function onFailure(Closure $callback): static
    {
        $this->onFailureCallbacks[] = $callback;

        return $this;
    }

    /**
     * Disable event dispatching.
     */
    public function withoutEvents(): static
    {
        $this->eventsEnabled = false;

        return $this;
    }

    /**
     * Enable event dispatching.
     */
    public function withEvents(): static
    {
        $this->eventsEnabled = true;

        return $this;
    }
}
