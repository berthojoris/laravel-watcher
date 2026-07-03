<?php

namespace Berthojoris\Watcher;

use Berthojoris\Watcher\Buffer\MemoryBuffer;
use Berthojoris\Watcher\Contracts\Buffer as BufferContract;
use Berthojoris\Watcher\Contracts\Sender as SenderContract;
use Berthojoris\Watcher\Http\Middleware\WatcherMiddleware;
use Berthojoris\Watcher\Logger\WatcherHandler;
use Berthojoris\Watcher\Sender\AsyncSender;
use Berthojoris\Watcher\Support\Filter;
use Berthojoris\Watcher\Support\Redactor;
use Berthojoris\Watcher\Support\Sampler;
use Berthojoris\Watcher\Support\TraceContext;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use Monolog\Level;
use Throwable;

class WatcherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/watcher.php', 'watcher');

        $this->registerCoreBindings();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__ . '/../config/watcher.php' => config_path('watcher.php')],
                'watcher-config',
            );
        }

        if (! config('watcher.enabled', true)) {
            $this->app->make(Watcher::class)->disable();

            return;
        }

        $this->registerMiddleware();

        $this->registerEventListeners();

        $this->registerExceptionHandler();

        $this->registerLogHandler();

        $this->registerTerminatingCallback();
    }

    /**
     * Bind all core services as singletons so they are shared across a
     * single request / job / command lifecycle.
     */
    protected function registerCoreBindings(): void
    {
        $this->app->singleton(TraceContext::class);

        $this->app->singleton(BufferContract::class, function () {
            return new MemoryBuffer(
                maxSize: config('watcher.buffer.max_size', 500),
            );
        });

        $this->app->singleton(Redactor::class, function () {
            return new Redactor(
                sensitiveKeys: config('watcher.redaction.keys', []),
                mask: config('watcher.redaction.mask', '********'),
            );
        });

        $this->app->singleton(Sampler::class, function () {
            return new Sampler(
                rates: config('watcher.sampling', []),
            );
        });

        $this->app->singleton(Filter::class, function () {
            return new Filter(
                excludePaths: config('watcher.filtering.exclude_paths', []),
                excludeEvents: config('watcher.filtering.exclude_events', []),
                slowQueryThreshold: (int) config('watcher.filtering.slow_query_threshold', 0),
            );
        });

        $this->app->singleton(SenderContract::class, function () {
            return new AsyncSender(
                url: (string) config('watcher.server.url', ''),
                token: (string) config('watcher.server.token', ''),
                timeout: (int) config('watcher.server.timeout', 5),
            );
        });

        $this->app->singleton(Watcher::class);

        $this->app->alias(Watcher::class, 'watcher');
    }

    /**
     * Prepend the global HTTP middleware for request capture.
     */
    protected function registerMiddleware(): void
    {
        try {
            $kernel = $this->app->make(HttpKernel::class);

            if (method_exists($kernel, 'prependMiddleware')) {
                $kernel->prependMiddleware(WatcherMiddleware::class);
            }
        } catch (Throwable) {
            // No HTTP kernel — console / queue context.
        }
    }

    /**
     * Wire up all framework event listeners.
     */
    protected function registerEventListeners(): void
    {
        $events = $this->app->make('events');

        // --- Database Queries ---
        if (class_exists(\Illuminate\Database\Events\QueryExecuted::class)) {
            $events->listen(
                \Illuminate\Database\Events\QueryExecuted::class,
                [\Berthojoris\Watcher\Listeners\QueryListener::class, 'handle'],
            );
        }

        // --- Queue / Jobs ---
        if (class_exists(\Illuminate\Queue\Events\JobQueued::class)) {
            $events->listen(
                \Illuminate\Queue\Events\JobQueued::class,
                [\Berthojoris\Watcher\Listeners\JobListener::class, 'handleJobQueued'],
            );
        }
        $events->listen(
            \Illuminate\Queue\Events\JobProcessing::class,
            [\Berthojoris\Watcher\Listeners\JobListener::class, 'handleJobProcessing'],
        );
        $events->listen(
            \Illuminate\Queue\Events\JobProcessed::class,
            [\Berthojoris\Watcher\Listeners\JobListener::class, 'handleJobProcessed'],
        );
        $events->listen(
            \Illuminate\Queue\Events\JobFailed::class,
            [\Berthojoris\Watcher\Listeners\JobListener::class, 'handleJobFailed'],
        );

        // --- Cache ---
        $events->listen(
            \Illuminate\Cache\Events\CacheHit::class,
            [\Berthojoris\Watcher\Listeners\CacheListener::class, 'handleHit'],
        );
        $events->listen(
            \Illuminate\Cache\Events\CacheMissed::class,
            [\Berthojoris\Watcher\Listeners\CacheListener::class, 'handleMissed'],
        );
        $events->listen(
            \Illuminate\Cache\Events\KeyWritten::class,
            [\Berthojoris\Watcher\Listeners\CacheListener::class, 'handleWritten'],
        );
        $events->listen(
            \Illuminate\Cache\Events\KeyForgotten::class,
            [\Berthojoris\Watcher\Listeners\CacheListener::class, 'handleForgotten'],
        );

        // --- Outgoing HTTP ---
        if (class_exists(\Illuminate\Http\Client\Events\ResponseReceived::class)) {
            $events->listen(
                \Illuminate\Http\Client\Events\ResponseReceived::class,
                [\Berthojoris\Watcher\Listeners\OutgoingHttpListener::class, 'handle'],
            );
        }

        // --- Mail ---
        $events->listen(
            \Illuminate\Mail\Events\MessageSent::class,
            [\Berthojoris\Watcher\Listeners\MailListener::class, 'handle'],
        );

        // --- Notifications ---
        $events->listen(
            \Illuminate\Notifications\Events\NotificationSent::class,
            [\Berthojoris\Watcher\Listeners\NotificationListener::class, 'handle'],
        );

        // --- Artisan Commands ---
        $events->listen(
            \Illuminate\Console\Events\CommandFinished::class,
            [\Berthojoris\Watcher\Listeners\CommandListener::class, 'handle'],
        );

        // --- Scheduler ---
        $events->listen(
            \Illuminate\Console\Events\ScheduledTaskFinished::class,
            [\Berthojoris\Watcher\Listeners\SchedulerListener::class, 'handle'],
        );
    }

    /**
     * Register a reportable callback to capture unhandled exceptions.
     */
    protected function registerExceptionHandler(): void
    {
        try {
            $handler = $this->app->make(ExceptionHandler::class);

            if (method_exists($handler, 'reportable')) {
                $listener = $this->app->make(\Berthojoris\Watcher\Listeners\ExceptionListener::class);

                $handler->reportable(function (Throwable $e) use ($listener) {
                    $listener->handle($e);
                });
            }
        } catch (Throwable) {
            // Exception handler not available in this context.
        }
    }

    /**
     * Push a Monolog handler onto configured logging channels.
     */
    protected function registerLogHandler(): void
    {
        if (! config('watcher.logging.enabled', true)) {
            return;
        }

        if (! class_exists(\Monolog\Handler\AbstractProcessingHandler::class)) {
            return;
        }

        try {
            $watcher = $this->app->make(Watcher::class);
            $logManager = $this->app->make('log');
            $level = config('watcher.logging.level', 'debug');

            foreach ((array) config('watcher.logging.channels', ['stack']) as $channel) {
                $logManager->channel($channel)->pushHandler(
                    new WatcherHandler($watcher, $level),
                );
            }
        } catch (Throwable) {
            // Logging setup failed — fail silently.
        }
    }

    /**
     * Flush the buffer when the application terminates.
     */
    protected function registerTerminatingCallback(): void
    {
        if (! config('watcher.buffer.flush_on_terminate', true)) {
            return;
        }

        $this->app->terminating(function () {
            $this->app->make(Watcher::class)->flush(block: true);
        });
    }
}
