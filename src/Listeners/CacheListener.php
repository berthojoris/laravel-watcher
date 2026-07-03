<?php

namespace Berthojoris\Watcher\Listeners;

use Berthojoris\Watcher\Payload\Payload;
use Berthojoris\Watcher\Watcher;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;

/**
 * Captures cache interactions: hits, misses, writes, and forgets.
 */
class CacheListener
{
    public function __construct(
        protected Watcher $watcher,
    ) {}

    public function handleHit(CacheHit $event): void
    {
        $this->record('hit', $event);
    }

    public function handleMissed(CacheMissed $event): void
    {
        $this->record('missed', $event);
    }

    public function handleWritten(KeyWritten $event): void
    {
        $this->record('written', $event, property_exists($event, 'seconds') ? $event->seconds : null);
    }

    public function handleForgotten(KeyForgotten $event): void
    {
        $this->record('forgotten', $event);
    }

    /**
     * @param object         $event    One of the Laravel cache event classes.
     * @param int|float|null $seconds  TTL for write events.
     */
    protected function record(string $action, object $event, $seconds = null): void
    {
        $value = property_exists($event, 'value') ? $event->value : null;

        $this->watcher->record(new Payload(
            type:      'cache',
            traceId:   $this->watcher->traceId(),
            timestamp: microtime(true),
            data: [
                'action'     => $action,
                'key'        => $event->key,
                'ttl'        => $seconds,
                'value_size' => $this->valueSize($value),
            ],
        ));
    }

    protected function valueSize(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return strlen((string) $value);
        }

        return strlen(serialize($value));
    }
}
