<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Contracts;

use Throwable;
use Yannelli\Attempt\AttemptContext;

interface ExceptionHandler
{
    /**
     * Handle an exception that occurred during an attempt.
     */
    public function handle(Throwable $e, AttemptContext $context): void;
}
