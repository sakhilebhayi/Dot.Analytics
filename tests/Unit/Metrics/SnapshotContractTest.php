<?php

namespace Tests\Unit\Metrics;

use App\Services\Metrics\MetricCatalogue;
use App\Services\Metrics\SnapshotContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SnapshotContractTest extends TestCase
{
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'company_uuid' => '6f1c1b7e-5d0e-4a39-9d52-0c0f6a1b2c3d',
            'platform' => 'tasks',
            'taken_at' => '2026-10-17T06:00:00Z',
            'period' => 'day',
            'metrics' => ['open' => 12, 'overdue' => 3],
        ], $overrides);
    }

    private function contract(): SnapshotContract
    {
        return new SnapshotContract;
    }

    public function test_a_valid_snapshot_is_normalised(): void
    {
        $result = $this->contract()->validate('tasks', $this->valid());

        $this->assertSame('tasks', $result['platform']);
        $this->assertSame('day', $result['period']);
        $this->assertSame('2026-10-17T06:00:00+00:00', $result['taken_at']->toIso8601String());
        $this->assertSame(['open' => 12, 'overdue' => 3], $result['metrics']);
        $this->assertNull($result['currency']);
        $this->assertSame([], $result['dropped']);
    }

    public function test_taken_at_is_converted_to_utc(): void
    {
        $result = $this->contract()->validate('tasks', $this->valid(['taken_at' => '2026-10-17T08:00:00+02:00']));

        $this->assertSame('2026-10-17T06:00:00+00:00', $result['taken_at']->toIso8601String());
    }

    public function test_unknown_keys_are_dropped_and_reported(): void
    {
        $result = $this->contract()->validate('tasks', $this->valid(['metrics' => ['open' => 5, 'mystery_kpi' => 99]]));

        $this->assertSame(['open' => 5], $result['metrics']);
        $this->assertSame(['mystery_kpi'], $result['dropped']);
    }

    public function test_a_snapshot_with_no_known_key_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->contract()->validate('tasks', $this->valid(['metrics' => ['mystery_kpi' => 99]]));
    }

    public function test_a_key_from_another_platforms_catalogue_is_not_accepted(): void
    {
        $result = $this->contract()->validate('tasks', $this->valid(['metrics' => ['open' => 1, 'mrr_cents' => 100]]));

        $this->assertSame(['open' => 1], $result['metrics']);
        $this->assertSame(['mrr_cents'], $result['dropped']);
    }

    public function test_null_means_not_reported_and_is_not_stored_as_zero(): void
    {
        $result = $this->contract()->validate('ehail', $this->valid([
            'platform' => 'ehail',
            'metrics' => ['drivers_online' => 4, 'avg_rating' => null],
        ]));

        $this->assertSame(['drivers_online' => 4], $result['metrics']);
        $this->assertArrayNotHasKey('avg_rating', $result['metrics']);
    }

    public function test_non_numeric_values_are_rejected(): void
    {
        foreach (['twelve', '12 apples', [], true, ['a' => 1]] as $bad) {
            try {
                $this->contract()->validate('tasks', $this->valid(['metrics' => ['open' => $bad]]));
                $this->fail('Expected a ValidationException for '.json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('metrics.open', $e->errors());
            }
        }
    }

    public function test_counts_must_be_whole_and_not_negative(): void
    {
        foreach ([-1, 1.5, '2.5'] as $bad) {
            try {
                $this->contract()->validate('tasks', $this->valid(['metrics' => ['open' => $bad]]));
                $this->fail('Expected a ValidationException for '.json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('metrics.open', $e->errors());
            }
        }
    }

    public function test_numeric_strings_and_integral_floats_are_accepted_as_numbers(): void
    {
        $result = $this->contract()->validate('tasks', $this->valid(['metrics' => ['open' => '7', 'overdue' => 4.0]]));

        $this->assertSame(['open' => 7, 'overdue' => 4], $result['metrics']);
    }

    public function test_ratings_are_range_checked(): void
    {
        $this->expectException(ValidationException::class);

        $this->contract()->validate('ehail', $this->valid(['platform' => 'ehail', 'metrics' => ['avg_rating' => 5.6]]));
    }

    public function test_decimal_quantities_are_kept_as_decimals(): void
    {
        $result = $this->contract()->validate('farms', $this->valid(['platform' => 'farms', 'metrics' => ['harvested_kg_30d' => 1234.5]]));

        $this->assertSame(['harvested_kg_30d' => 1234.5], $result['metrics']);
    }

    public function test_money_is_whole_cents_with_a_currency(): void
    {
        $result = $this->contract()->validate('billing', $this->valid([
            'platform' => 'billing',
            'metrics' => ['mrr_cents' => 1250000, 'currency' => 'zar'],
        ]));

        $this->assertSame(['mrr_cents' => 1250000], $result['metrics']);
        $this->assertSame('ZAR', $result['currency']);
    }

    public function test_money_without_a_currency_is_rejected(): void
    {
        try {
            $this->contract()->validate('billing', $this->valid(['platform' => 'billing', 'metrics' => ['mrr_cents' => 100]]));
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('metrics.currency', $e->errors());
        }
    }

    public function test_fractional_cents_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->contract()->validate('billing', $this->valid([
            'platform' => 'billing',
            'metrics' => ['mrr_cents' => 100.5, 'currency' => 'ZAR'],
        ]));
    }

    public function test_a_malformed_currency_is_rejected(): void
    {
        foreach (['RAND', 'Z', '12$', 7, ''] as $bad) {
            try {
                $this->contract()->validate('billing', $this->valid([
                    'platform' => 'billing',
                    'metrics' => ['mrr_cents' => 100, 'currency' => $bad],
                ]));
                $this->fail('Expected a ValidationException for '.json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('metrics.currency', $e->errors());
            }
        }
    }

    public function test_the_body_platform_must_match_the_url_platform(): void
    {
        $this->expectException(ValidationException::class);

        $this->contract()->validate('tasks', $this->valid(['platform' => 'billing']));
    }

    public function test_period_must_be_day(): void
    {
        $this->expectException(ValidationException::class);

        $this->contract()->validate('tasks', $this->valid(['period' => 'week']));
    }

    public function test_taken_at_must_be_iso_8601_with_a_zone(): void
    {
        foreach (['yesterday', '2026-10-17', '2026-10-17 06:00:00', '17/10/2026', 1760680800, ''] as $bad) {
            try {
                $this->contract()->validate('tasks', $this->valid(['taken_at' => $bad]));
                $this->fail('Expected a ValidationException for '.json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('taken_at', $e->errors());
            }
        }
    }

    public function test_taken_at_cannot_be_in_the_future(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 17)->setTime(6, 0));

        $this->expectException(ValidationException::class);

        $this->contract()->validate('tasks', $this->valid(['taken_at' => '2026-10-18T06:00:00Z']));
    }

    public function test_metrics_must_be_an_object(): void
    {
        foreach ([null, 'open', [1, 2, 3], []] as $bad) {
            try {
                $this->contract()->validate('tasks', $this->valid(['metrics' => $bad]));
                $this->fail('Expected a ValidationException for '.json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('metrics', $e->errors());
            }
        }
    }

    public function test_catalogue_resolves_full_keys_and_money_metrics(): void
    {
        $catalogue = new MetricCatalogue;

        $this->assertTrue($catalogue->hasPlatform('tasks'));
        $this->assertFalse($catalogue->hasPlatform('dot.fleet'));
        $this->assertSame('Overdue tasks', $catalogue->metric('tasks', 'overdue')['label']);
        $this->assertSame(['platform' => 'tasks', 'metric' => 'overdue'], $catalogue->parse('tasks.overdue'));
        $this->assertNull($catalogue->parse('tasks.nope'));
        $this->assertTrue($catalogue->isMoney('billing', 'mrr_cents'));
        $this->assertFalse($catalogue->isMoney('billing', 'open_invoices'));
    }
}
