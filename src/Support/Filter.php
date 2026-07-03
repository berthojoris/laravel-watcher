<?php

namespace Berthojoris\Watcher\Support;

use Berthojoris\Watcher\Payload\Payload;

/**
 * Filters events based on exclusion rules: event-type blacklist,
 * path-pattern blacklist, and optional slow-query threshold.
 */
class Filter
{
    /**
     * @param list<string> $excludePaths        Glob patterns for request paths to skip.
     * @param list<string> $excludeEvents       Event types to skip entirely.
     * @param int          $slowQueryThreshold  Minimum query duration (ms) to capture; 0 = capture all.
     */
    public function __construct(
        protected array $excludePaths = [],
        protected array $excludeEvents = [],
        protected int $slowQueryThreshold = 0,
    ) {}

    /**
     * Determine if a payload should be excluded from capture.
     */
    public function shouldExclude(Payload $payload): bool
    {
        if (in_array($payload->type, $this->excludeEvents, true)) {
            return true;
        }

        if ($payload->type === 'request' && $this->isPathExcluded($payload->data['path'] ?? '')) {
            return true;
        }

        if (
            $payload->type === 'query'
            && $this->slowQueryThreshold > 0
            && ($payload->duration ?? 0) < $this->slowQueryThreshold
        ) {
            return true;
        }

        return false;
    }

    protected function isPathExcluded(string $path): bool
    {
        foreach ($this->excludePaths as $pattern) {
            if (fnmatch($pattern, $path) || fnmatch($pattern, '/' . $path)) {
                return true;
            }
        }

        return false;
    }
}
