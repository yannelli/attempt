# Changelog

All notable changes to `yannelli/attempt` will be documented in this file.

## v1.4.0 - 2026-11-21

### Added

- Added static configuration on the `Attempt` facade: `neverRetry()`, `alwaysRetry()`, `defineBackoff()`, `disableEvents()`, `useAsyncJob()`, and `flushState()`. These never resolve the manager, so configuring Attempt from a service provider does not load the deferred provider.
- Added `Attempt::useAsyncJob()` to run async attempts with a custom `AsyncAttemptJob` subclass that sets its own connection, queue, timeout, or middleware on Laravel 12 and 13.
- Added the `attempt:migrate-config` command, which converts a published `config/attempt.php` into a generated service provider (and job class when needed).
- Added the `fixed` named backoff strategy.

### Changed

- The service provider is now deferred. It still extends Spatie's `PackageServiceProvider`; subclasses stay eager unless they override `isDeferred()`.
- Async attempts are dispatched through Laravel's bus, so `Bus::fake()` captures them and Laravel 13 `Queue::route()` applies.
- Retry delays use Laravel's `Sleep` helper, so `Sleep::fake()` skips them in tests.
- A `delayUsing` callback may return `null` to defer to the configured delay or strategy.

### Fixed

- Fixed `Attempt::ai()` not retrying connection errors now that laravel/ai wraps them in `ProviderConnectionException`.
- Fixed backoff methods called after `ai()` or in `RetryAiRequests::configureUsing()` being ignored; `Retry-After` hints are still honored.
- Fixed undefined backoff names being silently ignored (they are now reported) and positional backoff options being dropped.
- Fixed skipped fallbacks firing `FallbackTriggered` and `onFallback`, fallbacks being resolved twice, `configureAttempt()` being applied to fallbacks, and list fallbacks being duplicated when a builder runs again.
- Fixed exponential and Fibonacci backoff overflowing at high attempt counts.
- Fixed `AttemptPipe` ignoring `delayUsing()`.

### Deprecated

- `config/attempt.php`, the `attempt-config` publish tag, and the `ATTEMPT_QUEUE_CONNECTION` and `ATTEMPT_QUEUE` environment variables. Settings not configured statically still fall back to the config file until 2.0. Run `php artisan attempt:migrate-config` to migrate.
- The no-op `AttemptBuilder::via()`, `through()`, and `pipe()` methods, and the unused `DelayCalculator::with*()` methods.
- Undefined backoff names will throw in 2.0.

See [UPGRADING](https://github.com/yannelli/attempt/blob/main/UPGRADING.md) for details.

**Full Changelog**: https://github.com/yannelli/attempt/compare/v1.3.1...v1.4.0

## v1.3.1 - 2026-10-03

### What's Changed

* chore(deps): bump actions/checkout from 6 to 7 by @dependabot[bot] in https://github.com/yannelli/attempt/pull/9
* build(deps): bump dependabot/fetch-metadata from 2.5.0 to 3.1.0 by @dependabot[bot] in https://github.com/yannelli/attempt/pull/5

**Full Changelog**: https://github.com/yannelli/attempt/compare/v1.3.0...v1.3.1

## v1.3.0 - 2026-07-30

### What's Changed

* Laravel AI SDK integration, package icon & banner by @yannelli in https://github.com/yannelli/attempt/pull/13

**Full Changelog**: https://github.com/yannelli/attempt/compare/v1.2.0...v1.3.0

## v1.2.0 - 2026-07-30

### Added

- Added complete PHP 8.4/8.5 and Laravel 12/13 CI coverage, including Composer validation and code-style checks on pull requests.
- Added facade assertion proxies so the documented `Attempt::assert*()` testing API works as expected.

### Fixed

- Fixed PHP array callables being interpreted as fallback chains.
- Fixed failed fallback chains reporting the primary exception instead of the final fallback exception.
- Fixed self-configuring callables applying their configuration too late and repeatedly on every retry.
- Fixed named backoff strategy construction, configured maximum delays, and sparse delay arrays.
- Fixed async queue timeouts being ignored and builder options being lost during queue serialization.
- Fixed async callback failures being silently acknowledged or invoking failure callbacks more than once.
- Fixed pipeline attempts dropping exception handlers, retry predicates, custom delays, and lifecycle hooks.
- Fixed aggregate retry and delay settings being ignored for supplied attempt builders.
- Fixed fake assertions counting builder creation as an executed attempt.

### Changed

- Declared the queue and bus packages used at runtime and removed dependencies on Foundation-only dispatch traits.
- Removed unused retry defaults that contradicted the documented no-retry default.
- Clarified that `concurrent()` groups sequential attempt results and `race()` tries operations in declaration order; neither API currently provides parallel execution.

**Full Changelog**: https://github.com/yannelli/attempt/compare/v1.1.0...v1.2.0

## v1.1.0 - 2026-07-05

### What's Changed

* feat: add Laravel 13 support by @yannelli in https://github.com/yannelli/attempt/pull/10

**Full Changelog**: https://github.com/yannelli/attempt/compare/v1.0.1...v1.1.0

## v1.0.1 - 2026-05-09

### Improvements

- **Dependencies** — normalized `laravel/pint` and `nunomaduro/collision` version constraints (dropped literal `v` prefix) and bumped their lower bounds to the latest patch releases (`^1.29.1` and `^8.9.4`).

## v1.0.0 - 2026-03-14

### Breaking Changes

- **Dropped PHP 8.2 and 8.3 support** — now requires PHP 8.4+
- **Dropped Laravel 11 support** — now requires Laravel 12+
- Updated all `illuminate/*` dependencies to `^12.0`
- Updated dev dependencies: `orchestra/testbench` to `^10.0`, `pestphp/pest` to `^3.8.6`, `pestphp/pest-plugin-arch` to `^3.0`, `pestphp/pest-plugin-laravel` to `^3.0`

### Bug Fixes

- **Fixed async closure serialization** — closures are now wrapped in `SerializableClosure` for proper queue serialization, preventing failures when dispatching async attempts
- **Fixed `AsyncAttemptBuilder`** — removed incorrect `ShouldQueue` interface that should only be on the job class, not the builder
- **Fixed async job closure invocation** — `then` and `catch` callbacks are now called via `getClosure()` for consistency with `SerializableClosure`
- **Fixed async job configuration** — `delayCallback`, `catchHandlers`, and `finallyCallbacks` are now properly restored when rebuilding the `AttemptBuilder` inside the queued job
- **Fixed phpstan config** — removed reference to non-existent `database` path
- **Fixed phpunit config** — renamed placeholder test suite from `VendorName` to `Attempt`

### Improvements

- **Updated async documentation** — corrected the README async execution example to show the `dispatch()`/`await()` API accurately
- **Simplified `AttemptBuilder`** — removed unnecessary `defer()` existence check since Laravel 12 always provides it
- **Used `Illuminate\Support\Carbon`** instead of `Carbon\Carbon` in `AttemptContext` for better Laravel integration
- **Code style cleanup** — replaced fully-qualified class references with imports across config, facades, and tests
- **CI matrix updated** — trimmed to PHP 8.4 + Laravel 12 only

## v0.1.1 - 2026-01-19

All notable changes to `attempt` will be documented in this file.

### v0.1.1 - 2025-01-19

#### Changed

- Rewrote README documentation to be more warm and readable
- Added CONTRIBUTING.md with contribution guidelines
- Added SECURITY.md with vulnerability reporting policy

**Full Changelog:** https://github.com/yannelli/attempt/compare/v0.1.0…v0.1.1

## v0.1.0 - 2026-01-19

### What's Changed

* Bump actions/checkout from 5 to 6 by @dependabot[bot] in https://github.com/yannelli/attempt/pull/1
* Implement yannelli/attempt package with full test coverage by @yannelli in https://github.com/yannelli/attempt/pull/2

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/yannelli/attempt/pull/1
* @yannelli made their first contribution in https://github.com/yannelli/attempt/pull/2

**Full Changelog**: https://github.com/yannelli/attempt/commits/v0.1.0
