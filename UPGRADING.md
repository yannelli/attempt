# Upgrading

## Deprecations in 1.x

These deprecations keep working throughout 1.x and will be removed in 2.0.

### The configuration file

`config/attempt.php` is deprecated in favor of static methods on the `Attempt` facade, called from a service provider's `boot` method. Static settings take precedence; any setting that has not been configured statically still falls back to the configuration file.

Run the migration command in your development environment to convert a published configuration file:

```bash
php artisan attempt:migrate-config
```

It writes `app/Providers/AttemptServiceProvider.php`, registers it in `bootstrap/providers.php`, and offers to delete `config/attempt.php` (pass `--delete` to skip the prompt). It only migrates settings that differ from the package defaults:

| Configuration                     | Replacement                                                                          |
|-----------------------------------|--------------------------------------------------------------------------------------|
| `never_retry`                     | `Attempt::neverRetry([...])`                                                         |
| `always_retry`                    | `Attempt::alwaysRetry([...])`                                                        |
| `backoff_strategies`              | `Attempt::defineBackoff('name', fn (mixed ...$options) => new Strategy(...))`        |
| `events.enabled`                  | `Attempt::disableEvents()`                                                           |
| `async.connection`, `async.queue` | `Queue::route(AsyncAttemptJob::class, ...)` on Laravel 13, or a job subclass registered with `Attempt::useAsyncJob()` |
| `async.timeout`                   | A job subclass with `public ?int $timeout`, registered with `Attempt::useAsyncJob()` |

When queue routing cannot express the async settings (on Laravel 12, or with a customized timeout), the command generates `app/Jobs/ProcessAttempt.php` and registers it with `Attempt::useAsyncJob()`. The deprecated `async` settings only apply to the default job class, so a custom job configures itself.

The configuration file is kept when `async.connection` or `async.queue` is read from `ATTEMPT_QUEUE_CONNECTION` or `ATTEMPT_QUEUE`. Those values may differ between environments, so they are not hard-coded from the environment the command runs in; move them into your own configuration and use them with `Queue::route()` or a custom job.

### Undefined backoff names

Calling `backoff()` with a name that is neither built in, registered, nor configured reports an `InvalidArgumentException` and leaves the strategy unchanged. In 2.0 it will throw.

### No-op methods

`AttemptBuilder::via()`, `through()`, and `pipe()` never affected execution; use `Attempt::pipeline()` instead. `DelayCalculator::withDelay()`, `withStrategy()`, `withCallback()`, and `withJitter()` are unused.

## Behavior Changes

These fixes ship without a deprecation period:

- The service provider is deferred, so it only loads when Attempt is used. Subclasses of the provider stay eager unless they override `isDeferred()`, so lifecycle hooks they add keep running on every request.
- Async attempts are dispatched through Laravel's bus, so `Bus::fake()` captures them and Laravel 13 queue routes apply when no connection or queue is configured.
- Retry delays use Laravel's `Sleep` helper, so `Sleep::fake()` skips them in tests.
- A `delayUsing` callback that returns `null` defers to the configured delay or strategy instead of meaning zero.
- `Attempt::ai()` retries `Laravel\Ai\Exceptions\ProviderConnectionException`, and calling a backoff method after `ai()` changes its fallback delay while `Retry-After` hints are still honored.
- Fallbacks skipped via `Fallbackable::shouldSkip()` no longer fire `FallbackTriggered` or `onFallback` callbacks, and fallback classes no longer have `configureAttempt()` applied.

## Removals in 2.0

- `config/attempt.php`, the `attempt-config` publish tag, and the `ATTEMPT_QUEUE_CONNECTION` and `ATTEMPT_QUEUE` environment variables. Async jobs will no longer set a 60 second timeout by default; the worker's `--timeout` applies.
- The deprecated methods listed above.
- `backoff()` will throw for undefined names.
