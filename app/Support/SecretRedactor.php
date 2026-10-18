<?php

namespace App\Support;

/**
 * Strips credential-looking values out of arrays before they are persisted
 * somewhere readable (audit logs) or logged.
 */
class SecretRedactor
{
    public const MASK = '[redacted]';

    private const SENSITIVE_KEY = '/(secret|token|password|passwd|api[_-]?key|private[_-]?key|credential|authorization)/i';

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    public static function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY, $key)) {
                $values[$key] = self::MASK;

                continue;
            }

            if (is_array($value)) {
                $values[$key] = self::redact($value);
            } elseif (is_string($value) && $value !== '' && ($value[0] === '{' || $value[0] === '[')) {
                // e.g. getDirty() returns JSON-cast attributes as raw JSON strings
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $values[$key] = self::redact($decoded);
                }
            }
        }

        return $values;
    }
}
