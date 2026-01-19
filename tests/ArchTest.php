<?php

declare(strict_types=1);

arch('contracts are interfaces')
    ->expect('Yannelli\Attempt\Contracts')
    ->toBeInterfaces();

arch('strategies implement RetryStrategy')
    ->expect('Yannelli\Attempt\Strategies')
    ->toImplement(\Yannelli\Attempt\Contracts\RetryStrategy::class);

arch('exceptions extend base exception')
    ->expect('Yannelli\Attempt\Exceptions')
    ->toExtend(\Yannelli\Attempt\Exceptions\AttemptException::class)
    ->ignoring(\Yannelli\Attempt\Exceptions\AttemptException::class);

arch('events use Dispatchable trait')
    ->expect('Yannelli\Attempt\Events')
    ->toUseTrait(\Illuminate\Foundation\Events\Dispatchable::class);

arch('no debugging statements')
    ->expect('Yannelli\Attempt')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump', 'print_r']);

arch('concerns are traits')
    ->expect('Yannelli\Attempt\Concerns')
    ->toBeTraits();

arch('builder classes are not final')
    ->expect('Yannelli\Attempt\Builders')
    ->not->toBeFinal();

arch('facades extend base facade')
    ->expect('Yannelli\Attempt\Facades')
    ->toExtend(\Illuminate\Support\Facades\Facade::class);

arch('service provider extends package service provider')
    ->expect('Yannelli\Attempt\AttemptServiceProvider')
    ->toExtend(\Spatie\LaravelPackageTools\PackageServiceProvider::class);

arch('strict types are declared')
    ->expect('Yannelli\Attempt')
    ->toUseStrictTypes();

arch('tests use strict types')
    ->expect('Yannelli\Attempt\Tests')
    ->toUseStrictTypes()
    ->ignoring('Yannelli\Attempt\Tests\TestCase');
