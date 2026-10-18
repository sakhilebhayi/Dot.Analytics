<?php

namespace Tests\Unit\Metrics;

use App\Services\Metrics\MetricAnomalyDetector;
use Tests\TestCase;

class AnomalyStatisticsTest extends TestCase
{
    private function evaluate(array $prior, float $latest, string $type = 'count'): array
    {
        return (new MetricAnomalyDetector)->evaluate($prior, $latest, $type);
    }

    public function test_median_and_mad_are_computed_on_the_prior_points_only(): void
    {
        $v = $this->evaluate([6, 8, 8, 10, 10, 12, 12, 14], 10);

        $this->assertSame(10.0, $v['median']);
        $this->assertSame(2.0, $v['mad']);
        $this->assertSame(2.0, $v['scale']);
        $this->assertSame(0.0, $v['score']);
    }

    public function test_one_earlier_outlier_does_not_hide_a_later_one(): void
    {
        // A mean/standard-deviation test would have been widened by the 500; the MAD is not.
        $v = $this->evaluate([10, 11, 9, 10, 12, 10, 500, 10], 40);

        $this->assertSame(10.0, $v['median']);
        $this->assertGreaterThan(3.5, $v['score']);
    }

    public function test_the_scale_never_falls_below_one_percent_of_the_median_or_the_kind_floor(): void
    {
        $flat = $this->evaluate(array_fill(0, 8, 5000.0), 5100.0, 'cents');
        $this->assertSame(0.0, $flat['mad']);
        $this->assertSame(50.0, $flat['scale'], '1% of 5000');
        $this->assertSame(2.0, $flat['score']);

        $zeros = $this->evaluate(array_fill(0, 8, 0.0), 2.0, 'count');
        $this->assertSame(1.0, $zeros['scale'], 'count floor');

        $rating = $this->evaluate(array_fill(0, 8, 0.0), 0.2, 'rating');
        $this->assertSame(0.1, $rating['scale'], 'rating floor');
    }

    public function test_direction_follows_the_sign_of_the_deviation(): void
    {
        $this->assertSame('spike', $this->evaluate([10, 11, 9, 10, 12, 10, 8, 10], 30)['direction']);
        $this->assertSame('drop', $this->evaluate([10, 11, 9, 10, 12, 10, 8, 10], 1)['direction']);
    }
}
