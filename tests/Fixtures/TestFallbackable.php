<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Tests\Fixtures;

use Throwable;
use Yannelli\Attempt\Contracts\Fallbackable;

class TestFallbackable implements Fallbackable
{
    public function handleFallback(Throwable $e, mixed ...$input): mixed
    {
        return [
            'fallback' => true,
            'originalError' => $e->getMessage(),
            'input' => $input,
        ];
    }

    public function shouldSkip(Throwable $e): bool
    {
        return false;
    }
}
