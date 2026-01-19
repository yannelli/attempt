<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Tests\Fixtures;

class InvokableCallable
{
    public function __invoke(mixed ...$input): mixed
    {
        return ['invoked' => true, 'input' => $input];
    }
}
