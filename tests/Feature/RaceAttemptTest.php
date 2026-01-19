<?php

declare(strict_types=1);

use Yannelli\Attempt\Facades\Attempt;

it('returns first successful result', function () {
    $result = Attempt::race([
        fn () => throw new RuntimeException('first fails'),
        fn () => 'second succeeds',
        fn () => 'third not reached',
    ])->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('second succeeds');
});

it('thenReturn returns first successful value', function () {
    $value = Attempt::race([
        fn () => throw new RuntimeException('fail'),
        fn () => 'success',
    ])->thenReturn();

    expect($value)->toBe('success');
});

it('fails when all attempts fail', function () {
    $result = Attempt::race([
        fn () => throw new RuntimeException('first fails'),
        fn () => throw new RuntimeException('second fails'),
    ])->run();

    expect($result->failed())->toBeTrue();
});

it('thenReturnOrFail throws when all fail', function () {
    expect(fn () => Attempt::race([
        fn () => throw new RuntimeException('fail'),
    ])->thenReturnOrFail())->toThrow(RuntimeException::class);
});

it('supports retry on race attempts', function () {
    $attempts = [0, 0];

    $result = Attempt::race([
        function () use (&$attempts) {
            $attempts[0]++;
            if ($attempts[0] < 2) {
                throw new RuntimeException('fail');
            }

            return 'first succeeds on retry';
        },
    ])
        ->retry(2)
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('first succeeds on retry');
});

it('can add attempts dynamically', function () {
    $result = Attempt::race()
        ->add(fn () => throw new RuntimeException('fail'))
        ->add(fn () => 'success')
        ->run();

    expect($result->succeeded())->toBeTrue();
    expect($result->value())->toBe('success');
});

it('returns first success even if later ones would succeed', function () {
    $executed = [];

    $result = Attempt::race([
        function () use (&$executed) {
            $executed[] = 1;

            return 'first';
        },
        function () use (&$executed) {
            $executed[] = 2;

            return 'second';
        },
    ])->run();

    expect($result->value())->toBe('first');
    expect($executed)->toBe([1]); // Only first was executed
});
