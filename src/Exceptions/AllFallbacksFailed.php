<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Exceptions;

use Throwable;

class AllFallbacksFailed extends AttemptException
{
    public function __construct(
        string $message = 'All attempts and fallbacks failed',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
