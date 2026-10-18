<?php

namespace Tests\Unit\Metrics;

use App\Support\MetricFormat;
use PHPUnit\Framework\TestCase;

class MetricFormatTest extends TestCase
{
    public function test_counts_are_whole_numbers_with_thousands_separators(): void
    {
        $this->assertSame('1,234', MetricFormat::value('1234.0000', 'count'));
        $this->assertSame('0', MetricFormat::value(0, 'count'));
    }

    public function test_money_is_cents_shown_with_its_currency(): void
    {
        $this->assertSame('ZAR 1,250.00', MetricFormat::value('125000.0000', 'cents', 'ZAR'));
        $this->assertSame('0.05', MetricFormat::value(5, 'cents'), 'no currency, no invented symbol');
    }

    public function test_quantities_drop_trailing_zeros_and_ratings_keep_two_decimals(): void
    {
        $this->assertSame('1,234.5', MetricFormat::value(1234.5, 'quantity'));
        $this->assertSame('12', MetricFormat::value(12.0, 'quantity'));
        $this->assertSame('4.50', MetricFormat::value(4.5, 'rating'));
    }

    public function test_a_missing_value_is_a_dash_never_a_zero(): void
    {
        $this->assertSame('—', MetricFormat::value(null, 'count'));
        $this->assertSame('—', MetricFormat::delta(null, 'count'));
        $this->assertSame('—', MetricFormat::percent(null));
    }

    public function test_deltas_and_percents_are_signed(): void
    {
        $this->assertSame('+5', MetricFormat::delta(5, 'count'));
        $this->assertSame('-2', MetricFormat::delta('-2.0000', 'count'));
        $this->assertSame('0', MetricFormat::delta(0, 'count'));
        $this->assertSame('-ZAR 10.00', MetricFormat::delta(-1000, 'cents', 'ZAR'));
        $this->assertSame('+50%', MetricFormat::percent('50.0000'));
        $this->assertSame('-12.5%', MetricFormat::percent(-12.5));
        $this->assertSame('0%', MetricFormat::percent(0));
    }

    public function test_axis_labels_are_short(): void
    {
        $this->assertSame('0', MetricFormat::axis(0, 'count'));
        $this->assertSame('42', MetricFormat::axis(42, 'count'));
        $this->assertSame('12.5k', MetricFormat::axis(12500, 'count'));
        $this->assertSame('1.2M', MetricFormat::axis(1_234_567, 'count'));
        $this->assertSame('1,250', MetricFormat::axis(125000, 'cents'));
        $this->assertSame('4.5', MetricFormat::axis(4.5, 'rating'));
        $this->assertSame('2.5', MetricFormat::axis(2.5, 'quantity'));
    }
}
