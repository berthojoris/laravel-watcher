<?php

namespace Berthojoris\Watcher\Support;

/**
 * Recursively walks an array tree and masks the values of any key whose
 * lower-cased name appears in the configured sensitive-key list.
 *
 * Redaction happens BEFORE data leaves the application memory.
 */
class Redactor
{
    /**
     * @param list<string> $sensitiveKeys  Lower-case key names to mask.
     * @param string       $mask           Replacement value.
     */
    public function __construct(
        protected array $sensitiveKeys,
        protected string $mask = '********',
    ) {
        $this->sensitiveKeys = array_map('strtolower', $sensitiveKeys);
    }

    /**
     * Recursively redact all sensitive keys from the given array.
     */
    public function redact(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->redact($value);
            } elseif (in_array(strtolower((string) $key), $this->sensitiveKeys, true)) {
                $value = $this->mask;
            }
        }

        return $data;
    }

    /**
     * Redact a single value if its key is sensitive.
     */
    public function redactValue(string $key, mixed $value): mixed
    {
        if (in_array(strtolower($key), $this->sensitiveKeys, true)) {
            return $this->mask;
        }

        return $value;
    }
}
