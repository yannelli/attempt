<?php

declare(strict_types=1);

use Yannelli\Attempt\Facades\Attempt;

it('can run concurrent attempts', function () {
    $results = Attempt::concurrent([
        fn () => 'first',
        fn () => 'second',
        fn () => 'third',
    ])->run();

    expect($results)->toHaveCount(3);
    expect($results[0]->value())->toBe('first');
    expect($results[1]->value())->toBe('second');
    expect($results[2]->value())->toBe('third');
});

it('thenReturn returns values', function () {
    $values = Attempt::concurrent([
        fn () => 1,
        fn () => 2,
        fn () => 3,
    ])->thenReturn();

    expect($values)->toBe([1, 2, 3]);
});

it('can get only successful results', function () {
    $successful = Attempt::concurrent([
        fn () => 'success1',
        fn () => throw new RuntimeException('fail'),
        fn () => 'success2',
    ])->successful();

    expect($successful)->toHaveCount(2);
});

it('can get only failed results', function () {
    $failed = Attempt::concurrent([
        fn () => 'success1',
        fn () => throw new RuntimeException('fail'),
        fn () => 'success2',
    ])->failed();

    expect($failed)->toHaveCount(1);
    expect($failed[1]->exception()->getMessage())->toBe('fail');
});

it('supports failFast mode', function () {
    $executed = [];

    $results = Attempt::concurrent([
        function () use (&$executed) {
            $executed[] = 1;
            throw new RuntimeException('fail');
        },
        function () use (&$executed) {
            $executed[] = 2;

            return 'second';
        },
    ])
        ->failFast()
        ->run();

    expect($executed)->toBe([1]); // Second never executed
    expect($results)->toHaveCount(1);
});

it('supports retry on concurrent attempts', function () {
    $attempts = [0, 0, 0];

    $results = Attempt::concurrent([
        function () use (&$attempts) {
            $attempts[0]++;
            if ($attempts[0] < 2) {
                throw new RuntimeException('fail');
            }

            return 'first';
        },
        function () use (&$attempts) {
            $attempts[1]++;

            return 'second';
        },
    ])
        ->retry(2)
        ->run();

    expect($results[0]->succeeded())->toBeTrue();
    expect($results[1]->succeeded())->toBeTrue();
});

it('can add attempts dynamically', function () {
    $builder = Attempt::concurrent()
        ->add(fn () => 'first')
        ->add(fn () => 'second');

    $results = $builder->run();

    expect($results)->toHaveCount(2);
});
