<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Throwable;
use Yannelli\Attempt\AttemptContext;

readonly class AllAttemptsFailed
{
    use Dispatchable;

    public function __construct(
        public AttemptContext $context,
        public Throwable $exception
    ) {}
}
