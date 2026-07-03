<?php

namespace Berthojoris\Watcher\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string  traceId()
 * @method static string  newTrace()
 * @method static void    record(\Berthojoris\Watcher\Payload\Payload $payload)
 * @method static void    flush(bool $block = false)
 * @method static void    log(string $level, string $message, array $context = [])
 * @method static bool    isEnabled()
 * @method static void    disable()
 *
 * @see \Berthojoris\Watcher\Watcher
 */
class Watcher extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'watcher';
    }
}
