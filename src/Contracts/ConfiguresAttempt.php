<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Contracts;

use Yannelli\Attempt\AttemptBuilder;

interface ConfiguresAttempt
{
    /**
     * Configure attempt behavior for this class.
     * Called automatically when this class is used as the try target.
     */
    public function configureAttempt(AttemptBuilder $attempt): void;
}
