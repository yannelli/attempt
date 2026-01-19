<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Tests\Fixtures;

class CountingCallable
{
    protected static int $count = 0;

    public function __invoke(): int
    {
        return ++static::$count;
    }

    public static function getCount(): int
    {
        return static::$count;
    }

    public static function reset(): void
    {
        static::$count = 0;
    }
}
