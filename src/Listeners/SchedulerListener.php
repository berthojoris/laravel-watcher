<?php

namespace Berthojoris\Watcher\Listeners;

use Berthojoris\Watcher\Payload\Payload;
use Berthojoris\Watcher\Watcher;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Events\ScheduledTaskFinished;

/**
 * Captures scheduled task execution, including the cron expression,
 * duration, and exit code.
 */
class SchedulerListener
{
    public function __construct(
        protected Watcher $watcher,
    ) {}

    public function handle(ScheduledTaskFinished $event): void
    {
        $task = $event->task;

        $this->watcher->record(new Payload(
            type:      'schedule',
            traceId:   $this->watcher->traceId(),
            timestamp: microtime(true),
            duration:  ($event->runtime ?? 0) * 1000,
            data: [
                'command'     => $task->command ?? get_class($task),
                'description' => $task->description ?? null,
                'cron'        => $this->extractCron($task),
                'runtime_sec' => $event->runtime ?? null,
            ],
        ));

        $this->watcher->flush(false);
    }

    /**
     * Extract the cron expression from a scheduled task event.
     */
    protected function extractCron(ScheduledEvent $task): ?string
    {
        try {
            return $task->getExpression();
        } catch (\Throwable) {
            return null;
        }
    }
}
