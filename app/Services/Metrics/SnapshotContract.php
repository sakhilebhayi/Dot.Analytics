<?php

namespace App\Services\Metrics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Enforces docs/metrics-contract.md on a posted snapshot and normalises it.
 *
 * Unknown metric keys are dropped (and reported), never stored. A value of the
 * wrong kind rejects the whole snapshot with a 422: a half-trusted snapshot is
 * worse than none. `null` means "not reported" and is neither stored nor read as zero.
 */
class SnapshotContract
{
    private const ISO_8601 = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/';

    private const CURRENCY = '/^[A-Za-z]{3}$/';

    public function __construct(private readonly MetricCatalogue $catalogue = new MetricCatalogue) {}

    /**
     * @param  array<string, mixed>  $payload  the decoded request body
     * @return array{platform: string, taken_at: Carbon, period: string, metrics: array<string, int|float>, currency: string|null, dropped: list<string>}
     *
     * @throws ValidationException
     */
    public function validate(string $platform, array $payload): array
    {
        $errors = [];

        if (($payload['platform'] ?? null) !== $platform) {
            $errors['platform'] = ["The platform in the body must be '{$platform}', the platform in the URL."];
        }

        $period = $payload['period'] ?? null;
        if (! is_string($period) || ! in_array($period, $this->catalogue->periods(), true)) {
            $errors['period'] = ['The period must be one of: '.implode(', ', $this->catalogue->periods()).'.'];
        }

        $takenAt = $this->parseTakenAt($payload['taken_at'] ?? null, $errors);

        [$metrics, $currency, $dropped] = $this->validateMetrics($platform, $payload['metrics'] ?? null, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'platform' => $platform,
            'taken_at' => $takenAt,
            'period' => $period,
            'metrics' => $metrics,
            'currency' => $currency,
            'dropped' => $dropped,
        ];
    }

    /** @param  array<string, list<string>>  $errors */
    private function parseTakenAt(mixed $value, array &$errors): ?Carbon
    {
        if (! is_string($value) || ! preg_match(self::ISO_8601, $value)) {
            $errors['taken_at'] = ['taken_at must be an ISO 8601 timestamp with a zone, e.g. 2026-10-17T06:00:00Z.'];

            return null;
        }

        try {
            $takenAt = Carbon::parse($value)->utc();
        } catch (\Throwable) {
            $errors['taken_at'] = ['taken_at is not a valid timestamp.'];

            return null;
        }

        $skew = (int) config('metrics.max_clock_skew_seconds', 300);
        if ($takenAt->greaterThan(now()->addSeconds($skew))) {
            $errors['taken_at'] = ['taken_at cannot be in the future.'];

            return null;
        }

        return $takenAt->startOfSecond();
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @return array{0: array<string, int|float>, 1: string|null, 2: list<string>}
     */
    private function validateMetrics(string $platform, mixed $raw, array &$errors): array
    {
        if (! is_array($raw) || $raw === [] || array_is_list($raw)) {
            $errors['metrics'] = ['metrics must be an object of metric keys to numbers.'];

            return [[], null, []];
        }

        $metrics = [];
        $dropped = [];
        $currency = null;
        $needsCurrency = false;

        foreach ($raw as $key => $value) {
            $key = (string) $key;

            if ($key === MetricCatalogue::CURRENCY_KEY) {
                $currency = $this->validateCurrency($value, $errors);

                continue;
            }

            $spec = $this->catalogue->metric($platform, $key);
            if ($spec === null) {
                $dropped[] = $key;

                continue;
            }

            if ($value === null) {
                continue; // not reported: absent, never zero
            }

            $number = $this->validateNumber($key, $value, $spec, $errors);
            if ($number === null) {
                continue;
            }

            $metrics[$key] = $number;
            $needsCurrency = $needsCurrency || $this->catalogue->isMoney($platform, $key);
        }

        if ($metrics === [] && ! isset($errors['metrics']) && $this->noMetricErrors($errors)) {
            $errors['metrics'] = ["No metric in the snapshot is in the catalogue for '{$platform}'."];
        }

        if ($needsCurrency && $currency === null && ! isset($errors['metrics.currency'])) {
            $errors['metrics.currency'] = ['A snapshot with money metrics (*_cents) must carry an ISO 4217 currency, e.g. "ZAR".'];
        }

        return [$metrics, $currency, $dropped];
    }

    /** @param  array<string, list<string>>  $errors */
    private function noMetricErrors(array $errors): bool
    {
        foreach (array_keys($errors) as $key) {
            if (str_starts_with($key, 'metrics.')) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, list<string>>  $errors */
    private function validateCurrency(mixed $value, array &$errors): ?string
    {
        if (! is_string($value) || ! preg_match(self::CURRENCY, $value)) {
            $errors['metrics.currency'] = ['currency must be a three-letter ISO 4217 code, e.g. "ZAR".'];

            return null;
        }

        return Str::upper($value);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, list<string>>  $errors
     */
    private function validateNumber(string $key, mixed $value, array $spec, array &$errors): int|float|null
    {
        $type = $this->catalogue->type($spec['type']);
        $field = "metrics.{$key}";

        if (is_string($value) && trim($value) !== '' && ! preg_match('/^-?\d+(\.\d+)?$/', trim($value))) {
            $errors[$field] = ["{$key} must be a number."];

            return null;
        }

        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && trim($value) !== '')) {
            $errors[$field] = ["{$key} must be a number."];

            return null;
        }

        $number = is_string($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : $value;

        if (! is_finite((float) $number) || abs((float) $number) > 1e15) {
            $errors[$field] = ["{$key} is out of range."];

            return null;
        }

        if (($type['integer'] ?? false) === true) {
            if (is_float($number) && floor($number) !== $number) {
                $errors[$field] = ["{$key} must be a whole number."];

                return null;
            }
            $number = (int) $number;
        }

        if (isset($type['min']) && $number < $type['min']) {
            $errors[$field] = ["{$key} cannot be below {$type['min']}."];

            return null;
        }

        if (isset($type['max']) && $number > $type['max']) {
            $errors[$field] = ["{$key} cannot be above {$type['max']}."];

            return null;
        }

        return $number;
    }
}
