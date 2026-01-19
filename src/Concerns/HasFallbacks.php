<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Concerns;

use Closure;
use Illuminate\Support\Collection;

trait HasFallbacks
{
    protected array $fallbacks = [];

    protected bool $fallbackPipelineMode = false;

    /**
     * Set fallback handler(s).
     */
    public function fallback(Closure|string|array|Collection $fallbacks): static
    {
        if ($fallbacks instanceof Collection) {
            $fallbacks = $fallbacks->all();
        }

        $this->fallbacks = is_array($fallbacks) ? $fallbacks : [$fallbacks];
        $this->fallbackPipelineMode = false;

        return $this;
    }

    /**
     * Set fallbacks as a first-success-wins pipeline.
     */
    public function fallbackPipeline(array|Collection $pipes): static
    {
        if ($pipes instanceof Collection) {
            $pipes = $pipes->all();
        }

        $this->fallbacks = $pipes;
        $this->fallbackPipelineMode = true;

        return $this;
    }

    /**
     * Append a fallback handler.
     */
    public function orFallback(Closure|string $fallback): static
    {
        $this->fallbacks[] = $fallback;

        return $this;
    }
}
