<?php

namespace Berthojoris\Watcher\Contracts;

interface Sender
{
    /**
     * Transmit a batch of event payloads to the central server.
     *
     * @param list<array> $payloads
     * @param bool        $block    When true, block until the request completes
     *                              (used during the terminating phase).
     */
    public function send(array $payloads, bool $block = false): void;
}
