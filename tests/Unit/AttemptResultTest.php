<?php

declare(strict_types=1);

use Yannelli\Attempt\AttemptResult;
use Yannelli\Attempt\Exceptions\AllFallbacksFailed;

it('can create a successful result', function () {
    $result = new AttemptResult(
        value: 'test',
        succeeded: true,
        exception: null,
        attempts: 1
    );

    expect($result->succeeded())->toBeTrue();
    expect($result->failed())->toBeFalse();
    expect($result->value())->toBe('test');
});

it('can create a failed result', function () {
    $exception = new RuntimeException('error');

    $result = new AttemptResult(
        value: null,
        succeeded: false,
        exception: $exception,
        attempts: 3
    );

    expect($result->succeeded())->toBeFalse();
    expect($result->failed())->toBeTrue();
    expect($result->exception())->toBe($exception);
    expect($result->attempts())->toBe(3);
});

it('can get resolvedBy', function () {
    $result = new AttemptResult(
        value: 'test',
        succeeded: true,
        exception: null,
        attempts: 2,
        resolvedBy: 'retry:2'
    );

    expect($result->resolvedBy())->toBe('retry:2');
});

it('can map value on success', function () {
    $result = new AttemptResult(
        value: 5,
        succeeded: true,
        exception: null,
        attempts: 1
    );

    $mapped = $result->map(fn ($v) => $v * 2);

    expect($mapped->value())->toBe(10);
    expect($mapped->succeeded())->toBeTrue();
});

it('does not map value on failure', function () {
    $result = new AttemptResult(
        value: null,
        succeeded: false,
        exception: new RuntimeException('error'),
        attempts: 1
    );

    $mapped = $result->map(fn ($v) => $v * 2);

    expect($mapped->failed())->toBeTrue();
    expect($mapped)->toBe($result);
});

it('getOrElse returns value on success', function () {
    $result = new AttemptResult(
        value: 'success',
        succeeded: true,
        exception: null,
        attempts: 1
    );

    expect($result->getOrElse('default'))->toBe('success');
});

it('getOrElse returns default on failure', function () {
    $result = new AttemptResult(
        value: null,
        succeeded: false,
        exception: new RuntimeException('error'),
        attempts: 1
    );

    expect($result->getOrElse('default'))->toBe('default');
});

it('getOrElse accepts closure as default', function () {
    $result = new AttemptResult(
        value: null,
        succeeded: false,
        exception: new RuntimeException('error'),
        attempts: 1
    );

    expect($result->getOrElse(fn () => 'computed'))->toBe('computed');
});

it('getOrThrow returns value on success', function () {
    $result = new AttemptResult(
        value: 'success',
        succeeded: true,
        exception: null,
        attempts: 1
    );

    expect($result->getOrThrow())->toBe('success');
});

it('getOrThrow throws on failure', function () {
    $result = new AttemptResult(
        value: null,
        succeeded: false,
        exception: new RuntimeException('error'),
        attempts: 1
    );

    expect(fn () => $result->getOrThrow())->toThrow(RuntimeException::class);
});

it('getOrThrow throws AllFallbacksFailed when no exception', function () {
    $result = new AttemptResult(
        value: null,
        succeeded: false,
        exception: null,
        attempts: 1
    );

    expect(fn () => $result->getOrThrow())->toThrow(AllFallbacksFailed::class);
});

it('onSuccess runs callback on success', function () {
    $called = false;
    $receivedValue = null;

    $result = new AttemptResult(
        value: 'test',
        succeeded: true,
        exception: null,
        attempts: 1
    );

    $result->onSuccess(function ($value) use (&$called, &$receivedValue) {
        $called = true;
        $receivedValue = $value;
    });

    expect($called)->toBeTrue();
    expect($receivedValue)->toBe('test');
});

it('onSuccess does not run callback on failure', function () {
    $called = false;

    $result = new AttemptResult(
        value: null,
        succeeded: false,
        exception: new RuntimeException('error'),
        attempts: 1
    );

    $result->onSuccess(function () use (&$called) {
        $called = true;
    });

    expect($called)->toBeFalse();
});

it('onFailure runs callback on failure', function () {
    $called = false;
    $receivedException = null;
    $exception = new RuntimeException('error');

    $result = new AttemptResult(
        value: null,
        succeeded: false,
        exception: $exception,
        attempts: 1
    );

    $result->onFailure(function ($e) use (&$called, &$receivedException) {
        $called = true;
        $receivedException = $e;
    });

    expect($called)->toBeTrue();
    expect($receivedException)->toBe($exception);
});

it('onFailure does not run callback on success', function () {
    $called = false;

    $result = new AttemptResult(
        value: 'test',
        succeeded: true,
        exception: null,
        attempts: 1
    );

    $result->onFailure(function () use (&$called) {
        $called = true;
    });

    expect($called)->toBeFalse();
});

it('is immutable', function () {
    $result = new AttemptResult(
        value: 'test',
        succeeded: true,
        exception: null,
        attempts: 1
    );

    // The result should be readonly
    expect($result->value)->toBe('test');
    expect($result->succeeded)->toBeTrue();
});
