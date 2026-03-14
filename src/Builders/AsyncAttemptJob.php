<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Builders;

use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\SerializableClosure\SerializableClosure;
use Throwable;
use Yannelli\Attempt\AttemptBuilder;

class AsyncAttemptJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected array $configuration;

    protected ?SerializableClosure $thenCallback;

    protected ?SerializableClosure $catchCallback;

    public function __construct(
        array $configuration,
        ?Closure $thenCallback = null,
        ?Closure $catchCallback = null
    ) {
        $this->configuration = $this->wrapClosures($configuration);
        $this->thenCallback = $thenCallback ? new SerializableClosure($thenCallback) : null;
        $this->catchCallback = $catchCallback ? new SerializableClosure($catchCallback) : null;
    }

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
        $config = $this->unwrapClosures($this->configuration);

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

        if ($config['delayCallback'] !== null) {
            $builder->delayUsing($config['delayCallback']);
        }

        if ($config['jitter'] > 0) {
            $builder->withJitter($config['jitter']);
        }

        if (! empty($config['fallbacks'])) {
            $builder->fallback($config['fallbacks']);
        }

        foreach ($config['catchHandlers'] as $handler) {
            if ($handler['class'] !== null) {
                $builder->catch($handler['class'], $handler['callback']);
            } elseif ($handler['callback'] !== null) {
                $builder->catch($handler['callback']);
            }
        }

        foreach ($config['finallyCallbacks'] as $callback) {
            $builder->finally($callback);
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

    /**
     * Recursively wrap Closures in SerializableClosure for queue serialization.
     */
    protected function wrapClosures(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof Closure) {
                $data[$key] = new SerializableClosure($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->wrapClosures($value);
            }
        }

        return $data;
    }

    /**
     * Recursively unwrap SerializableClosures back to Closures.
     */
    protected function unwrapClosures(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof SerializableClosure) {
                $data[$key] = $value->getClosure();
            } elseif (is_array($value)) {
                $data[$key] = $this->unwrapClosures($value);
            }
        }

        return $data;
    }
}
