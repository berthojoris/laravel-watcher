<?php

namespace Berthojoris\Watcher\Listeners;

use Berthojoris\Watcher\Payload\Payload;
use Berthojoris\Watcher\Watcher;
use Illuminate\Mail\Events\MessageSent;

/**
 * Captures mail sent through Laravel's mailer.
 */
class MailListener
{
    public function __construct(
        protected Watcher $watcher,
    ) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        $this->watcher->record(new Payload(
            type:      'mail',
            traceId:   $this->watcher->traceId(),
            timestamp: microtime(true),
            data: [
                'subject'  => $message->getSubject(),
                'to'       => $this->formatAddresses($message->getTo()),
                'from'     => $this->formatAddresses($message->getFrom()),
                'cc'       => $this->formatAddresses($message->getCc()),
                'bcc'      => $this->formatAddresses($message->getBcc()),
            ],
        ));
    }

    /**
     * @param \Symfony\Component\Mime\Address[]|null $addresses
     *
     * @return string[]
     */
    protected function formatAddresses(?array $addresses): array
    {
        if (empty($addresses)) {
            return [];
        }

        return array_map(
            static fn ($address) => $address->getAddress(),
            $addresses,
        );
    }
}
