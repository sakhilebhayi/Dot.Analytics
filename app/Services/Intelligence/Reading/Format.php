<?php

namespace App\Services\Intelligence\Reading;

use Illuminate\Support\Carbon;

/** How numbers, units and periods are written on the page. A number and its unit never split across a line. */
class Format
{
    public const NBSP = "\u{00A0}";

    public static function number(float $value, int $decimals): string
    {
        $abs = abs($value);

        if ($abs >= 1_000_000_000) {
            return self::trim($value / 1_000_000_000, 1).self::NBSP.'billion';
        }

        if ($abs >= 1_000_000) {
            return self::trim($value / 1_000_000, 1).self::NBSP.'million';
        }

        return str_replace('-', '−', number_format($value, $decimals, '.', self::NBSP));
    }

    /** The number as printed: a percentage keeps its sign, anything else is the bare number. */
    public static function figure(float $value, string $unit, int $decimals): string
    {
        $number = self::number($value, $decimals);

        return str_starts_with($unit, '%') ? $number.'%' : $number;
    }

    /** What the number is a number of: "% of GDP" says "of GDP", "% a year" says "a year", "kg per hectare" stays whole. */
    public static function note(string $unit): string
    {
        return str_starts_with($unit, '%') ? trim(substr($unit, 1)) : $unit;
    }

    public static function period(Carbon $period, string $frequency): string
    {
        return match ($frequency) {
            'A' => $period->format('Y'),
            'Q' => 'Q'.$period->quarter.' '.$period->format('Y'),
            'M' => $period->format('F Y'),
            default => $period->format('j F Y'),
        };
    }

    public static function age(Carbon $moment): string
    {
        return $moment->diffForHumans(['parts' => 1, 'short' => false, 'syntax' => Carbon::DIFF_RELATIVE_TO_NOW]);
    }

    private static function trim(float $value, int $decimals): string
    {
        return rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');
    }
}
