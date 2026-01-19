<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Tests\Fixtures;

use Yannelli\Attempt\Contracts\Attemptable;

class TestAttemptable implements Attemptable
{
    public function handle(mixed ...$input): mixed
    {
        return array_merge(['handled' => true], $input);
    }
}
