<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Retry Settings
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'max_retries' => 3,
        'delay' => 100, // milliseconds
        'backoff' => 'exponential',
        'jitter' => 0.1, // 10% randomization
    ],

    /*
    |--------------------------------------------------------------------------
    | Backoff Strategies
    |--------------------------------------------------------------------------
    */
    'backoff_strategies' => [
        'exponential' => [
            'class' => \Yannelli\Attempt\Strategies\ExponentialBackoff::class,
            'base' => 100,
            'multiplier' => 2.0,
            'max' => 30000,
        ],
        'linear' => [
            'class' => \Yannelli\Attempt\Strategies\LinearBackoff::class,
            'base' => 100,
            'increment' => 100,
        ],
        'fibonacci' => [
            'class' => \Yannelli\Attempt\Strategies\FibonacciBackoff::class,
            'base' => 100,
        ],
        'decorrelated_jitter' => [
            'class' => \Yannelli\Attempt\Strategies\DecorrelatedJitter::class,
            'base' => 100,
            'max' => 30000,
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
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],

    'always_retry' => [
        \Illuminate\Http\Client\ConnectionException::class,
    ],
];
