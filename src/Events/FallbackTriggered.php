<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Events;

use Throwable;
use Yannelli\Attempt\AttemptContext;

readonly class FallbackTriggered
{
    public function __construct(
        public AttemptContext $context,
        public string|array|object $fallback,
        public Throwable $exception
    ) {}
}
