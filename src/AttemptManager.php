<?php

declare(strict_types=1);

namespace Yannelli\Attempt;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Yannelli\Attempt\Builders\ConcurrentAttemptBuilder;
use Yannelli\Attempt\Builders\PipelineAttemptBuilder;
use Yannelli\Attempt\Builders\RaceAttemptBuilder;
use Yannelli\Attempt\Testing\AttemptFake;

class AttemptManager
{
    protected ?AttemptFake $fake = null;

    public function __construct(
        protected Container $container
    ) {}

    /**
     * Create a new attempt builder.
     *
     * @param  Closure|string|array|Collection  $callable  The callable(s) to attempt
     * @param  mixed  ...$input  Optional input to pass to the callable
     */
    public function try(
        Closure|string|array|Collection $callable,
        mixed ...$input
    ): AttemptBuilder {
        if ($this->fake) {
            return $this->fake->try($callable, ...$input);
        }

        return new AttemptBuilder($callable, ...$input);
    }

    /**
     * Create a pipeline attempt builder.
     */
    public function pipeline(array|Collection $pipes = []): PipelineAttemptBuilder
    {
        if ($this->fake) {
            return $this->fake->pipeline($pipes);
        }

        return new PipelineAttemptBuilder($pipes);
    }

    /**
     * Create a concurrent attempt builder.
     */
    public function concurrent(array|Collection $attempts = []): ConcurrentAttemptBuilder
    {
        return new ConcurrentAttemptBuilder($attempts);
    }

    /**
     * Create a race attempt builder.
     */
    public function race(array|Collection $attempts = []): RaceAttemptBuilder
    {
        return new RaceAttemptBuilder($attempts);
    }

    /**
     * Replace the manager with a fake for testing.
     */
    public function fake(): AttemptFake
    {
        $this->fake = new AttemptFake;

        return $this->fake;
    }

    /**
     * Check if the manager is faked.
     */
    public function isFaked(): bool
    {
        return $this->fake !== null;
    }

    /**
     * Reset the fake.
     */
    public function resetFake(): void
    {
        $this->fake = null;
    }

    /**
     * Get the container instance.
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the fake instance.
     */
    public function getFake(): ?AttemptFake
    {
        return $this->fake;
    }
}
