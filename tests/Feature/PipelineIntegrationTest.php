<?php

declare(strict_types=1);

use Illuminate\Pipeline\Pipeline;
use Yannelli\Attempt\Facades\Attempt;
use Yannelli\Attempt\Pipes\AttemptPipe;

it('can create pipeline builder', function () {
    $result = Attempt::pipeline([
        fn ($data, $next) => $next($data + 1),
        fn ($data, $next) => $next($data * 2),
    ])
        ->send(5)
        ->thenReturn();

    expect($result)->toBe(12); // (5 + 1) * 2
});

it('supports via method', function () {
    $result = Attempt::pipeline([
        new class {
            public function process($data, $next) {
                return $next($data . '-processed');
            }
        },
    ])
        ->send('input')
        ->via('process')
        ->thenReturn();

    expect($result)->toBe('input-processed');
});

it('supports retry on pipeline', function () {
    $attempts = 0;

    $result = Attempt::pipeline([
        function ($data, $next) use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new RuntimeException('fail');
            }
            return $next($data . '-processed');
        },
    ])
        ->send('input')
        ->retry(3)
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('input-processed');
});

it('supports fallback on pipeline', function () {
    $result = Attempt::pipeline([
        fn ($data, $next) => throw new RuntimeException('pipeline failed'),
    ])
        ->send('input')
        ->fallback(fn () => 'fallback value')
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('fallback value');
});

it('can use AttemptPipe in native Laravel pipeline', function () {
    $attempts = 0;

    $pipe = AttemptPipe::wrap(function ($data) use (&$attempts) {
        $attempts++;
        if ($attempts < 2) {
            throw new RuntimeException('fail');
        }
        return $data * 2;
    })->retry(3);

    $result = app(Pipeline::class)
        ->send(5)
        ->through([$pipe])
        ->thenReturn();

    expect($result)->toBe(10);
    expect($attempts)->toBe(2);
});

it('AttemptPipe supports delay', function () {
    $attempts = 0;

    $pipe = AttemptPipe::wrap(function ($data) use (&$attempts) {
        $attempts++;
        if ($attempts < 2) {
            throw new RuntimeException('fail');
        }
        return $data;
    })
        ->retry(3)
        ->delay(10);

    $result = app(Pipeline::class)
        ->send('input')
        ->through([$pipe])
        ->thenReturn();

    expect($result)->toBe('input');
});

it('AttemptPipe supports fallback', function () {
    $pipe = AttemptPipe::wrap(fn () => throw new RuntimeException('fail'))
        ->fallback(fn () => 'fallback');

    $result = app(Pipeline::class)
        ->send('input')
        ->through([$pipe])
        ->thenReturn();

    expect($result)->toBe('fallback');
});
