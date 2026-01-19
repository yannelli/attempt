<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Throwable;
use Yannelli\Attempt\AttemptContext;

readonly class FallbackTriggered
{
    use Dispatchable;

    public function __construct(
        public AttemptContext $context,
        public string|object $fallback,
        public Throwable $exception
    ) {}
}
