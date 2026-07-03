<?php

namespace Berthojoris\Watcher\Support;

/**
 * High-resolution timing helper.
 *
 * Uses hrtime(true) (nanosecond monotonic clock) for precise duration
 * measurements and microtime(true) for wall-clock event timestamps.
 */
class Clock
{
    /**
     * Wall-clock timestamp in seconds with microsecond precision.
     */
    public function timestamp(): float
    {
        return microtime(true);
    }

    /**
     * Monotonic nanosecond counter, used as a start/end anchor.
     */
    public function now(): int
    {
        return hrtime(true);
    }

    /**
     * Elapsed time in milliseconds between a captured start and now.
     */
    public function elapsedMs(int $startNanoseconds): float
    {
        return (hrtime(true) - $startNanoseconds) / 1e6;
    }

    /**
     * Elapsed time in microseconds between two nanosecond anchors.
     */
    public static function diffUs(int $startNanoseconds, int $endNanoseconds): float
    {
        return ($endNanoseconds - $startNanoseconds) / 1e3;
    }
}
