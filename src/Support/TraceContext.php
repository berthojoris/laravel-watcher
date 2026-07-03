<?php

namespace Berthojoris\Watcher\Support;

use Illuminate\Support\Str;

/**
 * Holds the trace identifier that connects all events within a single
 * request, job, or command lifecycle.
 */
class TraceContext
{
    protected string $traceId;

    public function __construct()
    {
        $this->traceId = $this->generate();
    }

    /**
     * Generate a fresh UUID v4 trace identifier.
     */
    public function generate(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Get the current trace ID.
     */
    public function id(): string
    {
        return $this->traceId;
    }

    /**
     * Reset the trace ID, e.g. at the start of a new job cycle in a
     * long-running queue worker.
     */
    public function reset(): void
    {
        $this->traceId = $this->generate();
    }

    /**
     * Replace the current trace ID with an explicitly provided one.
     */
    public function set(string $id): void
    {
        $this->traceId = $id;
    }
}
