<?php

namespace App\Support;

/**
 * One place that turns a stored metric number into the text a person reads, so a
 * tile, a trend axis, an alert and a briefing never disagree about it.
 * Money is stored as whole cents and shown as "ZAR 1,250.00".
 */
final class MetricFormat
{
    public static function value(float|int|string|null $value, ?string $type, ?string $currency = null): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $number = (float) $value;

        return match ($type) {
            'cents' => trim(($currency ? $currency.' ' : '').number_format($number / 100, 2)),
            'rating' => number_format($number, 2),
            'quantity' => self::trimmed($number, 2),
            default => self::trimmed($number, 0),
        };
    }

    /** "+12", "-3.5": a change in the metric's own unit, always signed. */
    public static function delta(float|int|string|null $delta, ?string $type, ?string $currency = null): string
    {
        if ($delta === null || $delta === '') {
            return '—';
        }

        $number = (float) $delta;
        $sign = $number > 0 ? '+' : ($number < 0 ? '-' : '');

        return $sign.self::value(abs($number), $type, $currency);
    }

    public static function percent(float|int|string|null $pct): string
    {
        if ($pct === null || $pct === '') {
            return '—';
        }

        $number = (float) $pct;

        return ($number > 0 ? '+' : ($number < 0 ? '-' : '')).self::trimmed(abs($number), 1).'%';
    }

    private static function trimmed(float $number, int $decimals): string
    {
        $text = number_format($number, $decimals);

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
