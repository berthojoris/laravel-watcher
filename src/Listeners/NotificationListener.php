<?php

namespace Berthojoris\Watcher\Listeners;

use Berthojoris\Watcher\Payload\Payload;
use Berthojoris\Watcher\Watcher;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Captures notifications dispatched through Laravel's notification system.
 */
class NotificationListener
{
    public function __construct(
        protected Watcher $watcher,
    ) {}

    public function handle(NotificationSent $event): void
    {
        $notifiable = $event->notifiable;

        $this->watcher->record(new Payload(
            type:      'notification',
            traceId:   $this->watcher->traceId(),
            timestamp: microtime(true),
            data: [
                'notification'   => get_class($event->notification),
                'channel'        => $event->channel,
                'notifiable'     => is_object($notifiable) ? get_class($notifiable) : gettype($notifiable),
                'notifiable_id'  => is_object($notifiable) && method_exists($notifiable, 'getKey')
                    ? $notifiable->getKey()
                    : null,
                'response'       => is_scalar($event->response) ? $event->response : null,
            ],
        ));
    }
}
