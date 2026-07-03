<?php

namespace Berthojoris\Watcher\Sender;

use Berthojoris\Watcher\Contracts\Sender as SenderContract;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Throwable;

/**
 * Transmits batched event payloads to the central ingestion server.
 *
 * Strategy:
 *  - Non-blocking mode (default): fires an async HTTP request via Guzzle
 *    promises without waiting for completion.
 *  - Blocking mode (used on terminate): optionally calls
 *    fastcgi_finish_request() first so the user's response is delivered
 *    before the flush runs, then sends synchronously.
 *
 * All errors are silently swallowed so the agent never breaks the host
 * application.
 */
class AsyncSender implements SenderContract
{
    protected ?Client $client = null;

    /** @var PromiseInterface[] */
    protected array $pending = [];

    public function __construct(
        protected string $url,
        protected string $token,
        protected int $timeout = 5,
        protected string $version = '1.0.0',
    ) {}

    public function send(array $payloads, bool $block = false): void
    {
        if (empty($payloads)) {
            return;
        }

        $options = $this->buildOptions($payloads);

        try {
            if ($block) {
                $this->finishRequest();
                $this->client()->post($this->url, $options);
            } else {
                $promise = $this->client()->postAsync($this->url, $options);

                // Attach no-op handlers so rejected promises do not throw.
                $this->pending[] = $promise->then(
                    fn () => null,
                    fn () => null,
                );
            }
        } catch (Throwable) {
            // Silent fail — never break the host application.
        }
    }

    /**
     * Wait for any pending async promises to settle.
     */
    public function wait(): void
    {
        foreach ($this->pending as $promise) {
            try {
                $promise->wait(false);
            } catch (Throwable) {
                // ignore
            }
        }

        $this->pending = [];
    }

    /**
     * Release the HTTP response to the client before flushing in the
     * background (PHP-FPM only).
     */
    protected function finishRequest(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
    }

    protected function client(): Client
    {
        if ($this->client === null) {
            $this->client = new Client([
                'timeout'         => $this->timeout,
                'connect_timeout' => $this->timeout,
                'http_errors'     => false,
            ]);
        }

        return $this->client;
    }

    /**
     * @param list<array> $payloads
     *
     * @return array<string,mixed>
     */
    protected function buildOptions(array $payloads): array
    {
        return [
            'headers' => [
                'Authorization'      => 'Bearer ' . $this->token,
                'Content-Type'       => 'application/json',
                'Accept'             => 'application/json',
                'X-Watcher-Version'  => $this->version,
            ],
            'json' => ['events' => $payloads],
        ];
    }
}
