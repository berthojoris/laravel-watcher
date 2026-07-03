<?php

namespace Berthojoris\Watcher\Logger;

use Berthojoris\Watcher\Watcher;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Monolog handler that taps into the application's logger and forwards
 * each log entry to the Watcher agent.
 *
 * Compatible with Monolog 2.x (array $record) and 3.x (LogRecord $record).
 */
class WatcherHandler extends AbstractProcessingHandler
{
    public function __construct(
        protected Watcher $watcher,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    /**
     * @param array|LogRecord $record
     */
    protected function write($record): void
    {
        $level   = $this->extract($record, 'level_name', 'level');
        $message = $this->extract($record, 'message', 'message');
        $context = $this->extract($record, 'context', 'context', []);

        // In Monolog 3.x, level is a Level enum.
        if ($level instanceof Level) {
            $level = $level->getName();
        }

        $this->watcher->log(
            level:   is_string($level) ? $level : (string) $level,
            message: is_string($message) ? $message : '',
            context: is_array($context) ? $context : [],
        );
    }

    /**
     * Extract a value from either the Monolog 2.x array format or the
     * Monolog 3.x LogRecord object.
     *
     * @param array|LogRecord $record
     */
    protected function extract($record, string $arrayKey, string $objectMethod, mixed $default = null): mixed
    {
        if (is_array($record)) {
            return $record[$arrayKey] ?? $default;
        }

        if ($objectMethod === 'level') {
            return $record->level ?? $default;
        }

        return $record->{$objectMethod} ?? $default;
    }
}
