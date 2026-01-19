<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Testing;

use Closure;
use Illuminate\Support\Collection;
use Throwable;
use Yannelli\Attempt\Builders\PipelineAttemptBuilder;
use Yannelli\Attempt\Testing\Concerns\AttemptAssertions;

class AttemptFake
{
    use AttemptAssertions;

    protected array $recorded = [];

    protected array $sequences = [];

    protected int $sequenceIndex = 0;

    protected array $forcedFailures = [];

    protected array $forcedResults = [];

    /**
     * Create a fake attempt builder.
     */
    public function try(
        Closure|string|array|Collection $callable,
        mixed ...$input
    ): FakeAttemptBuilder {
        $callableName = $this->getCallableName($callable);
        $this->recorded[] = [
            'type' => 'try',
            'callable' => $callableName,
            'input' => $input,
        ];

        return new FakeAttemptBuilder(
            $callable,
            $this,
            ...$input
        );
    }

    /**
     * Create a fake pipeline builder.
     */
    public function pipeline(array|Collection $pipes = []): PipelineAttemptBuilder
    {
        $this->recorded[] = [
            'type' => 'pipeline',
            'pipes' => $pipes instanceof Collection ? $pipes->all() : $pipes,
        ];

        return new PipelineAttemptBuilder($pipes);
    }

    /**
     * Make a specific callable fail a number of times.
     */
    public function failFor(string $callable, int $times = 1, ?Throwable $exception = null): static
    {
        $this->forcedFailures[$callable] = [
            'times' => $times,
            'count' => 0,
            'exception' => $exception ?? new \RuntimeException("Forced failure for {$callable}"),
        ];

        return $this;
    }

    /**
     * Set a sequence of results/exceptions.
     */
    public function sequence(array $sequence): static
    {
        $this->sequences = $sequence;
        $this->sequenceIndex = 0;

        return $this;
    }

    /**
     * Force a specific result for a callable.
     */
    public function forceResult(string $callable, mixed $result): static
    {
        $this->forcedResults[$callable] = $result;

        return $this;
    }

    /**
     * Get the next item in the sequence.
     */
    public function getNextSequenceItem(): mixed
    {
        if (empty($this->sequences)) {
            return null;
        }

        $item = $this->sequences[$this->sequenceIndex] ?? $this->sequences[count($this->sequences) - 1];
        $this->sequenceIndex++;

        return $item;
    }

    /**
     * Check if a callable should fail.
     */
    public function shouldFail(string $callable): ?Throwable
    {
        if (! isset($this->forcedFailures[$callable])) {
            return null;
        }

        $failure = &$this->forcedFailures[$callable];

        if ($failure['count'] < $failure['times']) {
            $failure['count']++;

            return $failure['exception'];
        }

        return null;
    }

    /**
     * Get forced result for a callable.
     */
    public function getForcedResult(string $callable): mixed
    {
        return $this->forcedResults[$callable] ?? null;
    }

    /**
     * Check if callable has a forced result.
     */
    public function hasForcedResult(string $callable): bool
    {
        return array_key_exists($callable, $this->forcedResults);
    }

    /**
     * Record an attempt.
     */
    public function recordAttempt(string $callable, array $data = []): void
    {
        $this->recorded[] = array_merge([
            'type' => 'attempt',
            'callable' => $callable,
        ], $data);
    }

    /**
     * Record a fallback.
     */
    public function recordFallback(string $callable, array $data = []): void
    {
        $this->recorded[] = array_merge([
            'type' => 'fallback',
            'callable' => $callable,
        ], $data);
    }

    /**
     * Get all recorded events.
     */
    public function getRecorded(): array
    {
        return $this->recorded;
    }

    /**
     * Get the callable name from various types.
     */
    protected function getCallableName(mixed $callable): string
    {
        if ($callable instanceof Closure) {
            return 'Closure';
        }

        if (is_string($callable)) {
            return $callable;
        }

        if (is_array($callable)) {
            return 'Array('.count($callable).')';
        }

        if ($callable instanceof Collection) {
            return 'Collection('.$callable->count().')';
        }

        return 'Unknown';
    }

    /**
     * Reset the fake state.
     */
    public function reset(): void
    {
        $this->recorded = [];
        $this->sequences = [];
        $this->sequenceIndex = 0;
        $this->forcedFailures = [];
        $this->forcedResults = [];
    }
}
