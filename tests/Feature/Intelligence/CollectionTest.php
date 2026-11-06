<?php

namespace Tests\Feature\Intelligence;

use App\Models\Intelligence\DataIssue;
use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Industry;
use App\Models\Intelligence\Item;
use App\Models\Intelligence\Observation;
use App\Models\Intelligence\ObservationRevision;
use App\Models\Intelligence\Source;
use App\Models\Intelligence\SourceRun;
use App\Services\Intelligence\Collector;
use App\Services\Intelligence\ReferenceData;
use App\Services\Intelligence\SourceRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ReferenceData::class)->install();
    }

    private function wbRows(array $values): array
    {
        return [['page' => 1], array_map(fn ($date, $v) => ['countryiso3code' => 'ZAF', 'date' => (string) $date, 'value' => $v], array_keys($values), $values)];
    }

    private function metadata(string $licence = 'CC BY-4.0'): array
    {
        return ['source' => [['concept' => [['variable' => [['metatype' => [['id' => 'License_Type', 'value' => $licence], ['id' => 'License_URL', 'value' => 'https://example.test/l'], ['id' => 'Shortdefinition', 'value' => 'The World Bank says what this is.']]]]]]]]];
    }

    private function onlyIndicator(string $key = 'wb.gdp-growth'): Indicator
    {
        Indicator::where('source_id', Source::where('key', 'world-bank')->value('id'))->where('key', '!=', $key)->update(['is_published' => false, 'licence_checked_at' => now()]);

        return Indicator::where('key', $key)->firstOrFail();
    }

    public function test_the_reference_data_installs_once_and_changes_nothing_when_run_again(): void
    {
        $counts = [Geography::count(), Indicator::count(), Source::count()];

        app(ReferenceData::class)->install();

        $this->assertSame($counts, [Geography::count(), Indicator::count(), Source::count()]);
        $this->assertSame(17, Industry::count());
        $this->assertGreaterThan(50, Geography::where('kind', 'country')->count());
    }

    public function test_a_world_bank_collection_stores_figures_with_their_source_run(): void
    {
        $this->onlyIndicator();
        Http::fake([
            'api.worldbank.org/v2/sources/*' => Http::response($this->metadata()),
            'api.worldbank.org/v2/country/*' => Http::response($this->wbRows([2023 => 0.7, 2024 => 0.6, 2025 => 1.1])),
        ]);

        $run = app(SourceRunner::class)->run(Source::where('key', 'world-bank')->first());

        $this->assertSame('succeeded', $run->status);
        $this->assertSame(3, $run->created);
        $this->assertSame(3, Observation::count());
        $this->assertSame($run->id, Observation::first()->source_run_id);
        $this->assertSame('The World Bank says what this is.', Indicator::where('key', 'wb.gdp-growth')->value('definition'));
    }

    public function test_collecting_the_same_data_twice_changes_nothing(): void
    {
        $this->onlyIndicator();
        Http::fake(['api.worldbank.org/v2/sources/*' => Http::response($this->metadata()), 'api.worldbank.org/v2/country/*' => Http::response($this->wbRows([2024 => 0.6, 2025 => 1.1]))]);
        $source = Source::where('key', 'world-bank')->first();

        app(SourceRunner::class)->run($source);
        $second = app(SourceRunner::class)->run($source->refresh());

        $this->assertSame(2, Observation::count());
        $this->assertSame(0, $second->created);
        $this->assertSame(2, $second->unchanged);
    }

    public function test_a_value_the_publisher_revised_keeps_the_old_one_on_record(): void
    {
        $this->onlyIndicator();
        $source = Source::where('key', 'world-bank')->first();
        Http::fake(['api.worldbank.org/v2/sources/*' => Http::response($this->metadata()), 'api.worldbank.org/v2/country/*' => Http::response($this->wbRows([2025 => 1.1]))]);
        app(SourceRunner::class)->run($source);

        Http::swap(new Factory);
        Http::fake(['api.worldbank.org/v2/sources/*' => Http::response($this->metadata()), 'api.worldbank.org/v2/country/*' => Http::response($this->wbRows([2025 => 1.4]))]);
        $run = app(SourceRunner::class)->run($source->refresh());

        $this->assertSame(1, $run->updated);
        $this->assertEqualsWithDelta(1.4, Observation::first()->value, 0.0001);
        $revision = ObservationRevision::firstOrFail();
        $this->assertEqualsWithDelta(1.1, $revision->previous_value, 0.0001);
        $this->assertEqualsWithDelta(1.4, $revision->new_value, 0.0001);
    }

    public function test_a_series_under_a_non_open_licence_is_not_collected_and_is_raised_as_an_issue(): void
    {
        $this->onlyIndicator();
        Http::fake(['api.worldbank.org/v2/sources/*' => Http::response($this->metadata('Use and distribution of these data are subject to IRF terms and conditions.')), 'api.worldbank.org/v2/country/*' => Http::response($this->wbRows([2025 => 1.1]))]);

        app(SourceRunner::class)->run(Source::where('key', 'world-bank')->first());

        $this->assertSame(0, Observation::count());
        $this->assertFalse(Indicator::where('key', 'wb.gdp-growth')->value('is_published'));
        $this->assertSame('licence', DataIssue::firstOrFail()->kind);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/country/'));
    }

    public function test_a_non_commercial_licence_is_not_published_either(): void
    {
        $this->onlyIndicator();
        Http::fake(['api.worldbank.org/v2/sources/*' => Http::response($this->metadata('CC BY-NC 4.0'))]);

        app(SourceRunner::class)->run(Source::where('key', 'world-bank')->first());

        $this->assertFalse(Indicator::where('key', 'wb.gdp-growth')->value('is_published'));
    }

    public function test_malformed_figures_are_counted_and_left_out(): void
    {
        $this->onlyIndicator();
        $rows = [['page' => 1], [
            ['countryiso3code' => 'ZAF', 'date' => '2024', 'value' => 'abc'],
            ['countryiso3code' => 'XXX', 'date' => '2024', 'value' => 1.0],
            ['countryiso3code' => 'ZAF', 'date' => 'latest', 'value' => 1.0],
            ['countryiso3code' => 'ZAF', 'date' => '2025', 'value' => 2.5],
        ]];
        Http::fake(['api.worldbank.org/v2/sources/*' => Http::response($this->metadata()), 'api.worldbank.org/v2/country/*' => Http::response($rows)]);

        $run = app(SourceRunner::class)->run(Source::where('key', 'world-bank')->first());

        $this->assertSame(1, Observation::count());
        $this->assertSame(3, $run->rejected);
    }

    public function test_a_failing_source_keeps_what_it_had_and_waits_longer_each_time(): void
    {
        $this->onlyIndicator();
        $source = Source::where('key', 'world-bank')->first();
        Http::fake(['api.worldbank.org/v2/sources/*' => Http::response($this->metadata()), 'api.worldbank.org/v2/country/*' => Http::response($this->wbRows([2025 => 1.1]))]);
        app(SourceRunner::class)->run($source);

        Http::swap(new Factory);
        Http::fake(['api.worldbank.org/*' => Http::response('down', 503)]);
        $waits = [];

        foreach (range(1, 4) as $i) {
            $run = app(SourceRunner::class)->run($source->refresh());
            $waits[] = now()->diffInMinutes($source->refresh()->next_due_at, true);
            $this->assertSame('failed', $run->status);
        }

        $this->assertSame(1, Observation::count(), 'figures already stored are not touched by a failure');
        $this->assertSame(4, $source->refresh()->consecutive_failures);
        $this->assertSame('failing', $source->status);
        $this->assertGreaterThan($waits[0], $waits[3]);
    }

    public function test_a_source_that_asks_us_to_slow_down_is_left_alone_for_at_least_that_long(): void
    {
        $this->onlyIndicator();
        Http::fake(['api.worldbank.org/*' => Http::response('slow down', 429, ['Retry-After' => '7200'])]);
        $source = Source::where('key', 'world-bank')->first();

        app(SourceRunner::class)->run($source);

        $this->assertGreaterThanOrEqual(119, now()->diffInMinutes($source->refresh()->next_due_at, true));
    }

    public function test_a_long_collection_stops_in_time_and_carries_on_from_where_it_stopped(): void
    {
        $source = Source::where('key', 'world-bank')->first();
        Indicator::where('source_id', $source->id)->update(['licence_checked_at' => now(), 'is_published' => true]);
        Http::fake(['api.worldbank.org/v2/country/*' => Http::response($this->wbRows([2025 => 1.1]))]);

        $run = app(SourceRunner::class)->run($source, 0);

        $this->assertSame('partial', $run->status);
        $this->assertTrue($source->refresh()->cursor['more']);
        $this->assertLessThan(Indicator::where('source_id', $source->id)->count(), $source->cursor['position'] + 1);
        $this->assertSame('healthy', $source->status);
    }

    public function test_a_collection_may_only_reach_hosts_named_on_the_source(): void
    {
        $source = Source::where('key', 'federal-reserve')->first();
        $source->update(['settings' => ['feed' => 'https://evil.example/feed.xml', 'hosts' => ['www.federalreserve.gov'], 'kind' => 'announcement']]);
        Http::fake();

        $run = app(SourceRunner::class)->run($source);

        $this->assertSame('failed', $run->status);
        Http::assertNothingSent();
    }

    public function test_a_redirect_to_another_host_is_not_followed(): void
    {
        $source = Source::where('key', 'federal-reserve')->first();
        Http::fake(['www.federalreserve.gov/*' => Http::response('', 302, ['Location' => 'https://evil.example/x'])]);

        $run = app(SourceRunner::class)->run($source);

        $this->assertSame('failed', $run->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example'));
    }

    public function test_a_feed_yields_items_with_a_link_the_summary_only_where_allowed_and_no_duplicates(): void
    {
        $rss = fn (string $title, string $link) => '<?xml version="1.0"?><rss version="2.0"><channel><item><title>'.$title.'</title><link>'.$link.'</link><description><![CDATA[<p>Rates are held.</p>]]></description><pubDate>'.now()->subHour()->toRfc2822String().'</pubDate></item></channel></rss>';
        Http::fake(['www.federalreserve.gov/*' => Http::response($rss('Fed holds rates', 'https://www.federalreserve.gov/news/1.htm?utm_source=x')), 'www.ecb.europa.eu/*' => Http::response($rss('Fed holds rates', 'https://www.ecb.europa.eu/other.html'))]);

        app(SourceRunner::class)->run(Source::where('key', 'federal-reserve')->first());
        app(SourceRunner::class)->run(Source::where('key', 'federal-reserve')->first());
        app(SourceRunner::class)->run(Source::where('key', 'ecb-press')->first());

        $this->assertSame(2, Item::count(), 'the same link twice is one item');
        $fed = Item::whereHas('source', fn ($q) => $q->where('key', 'federal-reserve'))->first();
        $this->assertSame('https://www.federalreserve.gov/news/1.htm', $fed->url, 'tracking is stripped from the address');
        $this->assertSame('Rates are held.', $fed->source_summary);
        $ecb = Item::whereHas('source', fn ($q) => $q->where('key', 'ecb-press'))->first();
        $this->assertNull($ecb->source_summary, 'a source whose terms do not allow a summary stores none');
        $this->assertSame($fed->id, $ecb->duplicate_of, 'the same headline from another source is one development');
        $this->assertSame(1, Item::listed()->count());
    }

    public function test_a_series_csv_is_read_with_its_periods_and_missing_values_left_out(): void
    {
        $csv = "KEY,FREQ,TIME_PERIOD,OBS_VALUE\nx,D,2026-11-01,NaN\nx,D,2026-11-02,7.25\nx,D,2026-11-03,7.5\n";
        Http::fake(['stats.bis.org/*' => Http::response($csv)]);
        Indicator::where('key', '!=', 'bis.policy-rate-za')->whereIn('source_id', [Source::where('key', 'bis')->value('id')])->delete();

        $run = app(SourceRunner::class)->run(Source::where('key', 'bis')->first());

        $this->assertSame('succeeded', $run->status);
        $this->assertSame(2, Observation::count());
        $this->assertSame('2026-11-02', Observation::orderBy('period')->first()->period->toDateString());
    }

    public function test_quarter_and_month_periods_become_the_first_day_of_the_period(): void
    {
        $csv = "TIME_PERIOD,OBS_VALUE\n2026-Q2,1.5\n2026-08,2.5\n";
        Http::fake(['sdmx.oecd.org/*' => Http::response($csv)]);
        Indicator::where('source_id', Source::where('key', 'oecd')->value('id'))->where('key', '!=', 'oecd.retail-volume-za')->delete();

        app(SourceRunner::class)->run(Source::where('key', 'oecd')->first());

        $this->assertSame(['2026-04-01', '2026-08-01'], Observation::orderBy('period')->get()->map(fn ($o) => $o->period->toDateString())->all());
    }

    public function test_a_source_waiting_for_permission_is_never_collected(): void
    {
        $source = Source::where('key', 'sarb')->first();

        $this->assertFalse($source->is_enabled);
        $this->artisan('intelligence:collect', ['--source' => 'sarb'])->assertSuccessful();
        $this->assertSame(0, Observation::count());
    }

    public function test_a_source_that_needs_a_key_stays_off_without_one(): void
    {
        $this->assertFalse(Source::where('key', 'eia')->first()->is_enabled);
        $this->assertSame('needs-key', Source::where('key', 'eia')->first()->status);
    }

    public function test_press_release_text_loses_the_captions_of_attached_files(): void
    {
        $source = Source::where('key', 'apo-africa-newsroom')->firstOrFail();
        $source->update(['allows_summary' => true]);
        $collector = new Collector($source, SourceRun::create(['source_id' => $source->id, 'started_at' => now()]));

        $item = $collector->item('press-release', 'A title', 'https://www.africa-newsroom.com/x', now(), 'Download logoThe embassy said <b>this</b>. Download image More.');

        $this->assertSame('The embassy said this. More.', $item->source_summary);
    }
}
