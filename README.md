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

Network calls fail. APIs time out, rate limits trip, third-party services drop offline for thirty seconds at a time. Attempt is a Laravel package for wrapping operations that might fail, so you can add retries, backoff, and fallbacks without scattering `try`/`catch` blocks all over your code.

You build an attempt by chaining methods on a builder. It works with closures, invokable classes, action classes, and Laravel pipeline stages. It can also run on the queue when you need it to.

## Installation

Install with Composer:

```bash
composer require yannelli/attempt
```

If you want to override the defaults, publish the config file:

```bash
php artisan vendor:publish --tag="attempt-config"
```

## Making Attempts

### Basic Usage

Wrap an operation with `try`, then call `thenReturn` to execute and get the result back:

```php
use Yannelli\Attempt\Facades\Attempt;

$result = Attempt::try(fn() => $api->call())->thenReturn();
```

Pass arguments to the callable as extra parameters:

```php
$result = Attempt::try(MyAction::class, $order, $user)->thenReturn();
```

Pass an array of callables and they run in order as a fallback chain. If the first one fails, the second runs, and so on:

```php
$result = Attempt::try([
    PrimaryProvider::class,
    BackupProvider::class,
    CachedResponse::class,
], $payload)->thenReturn();
```

Use any of these methods to run the attempt and get the result back:

|Method                   |Behavior                                      |
|-------------------------|----------------------------------------------|
|`then(Closure $callback)`|Transform and return the final result         |
|`thenReturn()`           |Return the processed value directly           |
|`thenReturnOrFail()`     |Return the value or throw on failure          |
|`run()`                  |Return an `AttemptResult` object with metadata|
|`get()`                  |Alias for `thenReturn()`                      |
|`value()`                |Alias for `thenReturn()`                      |

### Attemptable Classes

For anything more involved than a closure, create a class that implements `Attemptable` and put the work in `handle`:

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

Pass the class name to `try`:

```php
$userData = Attempt::try(FetchUserData::class, $userId)
    ->retry(3)
    ->thenReturn();
```

### Self-Configuring Classes

If you want a class to carry its own retry and fallback config, implement `ConfiguresAttempt` alongside `Attemptable`. The `configureAttempt` method gets an `AttemptBuilder` to set things up:

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

The config is applied automatically when you use the class:

```php
$result = Attempt::try(ResilientApiCall::class)->thenReturn();
```

## Retry Configuration

### Specifying Retry Attempts

By default a failed operation isn't retried. Call `retry` with the number of attempts to allow:

```php
Attempt::try($callable)
    ->retry(3)
    ->thenReturn();
```

### Delay Strategies

You'll usually want to wait between retries so the underlying issue has a chance to clear. There are a few ways to do that.

#### Fixed Delay

Pass an integer to `delay` for a fixed wait in milliseconds between every retry:

```php
Attempt::try($callable)
    ->retry(3)
    ->delay(100) // Wait 100ms between retries
    ->thenReturn();
```

#### Explicit Delays

For different waits on each retry, pass an array of millisecond values:

```php
Attempt::try($callable)
    ->retry(3)
    ->delay([1000, 5000, 15000]) // 1s, 5s, 15s
    ->thenReturn();
```

#### Exponential Backoff

Exponential backoff doubles the wait each retry, which is what you usually want against a rate-limited or overloaded service. `exponentialBackoff` takes a base delay and an optional cap:

```php
Attempt::try($callable)
    ->retry(5)
    ->exponentialBackoff(base: 100, max: 30000) // 100ms, 200ms, 400ms, 800ms...
    ->thenReturn();
```

#### Linear Backoff

Linear backoff adds a fixed increment to the wait on each retry:

```php
Attempt::try($callable)
    ->retry(3)
    ->linearBackoff(base: 100, increment: 100) // 100ms, 200ms, 300ms
    ->thenReturn();
```

#### Adding Jitter

If many clients fail at once and retry on the same schedule, they'll hammer the recovering service in lockstep. This is the "thundering herd" problem. `withJitter` randomizes each delay; the argument is the percent variance to apply:

```php
Attempt::try($callable)
    ->retry(3)
    ->delay([1000, 5000, 10000])
    ->withJitter(0.2) // +/- 20% randomization
    ->thenReturn();
```

#### Custom Delay Functions

For full control, pass a closure to `delayUsing`. It receives the current attempt number and the exception that triggered the retry:

```php
Attempt::try($callable)
    ->retry(5)
    ->delayUsing(fn(int $attempt, ?Throwable $e) => $attempt * 1000)
    ->thenReturn();
```

### Conditional Retries

To retry only on certain failures, use `retryIf` with a closure. Return `true` to retry, `false` to give up:

```php
Attempt::try($callable)
    ->retry(3)
    ->retryIf(fn(Throwable $e) => $e instanceof ConnectionException)
    ->thenReturn();
```

## Fallback Handlers

### Defining Fallbacks

When all retries are exhausted, you can run a fallback instead of letting the exception propagate. Pass a callable to `fallback`:

```php
Attempt::try(PrimaryApi::class)
    ->fallback(BackupApi::class)
    ->thenReturn();
```

### Fallback Chains

Pass an array of fallbacks and they're tried in order. The first one to succeed wins:

```php
Attempt::try(PrimaryApi::class)
    ->fallback([
        SecondaryApi::class,
        TertiaryApi::class,
        fn() => Cache::get('fallback_value'),
    ])
    ->thenReturn();
```

Or chain `orFallback` calls if you prefer:

```php
Attempt::try(PrimaryApi::class)
    ->orFallback(SecondaryApi::class)
    ->orFallback(fn() => 'default')
    ->thenReturn();
```

### The Fallbackable Interface

If your fallback class needs the original exception (for example, to log a different message based on the failure type), implement `Fallbackable`. The `handleFallback` method receives the exception and the original input:

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

Register exception handlers with `catch`. You can target specific exception classes or catch everything:

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

To run the handler and still rethrow afterward, chain `throw`:

```php
Attempt::try($callable)
    ->catch(fn($e) => Log::error($e))
    ->throw()
    ->thenReturn();
```

### Suppressing Exceptions

To swallow exceptions entirely and return `null` on failure, use `quiet`:

```php
Attempt::try($callable)
    ->quiet()
    ->thenReturn(); // Returns null on failure
```

## Lifecycle Hooks

Hook into different points of the attempt lifecycle:

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

Skip the attempt entirely with `when` and `unless`:

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

Attempt works with Laravel's Pipeline. Use `pipeline` to run stages with retry and fallback support:

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

Or use `AttemptPipe` inside a native Laravel pipeline to wrap a single stage with retry logic, leaving the rest of the pipeline alone:

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

Use `concurrent` to run multiple operations in parallel. You get back an array of results:

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

Use `race` when you only care about the first successful result. As soon as one operation succeeds the rest are abandoned:

```php
$result = Attempt::race([
    PrimaryProvider::class,
    SecondaryProvider::class,
    TertiaryProvider::class,
])->thenReturn();
```

## Async Execution

Dispatch long-running attempts to the queue:

```php
Attempt::try(LongRunningTask::class, $data)
    ->retry(3)
    ->async()
    ->onQueue('processing')
    ->then(fn ($value) => Log::info('Completed', ['value' => $value]))
    ->catch(fn (Throwable $e) => Log::error('Failed', ['error' => $e->getMessage()]))
    ->dispatch();
```

To run synchronously and bypass the queue, use `await`:

```php
$result = Attempt::try(LongRunningTask::class, $data)
    ->retry(3)
    ->async()
    ->await(); // Runs synchronously, returns AttemptResult
```

## Working with Results

### The AttemptResult Object

`run` returns an `AttemptResult` instead of the bare value, with details about what happened:

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

`AttemptResult` also supports a few monadic-style helpers:

```php
$result->map(fn($value) => transform($value));
$result->getOrElse('default');
$result->getOrThrow();
$result->onSuccess(fn($value) => doSomething($value));
$result->onFailure(fn($e) => handleError($e));
```

## Events

Attempt dispatches events you can listen to for logging, metrics, or anything else:

|Event              |When Fired                          |
|-------------------|------------------------------------|
|`AttemptStarted`   |When the attempt begins             |
|`AttemptSucceeded` |On successful completion            |
|`AttemptFailed`    |On each failure (before retry)      |
|`RetryAttempted`   |When a retry is initiated           |
|`FallbackTriggered`|When a fallback is tried            |
|`AllAttemptsFailed`|When all attempts and fallbacks fail|

To disable events for a single attempt, use `withoutEvents`:

```php
Attempt::try($callable)
    ->withoutEvents()
    ->thenReturn();
```

## Testing

Attempt ships with a fake for tests. Replace the facade with `Attempt::fake()` and queue up the responses you want:

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

To run the package's own test suite:

```bash
composer test
```

## Configuration

The published config file (`config/attempt.php`) holds the default behaviors:

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
