# Attempt

[![Latest Version on Packagist](https://img.shields.io/packagist/v/yannelli/attempt.svg?style=flat-square)](https://packagist.org/packages/yannelli/attempt)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/yannelli/attempt/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/yannelli/attempt/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/yannelli/attempt.svg?style=flat-square)](https://packagist.org/packages/yannelli/attempt)

- [Introduction](#introduction)
- [Installation](#installation)
- [Making Attempts](#making-attempts)
  - [Basic Usage](#basic-usage)
  - [Attemptable Classes](#attemptable-classes)
  - [Self-Configuring Classes](#self-configuring-classes)
- [Retry Configuration](#retry-configuration)
  - [Specifying Retry Attempts](#specifying-retry-attempts)
  - [Delay Strategies](#delay-strategies)
  - [Conditional Retries](#conditional-retries)
- [Fallback Handlers](#fallback-handlers)
  - [Defining Fallbacks](#defining-fallbacks)
  - [Fallback Chains](#fallback-chains)
  - [The Fallbackable Interface](#the-fallbackable-interface)
- [Exception Handling](#exception-handling)
  - [Catching Exceptions](#catching-exceptions)
  - [Re-throwing Exceptions](#re-throwing-exceptions)
  - [Suppressing Exceptions](#suppressing-exceptions)
- [Lifecycle Hooks](#lifecycle-hooks)
- [Conditional Execution](#conditional-execution)
- [Pipeline Integration](#pipeline-integration)
  - [Pipeline Attempts](#pipeline-attempts)
  - [Using AttemptPipe](#using-attemptpipe)
- [Concurrent Execution](#concurrent-execution)
  - [Running Concurrent Attempts](#running-concurrent-attempts)
  - [Racing Attempts](#racing-attempts)
- [Async Execution](#async-execution)
- [Working with Results](#working-with-results)
  - [The AttemptResult Object](#the-attemptresult-object)
  - [Monadic Operations](#monadic-operations)
- [Events](#events)
- [Testing](#testing)
- [Configuration](#configuration)

## Introduction

While building your application, you may encounter operations that can fail due to transient issues like network timeouts, API rate limits, or temporary service unavailability. Rather than letting these failures crash your application or writing repetitive try-catch blocks, Laravel Attempt provides a fluent, composable system for handling retries, fallbacks, and error recovery.

Attempt treats error handling as a first-class pipeline concern, allowing you to declaratively define how your application should respond when things go wrong. Whether you need simple retry logic with exponential backoff, complex fallback chains, or integration with Laravel’s native Pipeline, Attempt provides an expressive API that reads like natural language.

## Installation

You may install Attempt into your project using the Composer package manager:

```bash
composer require yannelli/attempt
```

After installing Attempt, you may optionally publish its configuration file using the `vendor:publish` Artisan command:

```bash
php artisan vendor:publish --tag="attempt-config"
```

## Making Attempts

### Basic Usage

The simplest way to use Attempt is to wrap a potentially failing operation with the `try` method. To execute the attempt and retrieve the result, you may call the `thenReturn` method:

```php
use Yannelli\Attempt\Facades\Attempt;

$result = Attempt::try(fn() => $api->call())->thenReturn();
```

If you need to pass input to your callable, you may provide additional arguments to the `try` method:

```php
$result = Attempt::try(MyAction::class, $order, $user)->thenReturn();
```

You may also pass an array of callables to the `try` method. When an array is provided, each callable will be executed in order as a fallback chain. If the first callable fails, the second will be attempted, and so on:

```php
$result = Attempt::try([
    PrimaryProvider::class,
    BackupProvider::class,
    CachedResponse::class,
], $payload)->thenReturn();
```

Attempt provides several methods for executing your attempt and retrieving the result:

|Method                   |Behavior                                      |
|-------------------------|----------------------------------------------|
|`then(Closure $callback)`|Transform and return the final result         |
|`thenReturn()`           |Return the processed value directly           |
|`thenReturnOrFail()`     |Return the value or throw on failure          |
|`run()`                  |Return an `AttemptResult` object with metadata|
|`get()`                  |Alias for `thenReturn()`                      |
|`value()`                |Alias for `thenReturn()`                      |

### Attemptable Classes

For more complex operations, you may create dedicated attemptable classes. These classes should implement the `Attemptable` interface and define a `handle` method that receives the input and returns a result:

```php
use Yannelli\Attempt\Contracts\Attemptable;

class FetchUserData implements Attemptable
{
    public function handle(mixed ...$input): mixed
    {
        [$userId] = $input;

        return Http::get("https://api.example.com/users/{$userId}")->json();
    }
}
```

Once you have defined your attemptable class, you may pass its class name to the `try` method:

```php
$userData = Attempt::try(FetchUserData::class, $userId)
    ->retry(3)
    ->thenReturn();
```

### Self-Configuring Classes

Sometimes you may want a class to define its own retry and fallback configuration. To accomplish this, your class may implement both the `Attemptable` and `ConfiguresAttempt` interfaces. The `configureAttempt` method receives an `AttemptBuilder` instance that you may use to define your preferred configuration:

```php
use Yannelli\Attempt\Contracts\Attemptable;
use Yannelli\Attempt\Contracts\ConfiguresAttempt;
use Yannelli\Attempt\AttemptBuilder;

class ResilientApiCall implements Attemptable, ConfiguresAttempt
{
    public function configureAttempt(AttemptBuilder $attempt): void
    {
        $attempt
            ->retry(3)
            ->exponentialBackoff(100, 5000)
            ->withJitter(0.1);
    }

    public function handle(mixed ...$input): mixed
    {
        return Http::get('https://api.example.com/data')->json();
    }
}
```

When using a self-configuring class, the configuration is automatically applied:

```php
$result = Attempt::try(ResilientApiCall::class)->thenReturn();
```

## Retry Configuration

### Specifying Retry Attempts

By default, Attempt will not retry a failed operation. To enable retries, call the `retry` method and specify how many times the operation should be attempted:

```php
Attempt::try($callable)
    ->retry(3)
    ->thenReturn();
```

### Delay Strategies

Often, you will want to wait between retry attempts to give transient issues time to resolve. Attempt provides several strategies for configuring delays between retries.

#### Fixed Delay

To wait a fixed number of milliseconds between all retries, pass an integer to the `delay` method:

```php
Attempt::try($callable)
    ->retry(3)
    ->delay(100) // Wait 100ms between retries
    ->thenReturn();
```

#### Explicit Delays

If you need different delays for each retry attempt, you may pass an array of millisecond values:

```php
Attempt::try($callable)
    ->retry(3)
    ->delay([1000, 5000, 15000]) // 1s, 5s, 15s
    ->thenReturn();
```

#### Exponential Backoff

Exponential backoff progressively increases the delay between retries. This strategy is particularly useful when interacting with rate-limited APIs or overloaded services. The `exponentialBackoff` method accepts a base delay and an optional maximum delay:

```php
Attempt::try($callable)
    ->retry(5)
    ->exponentialBackoff(base: 100, max: 30000) // 100ms, 200ms, 400ms, 800ms...
    ->thenReturn();
```

#### Linear Backoff

Linear backoff increases the delay by a fixed increment with each retry:

```php
Attempt::try($callable)
    ->retry(3)
    ->linearBackoff(base: 100, increment: 100) // 100ms, 200ms, 300ms
    ->thenReturn();
```

#### Adding Jitter

To prevent multiple failing operations from retrying in lockstep (known as the “thundering herd” problem), you may add randomized jitter to your delays. The `withJitter` method accepts a percentage value that determines how much variance to apply:

```php
Attempt::try($callable)
    ->retry(3)
    ->delay([1000, 5000, 10000])
    ->withJitter(0.2) // +/- 20% randomization
    ->thenReturn();
```

#### Custom Delay Functions

For complete control over delay calculation, you may use the `delayUsing` method with a closure that receives the current attempt number and the exception that triggered the retry:

```php
Attempt::try($callable)
    ->retry(5)
    ->delayUsing(fn(int $attempt, ?Throwable $e) => $attempt * 1000)
    ->thenReturn();
```

### Conditional Retries

Sometimes you may only want to retry an operation for specific types of failures. The `retryIf` method accepts a closure that receives the thrown exception and returns a boolean indicating whether the operation should be retried:

```php
Attempt::try($callable)
    ->retry(3)
    ->retryIf(fn(Throwable $e) => $e instanceof ConnectionException)
    ->thenReturn();
```

## Fallback Handlers

### Defining Fallbacks

When an operation fails after exhausting all retries, you may want to execute a fallback operation instead of throwing an exception. Use the `fallback` method to define an alternative callable:

```php
Attempt::try(PrimaryApi::class)
    ->fallback(BackupApi::class)
    ->thenReturn();
```

### Fallback Chains

You may define multiple fallbacks that will be tried in order. The first successful fallback wins:

```php
Attempt::try(PrimaryApi::class)
    ->fallback([
        SecondaryApi::class,
        TertiaryApi::class,
        fn() => Cache::get('fallback_value'),
    ])
    ->thenReturn();
```

For a more expressive syntax, you may chain multiple `orFallback` calls:

```php
Attempt::try(PrimaryApi::class)
    ->orFallback(SecondaryApi::class)
    ->orFallback(fn() => 'default')
    ->thenReturn();
```

### The Fallbackable Interface

For fallback classes that need access to the original exception, implement the `Fallbackable` interface. This interface defines a `handleFallback` method that receives both the exception and the original input:

```php
use Yannelli\Attempt\Contracts\Fallbackable;

class ApiErrorFallback implements Fallbackable
{
    public function handleFallback(Throwable $e, mixed ...$input): mixed
    {
        Log::warning('Using fallback due to: ' . $e->getMessage());

        return Cache::get('cached_response');
    }

    public function shouldSkip(Throwable $e): bool
    {
        // Skip this fallback for certain exceptions
        return $e instanceof ValidationException;
    }
}
```

## Exception Handling

### Catching Exceptions

Attempt allows you to register exception handlers that will be invoked when specific exceptions occur. You may catch specific exception types or all exceptions:

```php
// Catch specific exceptions
Attempt::try($callable)
    ->catch(ConnectionException::class, fn($e) => Log::error($e))
    ->catch(TimeoutException::class, fn($e) => Metrics::timeout())
    ->thenReturn();

// Catch all exceptions
Attempt::try($callable)
    ->catch(fn(Throwable $e) => Log::error($e))
    ->thenReturn();
```

### Re-throwing Exceptions

If you want to execute a handler but still throw the exception afterward, chain the `throw` method:

```php
Attempt::try($callable)
    ->catch(fn($e) => Log::error($e))
    ->throw()
    ->thenReturn();
```

### Suppressing Exceptions

To suppress all exceptions and return `null` on failure, use the `quiet` method:

```php
Attempt::try($callable)
    ->quiet()
    ->thenReturn(); // Returns null on failure
```

## Lifecycle Hooks

Attempt provides several hooks that allow you to execute code at specific points during the attempt lifecycle:

```php
Attempt::try($callable)
    ->finally(fn($context) => Log::info('Attempt completed'))
    ->defer(fn($context) => Metrics::record($context->elapsed()))
    ->onRetry(fn($context, $e) => Log::warning("Retry {$context->attemptNumber}"))
    ->onSuccess(fn($context, $result) => Cache::put('last_result', $result))
    ->onFailure(fn($context, $e) => Alert::send($e))
    ->thenReturn();
```

## Conditional Execution

You may conditionally execute an attempt using the `when` and `unless` methods:

```php
// Only execute if condition is true
Attempt::try($callable)
    ->when($shouldRun)
    ->thenReturn();

// Only execute if condition is false
Attempt::try($callable)
    ->unless($shouldSkip)
    ->thenReturn();

// With closure conditions
Attempt::try($callable)
    ->when(fn() => Feature::active('new-api'))
    ->thenReturn();
```

## Pipeline Integration

### Pipeline Attempts

Attempt integrates seamlessly with Laravel’s Pipeline. Use the `pipeline` method to execute a series of stages with built-in retry and fallback capabilities:

```php
$result = Attempt::pipeline([
    ValidateInput::class,
    ProcessData::class,
    SaveToDatabase::class,
])
    ->send($data)
    ->retry(2)
    ->thenReturn();
```

### Using AttemptPipe

You may also use `AttemptPipe` within a native Laravel Pipeline to wrap individual stages with retry logic:

```php
use Illuminate\Support\Facades\Pipeline;
use Yannelli\Attempt\Pipes\AttemptPipe;

$result = Pipeline::send($data)
    ->through([
        AttemptPipe::wrap(ExternalApiCall::class)
            ->retry(3)
            ->delay([100, 500, 1000]),
        ProcessResponse::class,
    ])
    ->thenReturn();
```

## Concurrent Execution

### Running Concurrent Attempts

When you need to execute multiple operations simultaneously, use the `concurrent` method. All operations will run in parallel, and you will receive an array of results:

```php
$concurrent = Attempt::concurrent([
    fn() => Http::get('https://api1.example.com'),
    fn() => Http::get('https://api2.example.com'),
    fn() => Http::get('https://api3.example.com'),
]);

// Run all and get array of AttemptResult objects
$results = $concurrent->run();

// Get only successful results
$successful = Attempt::concurrent([...])->successful();

// Get only failed results
$failed = Attempt::concurrent([...])->failed();

// Get values directly
$values = Attempt::concurrent([...])->thenReturn();
```

### Racing Attempts

When you need the result of the first successful operation, use the `race` method. The first operation to succeed wins, and other operations are abandoned:

```php
$result = Attempt::race([
    PrimaryProvider::class,
    SecondaryProvider::class,
    TertiaryProvider::class,
])->thenReturn();
```

## Async Execution

For long-running operations, you may dispatch an attempt to run asynchronously on the queue:

```php
$pendingResult = Attempt::try(LongRunningTask::class, $data)
    ->retry(3)
    ->async()
    ->onQueue('processing')
    ->dispatch();

// Wait for result when ready
$result = $pendingResult->await();
```

## Working with Results

### The AttemptResult Object

When you call the `run` method instead of `thenReturn`, you receive an `AttemptResult` object that provides detailed information about the attempt:

```php
$result = Attempt::try($callable)->run();

// Check status
$result->succeeded();  // bool
$result->failed();     // bool

// Get values
$result->value();      // mixed - the result value
$result->exception();  // ?Throwable - the exception if failed
$result->attempts();   // int - number of attempts made
$result->resolvedBy(); // string - 'primary', 'retry:2', 'fallback:ClassName'
```

### Monadic Operations

The `AttemptResult` object supports monadic operations for functional-style programming:

```php
$result->map(fn($value) => transform($value));
$result->getOrElse('default');
$result->getOrThrow();
$result->onSuccess(fn($value) => doSomething($value));
$result->onFailure(fn($e) => handleError($e));
```

## Events

Attempt dispatches events throughout the attempt lifecycle, allowing you to hook into various stages for logging, monitoring, or other purposes:

|Event              |When Fired                          |
|-------------------|------------------------------------|
|`AttemptStarted`   |When the attempt begins             |
|`AttemptSucceeded` |On successful completion            |
|`AttemptFailed`    |On each failure (before retry)      |
|`RetryAttempted`   |When a retry is initiated           |
|`FallbackTriggered`|When a fallback is tried            |
|`AllAttemptsFailed`|When all attempts and fallbacks fail|

If you need to disable events for a specific attempt, use the `withoutEvents` method:

```php
Attempt::try($callable)
    ->withoutEvents()
    ->thenReturn();
```

## Testing

Attempt includes a convenient fake implementation for testing. Use the `fake` method to replace the Attempt facade with a test double:

```php
use Yannelli\Attempt\Facades\Attempt;

it('retries on failure', function () {
    Attempt::fake()->sequence([
        new ConnectionException('Failed'),
        new ConnectionException('Failed'),
        ['success' => true],
    ]);

    $result = Attempt::try(MyApiCall::class)
        ->retry(3)
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->attempts())->toBe(3);

    Attempt::assertAttemptedTimes(MyApiCall::class, 3);
});

it('uses fallback when all retries fail', function () {
    Attempt::fake()->failFor(PrimaryApi::class, times: 5);

    $result = Attempt::try(PrimaryApi::class)
        ->retry(3)
        ->fallback(BackupApi::class)
        ->run();

    Attempt::assertFallbackUsed(BackupApi::class);
});
```

To run the package’s test suite:

```bash
composer test
```

## Configuration

The published configuration file (`config/attempt.php`) allows you to customize default behaviors:

```php
return [
    'defaults' => [
        'max_retries' => 3,
        'delay' => 100,
        'backoff' => 'exponential',
        'jitter' => 0.1,
    ],

    'backoff_strategies' => [
        'exponential' => [...],
        'linear' => [...],
        'fibonacci' => [...],
        'decorrelated_jitter' => [...],
    ],

    'async' => [
        'connection' => env('ATTEMPT_QUEUE_CONNECTION'),
        'queue' => env('ATTEMPT_QUEUE'),
        'timeout' => 60,
    ],

    'events' => [
        'enabled' => true,
    ],

    // Exceptions that should never trigger retries
    'never_retry' => [
        ValidationException::class,
        AuthenticationException::class,
        AuthorizationException::class,
        ModelNotFoundException::class,
    ],

    // Exceptions that should always trigger retries
    'always_retry' => [
        ConnectionException::class,
    ],
];
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Ryan Yannelli](https://ryanyannelli.com)
- [Nextvisit AI](https://nextvisit.ai)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
