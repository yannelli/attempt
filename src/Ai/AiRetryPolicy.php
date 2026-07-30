<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;
use Yannelli\Attempt\AttemptBuilder;
use Yannelli\Attempt\Strategies\DecorrelatedJitter;

/**
 * A retry policy tuned for Laravel AI SDK requests.
 *
 * Retries transient failures (rate limits, provider overload, connection
 * errors, retryable HTTP statuses) while refusing to retry permanent
 * failures such as insufficient credits, unknown tools, or client errors.
 * Provider "Retry-After" hints are honored when calculating delays.
 */
class AiRetryPolicy
{
    /**
     * The default base delay in milliseconds between retries.
     */
    public const int BASE_DELAY = 500;

    /**
     * The default maximum delay in milliseconds between retries.
     */
    public const int MAX_DELAY = 30000;

    /**
     * Apply the policy's retry condition and delay strategy to a builder.
     */
    public static function applyTo(AttemptBuilder $builder): AttemptBuilder
    {
        $strategy = new DecorrelatedJitter(base: static::BASE_DELAY, maxDelay: static::MAX_DELAY);

        return $builder
            ->retryIf(fn (Throwable $e): bool => static::shouldRetry($e))
            ->delayUsing(fn (int $attempt, ?Throwable $e): int => $e !== null
                ? static::retryAfter($e) ?? $strategy->getDelay($attempt, static::BASE_DELAY)
                : $strategy->getDelay($attempt, static::BASE_DELAY)
            );
    }

    /**
     * Determine if the given exception represents a transient AI failure.
     */
    public static function shouldRetry(Throwable $e): bool
    {
        if ($e instanceof RateLimitedException || $e instanceof ProviderOverloadedException) {
            return true;
        }

        // Insufficient credits, unknown tools, approval mismatches, and other
        // SDK-level failures will not resolve by retrying the same request.
        if ($e instanceof AiException) {
            return false;
        }

        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException) {
            $status = $e->response->status();

            return $status === 408 || $status === 429 || $status >= 500;
        }

        return false;
    }

    /**
     * Extract a provider supplied "Retry-After" delay in milliseconds.
     *
     * The Laravel AI SDK preserves the original HTTP exception as the
     * previous exception, so the header is resolved from the exception
     * chain. Returns null when no usable hint is present.
     */
    public static function retryAfter(Throwable $e, int $max = self::MAX_DELAY): ?int
    {
        for ($depth = 0; $e !== null && $depth < 5; $e = $e->getPrevious(), $depth++) {
            if (! $e instanceof RequestException) {
                continue;
            }

            $header = $e->response->header('Retry-After');

            if ($header === '') {
                return null;
            }

            if (is_numeric($header)) {
                return static::clampSeconds((float) $header, $max);
            }

            $timestamp = strtotime($header);

            return $timestamp === false
                ? null
                : static::clampSeconds($timestamp - time(), $max);
        }

        return null;
    }

    /**
     * Convert a seconds value to clamped milliseconds.
     */
    protected static function clampSeconds(float|int $seconds, int $max): int
    {
        return (int) min(max(0, ceil($seconds * 1000)), $max);
    }
}
