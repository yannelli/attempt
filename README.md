# Attempt for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/yannelli/attempt.svg?style=flat-square)](https://packagist.org/packages/yannelli/attempt)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/yannelli/attempt/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/yannelli/attempt/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/yannelli/attempt.svg?style=flat-square)](https://packagist.org/packages/yannelli/attempt)

A fluent, composable attempt/retry/fallback system for Laravel applications. Treats error handling as a first-class pipeline concern, supporting closures, invokable classes, class pipelines, and seamless integration with Laravel's native Pipeline.

## Installation

You can install the package via composer:

```bash
composer require yannelli/attempt
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="attempt-config"
```

## Basic Usage

```php
use Yannelli\Attempt\Facades\Attempt;

// Simplest form
$result = Attempt::try(fn() => $api->call())->thenReturn();

// With inline input
$result = Attempt::try(MyAction::class, $order, $user)->thenReturn();

// Array of callables (executed in order as fallback chain)
$result = Attempt::try([
    PrimaryProvider::class,
    BackupProvider::class,
    CachedResponse::class,
], $payload)->thenReturn();
```

## Full Composition Example

```php
$result = Attempt::try(MyApiCall::class, $payload)
    ->retry(3)
    ->delay([1000, 5000, 15000])
    ->fallback([
        FallbackOne::class,
        EmergencyFallback::class,
    ])
    ->catch(fn(Throwable $e) => Log::error($e))
    ->finally(fn() => Metrics::flush())
    ->defer(fn() => CleanupJob::dispatch())
    ->then(fn($result) => new ApiResponse($result));
```

## Execution Methods

| Method | Behavior |
|--------|----------|
| `then(Closure $callback)` | Transform and return final result |
| `thenReturn()` | Return processed value directly |
| `thenReturnOrFail()` | Return value or throw on failure |
| `run()` | Return `AttemptResult` object with metadata |
| `get()` | Alias for `thenReturn()` |
| `value()` | Alias for `thenReturn()` |

## Retry Configuration

```php
// Fixed delay (100ms between all retries)
Attempt::try($callable)
    ->retry(3)
    ->delay(100)
    ->thenReturn();

// Explicit delays per attempt
Attempt::try($callable)
    ->retry(3)
    ->delay([1000, 5000, 15000]) // 1s, 5s, 15s
    ->thenReturn();

// Exponential backoff (100ms, 200ms, 400ms, 800ms...)
Attempt::try($callable)
    ->retry(5)
    ->exponentialBackoff(base: 100, max: 30000)
    ->thenReturn();

// Linear backoff (100ms, 200ms, 300ms...)
Attempt::try($callable)
    ->retry(3)
    ->linearBackoff(base: 100, increment: 100)
    ->thenReturn();

// With jitter (+/- 20% randomization)
Attempt::try($callable)
    ->retry(3)
    ->delay([1000, 5000, 10000])
    ->withJitter(0.2)
    ->thenReturn();

// Custom delay function
Attempt::try($callable)
    ->retry(5)
    ->delayUsing(fn(int $attempt, ?Throwable $e) => $attempt * 1000)
    ->thenReturn();

// Conditional retry
Attempt::try($callable)
    ->retry(3)
    ->retryIf(fn(Throwable $e) => $e instanceof ConnectionException)
    ->thenReturn();
```

## Fallback Handlers

```php
// Single fallback
Attempt::try(PrimaryApi::class)
    ->fallback(BackupApi::class)
    ->thenReturn();

// Fallback chain (first success wins)
Attempt::try(PrimaryApi::class)
    ->fallback([
        SecondaryApi::class,
        TertiaryApi::class,
        fn() => Cache::get('fallback_value'),
    ])
    ->thenReturn();

// Fluent fallback syntax
Attempt::try(PrimaryApi::class)
    ->orFallback(SecondaryApi::class)
    ->orFallback(fn() => 'default')
    ->thenReturn();
```

### Fallbackable Interface

Create classes that handle fallback scenarios with access to the original exception:

```php
use Yannelli\Attempt\Contracts\Fallbackable;

class ApiErrorFallback implements Fallbackable
{
    public function handleFallback(Throwable $e, mixed ...$input): mixed
    {
        // Access the original exception
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

## Lifecycle Hooks

```php
Attempt::try($callable)
    ->finally(fn($context) => Log::info('Attempt completed'))
    ->defer(fn($context) => Metrics::record($context->elapsed()))
    ->onRetry(fn($context, $e) => Log::warning("Retry {$context->attemptNumber}"))
    ->onSuccess(fn($context, $result) => Cache::put('last_result', $result))
    ->onFailure(fn($context, $e) => Alert::send($e))
    ->thenReturn();
```

## Exception Handling

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

// Re-throw after handling
Attempt::try($callable)
    ->catch(fn($e) => Log::error($e))
    ->throw()
    ->thenReturn();

// Suppress all exceptions (return null on failure)
Attempt::try($callable)
    ->quiet()
    ->thenReturn();
```

## Conditional Execution

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

### Using PipelineAttemptBuilder

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

### Using AttemptPipe in Native Laravel Pipeline

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

## Concurrent and Race Execution

### Concurrent (Run All)

```php
$results = Attempt::concurrent([
    fn() => Http::get('https://api1.example.com'),
    fn() => Http::get('https://api2.example.com'),
    fn() => Http::get('https://api3.example.com'),
])->run();

// Get successful results only
$successful = $results->successes();

// Get failed results only
$failed = $results->failures();
```

### Race (First Success Wins)

```php
$result = Attempt::race([
    PrimaryProvider::class,
    SecondaryProvider::class,
    TertiaryProvider::class,
])->thenReturn();
```

## Async Execution

```php
// Dispatch to queue
$pendingResult = Attempt::try(LongRunningTask::class, $data)
    ->retry(3)
    ->async()
    ->onQueue('processing')
    ->dispatch();

// Wait for result
$result = $pendingResult->await();
```

## Working with AttemptResult

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

// Monadic operations
$result->map(fn($value) => transform($value));
$result->getOrElse('default');
$result->getOrThrow();
$result->onSuccess(fn($value) => doSomething($value));
$result->onFailure(fn($e) => handleError($e));
```

## Creating Attemptable Classes

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

// Usage
$userData = Attempt::try(FetchUserData::class, $userId)
    ->retry(3)
    ->thenReturn();
```

## Self-Configuring Classes

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

// Configuration is automatically applied
$result = Attempt::try(ResilientApiCall::class)->thenReturn();
```

## Events

The package dispatches events throughout the attempt lifecycle:

| Event | When Fired |
|-------|-----------|
| `AttemptStarted` | When attempt begins |
| `AttemptSucceeded` | On successful completion |
| `AttemptFailed` | On each failure (before retry) |
| `RetryAttempted` | When a retry is initiated |
| `FallbackTriggered` | When a fallback is tried |
| `AllAttemptsFailed` | When all attempts and fallbacks fail |

```php
// Disable events for a specific attempt
Attempt::try($callable)
    ->withoutEvents()
    ->thenReturn();
```

## Testing

### Using AttemptFake

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

### Run Tests

```bash
composer test
```

## Configuration

The published config file (`config/attempt.php`) includes:

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

- [Yannelli](https://github.com/yannelli)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
