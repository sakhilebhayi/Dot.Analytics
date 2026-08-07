<?php

namespace App\Services;

/**
 * Currency Service
 *
 * Formats monetary values using the organisation's configured currency.
 * Supports all ISO 4217 currencies with proper symbol, decimal, and
 * thousands-separator formatting for display in dashboards and reports.
 */
class CurrencyService
{
    /** ISO 4217 currency metadata: symbol, decimals, position */
    private const CURRENCIES = [
        'USD' => ['symbol' => '$',   'decimals' => 2, 'position' => 'before'],
        'EUR' => ['symbol' => '€',   'decimals' => 2, 'position' => 'after'],
        'GBP' => ['symbol' => '£',   'decimals' => 2, 'position' => 'before'],
        'ZAR' => ['symbol' => 'R',   'decimals' => 2, 'position' => 'before'],
        'AUD' => ['symbol' => 'A$',  'decimals' => 2, 'position' => 'before'],
        'CAD' => ['symbol' => 'C$',  'decimals' => 2, 'position' => 'before'],
        'JPY' => ['symbol' => '¥',   'decimals' => 0, 'position' => 'before'],
        'CNY' => ['symbol' => '¥',   'decimals' => 2, 'position' => 'before'],
        'INR' => ['symbol' => '₹',   'decimals' => 2, 'position' => 'before'],
        'BRL' => ['symbol' => 'R$',  'decimals' => 2, 'position' => 'before'],
        'NGN' => ['symbol' => '₦',   'decimals' => 2, 'position' => 'before'],
        'KES' => ['symbol' => 'KSh', 'decimals' => 2, 'position' => 'before'],
        'GHS' => ['symbol' => 'GH₵', 'decimals' => 2, 'position' => 'before'],
        'ZMW' => ['symbol' => 'ZK',  'decimals' => 2, 'position' => 'before'],
    ];

    /**
     * Format a monetary value for the given currency code.
     *
     * @param  string  $currency  ISO 4217 code (e.g. 'ZAR')
     * @param  bool  $compact  Use compact notation for large numbers (e.g. R1.2M)
     */
    public function format(float|int $amount, string $currency = 'USD', bool $compact = false): string
    {
        $meta = self::CURRENCIES[strtoupper($currency)] ?? self::CURRENCIES['USD'];
        $symbol = $meta['symbol'];
        $decimals = $meta['decimals'];
        $before = $meta['position'] === 'before';

        if ($compact) {
            $formatted = $this->compactFormat($amount, $decimals);
        } else {
            $formatted = number_format($amount, $decimals, '.', ',');
        }

        return $before ? $symbol.$formatted : $formatted.' '.$symbol;
    }

    /**
     * Return just the currency symbol for the given code.
     */
    public function symbol(string $currency): string
    {
        return self::CURRENCIES[strtoupper($currency)]['symbol'] ?? $currency;
    }

    /**
     * Return all supported currency codes and their metadata.
     */
    public function supported(): array
    {
        return array_map(fn ($code, $meta) => array_merge(['code' => $code], $meta),
            array_keys(self::CURRENCIES),
            array_values(self::CURRENCIES),
        );
    }

    /**
     * Convert between currencies using a simple rate map.
     * Rates should come from a real-time source in production.
     */
    public function convert(float $amount, string $from, string $to, float $rate): float
    {
        if ($from === $to) {
            return $amount;
        }

        return round($amount * $rate, self::CURRENCIES[strtoupper($to)]['decimals'] ?? 2);
    }

    private function compactFormat(float $amount, int $decimals): string
    {
        if ($amount >= 1_000_000_000) {
            return round($amount / 1_000_000_000, 1).'B';
        }
        if ($amount >= 1_000_000) {
            return round($amount / 1_000_000, 1).'M';
        }
        if ($amount >= 1_000) {
            return round($amount / 1_000, 1).'K';
        }

        return number_format($amount, $decimals);
    }
}
