<?php

namespace Berthojoris\Watcher\Listeners;

use Berthojoris\Watcher\Payload\Payload;
use Berthojoris\Watcher\Watcher;
use Illuminate\Http\Client\Events\ResponseReceived;

/**
 * Captures outgoing HTTP requests made via Laravel's HTTP client wrapper.
 */
class OutgoingHttpListener
{
    public function __construct(
        protected Watcher $watcher,
    ) {}

    public function handle(ResponseReceived $event): void
    {
        $request = $event->request;
        $response = $event->response;

        $this->watcher->record(new Payload(
            type:      'http_client',
            traceId:   $this->watcher->traceId(),
            timestamp: microtime(true),
            data: [
                'method'     => $request->method(),
                'url'        => $request->url(),
                'status'     => $response->status(),
                'successful' => $response->successful(),
                'headers'    => $this->selectHeaders($request->headers()),
            ],
        ));
    }

    /**
     * @param array<string,array<string>> $headers
     *
     * @return array<string,mixed>
     */
    protected function selectHeaders(array $headers): array
    {
        $safe = ['accept', 'content-type', 'user-agent'];

        return array_filter(
            $headers,
            fn ($key) => in_array(strtolower($key), $safe, true),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
