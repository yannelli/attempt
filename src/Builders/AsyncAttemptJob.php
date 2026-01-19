<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Builders;

use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Yannelli\Attempt\AttemptBuilder;

class AsyncAttemptJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        protected array $configuration,
        protected ?Closure $thenCallback = null,
        protected ?Closure $catchCallback = null
    ) {}

    public function handle(): void
    {
        try {
            $builder = $this->createBuilder();
            $result = $builder->run();

            if ($result->succeeded() && $this->thenCallback !== null) {
                ($this->thenCallback)($result->value());
            } elseif ($result->failed() && $this->catchCallback !== null) {
                ($this->catchCallback)($result->exception());
            }
        } catch (Throwable $e) {
            if ($this->catchCallback !== null) {
                ($this->catchCallback)($e);
            } else {
                throw $e;
            }
        }
    }

    protected function createBuilder(): AttemptBuilder
    {
        $config = $this->configuration;

        $builder = new AttemptBuilder(
            $config['callable'],
            ...$config['input']
        );

        if ($config['maxRetries'] > 0) {
            $builder->retry($config['maxRetries']);
        }

        if (! empty($config['delay'])) {
            $builder->delay($config['delay']);
        }

        if ($config['retryStrategy'] !== null) {
            $builder->usingStrategy($config['retryStrategy']);
        }

        if ($config['jitter'] > 0) {
            $builder->withJitter($config['jitter']);
        }

        if (! empty($config['fallbacks'])) {
            $builder->fallback($config['fallbacks']);
        }

        if ($config['shouldThrow']) {
            $builder->throw();
        }

        if ($config['quiet']) {
            $builder->quiet();
        }

        if (! $config['eventsEnabled']) {
            $builder->withoutEvents();
        }

        return $builder;
    }
}
