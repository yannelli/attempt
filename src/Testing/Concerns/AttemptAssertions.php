<?php

declare(strict_types=1);

namespace Yannelli\Attempt\Testing\Concerns;

use PHPUnit\Framework\Assert as PHPUnit;

trait AttemptAssertions
{
    /**
     * Assert that a callable was attempted.
     */
    public function assertAttempted(string $callable): void
    {
        $found = collect($this->recorded)
            ->filter(fn ($record) => $record['callable'] === $callable || ($record['callable'] ?? null) === $callable)
            ->isNotEmpty();

        PHPUnit::assertTrue(
            $found,
            "Failed asserting that [{$callable}] was attempted."
        );
    }

    /**
     * Assert that a callable was attempted a specific number of times.
     */
    public function assertAttemptedTimes(string $callable, int $times): void
    {
        $count = collect($this->recorded)
            ->filter(fn ($record) => ($record['type'] === 'attempt' || $record['type'] === 'try')
                && ($record['callable'] === $callable))
            ->count();

        PHPUnit::assertEquals(
            $times,
            $count,
            "Failed asserting that [{$callable}] was attempted {$times} times. Got {$count}."
        );
    }

    /**
     * Assert that a fallback was used.
     */
    public function assertFallbackUsed(string $callable): void
    {
        $found = collect($this->recorded)
            ->filter(fn ($record) => $record['type'] === 'fallback'
                && $record['callable'] === $callable)
            ->isNotEmpty();

        PHPUnit::assertTrue(
            $found,
            "Failed asserting that fallback [{$callable}] was used."
        );
    }

    /**
     * Assert that a callable was never attempted.
     */
    public function assertNeverAttempted(string $callable): void
    {
        $found = collect($this->recorded)
            ->filter(fn ($record) => $record['callable'] === $callable)
            ->isNotEmpty();

        PHPUnit::assertFalse(
            $found,
            "Failed asserting that [{$callable}] was never attempted."
        );
    }

    /**
     * Assert no attempts were made.
     */
    public function assertNothingAttempted(): void
    {
        PHPUnit::assertEmpty(
            $this->recorded,
            'Failed asserting that no attempts were made.'
        );
    }

    /**
     * Assert the attempt count.
     */
    public function assertAttemptCount(int $count): void
    {
        $actualCount = collect($this->recorded)
            ->filter(fn ($record) => in_array($record['type'], ['attempt', 'try']))
            ->count();

        PHPUnit::assertEquals(
            $count,
            $actualCount,
            "Failed asserting attempt count. Expected {$count}, got {$actualCount}."
        );
    }

    /**
     * Assert that a callable succeeded.
     */
    public function assertSucceeded(string $callable): void
    {
        $found = collect($this->recorded)
            ->filter(fn ($record) => $record['callable'] === $callable
                && ($record['success'] ?? false) === true)
            ->isNotEmpty();

        PHPUnit::assertTrue(
            $found,
            "Failed asserting that [{$callable}] succeeded."
        );
    }

    /**
     * Assert that a callable failed.
     */
    public function assertFailed(string $callable): void
    {
        $found = collect($this->recorded)
            ->filter(fn ($record) => $record['callable'] === $callable
                && ($record['success'] ?? true) === false)
            ->isNotEmpty();

        PHPUnit::assertTrue(
            $found,
            "Failed asserting that [{$callable}] failed."
        );
    }
}
