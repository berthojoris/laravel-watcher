<?php

namespace Berthojoris\Watcher\Contracts;

interface Buffer
{
    /**
     * Add a serialized payload to the buffer.
     */
    public function add(array $payload): void;

    /**
     * Flush and return all buffered payloads.
     *
     * @return list<array>
     */
    public function flush(): array;

    /**
     * Check if the buffer has reached its maximum capacity.
     */
    public function isFull(): bool;

    /**
     * Check if the buffer is empty.
     */
    public function isEmpty(): bool;

    /**
     * Return the number of payloads currently buffered.
     */
    public function count(): int;
}
