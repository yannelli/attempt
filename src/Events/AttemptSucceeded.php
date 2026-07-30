<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Events;

use Yannelli\Attempt\AttemptContext;

readonly class AttemptSucceeded
{
    public function __construct(
        public AttemptContext $context,
        public mixed $result
    ) {}
}
