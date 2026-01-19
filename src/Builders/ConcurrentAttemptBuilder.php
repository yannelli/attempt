<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Builders;

use Closure;
use Illuminate\Support\Collection;
use Throwable;
use Yannelli\Attempt\AttemptBuilder;
use Yannelli\Attempt\AttemptResult;

class ConcurrentAttemptBuilder
{
    protected array $attempts = [];

    protected int $maxRetries = 0;

    protected int|array $delay = 0;

    protected bool $failFast = false;

    public function __construct(array|Collection $attempts = [])
    {
        $this->attempts = $attempts instanceof Collection ? $attempts->all() : $attempts;
    }

    /**
     * Add an attempt.
     */
    public function add(Closure|string|AttemptBuilder $attempt): static
    {
        $this->attempts[] = $attempt;

        return $this;
    }

    /**
     * Set retry count for all attempts.
     */
    public function retry(int $times): static
    {
        $this->maxRetries = max(0, $times);

        return $this;
    }

    /**
     * Set delay for all attempts.
     */
    public function delay(int|array $milliseconds): static
    {
        $this->delay = $milliseconds;

        return $this;
    }

    /**
     * Fail immediately if any attempt fails.
     */
    public function failFast(bool $failFast = true): static
    {
        $this->failFast = $failFast;

        return $this;
    }

    /**
     * Execute all attempts and return results.
     */
    public function run(): array
    {
        $results = [];
        $exceptions = [];

        foreach ($this->attempts as $index => $attempt) {
            try {
                $builder = $this->normalizeAttempt($attempt);
                $result = $builder->run();
                $results[$index] = $result;

                if ($result->failed() && $this->failFast) {
                    break;
                }
            } catch (Throwable $e) {
                $exceptions[$index] = $e;
                $results[$index] = new AttemptResult(
                    value: null,
                    succeeded: false,
                    exception: $e,
                    attempts: 1,
                    resolvedBy: null
                );

                if ($this->failFast) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Execute all attempts and return values.
     */
    public function thenReturn(): array
    {
        return array_map(
            fn (AttemptResult $result) => $result->value(),
            $this->run()
        );
    }

    /**
     * Get results for successful attempts only.
     */
    public function successful(): array
    {
        return array_filter(
            $this->run(),
            fn (AttemptResult $result) => $result->succeeded()
        );
    }

    /**
     * Get results for failed attempts only.
     */
    public function failed(): array
    {
        return array_filter(
            $this->run(),
            fn (AttemptResult $result) => $result->failed()
        );
    }

    /**
     * Normalize an attempt to an AttemptBuilder.
     */
    protected function normalizeAttempt(mixed $attempt): AttemptBuilder
    {
        if ($attempt instanceof AttemptBuilder) {
            return $attempt;
        }

        $builder = new AttemptBuilder($attempt);

        if ($this->maxRetries > 0) {
            $builder->retry($this->maxRetries);
        }

        if (! empty($this->delay)) {
            $builder->delay($this->delay);
        }

        return $builder;
    }
}
