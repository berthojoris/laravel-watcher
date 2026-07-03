<?php

namespace Berthojoris\Watcher\Listeners;

use Berthojoris\Watcher\Payload\Payload;
use Berthojoris\Watcher\Watcher;
use Throwable;

/**
 * Records unhandled exceptions. Registered as a reportable callback on
 * the framework's exception handler.
 */
class ExceptionListener
{
    public function __construct(
        protected Watcher $watcher,
    ) {}

    public function handle(Throwable $e): void
    {
        $this->watcher->record(new Payload(
            type:      'exception',
            traceId:   $this->watcher->traceId(),
            timestamp: microtime(true),
            data: [
                'class'   => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'code'    => $e->getCode(),
                'trace'   => $this->formatTrace($e->getTrace()),
                'previous'=> $e->getPrevious() ? get_class($e->getPrevious()) : null,
            ],
        ));
    }

    /**
     * Compact the stack trace to the first 20 frames.
     *
     * @param array<int,array<string,mixed>> $trace
     *
     * @return array<int,array<string,mixed>>
     */
    protected function formatTrace(array $trace): array
    {
        return collect($trace)
            ->take(20)
            ->map(fn ($frame) => [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
            ])
            ->values()
            ->toArray();
    }
}
