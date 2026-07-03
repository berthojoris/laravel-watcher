<?php

namespace Berthojoris\Watcher\Http\Middleware;

use Berthojoris\Watcher\Payload\Payload;
use Berthojoris\Watcher\Watcher;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Global middleware that captures every incoming HTTP request.
 *
 * It records a nanosecond start anchor before the request is processed,
 * measures the full response duration, and records the event once the
 * downstream middleware chain returns.
 */
class WatcherMiddleware
{
    public function __construct(
        protected Watcher $watcher,
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $start = hrtime(true);

        /** @var SymfonyResponse $response */
        $response = $next($request);

        $durationMs = (hrtime(true) - $start) / 1e6;

        $this->watcher->record(new Payload(
            type:      'request',
            traceId:   $this->watcher->traceId(),
            timestamp: microtime(true),
            duration:  $durationMs,
            memory:    memory_get_peak_usage(true),
            data: [
                'method'       => $request->method(),
                'path'         => $request->path(),
                'uri'          => $request->fullUrl(),
                'ip'           => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'headers'      => $this->selectHeaders($request),
                'status'       => $response->getStatusCode(),
                'payload_size' => strlen((string) $response->getContent()),
            ],
        ));

        return $response;
    }

    /**
     * Extract a minimal, safe subset of request headers.
     *
     * @return array<string,string>
     */
    protected function selectHeaders(Request $request): array
    {
        $keys = ['accept', 'accept-encoding', 'accept-language', 'host', 'referer'];

        $headers = [];

        foreach ($keys as $key) {
            if ($request->headers->has($key)) {
                $headers[$key] = $request->headers->get($key);
            }
        }

        return $headers;
    }
}
