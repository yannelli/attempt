# AGENTS.md

## Cursor Cloud specific instructions

This repo is the `yannelli/attempt` Laravel **library** (a Composer package), not a runnable
application. There is no web server, database, frontend, or dev server. "Running the app" here
means exercising the package through its test suite (Pest on top of Orchestra Testbench).

Runtime: PHP 8.4 + Composer are required and are installed by the update script (`composer install`).
There is no `composer.lock`, so `composer install` resolves dependencies fresh.

Standard commands (see `composer.json` scripts and `CONTRIBUTING.md`):
- Tests: `composer test` (alias for `vendor/bin/pest`). CI uses `vendor/bin/pest --ci`.
- Lint check: `vendor/bin/pint --test`. Auto-fix: `composer format`.

Non-obvious caveats:
- `CONTRIBUTING.md` mentions `composer analyse` and `composer format:test`, but those scripts do
  **not** exist in `composer.json`. PHPStan is configured (`phpstan.neon.dist`, level 5) but
  `phpstan` is **not** in `require-dev`, so static analysis needs a separate install
  (`composer require --dev larastan/larastan`) before `vendor/bin/phpstan analyse` will work.
- To run the package in an ad-hoc standalone PHP script you must boot a Laravel container so the
  `Attempt` facade resolves. Use Orchestra Testbench and its skeleton base path, e.g.
  `Application::create(basePath: Orchestra\Testbench\default_skeleton_path())` then
  `Facade::setFacadeApplication($app)` and `$app->register(AttemptServiceProvider::class)`.
  Passing the repo root as `basePath` fails with "bootstrap/cache directory must be present".
