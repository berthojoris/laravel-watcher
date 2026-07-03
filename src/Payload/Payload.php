<?php

namespace Berthojoris\Watcher\Payload;

/**
 * Immutable value object representing a single captured event.
 *
 * Each payload carries its event type, the trace ID that connects it
 * to other events in the same lifecycle, a wall-clock timestamp, an
 * optional duration, and event-specific data.
 */
class Payload
{
    public function __construct(
        public readonly string $type,
        public readonly string $traceId,
        public readonly float $timestamp,
        public array $data,
        public readonly ?float $duration = null,
        public readonly ?float $memory = null,
    ) {}

    /**
     * Serialize to the array format expected by the central server.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'type'      => $this->type,
            'trace_id'  => $this->traceId,
            'timestamp' => round($this->timestamp, 6),
            'duration'  => $this->duration !== null ? round($this->duration, 3) : null,
            'memory'    => $this->memory,
            'data'      => $this->data,
        ];
    }
}
