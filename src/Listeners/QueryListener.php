<?php

namespace Berthojoris\Watcher\Listeners;

use Berthojoris\Watcher\Payload\Payload;
use Berthojoris\Watcher\Watcher;
use Illuminate\Database\Events\QueryExecuted;

/**
 * Captures every SQL query executed by the ORM, including the raw SQL,
 * redacted bindings, duration, and connection name.
 */
class QueryListener
{
    public function __construct(
        protected Watcher $watcher,
    ) {}

    public function handle(QueryExecuted $event): void
    {
        $this->watcher->record(new Payload(
            type:      'query',
            traceId:   $this->watcher->traceId(),
            timestamp: microtime(true),
            duration:  $event->time,
            data: [
                'sql'        => $event->sql,
                'bindings'   => $event->bindings,
                'connection' => $event->connectionName,
            ],
        ));
    }
}
