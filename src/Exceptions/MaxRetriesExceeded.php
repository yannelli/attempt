<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Exceptions;

use Throwable;

class MaxRetriesExceeded extends AttemptException
{
    public function __construct(
        public readonly int $maxRetries,
        public readonly int $attemptsMade,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        if ($message === '') {
            $message = "Maximum retry attempts exceeded. Attempted {$attemptsMade} times (max: {$maxRetries}).";
        }

        parent::__construct($message, $code, $previous);
    }
}
