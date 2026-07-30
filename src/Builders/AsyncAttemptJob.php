<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Builders;

use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\SerializableClosure\SerializableClosure;
use Throwable;
use Yannelli\Attempt\AttemptBuilder;

class AsyncAttemptJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected array $configuration;

    protected ?SerializableClosure $thenCallback;

    protected ?SerializableClosure $catchCallback;

    public function __construct(
        array $configuration,
        ?Closure $thenCallback = null,
        ?Closure $catchCallback = null,
        public int $timeout = 60
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
        } catch (Throwable $e) {
            if ($this->catchCallback !== null) {
                ($this->catchCallback->getClosure())($e);
            }

            throw $e;
        }

        if ($result->failed()) {
            if ($this->catchCallback !== null) {
                ($this->catchCallback->getClosure())($result->exception());
            }

            return;
        }

        if ($this->thenCallback === null) {
            return;
        }

        try {
            ($this->thenCallback->getClosure())($result->value());
        } catch (Throwable $e) {
            if ($this->catchCallback !== null) {
                ($this->catchCallback->getClosure())($e);
            }

            throw $e;
        }
    }

    protected function createBuilder(): AttemptBuilder
    {
        $config = $this->unwrapClosures($this->configuration);

        return AttemptBuilder::fromConfiguration($config);
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
