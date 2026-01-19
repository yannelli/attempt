<?php

declare(strict_types=1);

use Yannelli\Attempt\AttemptBuilder;
use Yannelli\Attempt\AttemptResult;
use Yannelli\Attempt\Tests\Fixtures\TestAttemptable;
use Yannelli\Attempt\Tests\Fixtures\InvokableCallable;

it('can be created with a closure', function () {
    $builder = new AttemptBuilder(fn () => 'test');

    expect($builder)->toBeInstanceOf(AttemptBuilder::class);
});

it('can be created with static make method', function () {
    $builder = AttemptBuilder::make(fn () => 'test');

    expect($builder)->toBeInstanceOf(AttemptBuilder::class);
});

it('executes a closure and returns result', function () {
    $result = AttemptBuilder::make(fn () => 'hello')
        ->thenReturn();

    expect($result)->toBe('hello');
});

it('executes with input parameters', function () {
    $result = AttemptBuilder::make(fn ($a, $b) => $a + $b, 2, 3)
        ->thenReturn();

    expect($result)->toBe(5);
});

it('can set input with with()', function () {
    $result = AttemptBuilder::make(fn ($a, $b) => $a * $b)
        ->with(4, 5)
        ->thenReturn();

    expect($result)->toBe(20);
});

it('can set input with send()', function () {
    $result = AttemptBuilder::make(fn ($value) => $value * 2)
        ->send(10)
        ->thenReturn();

    expect($result)->toBe(20);
});

it('returns AttemptResult from run()', function () {
    $result = AttemptBuilder::make(fn () => 'test')
        ->run();

    expect($result)->toBeInstanceOf(AttemptResult::class);
    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('test');
});

it('transforms result with then()', function () {
    $result = AttemptBuilder::make(fn () => 5)
        ->then(fn ($value) => $value * 2);

    expect($result)->toBe(10);
});

it('get() is alias for thenReturn()', function () {
    $result = AttemptBuilder::make(fn () => 'test')
        ->get();

    expect($result)->toBe('test');
});

it('value() is alias for thenReturn()', function () {
    $result = AttemptBuilder::make(fn () => 'test')
        ->value();

    expect($result)->toBe('test');
});

it('can set retry count', function () {
    $attempts = 0;

    $result = AttemptBuilder::make(function () use (&$attempts) {
        $attempts++;
        if ($attempts < 3) {
            throw new RuntimeException('fail');
        }
        return 'success';
    })
        ->retry(3)
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->attempts())->toBe(3);
});

it('can set delay', function () {
    $builder = AttemptBuilder::make(fn () => 'test');
    $builder->delay(100);

    // Just ensure it doesn't throw
    expect($builder->thenReturn())->toBe('test');
});

it('can set delay array', function () {
    $builder = AttemptBuilder::make(fn () => 'test');
    $builder->delay([100, 200, 300]);

    expect($builder->thenReturn())->toBe('test');
});

it('skips execution when condition is false', function () {
    $executed = false;

    $result = AttemptBuilder::make(function () use (&$executed) {
        $executed = true;
        return 'test';
    })
        ->when(false)
        ->run();

    expect($executed)->toBeFalse();
    expect($result->resolvedBy())->toBe('skipped');
});

it('executes when condition is true', function () {
    $executed = false;

    AttemptBuilder::make(function () use (&$executed) {
        $executed = true;
        return 'test';
    })
        ->when(true)
        ->thenReturn();

    expect($executed)->toBeTrue();
});

it('skips execution when unless condition is true', function () {
    $executed = false;

    $result = AttemptBuilder::make(function () use (&$executed) {
        $executed = true;
        return 'test';
    })
        ->unless(true)
        ->run();

    expect($executed)->toBeFalse();
    expect($result->resolvedBy())->toBe('skipped');
});

it('can use quiet mode to suppress exceptions', function () {
    $result = AttemptBuilder::make(fn () => throw new RuntimeException('error'))
        ->quiet()
        ->run();

    expect($result->failed())->toBeTrue();
    expect($result->value())->toBeNull();
});

it('throws on failure with thenReturnOrFail', function () {
    expect(fn () => AttemptBuilder::make(fn () => throw new RuntimeException('error'))
        ->thenReturnOrFail()
    )->toThrow(RuntimeException::class);
});

it('can resolve class string callables', function () {
    $result = AttemptBuilder::make(TestAttemptable::class)
        ->thenReturn();

    expect($result)->toBeArray();
    expect($result['handled'])->toBeTrue();
});

it('can resolve invokable classes', function () {
    $result = AttemptBuilder::make(InvokableCallable::class)
        ->thenReturn();

    expect($result)->toBeArray();
    expect($result['invoked'])->toBeTrue();
});

it('supports fallback handlers', function () {
    $result = AttemptBuilder::make(fn () => throw new RuntimeException('primary failed'))
        ->fallback(fn () => 'fallback result')
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('fallback result');
    expect($result->resolvedBy())->toStartWith('fallback:');
});

it('can chain multiple fallbacks', function () {
    $result = AttemptBuilder::make(fn () => throw new RuntimeException('primary failed'))
        ->fallback([
            fn () => throw new RuntimeException('fallback 1 failed'),
            fn () => 'fallback 2 success',
        ])
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('fallback 2 success');
});

it('runs catch handlers on exception', function () {
    $caughtException = null;

    AttemptBuilder::make(fn () => throw new RuntimeException('test error'))
        ->catch(function ($e) use (&$caughtException) {
            $caughtException = $e;
        })
        ->quiet()
        ->run();

    expect($caughtException)->toBeInstanceOf(RuntimeException::class);
    expect($caughtException->getMessage())->toBe('test error');
});

it('runs catch handlers for specific exception types', function () {
    $caughtException = null;

    AttemptBuilder::make(fn () => throw new InvalidArgumentException('invalid'))
        ->catch(InvalidArgumentException::class, function ($e) use (&$caughtException) {
            $caughtException = $e;
        })
        ->quiet()
        ->run();

    expect($caughtException)->toBeInstanceOf(InvalidArgumentException::class);
});

it('runs finally callbacks', function () {
    $finallyCalled = false;

    AttemptBuilder::make(fn () => 'test')
        ->finally(function () use (&$finallyCalled) {
            $finallyCalled = true;
        })
        ->thenReturn();

    expect($finallyCalled)->toBeTrue();
});

it('runs finally callbacks even on failure', function () {
    $finallyCalled = false;

    AttemptBuilder::make(fn () => throw new RuntimeException('error'))
        ->finally(function () use (&$finallyCalled) {
            $finallyCalled = true;
        })
        ->quiet()
        ->run();

    expect($finallyCalled)->toBeTrue();
});

it('supports array of callables as fallback chain', function () {
    $calls = [];

    $result = AttemptBuilder::make([
        function () use (&$calls) {
            $calls[] = 'first';
            throw new RuntimeException('first failed');
        },
        function () use (&$calls) {
            $calls[] = 'second';
            return 'second succeeded';
        },
    ])->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('second succeeded');
    expect($calls)->toBe(['first', 'second']);
});

it('caches result and does not re-execute', function () {
    $count = 0;

    $builder = AttemptBuilder::make(function () use (&$count) {
        $count++;
        return $count;
    });

    $result1 = $builder->run();
    $result2 = $builder->run();

    expect($count)->toBe(1);
    expect($result1->value())->toBe(1);
    expect($result2->value())->toBe(1);
});
