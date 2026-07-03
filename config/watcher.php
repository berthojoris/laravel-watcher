<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable / Disable Agent
    |--------------------------------------------------------------------------
    |
    | Set to false to completely disable all event capture and transmission.
    | No listeners will fire, no data will leave your application.
    |
    */

    'enabled' => env('WATCHER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Central Server Configuration
    |--------------------------------------------------------------------------
    |
    | The Agent batches events and POSTs them to this ingestion endpoint.
    | A Bearer Token authenticates every batch request.
    |
    */

    'server' => [
        'url'     => env('WATCHER_URL', 'https://watcher.my-domain.com/api/v1/ingest'),
        'token'   => env('WATCHER_TOKEN', ''),
        'timeout' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory Buffer & Batch Send
    |--------------------------------------------------------------------------
    |
    | Events accumulate in memory and are flushed in a single HTTP request
    | to reduce I/O overhead. The buffer auto-flushes when it reaches
    | max_size, or when the application enters the terminating phase.
    |
    */

    'buffer' => [
        'max_size'         => 500,
        'flush_on_terminate' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling
    |--------------------------------------------------------------------------
    |
    | Control the percentage of events captured per event type.
    | 1.0 = 100%, 0.5 = 50%, 0.0 = disabled.
    |
    */

    'sampling' => [
        'request'      => 1.0,
        'query'        => 1.0,
        'job'          => 1.0,
        'cache'        => 0.5,
        'http_client'  => 1.0,
        'mail'         => 1.0,
        'notification' => 1.0,
        'command'      => 1.0,
        'schedule'     => 1.0,
        'log'          => 1.0,
        'exception'    => 1.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Filtering
    |--------------------------------------------------------------------------
    |
    | Exclude noisy paths and event types. Only queries slower than
    | slow_query_threshold (ms) are recorded when set above 0.
    |
    */

    'filtering' => [
        'exclude_paths'        => ['_debugbar/*', 'telescope/*', 'horizon/*', 'favicon.ico'],
        'exclude_events'       => [],
        'slow_query_threshold' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redaction Engine
    |--------------------------------------------------------------------------
    |
    | Sensitive keys are recursively masked BEFORE data leaves memory.
    | Matches are case-insensitive against the key name.
    |
    */

    'redaction' => [
        'keys' => [
            'password',
            'password_confirmation',
            'token',
            'api_key',
            'secret',
            'authorization',
            'cookie',
            'credit_card',
            'cvv',
            'ssn',
        ],
        'mask' => '********',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Handler
    |--------------------------------------------------------------------------
    |
    | When enabled, a Monolog handler taps into the application logger
    | so log entries are captured as Watcher events.
    |
    */

    'logging' => [
        'enabled' => true,
        'level'   => 'debug',
        'channels' => ['stack'],
    ],

];
