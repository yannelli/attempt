<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Events;

use Yannelli\Attempt\AttemptContext;

readonly class AttemptStarted
{
    public function __construct(
        public AttemptContext $context
    ) {}
}
