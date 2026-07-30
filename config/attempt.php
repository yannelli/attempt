<?php

use Yannelli\Attempt\Strategies\DecorrelatedJitter;
use Yannelli\Attempt\Strategies\ExponentialBackoff;
use Yannelli\Attempt\Strategies\FibonacciBackoff;
use Yannelli\Attempt\Strategies\LinearBackoff;

return [
    /*
    |--------------------------------------------------------------------------
    | Backoff Strategies
    |--------------------------------------------------------------------------
    */
    'backoff_strategies' => [
        'exponential' => [
            'class' => ExponentialBackoff::class,
            'base' => 100,
            'multiplier' => 2.0,
            'maxDelay' => 30000,
        ],
        'linear' => [
            'class' => LinearBackoff::class,
            'base' => 100,
            'increment' => 100,
            'maxDelay' => 30000,
        ],
        'fibonacci' => [
            'class' => FibonacciBackoff::class,
            'base' => 100,
            'maxDelay' => 30000,
        ],
        'decorrelated_jitter' => [
            'class' => DecorrelatedJitter::class,
            'base' => 100,
            'maxDelay' => 30000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Async / Queue Settings
    |--------------------------------------------------------------------------
    | These default to Laravel's queue settings if not specified.
    */
    'async' => [
        'connection' => env('ATTEMPT_QUEUE_CONNECTION'),
        'queue' => env('ATTEMPT_QUEUE'),
        'timeout' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */
    'events' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Handling
    |--------------------------------------------------------------------------
    | Exceptions that should never or always trigger retries.
    */
    'never_retry' => [
        //  \Illuminate\Validation\ValidationException::class,
        //  \Illuminate\Auth\AuthenticationException::class,
        //  \Illuminate\Auth\Access\AuthorizationException::class,
        //  \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        //  \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],

    'always_retry' => [
        //  \Illuminate\Http\Client\ConnectionException::class,
    ],
];
