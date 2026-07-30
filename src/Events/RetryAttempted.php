<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Events;

use Throwable;
use Yannelli\Attempt\AttemptContext;

readonly class RetryAttempted
{
    public function __construct(
        public AttemptContext $context,
        public int $attemptNumber,
        public ?Throwable $previousException = null
    ) {}
}
