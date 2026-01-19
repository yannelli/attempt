<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Yannelli\Attempt\AttemptContext;

readonly class AttemptSucceeded
{
    use Dispatchable;

    public function __construct(
        public AttemptContext $context,
        public mixed $result
    ) {}
}
