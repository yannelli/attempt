<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Builders;

use Closure;
use Illuminate\Support\Collection;
use Throwable;
use Yannelli\Attempt\AttemptBuilder;
use Yannelli\Attempt\AttemptResult;
use Yannelli\Attempt\Exceptions\AllFallbacksFailed;

class RaceAttemptBuilder
{
    protected array $attempts = [];

    protected int $maxRetries = 0;

    protected int|array $delay = 0;

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
     * Execute attempts and return first successful result.
     */
    public function run(): AttemptResult
    {
        $lastException = null;

        foreach ($this->attempts as $attempt) {
            try {
                $builder = $this->normalizeAttempt($attempt);
                $result = $builder->run();

                if ($result->succeeded()) {
                    return $result;
                }

                $lastException = $result->exception();
            } catch (Throwable $e) {
                $lastException = $e;
            }
        }

        return new AttemptResult(
            value: null,
            succeeded: false,
            exception: $lastException ?? new AllFallbacksFailed('All race attempts failed'),
            attempts: count($this->attempts),
            resolvedBy: null
        );
    }

    /**
     * Execute and return first successful value.
     */
    public function thenReturn(): mixed
    {
        return $this->run()->value();
    }

    /**
     * Execute and throw on failure.
     */
    public function thenReturnOrFail(): mixed
    {
        $result = $this->run();

        if ($result->failed()) {
            throw $result->exception() ?? new AllFallbacksFailed('All race attempts failed');
        }

        return $result->value();
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
