<?php

namespace Tests\Feature\Intelligence;

use App\Models\Intelligence\DataIssue;
use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Item;
use App\Models\Intelligence\Observation;
use App\Models\Intelligence\Signal;
use App\Models\Intelligence\Source;
use App\Services\Intelligence\Classifier;
use App\Services\Intelligence\HealthMonitor;
use App\Services\Intelligence\ReferenceData;
use App\Services\Intelligence\SignalDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DerivedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ReferenceData::class)->install();
    }

    private function series(string $key, array $values, string $geography = 'ZAF'): Indicator
    {
        $indicator = Indicator::where('key', $key)->firstOrFail();
        $geo = Geography::where('code', $geography)->value('id');

        foreach ($values as $i => $value) {
            Observation::create(['indicator_id' => $indicator->id, 'geography_id' => $geo, 'period' => now()->subYears(count($values) - $i)->startOfYear(), 'value' => $value, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        }

        return $indicator;
    }

    private function item(string $title, ?string $summary = null): Item
    {
        return Item::create(['uuid' => (string) Str::uuid(), 'source_id' => Source::where('key', 'federal-reserve')->value('id'), 'kind' => 'announcement', 'title' => $title, 'source_summary' => $summary, 'url' => 'https://www.federalreserve.gov/'.Str::random(8), 'url_hash' => hash('sha256', Str::random(20)), 'title_hash' => hash('sha256', $title), 'published_at' => now(), 'collected_at' => now()]);
    }

    public function test_items_are_filed_by_visible_word_lists_and_unmatched_items_stay_unfiled(): void
    {
        $mining = $this->item('South Africa gold mining output rises');
        $none = $this->item('A speech on the weather');

        app(Classifier::class)->classifyPending();

        $this->assertSame(['mining-and-minerals'], $mining->industries()->pluck('slug')->all());
        $this->assertSame(['ZAF'], $mining->geographies()->pluck('code')->all());
        $this->assertSame('rule', $mining->industries()->first()->pivot->method);
        $this->assertSame(0, $none->industries()->count());
        $this->assertNotNull($none->refresh()->classified_at, 'looked at, and nothing found, is not the same as not looked at');
    }

    public function test_a_movement_far_outside_the_usual_is_flagged_and_a_normal_one_is_not(): void
    {
        $this->series('wb.gdp-growth', [2.1, 2.0, 2.2, 1.9, 2.1, 2.0, 2.2, 2.1, 2.0, -6.0]);
        $this->series('wb.inflation', [5.0, 5.1, 4.9, 5.0, 5.2, 5.1, 5.0, 4.9, 5.1, 5.2]);

        app(SignalDetector::class)->detect();

        $signal = Signal::firstOrFail();
        $this->assertSame('fall', $signal->kind);
        $this->assertSame(1, Signal::count());
        $this->assertSame('wb.gdp-growth', $signal->indicator->key);
    }

    public function test_too_little_history_says_nothing(): void
    {
        $this->series('wb.gdp-growth', [2.0, 2.1, -9.0]);

        app(SignalDetector::class)->detect();

        $this->assertSame(0, Signal::count());
    }

    public function test_a_stale_series_and_a_failing_source_are_raised_once_and_clear_when_fixed(): void
    {
        $indicator = $this->series('wb.gdp-growth', [1.0]);
        $indicator->update(['stale_after_days' => 30]);
        Source::where('key', 'federal-reserve')->update(['status' => 'failing', 'consecutive_failures' => 5, 'last_error' => 'The source answered 503.']);

        app(HealthMonitor::class)->check();
        app(HealthMonitor::class)->check();

        $this->assertSame(1, DataIssue::where('kind', 'feed')->whereNull('resolved_at')->count());
        $this->assertSame(1, DataIssue::where('kind', 'stale')->whereNull('resolved_at')->count());

        Source::where('key', 'federal-reserve')->update(['status' => 'healthy', 'consecutive_failures' => 0]);
        app(HealthMonitor::class)->check();

        $this->assertSame(0, DataIssue::where('kind', 'feed')->whereNull('resolved_at')->count());
    }
}
