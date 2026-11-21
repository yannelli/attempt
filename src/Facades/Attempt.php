<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Facades;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;
use Yannelli\Attempt\AttemptBuilder;
use Yannelli\Attempt\AttemptManager;
use Yannelli\Attempt\Builders\AsyncAttemptJob;
use Yannelli\Attempt\Builders\ConcurrentAttemptBuilder;
use Yannelli\Attempt\Builders\PipelineAttemptBuilder;
use Yannelli\Attempt\Builders\RaceAttemptBuilder;
use Yannelli\Attempt\Contracts\RetryStrategy;
use Yannelli\Attempt\Strategies\DecorrelatedJitter;
use Yannelli\Attempt\Strategies\ExponentialBackoff;
use Yannelli\Attempt\Strategies\FibonacciBackoff;
use Yannelli\Attempt\Strategies\FixedDelay;
use Yannelli\Attempt\Strategies\LinearBackoff;
use Yannelli\Attempt\Testing\AttemptFake;

/**
 * Global configuration methods on this facade are static and never resolve the
 * underlying manager, so calling them from a service provider's boot method
 * does not force the deferred AttemptServiceProvider to load. Settings that
 * are not configured statically fall back to the deprecated config file.
 *
 * @method static AttemptBuilder try(Closure|string|array|Collection $callable, mixed ...$input)
 * @method static AttemptBuilder ai(Closure|string|array|Collection $callable, mixed ...$input)
 * @method static PipelineAttemptBuilder pipeline(array|Collection $pipes = [])
 * @method static ConcurrentAttemptBuilder concurrent(array|Collection $attempts = [])
 * @method static RaceAttemptBuilder race(array|Collection $attempts = [])
 * @method static AttemptFake fake()
 * @method static bool isFaked()
 * @method static void resetFake()
 * @method static void assertAttempted(string $callable)
 * @method static void assertAttemptedTimes(string $callable, int $times)
 * @method static void assertFallbackUsed(string $callable)
 * @method static void assertNeverAttempted(string $callable)
 * @method static void assertNothingAttempted()
 * @method static void assertAttemptCount(int $count)
 * @method static void assertSucceeded(string $callable)
 * @method static void assertFailed(string $callable)
 *
 * @see AttemptManager
 */
class Attempt extends Facade
{
    /**
     * The backoff strategies available by name without registration.
     *
     * @var array<string, class-string<RetryStrategy>>
     */
    protected const array BUILT_IN_BACKOFF_STRATEGIES = [
        'exponential' => ExponentialBackoff::class,
        'linear' => LinearBackoff::class,
        'fibonacci' => FibonacciBackoff::class,
        'decorrelated_jitter' => DecorrelatedJitter::class,
        'fixed' => FixedDelay::class,
    ];

    /**
     * The exceptions that are never retried, or null to use the deprecated config file.
     *
     * @var array<int, class-string<Throwable>>|null
     */
    protected static ?array $neverRetry = null;

    /**
     * The exceptions that are always retried, or null to use the deprecated config file.
     *
     * @var array<int, class-string<Throwable>>|null
     */
    protected static ?array $alwaysRetry = null;

    /**
     * The registered named backoff strategies.
     *
     * @var array<string, class-string<RetryStrategy>|Closure(mixed ...): RetryStrategy>
     */
    protected static array $backoffStrategies = [];

    /**
     * Indicates if event dispatching is disabled for every attempt, or null to use the deprecated config file.
     */
    protected static ?bool $eventsDisabled = null;

    /**
     * The job class used to run async attempts.
     *
     * @var class-string<AsyncAttemptJob>
     */
    protected static string $asyncJob = AsyncAttemptJob::class;

    /**
     * Never retry the given exceptions, regardless of per-attempt retry rules.
     *
     * Calling this method again replaces the previously configured exceptions.
     *
     * @param  array<int, class-string<Throwable>>|class-string<Throwable>  $exceptions
     */
    public static function neverRetry(array|string $exceptions): void
    {
        static::$neverRetry = (array) $exceptions;
    }

    /**
     * Always retry the given exceptions while retry attempts remain.
     *
     * Calling this method again replaces the previously configured exceptions.
     *
     * @param  array<int, class-string<Throwable>>|class-string<Throwable>  $exceptions
     */
    public static function alwaysRetry(array|string $exceptions): void
    {
        static::$alwaysRetry = (array) $exceptions;
    }

    /**
     * Register a named backoff strategy for use with the builder's backoff method.
     *
     * Registering a built-in name replaces the built-in strategy.
     *
     * @param  class-string<RetryStrategy>|Closure(mixed ...): RetryStrategy  $strategy
     */
    public static function defineBackoff(string $name, string|Closure $strategy): void
    {
        static::$backoffStrategies[$name] = $strategy;
    }

    /**
     * Disable (or re-enable) event dispatching for every attempt.
     */
    public static function disableEvents(bool $disable = true): void
    {
        static::$eventsDisabled = $disable;
    }

    /**
     * Use a custom job class, such as one with its own connection, queue, or timeout, to run async attempts.
     *
     * @param  class-string<AsyncAttemptJob>  $class
     *
     * @throws InvalidArgumentException
     */
    public static function useAsyncJob(string $class): void
    {
        if (! is_a($class, AsyncAttemptJob::class, true)) {
            throw new InvalidArgumentException("Async job [{$class}] must extend [".AsyncAttemptJob::class.'].');
        }

        static::$asyncJob = $class;
    }

    /**
     * Get the job class used to run async attempts.
     *
     * @internal
     *
     * @return class-string<AsyncAttemptJob>
     */
    public static function asyncJob(): string
    {
        return static::$asyncJob;
    }

    /**
     * Get the global retry rule for the given exception.
     *
     * Returns false for never-retry exceptions, true for always-retry exceptions,
     * and null when no global rule applies.
     *
     * @internal
     */
    public static function retryRuleFor(Throwable $e): ?bool
    {
        foreach (static::$neverRetry ?? static::legacyConfig('never_retry', []) as $exception) {
            if ($e instanceof $exception) {
                return false;
            }
        }

        foreach (static::$alwaysRetry ?? static::legacyConfig('always_retry', []) as $exception) {
            if ($e instanceof $exception) {
                return true;
            }
        }

        return null;
    }

    /**
     * Create the named backoff strategy with the given constructor options.
     *
     * @internal
     *
     * @param  array<int|string, mixed>  $options
     *
     * @throws InvalidArgumentException
     */
    public static function makeBackoff(string $name, array $options = []): RetryStrategy
    {
        $strategy = static::$backoffStrategies[$name] ?? null;
        $legacy = $strategy === null ? static::legacyConfig("backoff_strategies.{$name}") : null;

        if (isset($legacy['class'])) {
            return static::makeLegacyBackoff($legacy, $options);
        }

        $strategy ??= static::BUILT_IN_BACKOFF_STRATEGIES[$name]
            ?? throw new InvalidArgumentException("Backoff strategy [{$name}] is not defined.");

        return $strategy instanceof Closure ? $strategy(...$options) : new $strategy(...$options);
    }

    /**
     * Determine if event dispatching is disabled for every attempt.
     *
     * @internal
     */
    public static function eventsDisabled(): bool
    {
        return static::$eventsDisabled ?? ! static::legacyConfig('events.enabled', true);
    }

    /**
     * Reset all global configuration to its defaults.
     */
    public static function flushState(): void
    {
        static::$neverRetry = null;
        static::$alwaysRetry = null;
        static::$backoffStrategies = [];
        static::$eventsDisabled = null;
        static::$asyncJob = AsyncAttemptJob::class;
    }

    /**
     * Create a backoff strategy from a deprecated config file entry.
     *
     * Options override the configured defaults. Positional options are mapped to
     * constructor parameter names so they can be merged with those defaults.
     *
     * @param  array<string, mixed>  $config
     * @param  array<int|string, mixed>  $options
     */
    protected static function makeLegacyBackoff(array $config, array $options): RetryStrategy
    {
        $class = $config['class'];
        unset($config['class']);

        $parameters = (new ReflectionClass($class))->getConstructor()?->getParameters() ?? [];

        foreach ($options as $key => $value) {
            if (is_int($key) && isset($parameters[$key])) {
                unset($options[$key]);
                $options[$parameters[$key]->getName()] = $value;
            }
        }

        return Container::getInstance()->make($class, array_merge($config, $options));
    }

    /**
     * Read a value from the deprecated attempt config file.
     *
     * @deprecated The attempt config file will be removed in 2.0.
     */
    protected static function legacyConfig(string $key, mixed $default = null): mixed
    {
        $container = Container::getInstance();

        return $container->bound('config')
            ? $container->make('config')->get("attempt.{$key}", $default)
            : $default;
    }

    protected static function getFacadeAccessor(): string
    {
        return AttemptManager::class;
    }
}
