<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Tests\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;
use Yannelli\Attempt\Ai\RetryAiRequests;

class TestAiAgent implements Agent, HasMiddleware
{
    use Promptable;

    public function __construct(
        protected int $retries = 2
    ) {}

    public function instructions(): string
    {
        return 'You are a test agent.';
    }

    public function middleware(): array
    {
        return [
            RetryAiRequests::times($this->retries),
        ];
    }
}
