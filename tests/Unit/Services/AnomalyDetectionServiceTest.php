<?php

namespace Tests\Unit\Services;

use App\Services\AnomalyDetectionService;
use Tests\TestCase;

class AnomalyDetectionServiceTest extends TestCase
{
    private AnomalyDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AnomalyDetectionService();
    }

    // ─── Mean and StdDev ─────────────────────────────────────────────────────

    public function test_mean_of_simple_values(): void
    {
        $this->assertEquals(3.0, $this->service->mean([1, 2, 3, 4, 5]));
    }

    public function test_mean_of_empty_array(): void
    {
        $this->assertEquals(0.0, $this->service->mean([]));
    }

    public function test_std_dev_of_identical_values(): void
    {
        $this->assertEquals(0.0, $this->service->stdDev([5, 5, 5, 5, 5]));
    }

    public function test_std_dev_of_varied_values(): void
    {
        // Sample std dev of [2,4,4,4,5,5,7,9] ≈ 2.138
        $stdDev = $this->service->stdDev([2, 4, 4, 4, 5, 5, 7, 9]);
        $this->assertGreaterThan(2.0, $stdDev);
        $this->assertLessThan(2.5, $stdDev);
    }

    // ─── Z-Score anomaly ─────────────────────────────────────────────────────

    public function test_z_score_detects_spike(): void
    {
        $history = [10, 10, 11, 9, 10, 10, 10, 10, 10, 10];
        $result  = $this->service->zScoreAnomaly(100.0, $history); // Extreme spike

        $this->assertNotNull($result);
        $this->assertEquals('z_score', $result['method']);
        $this->assertEquals('spike', $result['direction']);
    }

    public function test_z_score_detects_drop(): void
    {
        $history = [100, 100, 99, 101, 100, 100, 100, 100, 100];
        $result  = $this->service->zScoreAnomaly(10.0, $history); // Extreme drop

        $this->assertNotNull($result);
        $this->assertEquals('drop', $result['direction']);
    }

    public function test_z_score_returns_null_for_normal_value(): void
    {
        $history = [10, 10, 11, 9, 10, 10, 10, 10, 10];
        $result  = $this->service->zScoreAnomaly(10.5, $history);

        $this->assertNull($result);
    }

    public function test_z_score_returns_null_for_zero_variance(): void
    {
        $history = [5, 5, 5, 5, 5, 5, 5, 5, 5];
        $result  = $this->service->zScoreAnomaly(5.0, $history);

        $this->assertNull($result);
    }

    // ─── IQR anomaly ─────────────────────────────────────────────────────────

    public function test_iqr_detects_upper_outlier(): void
    {
        $history = [10, 11, 10, 10, 11, 10, 10, 11, 10, 10];
        $result  = $this->service->iqrAnomaly(50.0, $history);

        $this->assertNotNull($result);
        $this->assertEquals('iqr', $result['method']);
        $this->assertEquals('spike', $result['direction']);
    }

    public function test_iqr_detects_lower_outlier(): void
    {
        // History with enough spread for a non-zero IQR
        $history = [80, 85, 90, 95, 100, 105, 110, 115, 120];
        $result  = $this->service->iqrAnomaly(5.0, $history);

        $this->assertNotNull($result);
        $this->assertEquals('drop', $result['direction']);
    }

    public function test_iqr_returns_null_for_normal_value(): void
    {
        $history = [10, 11, 10, 10, 11, 10, 10, 11, 10, 10];
        $result  = $this->service->iqrAnomaly(11.0, $history);

        $this->assertNull($result);
    }

    // ─── detect_anomaly ──────────────────────────────────────────────────────

    public function test_detect_anomaly_returns_null_with_insufficient_history(): void
    {
        $result = $this->service->detectAnomaly(100.0, [1, 2, 3]);
        $this->assertNull($result);
    }

    public function test_detect_anomaly_returns_result_for_clear_spike(): void
    {
        // History must have some variance for z-score to work
        $history = [10, 11, 9, 10, 11, 10, 9, 11];
        $result  = $this->service->detectAnomaly(200.0, $history);

        $this->assertNotNull($result);
    }

    // ─── Forecast ────────────────────────────────────────────────────────────

    public function test_forecast_returns_correct_number_of_steps(): void
    {
        $values   = [1, 2, 3, 4, 5, 6, 7];
        $forecast = $this->service->forecast($values, 3);

        $this->assertCount(3, $forecast);
    }

    public function test_forecast_predicts_upward_trend(): void
    {
        $values   = [1, 2, 3, 4, 5, 6, 7];
        $forecast = $this->service->forecast($values, 3);

        // Linear regression on y=x+1 should predict 8, 9, 10
        $this->assertGreaterThan(7.5, $forecast[0]);
        $this->assertGreaterThan($forecast[0], $forecast[2]);
    }

    public function test_forecast_handles_single_value(): void
    {
        $forecast = $this->service->forecast([5.0], 3);
        $this->assertCount(3, $forecast);
    }
}
