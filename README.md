# Laravel Watcher

<p align="center">
<strong>Self-hosted Laravel monitoring agent with microsecond precision tracing.</strong>
<br>A lightweight, self-hosted alternative to Laravel Nightwatch.
</p>

<p align="center">
<a href="https://packagist.org/packages/berthojoris/laravel-watcher"><img src="https://img.shields.io/packagist/v/berthojoris/laravel-watcher.svg" alt="Latest Version"></a>
<a href="https://packagist.org/packages/berthojoris/laravel-watcher"><img src="https://img.shields.io/packagist/l/berthojoris/laravel-watcher.svg" alt="License"></a>
<a href="https://packagist.org/packages/berthojoris/laravel-watcher"><img src="https://img.shields.io/packagist/php-v/berthojoris/laravel-watcher.svg" alt="PHP Version"></a>
<a href="https://github.com/berthojoris/laravel-watcher"><img src="https://img.shields.io/badge/platform-Laravel%2010%20%7C%2011%20%7C%2012-red.svg" alt="Laravel"></a>
</p>

---

## About

Laravel Watcher is an **agent** that captures full-application telemetry in real time: requests, queries, jobs, cache, outgoing HTTP, mail, notifications, commands, scheduled tasks, logs, and exceptions. It processes everything (filtering, redaction, batching) and transmits batches to your own **central server** without blocking the main PHP thread.

All events within a single request, job, or command lifecycle are connected by a UUID v4 trace ID, so you can build a microsecond-precision timeline (waterfall) on your dashboard.

### Why?

- **Self-hosted** — Your data never leaves your infrastructure.
- **Non-blocking** — Async dispatch via Guzzle promises + `fastcgi_finish_request()`.
- **High-volume ready** — In-memory buffer with batch send minimizes I/O overhead.
- **Secure by default** — Sensitive keys are redacted *before* data leaves application memory.

---

## Features

| | Feature | Description |
| :--- | :--- | :--- |
| 1 | **Microsecond Precision Timeline** | Trace all connected events (queries, jobs, cache, HTTP) using `hrtime(true)`. |
| 2 | **Invisible Agent (Buffer & Batch)** | Events accumulate in memory and flush in a single HTTP request (default: 500 events). |
| 3 | **High-Volume Architecture** | Lightweight agent designed not to add more than 5ms latency per request. |
| 4 | **Smart Grouping** | Structured exception payloads for stack-trace grouping on your server. |
| 5 | **Comprehensive Event Coverage** | 11 event types out of the box. |

---

## Requirements

| Requirement | Version |
| :--- | :--- |
| PHP | **8.2+** |
| Laravel | **10.x, 11.x, 12.x** |
| Guzzle HTTP | **7.5+** |

---

## Installation

Install the package via Composer:

```bash
composer require berthojoris/laravel-watcher
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=watcher-config
```

This creates `config/watcher.php`.

---

## Configuration

All settings live in `config/watcher.php`. The most important values are the **server URL** and **token**, which you set via environment variables in your `.env`:

```env
WATCHER_ENABLED=true
WATCHER_URL=https://watcher.my-domain.com/api/v1/ingest
WATCHER_TOKEN=your-secret-bearer-token
```

### Full Configuration Reference

```php
return [

    'enabled' => env('WATCHER_ENABLED', true),

    // Where batches are sent. Bearer token authenticates each request.
    'server' => [
        'url'     => env('WATCHER_URL', '...'),
        'token'   => env('WATCHER_TOKEN', ''),
        'timeout' => 5, // seconds
    ],

    // Events accumulate here and flush at max_size or on terminate.
    'buffer' => [
        'max_size'         => 500,
        'flush_on_terminate' => true,
    ],

    // Percentage of events captured per type (0.0 - 1.0).
    'sampling' => [
        'request'      => 1.0,  // 100%
        'query'        => 1.0,
        'job'          => 1.0,
        'cache'        => 0.5,  // 50%
        'http_client'  => 1.0,
        'mail'         => 1.0,
        'notification' => 1.0,
        'command'      => 1.0,
        'schedule'     => 1.0,
        'log'          => 1.0,
        'exception'    => 1.0,
    ],

    // Exclude noisy paths and event types.
    'filtering' => [
        'exclude_paths'        => ['_debugbar/*', 'telescope/*', 'horizon/*', 'favicon.ico'],
        'exclude_events'       => [],
        'slow_query_threshold' => 0, // ms; 0 = capture all queries
    ],

    // Sensitive keys are masked BEFORE data leaves memory (case-insensitive).
    'redaction' => [
        'keys' => ['password', 'password_confirmation', 'token', 'api_key', 'secret', 'authorization', 'cookie', 'credit_card', 'cvv', 'ssn'],
        'mask' => '********',
    ],

    // Monolog handler — taps into your application logger.
    'logging' => [
        'enabled'  => true,
        'level'    => 'debug',
        'channels' => ['stack'],
    ],

];
```

---

## Supported Events

| Category | Laravel Hook | Captured Data |
| :--- | :--- | :--- |
| **HTTP Request** | Global Middleware | Method, path, URI, IP, user-agent, headers, status, duration, payload size |
| **Database** | `QueryExecuted` | SQL, bindings (redacted), duration, connection |
| **Queue / Jobs** | `JobQueued`, `JobProcessing`, `JobProcessed`, `JobFailed` | Job class, queue, attempts, payload, exception |
| **Cache** | `CacheHit`, `CacheMissed`, `KeyWritten`, `KeyForgotten` | Action, key, TTL, value size |
| **Outgoing HTTP** | `ResponseReceived` | Method, URL, status, headers |
| **Mail** | `MessageSent` | Subject, to, from, cc, bcc |
| **Notifications** | `NotificationSent` | Notification class, channel, notifiable type |
| **Artisan** | `CommandFinished` | Command, arguments, options, exit code, memory peak |
| **Scheduler** | `ScheduledTaskFinished` | Command, cron expression, runtime |
| **Logs** | Monolog Handler | Level, message, context |
| **Exceptions** | Exception Handler | Class, message, file, line, code, stack trace |

---

## Architecture & Data Flow

The agent runs a fixed pipeline for every captured event:

```
Event Captured
     │
     ▼
 ┌───────────┐
 │  Sampling  │  Drop if rate < 100% for this event type
 └─────┬─────┘
       ▼
 ┌───────────┐
 │ Filtering  │  Skip excluded paths, event types, or fast queries
 └─────┬─────┘
       ▼
 ┌───────────┐
 │ Redaction  │  Mask sensitive keys recursively (before leaving memory)
 └─────┬─────┘
       ▼
 ┌───────────┐
 │  Buffer    │  Hold in memory (max 500 by default)
 └─────┬─────┘
       ▼
 ┌───────────┐
 │ Batch Send │  Flush on: buffer full, app terminate, or manual flush
 └───────────┘
```

### Async Dispatch

- **Non-blocking (during request):** Guzzle `postAsync()` with fire-and-forget promises.
- **Blocking (on terminate):** Calls `fastcgi_finish_request()` (PHP-FPM) so the user receives their response first, then flushes synchronously.
- **Fail-safe:** All transmission errors are silently swallowed so the agent never breaks the host application.

---

## Usage

The package auto-registers its service provider and wires all listeners. In most cases **no manual code is needed** — events are captured automatically.

### Manual Control via Facade

```php
use Berthojoris\Watcher\Facades\Watcher;

// Get the current trace ID
$traceId = Watcher::traceId();

// Manually flush buffered events
Watcher::flush();

// Record a custom event
Watcher::log('info', 'Custom metric', ['key' => 'value']);
```

### Via Dependency Injection

```php
use Berthojoris\Watcher\Watcher;

class OrderService
{
    public function __construct(
        protected Watcher $watcher,
    ) {}

    public function process(): void
    {
        // Your logic...

        $this->watcher->log('info', 'Order processed');
    }
}
```

---

## Central Server (Payload Format)

The agent POSTs batches to your configured `WATCHER_URL`. Each request body:

```json
{
  "events": [
    {
      "type": "request",
      "trace_id": "550e8400-e29b-41d4-a716-446655440000",
      "timestamp": 1751540400.123456,
      "duration": 142.53,
      "memory": 20971520,
      "data": {
        "method": "GET",
        "path": "api/users",
        "status": 200
      }
    },
    {
      "type": "query",
      "trace_id": "550e8400-e29b-41d4-a716-446655440000",
      "timestamp": 1751540400.123789,
      "duration": 3.21,
      "memory": null,
      "data": {
        "sql": "select * from \"users\" where \"id\" = ?",
        "bindings": ["********"],
        "connection": "pgsql"
      }
    }
  ]
}
```

Headers sent with every request:

```
Authorization: Bearer <WATCHER_TOKEN>
Content-Type: application/json
Accept: application/json
X-Watcher-Version: 1.0.0
```

---

## Security

- **Token auth** is mandatory. Every batch request carries a Bearer token.
- **Redaction** runs *before* data leaves application memory. Sensitive keys are matched case-insensitively and replaced with `********`.
- The agent **never throws** — all errors are caught to avoid leaking stack traces or breaking your app.

---

## Changelog

### v1.0.0 (2026-07-03)

- Initial release.
- Full event coverage: request, query, job, cache, HTTP client, mail, notification, command, scheduler, log, exception.
- Memory buffer with batch send (auto-flush at 500 or on terminate).
- Sampling, filtering, and redaction pipeline.
- Async dispatch via Guzzle promises + `fastcgi_finish_request()`.
- Monolog 2.x / 3.x compatible log handler.
- Supports Laravel 10.x / 11.x / 12.x and PHP 8.2+.

---

## License

The MIT License (MIT). Please see the [LICENSE](LICENSE) file for details.

---

## Author

**berthojoris**
- GitHub: [@berthojoris](https://github.com/berthojoris)
