<?php

namespace Berthojoris\Watcher\Buffer;

use Berthojoris\Watcher\Contracts\Buffer as BufferContract;

/**
 * In-memory event buffer that accumulates payloads and flushes them
 * in a single batch to minimise I/O overhead.
 */
class MemoryBuffer implements BufferContract
{
    /** @var list<array> */
    protected array $items = [];

    public function __construct(
        protected int $maxSize = 500,
    ) {}

    public function add(array $payload): void
    {
        $this->items[] = $payload;
    }

    public function flush(): array
    {
        $items = $this->items;

        $this->items = [];

        return $items;
    }

    public function isFull(): bool
    {
        return count($this->items) >= $this->maxSize;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
