# Changelog

All notable changes to `yannelli/attempt` will be documented in this file.

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
