<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Tests\Fixtures;

use Yannelli\Attempt\AttemptBuilder;
use Yannelli\Attempt\Contracts\Attemptable;
use Yannelli\Attempt\Contracts\ConfiguresAttempt;

class ConfigurableAttemptable implements Attemptable, ConfiguresAttempt
{
    public function handle(mixed ...$input): mixed
    {
        return ['configured' => true, 'input' => $input];
    }

    public function configureAttempt(AttemptBuilder $attempt): void
    {
        $attempt->retry(2)->delay(100);
    }
}
