<?php

declare(strict_types=1);

namespace Yannelli\Attempt;

use Closure;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;
use Yannelli\Attempt\Builders\AsyncAttemptBuilder;
use Yannelli\Attempt\Concerns\HasDelayConfiguration;
use Yannelli\Attempt\Concerns\HasExceptionHandling;
use Yannelli\Attempt\Concerns\HasFallbacks;
use Yannelli\Attempt\Concerns\HasLifecycleHooks;
use Yannelli\Attempt\Contracts\Attemptable;
use Yannelli\Attempt\Contracts\ConfiguresAttempt;
use Yannelli\Attempt\Contracts\Fallbackable;
use Yannelli\Attempt\Events\AllAttemptsFailed;
use Yannelli\Attempt\Events\AttemptFailed;
use Yannelli\Attempt\Events\AttemptStarted;
use Yannelli\Attempt\Events\AttemptSucceeded;
use Yannelli\Attempt\Events\FallbackTriggered;
use Yannelli\Attempt\Events\RetryAttempted;
use Yannelli\Attempt\Exceptions\AllFallbacksFailed as AllFallbacksFailedException;
use Yannelli\Attempt\Support\DelayCalculator;

class AttemptBuilder
{
    use HasDelayConfiguration;
    use HasExceptionHandling;
    use HasFallbacks;
    use HasLifecycleHooks;

    protected Closure|string|array|Collection $callable;

    protected array $input = [];

    protected int $maxRetries = 0;

    protected bool $shouldThrow = false;

    protected bool $quiet = false;

    protected ?Closure $condition = null;

    protected bool $conditionNegated = false;

    protected string $pipelineMethod = 'handle';

    protected array $pipes = [];

    protected bool $executed = false;

    protected ?AttemptResult $cachedResult = null;

    public function __construct(
        Closure|string|array|Collection $callable,
        mixed ...$input
    ) {
        $this->callable = $callable;
        $this->input = $input;
    }

    /**
     * Static factory method.
     */
    public static function make(Closure|string|array|Collection $callable, mixed ...$input): static
    {
        return new static($callable, ...$input);
    }

    /**
     * Set the input to pass to the callable.
     */
    public function with(mixed ...$input): static
    {
        $this->input = $input;

        return $this;
    }

    /**
     * Alias for with() - Pipeline-style naming.
     */
    public function send(mixed $passable): static
    {
        $this->input = [$passable];

        return $this;
    }

    /**
     * Set the number of retry attempts.
     */
    public function retry(int $times): static
    {
        $this->maxRetries = max(0, $times);

        return $this;
    }

    /**
     * Set the method to call on pipeline handlers.
     */
    public function via(string $method): static
    {
        $this->pipelineMethod = $method;

        return $this;
    }

    /**
     * Append pipes (when using pipeline mode).
     */
    public function through(array $pipes): static
    {
        $this->pipes = array_merge($this->pipes, $pipes);

        return $this;
    }

    /**
     * Alias for through().
     */
    public function pipe(array $pipes): static
    {
        return $this->through($pipes);
    }

    /**
     * Re-throw the exception after catch handlers.
     */
    public function throw(): static
    {
        $this->shouldThrow = true;

        return $this;
    }

    /**
     * Suppress all exceptions and return null on failure.
     */
    public function quiet(): static
    {
        $this->quiet = true;

        return $this;
    }

    /**
     * Only execute if condition is truthy.
     */
    public function when(Closure|bool $condition): static
    {
        $this->condition = is_bool($condition) ? fn () => $condition : $condition;
        $this->conditionNegated = false;

        return $this;
    }

    /**
     * Only execute if condition is falsy.
     */
    public function unless(Closure|bool $condition): static
    {
        $this->condition = is_bool($condition) ? fn () => $condition : $condition;
        $this->conditionNegated = true;

        return $this;
    }

    /**
     * Convert to async builder.
     */
    public function async(): AsyncAttemptBuilder
    {
        return new AsyncAttemptBuilder($this);
    }

    /**
     * Execute and transform the result.
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
     * Execute and return the value directly.
     */
    public function thenReturn(): mixed
    {
        return $this->execute()->value();
    }

    /**
     * Alias for thenReturn().
     */
    public function get(): mixed
    {
        return $this->thenReturn();
    }

    /**
     * Alias for thenReturn().
     */
    public function value(): mixed
    {
        return $this->thenReturn();
    }

    /**
     * Execute and throw on failure.
     */
    public function thenReturnOrFail(): mixed
    {
        $result = $this->execute();

        if ($result->failed()) {
            throw $result->exception() ?? new AllFallbacksFailedException(
                'All attempts and fallbacks failed'
            );
        }

        return $result->value();
    }

    /**
     * Execute and return the full result object.
     */
    public function run(): AttemptResult
    {
        return $this->execute();
    }

    /**
     * Execute the attempt.
     */
    protected function execute(): AttemptResult
    {
        // Return cached result if already executed
        if ($this->executed && $this->cachedResult !== null) {
            return $this->cachedResult;
        }

        // Check condition
        if ($this->condition !== null) {
            $conditionResult = ($this->condition)();
            $shouldRun = $this->conditionNegated ? ! $conditionResult : $conditionResult;

            if (! $shouldRun) {
                $this->executed = true;
                $this->cachedResult = new AttemptResult(
                    value: null,
                    succeeded: true,
                    exception: null,
                    attempts: 0,
                    resolvedBy: 'skipped'
                );

                return $this->cachedResult;
            }
        }

        $context = new AttemptContext(
            maxAttempts: $this->maxRetries + 1,
            input: $this->input
        );

        // Fire started event
        $this->fireEvent(new AttemptStarted($context));

        try {
            $result = $this->executeWithRetries($context);
            $this->executed = true;
            $this->cachedResult = $result;

            return $result;
        } finally {
            $this->runFinallyCallbacks($context);
            $this->scheduleDeferredCallbacks($context);
        }
    }

    /**
     * Execute with retry logic.
     */
    protected function executeWithRetries(AttemptContext $context): AttemptResult
    {
        $lastException = null;
        $attemptNumber = 0;
        $maxAttempts = $this->maxRetries + 1;
        $delayCalculator = new DelayCalculator(
            $this->delay,
            $this->retryStrategy,
            $this->delayCallback,
            $this->jitter
        );

        // Handle array of callables as fallback chain
        $callables = $this->normalizeCallables();
        $primaryCallable = array_shift($callables);

        // Merge remaining callables with fallbacks
        $this->fallbacks = array_merge($callables, $this->fallbacks);

        while ($attemptNumber < $maxAttempts) {
            $attemptNumber++;
            $context->attemptNumber = $attemptNumber;

            try {
                // Fire retry event (not for first attempt)
                if ($attemptNumber > 1) {
                    $this->fireEvent(new RetryAttempted($context, $attemptNumber, $lastException));
                    $this->runOnRetryCallbacks($context, $lastException);
                }

                // Execute primary callable
                $result = $this->executeCallable($primaryCallable, $context);

                // Success
                $context->succeeded = true;
                $context->resolvedBy = $attemptNumber === 1 ? 'primary' : "retry:{$attemptNumber}";
                $context->recordAttempt($context->resolvedBy, true);

                $this->runOnSuccessCallbacks($context, $result);
                $this->fireEvent(new AttemptSucceeded($context, $result));

                return new AttemptResult(
                    value: $result,
                    succeeded: true,
                    exception: null,
                    attempts: $attemptNumber,
                    resolvedBy: $context->resolvedBy,
                    attemptLog: $context->attemptLog
                );
            } catch (Throwable $e) {
                $lastException = $e;
                $context->lastException = $e;
                $context->recordAttempt("attempt:{$attemptNumber}", false, $e);

                // Run catch handlers
                $this->runCatchHandlers($e, $context);

                // Fire failed event
                $this->fireEvent(new AttemptFailed($context, $e));

                // Check if we should retry
                if ($attemptNumber < $maxAttempts && $this->shouldRetry($e, $attemptNumber, $maxAttempts)) {
                    $delay = $delayCalculator->calculate($attemptNumber, $e);
                    if ($delay > 0) {
                        usleep($delay * 1000);
                    }

                    continue;
                }

                // No more retries, try fallbacks
                break;
            }
        }

        // Try fallbacks
        if (! empty($this->fallbacks)) {
            $fallbackResult = $this->executeFallbacks($context, $lastException);
            if ($fallbackResult !== null) {
                return $fallbackResult;
            }
        }

        // All failed
        $context->succeeded = false;
        $this->runOnFailureCallbacks($context, $lastException);
        $this->fireEvent(new AllAttemptsFailed($context, $lastException));

        if ($this->shouldThrow && ! $this->quiet) {
            throw $lastException;
        }

        if ($this->quiet) {
            return new AttemptResult(
                value: null,
                succeeded: false,
                exception: $lastException,
                attempts: $attemptNumber,
                resolvedBy: null,
                attemptLog: $context->attemptLog
            );
        }

        return new AttemptResult(
            value: null,
            succeeded: false,
            exception: $lastException,
            attempts: $attemptNumber,
            resolvedBy: null,
            attemptLog: $context->attemptLog
        );
    }

    /**
     * Execute fallback handlers.
     */
    protected function executeFallbacks(AttemptContext $context, Throwable $lastException): ?AttemptResult
    {
        foreach ($this->fallbacks as $index => $fallback) {
            $fallbackName = is_string($fallback) ? $fallback : 'closure:'.($index + 1);

            try {
                // Fire fallback event
                $this->fireEvent(new FallbackTriggered($context, $fallback, $lastException));
                $this->runOnFallbackCallbacks($context, $fallback, $lastException);

                // Check if fallback should be skipped
                $resolved = $this->resolveCallable($fallback);
                $instance = $resolved['instance'] ?? null;

                if ($instance instanceof Fallbackable && $instance->shouldSkip($lastException)) {
                    continue;
                }

                // Execute fallback
                $result = $instance instanceof Fallbackable
                    ? $instance->handleFallback($lastException, ...$this->input)
                    : $this->executeCallable($fallback, $context);

                // Success
                $context->succeeded = true;
                $context->resolvedBy = "fallback:{$fallbackName}";
                $context->recordAttempt($context->resolvedBy, true);

                $this->runOnSuccessCallbacks($context, $result);
                $this->fireEvent(new AttemptSucceeded($context, $result));

                return new AttemptResult(
                    value: $result,
                    succeeded: true,
                    exception: null,
                    attempts: $context->attemptNumber,
                    resolvedBy: $context->resolvedBy,
                    attemptLog: $context->attemptLog
                );
            } catch (Throwable $e) {
                $lastException = $e;
                $context->lastException = $e;
                $context->recordAttempt("fallback:{$fallbackName}", false, $e);
            }
        }

        return null;
    }

    /**
     * Normalize callable(s) to array.
     */
    protected function normalizeCallables(): array
    {
        $callable = $this->callable;

        if ($callable instanceof Collection) {
            $callable = $callable->all();
        }

        if (is_array($callable)) {
            // Check if it's an associative array (not a list of callables)
            if (array_is_list($callable)) {
                return $callable;
            }
        }

        return [$callable];
    }

    /**
     * Resolve a callable to an executable form.
     */
    protected function resolveCallable(mixed $callable): array
    {
        // Closure - use directly
        if ($callable instanceof Closure) {
            return ['callable' => $callable, 'instance' => null];
        }

        // Class string - resolve from container
        if (is_string($callable) && class_exists($callable)) {
            $instance = app($callable);

            // If class implements ConfiguresAttempt, apply configuration
            if ($instance instanceof ConfiguresAttempt) {
                $instance->configureAttempt($this);
            }

            // Return appropriate callable
            if ($instance instanceof Attemptable) {
                return [
                    'callable' => fn (...$args) => $instance->handle(...$args),
                    'instance' => $instance,
                ];
            }

            // Fallbackable - will be handled specially in executeFallbacks
            if ($instance instanceof Fallbackable) {
                return [
                    'callable' => fn (...$args) => $instance->handleFallback(new \RuntimeException('Fallbackable called directly'), ...$args),
                    'instance' => $instance,
                ];
            }

            if (is_callable($instance)) {
                return ['callable' => $instance, 'instance' => $instance];
            }

            throw new InvalidArgumentException(
                "Class {$callable} must implement Attemptable, Fallbackable, or be invokable"
            );
        }

        // Already callable
        if (is_callable($callable)) {
            return ['callable' => $callable, 'instance' => null];
        }

        throw new InvalidArgumentException(
            'Callable must be a Closure, class string, or callable'
        );
    }

    /**
     * Execute a callable.
     */
    protected function executeCallable(mixed $callable, AttemptContext $context): mixed
    {
        $resolved = $this->resolveCallable($callable);

        return ($resolved['callable'])(...$this->input);
    }

    /**
     * Determine if we should retry.
     */
    protected function shouldRetry(Throwable $e, int $attempt, int $maxAttempts): bool
    {
        // Check never_retry config
        $neverRetry = config('attempt.never_retry', []);
        foreach ($neverRetry as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return false;
            }
        }

        // Check always_retry config
        $alwaysRetry = config('attempt.always_retry', []);
        foreach ($alwaysRetry as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        // Check retryIf callback
        if ($this->retryIf !== null) {
            return (bool) ($this->retryIf)($e, $attempt);
        }

        // Check retryUnless callback
        if ($this->retryUnless !== null) {
            return ! (bool) ($this->retryUnless)($e, $attempt);
        }

        // Check strategy
        if ($this->retryStrategy !== null) {
            return $this->retryStrategy->shouldRetry($e, $attempt, $maxAttempts);
        }

        return true;
    }

    /**
     * Run catch handlers.
     */
    protected function runCatchHandlers(Throwable $e, AttemptContext $context): void
    {
        // Run class-based exception handler
        if ($this->exceptionHandlerClass !== null) {
            $handler = app($this->exceptionHandlerClass);
            $handler->handle($e, $context);
        }

        // Run registered catch handlers
        foreach ($this->catchHandlers as $handler) {
            $exceptionClass = $handler['class'];
            $callback = $handler['callback'];

            // If no class specified, run for all exceptions
            if ($exceptionClass === null) {
                if ($callback !== null) {
                    $callback($e, $context);
                }

                continue;
            }

            // Run if exception matches class
            if ($e instanceof $exceptionClass && $callback !== null) {
                $callback($e, $context);
            }
        }
    }

    /**
     * Run finally callbacks.
     */
    protected function runFinallyCallbacks(AttemptContext $context): void
    {
        foreach ($this->finallyCallbacks as $callback) {
            try {
                $callback($context);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Schedule deferred callbacks.
     */
    protected function scheduleDeferredCallbacks(AttemptContext $context): void
    {
        foreach ($this->deferCallbacks as $callback) {
            if (function_exists('defer')) {
                defer(fn () => $callback($context));
            } else {
                // Fallback: run immediately if defer is not available
                try {
                    $callback($context);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
    }

    /**
     * Run onRetry callbacks.
     */
    protected function runOnRetryCallbacks(AttemptContext $context, ?Throwable $e): void
    {
        foreach ($this->onRetryCallbacks as $callback) {
            try {
                $callback($context, $e);
            } catch (Throwable $ex) {
                report($ex);
            }
        }
    }

    /**
     * Run onFallback callbacks.
     */
    protected function runOnFallbackCallbacks(AttemptContext $context, mixed $fallback, Throwable $e): void
    {
        foreach ($this->onFallbackCallbacks as $callback) {
            try {
                $callback($context, $fallback, $e);
            } catch (Throwable $ex) {
                report($ex);
            }
        }
    }

    /**
     * Run onSuccess callbacks.
     */
    protected function runOnSuccessCallbacks(AttemptContext $context, mixed $result): void
    {
        foreach ($this->onSuccessCallbacks as $callback) {
            try {
                $callback($context, $result);
            } catch (Throwable $ex) {
                report($ex);
            }
        }
    }

    /**
     * Run onFailure callbacks.
     */
    protected function runOnFailureCallbacks(AttemptContext $context, ?Throwable $e): void
    {
        foreach ($this->onFailureCallbacks as $callback) {
            try {
                $callback($context, $e);
            } catch (Throwable $ex) {
                report($ex);
            }
        }
    }

    /**
     * Fire an event if events are enabled.
     */
    protected function fireEvent(object $event): void
    {
        if (! $this->eventsEnabled) {
            return;
        }

        if (! config('attempt.events.enabled', true)) {
            return;
        }

        event($event);
    }

    /**
     * Get the builder configuration for async execution.
     */
    public function getConfiguration(): array
    {
        return [
            'callable' => $this->callable,
            'input' => $this->input,
            'maxRetries' => $this->maxRetries,
            'delay' => $this->delay,
            'retryStrategy' => $this->retryStrategy,
            'delayCallback' => $this->delayCallback,
            'jitter' => $this->jitter,
            'fallbacks' => $this->fallbacks,
            'catchHandlers' => $this->catchHandlers,
            'finallyCallbacks' => $this->finallyCallbacks,
            'shouldThrow' => $this->shouldThrow,
            'quiet' => $this->quiet,
            'eventsEnabled' => $this->eventsEnabled,
        ];
    }
}
