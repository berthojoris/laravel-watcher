<?php

namespace Berthojoris\Watcher\Listeners;

use Berthojoris\Watcher\Payload\Payload;
use Berthojoris\Watcher\Watcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Throwable;

/**
 * Captures the full job lifecycle: queued, processing, processed, and
 * failed. A fresh trace ID is minted when each job starts processing so
 * that all events within the job share a single connected trace.
 */
class JobListener
{
    public function __construct(
        protected Watcher $watcher,
    ) {}

    public function handleJobQueued(JobQueued $event): void
    {
        $this->watcher->record(new Payload(
            type:      'job',
            traceId:   $this->watcher->traceId(),
            timestamp: microtime(true),
            data: [
                'action' => 'queued',
                'job'    => $event->job instanceof \Closure ? 'Closure' : get_class($event->job),
                'queue'  => method_exists($event->job, 'queue') ? $event->job->queue : null,
            ],
        ));
    }

    public function handleJobProcessing(JobProcessing $event): void
    {
        // Start a new trace for this job's processing cycle.
        $this->watcher->newTrace();

        $this->watcher->record(new Payload(
            type:      'job',
            traceId:   $this->watcher->traceId(),
            timestamp: microtime(true),
            data: [
                'action'   => 'processing',
                'job'      => $event->job->resolveName(),
                'queue'    => $event->job->getQueue(),
                'attempts' => $event->job->attempts(),
                'payload'  => $event->job->payload(),
            ],
        ));
    }

    public function handleJobProcessed(JobProcessed $event): void
    {
        $this->watcher->record(new Payload(
            type:      'job',
            traceId:   $this->watcher->traceId(),
            timestamp: microtime(true),
            data: [
                'action'   => 'processed',
                'job'      => $event->job->resolveName(),
                'queue'    => $event->job->getQueue(),
                'attempts' => $event->job->attempts(),
            ],
        ));

        // Flush after each job in long-running workers.
        $this->watcher->flush(false);
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $this->watcher->record(new Payload(
            type:      'job',
            traceId:   $this->watcher->traceId(),
            timestamp: microtime(true),
            data: [
                'action'    => 'failed',
                'job'       => $event->job->resolveName(),
                'queue'     => $event->job->getQueue(),
                'attempts'  => $event->job->attempts(),
                'exception' => $this->formatException($event->exception),
            ],
        ));

        $this->watcher->flush(false);
    }

    /**
     * @return array<string,mixed>
     */
    protected function formatException(Throwable $e): array
    {
        return [
            'class'   => get_class($e),
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
        ];
    }
}
