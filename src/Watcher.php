<?php

namespace Berthojoris\Watcher;

use Berthojoris\Watcher\Contracts\Buffer;
use Berthojoris\Watcher\Contracts\Sender;
use Berthojoris\Watcher\Payload\Payload;
use Berthojoris\Watcher\Support\Filter;
use Berthojoris\Watcher\Support\Redactor;
use Berthojoris\Watcher\Support\Sampler;
use Berthojoris\Watcher\Support\TraceContext;

/**
 * Central service that orchestrates the full capture pipeline:
 *
 *   Payload → Sampling → Filtering → Redaction → Buffer → Batch Send
 *
 * All long-running dependencies are injected as singletons so that the
 * same trace context, buffer, and sender are shared across a single
 * request/job/command lifecycle.
 */
class Watcher
{
    protected bool $enabled = true;

    public function __construct(
        protected TraceContext $context,
        protected Buffer $buffer,
        protected Redactor $redactor,
        protected Sampler $sampler,
        protected Filter $filter,
        protected Sender $sender,
    ) {}

    /**
     * Disable capture (e.g. when config flag is off).
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Convenience accessor for the current trace ID.
     */
    public function traceId(): string
    {
        return $this->context->id();
    }

    /**
     * Start a new trace cycle (resets the trace ID).
     */
    public function newTrace(): string
    {
        $this->context->reset();

        return $this->context->id();
    }

    /**
     * Record a captured event through the full pipeline.
     */
    public function record(Payload $payload): void
    {
        if (! $this->enabled) {
            return;
        }

        // --- Sampling ---
        if (! $this->sampler->shouldSample($payload->type)) {
            return;
        }

        // --- Filtering ---
        if ($this->filter->shouldExclude($payload)) {
            return;
        }

        // --- Redaction (before data leaves memory) ---
        $payload->data = $this->redactor->redact($payload->data);

        // --- Buffer ---
        $this->buffer->add($payload->toArray());

        // Auto-flush when the buffer is full.
        if ($this->buffer->isFull()) {
            $this->flush(false);
        }
    }

    /**
     * Flush all buffered payloads to the central server.
     *
     * @param bool $block When true, block until the HTTP request completes
     *                    (used during the terminating phase).
     */
    public function flush(bool $block = false): void
    {
        if ($this->buffer->isEmpty()) {
            return;
        }

        $this->sender->send($this->buffer->flush(), $block);
    }

    /**
     * Convenience method for recording log events.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->record(new Payload(
            type:      'log',
            traceId:   $this->context->id(),
            timestamp: microtime(true),
            data: [
                'level'   => $level,
                'message' => $message,
                'context' => $context,
            ],
        ));
    }
}
