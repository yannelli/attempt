<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Contracts;

interface Attemptable
{
    /**
     * Handle the attempt.
     *
     * @param  mixed  ...$input  The input passed via with() or inline
     * @return mixed The result of the attempt
     */
    public function handle(mixed ...$input): mixed;
}
