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
 * @method static PipelineAttemptBuilder pipeline(array|Collection $pipes = [])
 * @method static ConcurrentAttemptBuilder concurrent(array|Collection $attempts = [])
 * @method static RaceAttemptBuilder race(array|Collection $attempts = [])
 * @method static AttemptFake fake()
 * @method static bool isFaked()
 * @method static void resetFake()
 *
 * @see \Yannelli\Attempt\AttemptManager
 */
class Attempt extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AttemptManager::class;
    }
}
