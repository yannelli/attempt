<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Testing;

use Closure;
use Illuminate\Support\Collection;
use Throwable;
use Yannelli\Attempt\AttemptBuilder;
use Yannelli\Attempt\AttemptContext;
use Yannelli\Attempt\AttemptResult;

class FakeAttemptBuilder extends AttemptBuilder
{
    protected AttemptFake $fake;

    protected string $callableName;

    public function __construct(
        Closure|string|array|Collection $callable,
        AttemptFake $fake,
        mixed ...$input
    ) {
        parent::__construct($callable, ...$input);
        $this->fake = $fake;
        $this->callableName = $this->extractCallableName($callable);
    }

    /**
     * Execute the fake attempt.
     */
    protected function execute(): AttemptResult
    {
        // Check condition
        if ($this->condition !== null) {
            $conditionResult = ($this->condition)();
            $shouldRun = $this->conditionNegated ? ! $conditionResult : $conditionResult;

            if (! $shouldRun) {
                return new AttemptResult(
                    value: null,
                    succeeded: true,
                    exception: null,
                    attempts: 0,
                    resolvedBy: 'skipped'
                );
            }
        }

        $context = new AttemptContext(
            maxAttempts: $this->maxRetries + 1,
            input: $this->input
        );

        $attemptNumber = 0;
        $maxAttempts = $this->maxRetries + 1;
        $lastException = null;

        while ($attemptNumber < $maxAttempts) {
            $attemptNumber++;
            $context->attemptNumber = $attemptNumber;

            // Check for sequence
            $sequenceItem = $this->fake->getNextSequenceItem();
            if ($sequenceItem !== null) {
                if ($sequenceItem instanceof Throwable) {
                    $lastException = $sequenceItem;
                    $this->fake->recordAttempt($this->callableName, [
                        'attempt' => $attemptNumber,
                        'success' => false,
                        'exception' => $sequenceItem->getMessage(),
                    ]);

                    if ($attemptNumber < $maxAttempts) {
                        continue;
                    }

                    break;
                }

                $this->fake->recordAttempt($this->callableName, [
                    'attempt' => $attemptNumber,
                    'success' => true,
                ]);

                return new AttemptResult(
                    value: $sequenceItem,
                    succeeded: true,
                    exception: null,
                    attempts: $attemptNumber,
                    resolvedBy: $attemptNumber === 1 ? 'primary' : "retry:{$attemptNumber}"
                );
            }

            // Check for forced failure
            $failureException = $this->fake->shouldFail($this->callableName);
            if ($failureException !== null) {
                $lastException = $failureException;
                $this->fake->recordAttempt($this->callableName, [
                    'attempt' => $attemptNumber,
                    'success' => false,
                    'exception' => $failureException->getMessage(),
                ]);

                if ($attemptNumber < $maxAttempts) {
                    continue;
                }

                break;
            }

            // Check for forced result
            if ($this->fake->hasForcedResult($this->callableName)) {
                $result = $this->fake->getForcedResult($this->callableName);

                $this->fake->recordAttempt($this->callableName, [
                    'attempt' => $attemptNumber,
                    'success' => true,
                ]);

                return new AttemptResult(
                    value: $result,
                    succeeded: true,
                    exception: null,
                    attempts: $attemptNumber,
                    resolvedBy: $attemptNumber === 1 ? 'primary' : "retry:{$attemptNumber}"
                );
            }

            // Execute the real callable
            try {
                $result = parent::executeCallable($this->callable, $context);

                $this->fake->recordAttempt($this->callableName, [
                    'attempt' => $attemptNumber,
                    'success' => true,
                ]);

                return new AttemptResult(
                    value: $result,
                    succeeded: true,
                    exception: null,
                    attempts: $attemptNumber,
                    resolvedBy: $attemptNumber === 1 ? 'primary' : "retry:{$attemptNumber}"
                );
            } catch (Throwable $e) {
                $lastException = $e;
                $this->fake->recordAttempt($this->callableName, [
                    'attempt' => $attemptNumber,
                    'success' => false,
                    'exception' => $e->getMessage(),
                ]);

                if ($attemptNumber < $maxAttempts) {
                    continue;
                }

                break;
            }
        }

        // Try fallbacks
        foreach ($this->fallbacks as $fallback) {
            $fallbackName = is_string($fallback) ? $fallback : 'Closure';

            try {
                $this->fake->recordFallback($fallbackName);
                $result = parent::executeCallable($fallback, $context);

                return new AttemptResult(
                    value: $result,
                    succeeded: true,
                    exception: null,
                    attempts: $attemptNumber,
                    resolvedBy: "fallback:{$fallbackName}"
                );
            } catch (Throwable $e) {
                $lastException = $e;
            }
        }

        return new AttemptResult(
            value: null,
            succeeded: false,
            exception: $lastException,
            attempts: $attemptNumber,
            resolvedBy: null
        );
    }

    /**
     * Extract callable name.
     */
    protected function extractCallableName(mixed $callable): string
    {
        if ($callable instanceof Closure) {
            return 'Closure';
        }

        if (is_string($callable)) {
            return $callable;
        }

        if (is_array($callable) && isset($callable[0])) {
            return is_string($callable[0]) ? $callable[0] : 'Array';
        }

        return 'Unknown';
    }
}
