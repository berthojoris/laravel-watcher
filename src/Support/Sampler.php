<?php

namespace Berthojoris\Watcher\Support;

/**
 * Determines whether an event of a given type should be captured,
 * based on a configurable sampling rate per type.
 */
class Sampler
{
    /**
     * @param array<string,float> $rates  Map of event-type => probability (0.0 - 1.0).
     */
    public function __construct(
        protected array $rates = [],
    ) {}

    /**
     * True if this event type should be sampled under the current rate.
     */
    public function shouldSample(string $type): bool
    {
        $rate = $this->rates[$type] ?? 1.0;

        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) < $rate;
    }
}
