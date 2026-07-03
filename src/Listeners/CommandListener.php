<?php

namespace Berthojoris\Watcher\Listeners;

use Berthojoris\Watcher\Payload\Payload;
use Berthojoris\Watcher\Watcher;
use Illuminate\Console\Events\CommandFinished;

/**
 * Captures Artisan command execution, including arguments, options,
 * exit code, and peak memory usage.
 */
class CommandListener
{
    public function __construct(
        protected Watcher $watcher,
    ) {}

    public function handle(CommandFinished $event): void
    {
        $this->watcher->record(new Payload(
            type:      'command',
            traceId:   $this->watcher->traceId(),
            timestamp: microtime(true),
            memory:    memory_get_peak_usage(true),
            data: [
                'command'  => $event->command ?? 'closure',
                'exit'     => $event->exitCode,
                'input'    => [
                    'arguments' => method_exists($event->input, 'getArguments')
                        ? $event->input->getArguments()
                        : [],
                    'options'   => method_exists($event->input, 'getOptions')
                        ? $event->input->getOptions()
                        : [],
                ],
            ],
        ));

        // Flush after each command since CLI is typically single-shot.
        $this->watcher->flush(false);
    }
}
