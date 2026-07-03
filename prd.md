# 📄 Product Requirements Document (PRD)
**Nama Proyek:** `berthojoris/laravel-watcher`  
**Versi:** 1.0.0  
**Status:** Draft / Planning  
**Fokus:** Self-Hosted Laravel Monitoring Agent (Nightwatch Alternative)  
**Tanggal:** 3 Juli 2026  

---

## 1. Analisis Kompetitor: Laravel Nightwatch
Berdasarkan analisis terhadap [Laravel Nightwatch](https://nightwatch.laravel.com/), berikut adalah fitur dan arsitektur kunci yang akan direplikasi:

1.  **Microsecond Precision Timeline:** Melacak *trace* dari sebuah HTTP Request yang di dalamnya terdapat Queries, Jobs, Cache, dan Outgoing Requests dengan presisi mikrodetik menggunakan *connected events*.
2.  **Invisible Agent (Buffer & Batch):** Agent bekerja di *background*, mengumpulkan event di *memory*, lalu melakukan *batching* sebelum dikirim untuk mencegah *overhead* I/O pada aplikasi utama.
3.  **High-Volume Architecture:** Didesain untuk memproses "triliunan event" dengan arsitektur *column-oriented*. Package agent harus ringan dan tidak memblokir *thread* PHP utama.
4.  **Smart Grouping & Alerting:** Mengelompokkan *stack trace* exception yang sama dan memicu *alert* jika *threshold* (misal: response time > 5000ms) terlampaui.
5.  **Comprehensive Event Coverage:** Mendukung Requests, Outgoing Requests, Notifications, Jobs, Queries, Mail, Commands, Cache, dan Scheduled Tasks.

---

## 2. Visi & Ruang Lingkup `berthojoris/laravel-watcher`
**Tujuan:** Membuat Laravel Package (Agent) yang menangkap seluruh telemetri aplikasi secara *real-time*, memprosesnya (filtering, redaction, batching), dan mengirimkannya ke *Central Server* pribadi tanpa memblokir *response time* aplikasi.

**Target Pengguna:** Developer (Penggunaan Pribadi / Self-Hosted).

---

## 3. Arsitektur & Alur Data (Agent Side)

### A. Core Lifecycle
1.  **Booting:** Service Provider mendaftarkan Event Listeners dan Middleware. Membuat `Trace ID` unik untuk setiap Request/Job/Command.
2.  **Capturing:** Setiap event yang terpicu di-format menjadi *array payload* dan dimasukkan ke dalam **Memory Buffer**.
3.  **Processing:** Payload melewati *pipeline*: Sampling → Filtering → Redaction.
4.  **Flushing (Batch Send):** Buffer di-flush jika:
    - Mencapai batas maksimal (default: 500 events).
    - Aplikasi mencapai fase `Terminating`.
    - Memori hampir penuh (safety fallback).
5.  **Async Dispatch:** Data dikirim ke Central Server menggunakan *non-blocking HTTP Client* atau background process agar user tidak menunggu.

### B. Fitur Utama Agent
-   **Trace Context:** UUID v4 untuk mengikat semua event dalam satu siklus request/job.
-   **Buffer + Batch Send:** Menghemat *bandwidth* dan *TCP connection overhead*.
-   **Sampling:** Konfigurasi persentase capture per event type.
-   **Redaction Engine:** *Recursive array walker* untuk menyensor *sensitive keys*.
-   **Token Auth:** Bearer Token pada setiap *batch request*.

---

## 4. Pemetaan Event Laravel (Technical Specs)

| Kategori | Laravel Event / Hook | Data yang Diambil |
| :--- | :--- | :--- |
| **HTTP Request** | Global Middleware | URL, Method, Headers, Payload, Response Status, Duration (µs) |
| **Database** | `QueryExecuted` | SQL, Bindings (Redacted), Duration (ms), Connection |
| **Queue / Jobs** | `JobQueued`, `JobProcessing`, `JobProcessed`, `JobFailed` | Job Class, Queue, Payload, Attempts, Exception |
| **Cache** | `CacheHit`, `CacheMissed`, `KeyWritten`, `KeyForgotten` | Key, Store, Duration, Value size |
| **Outgoing HTTP** | `ResponseReceived` | URL, Method, Status, Body (Redacted), Duration |
| **Mail** | `MessageSent` | To, From, Subject, Mailable Class, Transport |
| **Notifications** | `NotificationSent` | Channel, Notifiable Type, Notification Class |
| **Artisan** | `CommandFinished` | Signature, Arguments, Options, Exit Code, Memory Peak |
| **Scheduler** | `ScheduledTaskFinished` | Task Command, Cron, Duration, Exit Code |
| **Logs** | Custom Monolog Handler | Level, Message, Context |
| **Exceptions** | Custom Exception Handler | Class, Message, File, Line, Trace |

---

## 5. Konfigurasi Package (`config/watcher.php`)

```php
return [
    'enabled' => env('WATCHER_ENABLED', true),
    
    'server' => [
        'url' => env('WATCHER_URL', 'https://watcher.my-domain.com/api/v1/ingest'),
        'token' => env('WATCHER_TOKEN', ''),
        'timeout' => 5,
    ],

    'buffer' => [
        'max_size' => 500,
        'flush_on_terminate' => true,
    ],

    'sampling' => [
        'requests' => 1.0,   // 100%
        'queries' => 1.0,
        'jobs' => 1.0,
        'cache' => 0.5,      // 50%
    ],

    'filtering' => [
        'exclude_paths' => ['_debugbar/*', 'telescope/*', 'favicon.ico'],
        'exclude_events' => [],
        'slow_query_threshold' => 100, // ms
    ],

    'redaction' => [
        'keys' => ['password', 'password_confirmation', 'token', 'api_key', 'secret', 'authorization', 'cookie'],
        'mask' => '********',
    ],
];
```

## 6. Detail Implementasi Fitur Kritis

### A. Async Sending (Non-Blocking)

- **Guzzle Async:** Menggunakan `promise()` untuk non-blocking HTTP.
- **FastCGI Finish Request:** Jika PHP-FPM, panggil `fastcgi_finish_request()` agar response terkirim dulu, baru agent flush di background.
- **Local Queue Fallback:** Jika async gagal, simpan ke Redis/DB queue lokal.

### B. Redaction Engine

```php
function redact(array $data, array $keysToMask): array {
    foreach ($data as $key => &$value) {
        if (is_array($value)) {
            $value = redact($value, $keysToMask);
        } elseif (in_array(strtolower($key), $keysToMask)) {
            $value = '********';
        }
    }
    return $data;
}
```

### C. Microsecond Precision

Menggunakan `hrtime(true)` untuk mencatat start/end setiap event:

```php
$start = hrtime(true);
// ... event terjadi ...
$durationNs = hrtime(true) - $start;
$durationMs = $durationNs / 1e+6;
```

---

## 7. Ekosistem yang Dibutuhkan (Central Server)

Package ini hanyalah Agent. Untuk replikasi Nightwatch penuh, diperlukan:

- **Ingestion API:** Endpoint Laravel penerima batch payload + validasi token.
- **Column-Oriented DB:** ClickHouse atau TimescaleDB (wajib untuk performa query miliaran row).
- **Dashboard UI:** Waterfall Timeline Visualizer, Log Explorer, Exception Grouping.
- **Alerting Engine:** Cron checker threshold + notifikasi (Slack/Telegram/Email).

---

## 8. Roadmap Pengembangan

### Phase 1: Foundation & Core Capture

- Setup package structure `berthojoris/laravel-watcher`
- Service Provider & Middleware (Request capture)
- Event Listeners (Query, Cache, Jobs, HTTP Outgoing)
- Memory Buffer & Trace ID

### Phase 2: Processing & Transmission

- Redaction Engine
- Filtering & Sampling logic
- Async Batch Sender
- Dummy Central Server untuk testing

### Phase 3: Advanced Hooks

- Monolog Handler (Logs)
- Artisan & Scheduler tracking
- Mail & Notification tracking
- Exception Handler override

### Phase 4: Central Server & Dashboard (Proyek Terpisah)

- Setup ClickHouse
- API Penerima & Parser
- UI Dashboard (Timeline Visualizer)

---

## 9. Catatan Penting

- **Default Behavior:** 100% events di-capture kecuali dikonfigurasi lain via sampling/filtering.
- **Keamanan:** Token auth wajib. Redaction berjalan SEBELUM data meninggalkan memori aplikasi.
- **Performa:** Agent tidak boleh menambah latency > 5ms per request. Gunakan `hrtime()` dan async dispatch.
- **Kompatibilitas:** Support Laravel 10.x, 11.x, 12.x dan PHP 8.2+.