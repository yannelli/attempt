<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Facades;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Yannelli\Attempt\AttemptBuilder;
use Yannelli\Attempt\AttemptManager;
use Yannelli\Attempt\Builders\ConcurrentAttemptBuilder;
use Yannelli\Attempt\Builders\PipelineAttemptBuilder;
use Yannelli\Attempt\Builders\RaceAttemptBuilder;
use Yannelli\Attempt\Testing\AttemptFake;

/**
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
    protected static function getFacadeAccessor(): string
    {
        return AttemptManager::class;
    }
}
