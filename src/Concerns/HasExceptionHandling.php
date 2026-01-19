<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Concerns;

use Closure;
use Yannelli\Attempt\Contracts\ExceptionHandler;

trait HasExceptionHandling
{
    protected array $catchHandlers = [];

    protected ?string $exceptionHandlerClass = null;

    protected ?Closure $retryIf = null;

    protected ?Closure $retryUnless = null;

    /**
     * Register an exception handler.
     *
     * @param  string|Closure  $exceptionClass  Exception class or closure handler
     * @param  Closure|null  $callback  Callback to run when exception matches (if $exceptionClass is a string)
     */
    public function catch(string|Closure $exceptionClass, ?Closure $callback = null): static
    {
        if ($exceptionClass instanceof Closure) {
            $this->catchHandlers[] = ['class' => null, 'callback' => $exceptionClass];
        } else {
            $this->catchHandlers[] = ['class' => $exceptionClass, 'callback' => $callback];
        }

        return $this;
    }

    /**
     * Set an exception handler class.
     */
    public function setExceptionHandler(string $class): static
    {
        if (! is_subclass_of($class, ExceptionHandler::class)) {
            throw new \InvalidArgumentException(
                "Exception handler must implement ".ExceptionHandler::class
            );
        }

        $this->exceptionHandlerClass = $class;

        return $this;
    }

    /**
     * Only retry if the condition returns true.
     */
    public function retryIf(Closure $condition): static
    {
        $this->retryIf = $condition;

        return $this;
    }

    /**
     * Retry unless the condition returns true.
     */
    public function retryUnless(Closure $condition): static
    {
        $this->retryUnless = $condition;

        return $this;
    }
}
