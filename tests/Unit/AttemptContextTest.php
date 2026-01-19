<?php

declare(strict_types=1);

use Yannelli\Attempt\AttemptContext;

it('creates with max attempts and input', function () {
    $context = new AttemptContext(
        maxAttempts: 3,
        input: ['foo', 'bar']
    );

    expect($context->maxAttempts)->toBe(3);
    expect($context->input)->toBe(['foo', 'bar']);
});

it('initializes with default values', function () {
    $context = new AttemptContext(
        maxAttempts: 1,
        input: []
    );

    expect($context->attemptNumber)->toBe(0);
    expect($context->succeeded)->toBeFalse();
    expect($context->lastException)->toBeNull();
    expect($context->resolvedBy)->toBeNull();
    expect($context->attemptLog)->toBe([]);
});

it('has startedAt timestamp', function () {
    $context = new AttemptContext(
        maxAttempts: 1,
        input: []
    );

    expect($context->startedAt)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('calculates elapsed time', function () {
    $context = new AttemptContext(
        maxAttempts: 1,
        input: []
    );

    usleep(10000); // 10ms

    $elapsed = $context->elapsed();

    expect($elapsed)->toBeGreaterThanOrEqual(5);
});

it('can check if retry', function () {
    $context = new AttemptContext(
        maxAttempts: 3,
        input: []
    );

    expect($context->isRetry())->toBeFalse();

    $context->attemptNumber = 1;
    expect($context->isRetry())->toBeFalse();

    $context->attemptNumber = 2;
    expect($context->isRetry())->toBeTrue();
});

it('can check if fallback', function () {
    $context = new AttemptContext(
        maxAttempts: 1,
        input: []
    );

    expect($context->isFallback())->toBeFalse();

    $context->resolvedBy = 'primary';
    expect($context->isFallback())->toBeFalse();

    $context->resolvedBy = 'retry:2';
    expect($context->isFallback())->toBeFalse();

    $context->resolvedBy = 'fallback:MyClass';
    expect($context->isFallback())->toBeTrue();
});

it('can record attempts', function () {
    $context = new AttemptContext(
        maxAttempts: 3,
        input: []
    );

    $context->recordAttempt('attempt:1', false, new RuntimeException('error'));
    $context->recordAttempt('attempt:2', true);

    expect($context->attemptLog)->toHaveCount(2);
    expect($context->attemptLog[0]['stage'])->toBe('attempt:1');
    expect($context->attemptLog[0]['success'])->toBeFalse();
    expect($context->attemptLog[0]['exception'])->toBe('error');
    expect($context->attemptLog[1]['stage'])->toBe('attempt:2');
    expect($context->attemptLog[1]['success'])->toBeTrue();
    expect($context->attemptLog[1]['exception'])->toBeNull();
});

it('records timestamp in attempt log', function () {
    $context = new AttemptContext(
        maxAttempts: 1,
        input: []
    );

    $context->recordAttempt('test', true);

    expect($context->attemptLog[0]['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});
