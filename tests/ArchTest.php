<?php

declare(strict_types=1);
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Facade;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Yannelli\Attempt\Contracts\RetryStrategy;
use Yannelli\Attempt\Exceptions\AttemptException;

arch('contracts are interfaces')
    ->expect('Yannelli\Attempt\Contracts')
    ->toBeInterfaces();

arch('strategies implement RetryStrategy')
    ->expect('Yannelli\Attempt\Strategies')
    ->toImplement(RetryStrategy::class);

arch('exceptions extend base exception')
    ->expect('Yannelli\Attempt\Exceptions')
    ->toExtend(AttemptException::class)
    ->ignoring(AttemptException::class);

arch('events use Dispatchable trait')
    ->expect('Yannelli\Attempt\Events')
    ->toUseTrait(Dispatchable::class);

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
    ->toExtend(Facade::class);

arch('service provider extends package service provider')
    ->expect('Yannelli\Attempt\AttemptServiceProvider')
    ->toExtend(PackageServiceProvider::class);

arch('strict types are declared')
    ->expect('Yannelli\Attempt')
    ->toUseStrictTypes();

arch('tests use strict types')
    ->expect('Yannelli\Attempt\Tests')
    ->toUseStrictTypes()
    ->ignoring('Yannelli\Attempt\Tests\TestCase');
