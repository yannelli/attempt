<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Tests\Fixtures;

use RuntimeException;
use Yannelli\Attempt\Contracts\Attemptable;

class FailingCallable implements Attemptable
{
    protected static int $attempts = 0;

    protected int $failTimes;

    public function __construct(int $failTimes = 1)
    {
        $this->failTimes = $failTimes;
        static::$attempts = 0;
    }

    public function handle(mixed ...$input): mixed
    {
        static::$attempts++;

        if (static::$attempts <= $this->failTimes) {
            throw new RuntimeException('Intentional failure: attempt '.static::$attempts);
        }

        return ['success' => true, 'attempts' => static::$attempts];
    }

    public static function getAttempts(): int
    {
        return static::$attempts;
    }

    public static function resetAttempts(): void
    {
        static::$attempts = 0;
    }
}
