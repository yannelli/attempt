<?php

declare(strict_types=1);

use Yannelli\Attempt\Facades\Attempt;

it('skips execution when when() condition is false', function () {
    $executed = false;

    $result = Attempt::try(function () use (&$executed) {
        $executed = true;

        return 'executed';
    })
        ->when(false)
        ->run();

    expect($executed)->toBeFalse();
    expect($result->resolvedBy())->toBe('skipped');
    expect($result->succeeded())->toBeTrue();
});

it('executes when when() condition is true', function () {
    $executed = false;

    $result = Attempt::try(function () use (&$executed) {
        $executed = true;

        return 'executed';
    })
        ->when(true)
        ->run();

    expect($executed)->toBeTrue();
    expect($result->value())->toBe('executed');
});

it('accepts closure for when() condition', function () {
    $conditionCalled = false;

    $result = Attempt::try(fn () => 'executed')
        ->when(function () use (&$conditionCalled) {
            $conditionCalled = true;

            return true;
        })
        ->run();

    expect($conditionCalled)->toBeTrue();
    expect($result->value())->toBe('executed');
});

it('skips execution when unless() condition is true', function () {
    $executed = false;

    $result = Attempt::try(function () use (&$executed) {
        $executed = true;

        return 'executed';
    })
        ->unless(true)
        ->run();

    expect($executed)->toBeFalse();
    expect($result->resolvedBy())->toBe('skipped');
});

it('executes when unless() condition is false', function () {
    $executed = false;

    $result = Attempt::try(function () use (&$executed) {
        $executed = true;

        return 'executed';
    })
        ->unless(false)
        ->run();

    expect($executed)->toBeTrue();
    expect($result->value())->toBe('executed');
});

it('accepts closure for unless() condition', function () {
    $conditionCalled = false;

    $result = Attempt::try(fn () => 'executed')
        ->unless(function () use (&$conditionCalled) {
            $conditionCalled = true;

            return false;
        })
        ->run();

    expect($conditionCalled)->toBeTrue();
    expect($result->value())->toBe('executed');
});

it('last condition wins', function () {
    $result = Attempt::try(fn () => 'executed')
        ->when(true)
        ->when(false)
        ->run();

    expect($result->resolvedBy())->toBe('skipped');
});
