<?php

namespace App\Services\Intelligence;

use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Industry;
use App\Models\Intelligence\Source;
use App\Services\Intelligence\Fetchers\PendingPermissionFetcher;
use Illuminate\Support\Str;

/**
 * Puts the places, industries, sources and series in place, and brings them up to date when the lists change. Running it
 * again changes nothing that has not changed. It never removes a source or series that already holds figures.
 */
class ReferenceData
{
    public function install(): void
    {
        $this->geographies();
        $this->industries();
        $this->sources();
        $this->worldBankSeries();
        $this->otherSeries();
    }

    private function geographies(): void
    {
        foreach ((array) config('intelligence_geographies') as $g) {
            Geography::updateOrCreate(['code' => $g['code']], [
                'iso2' => $g['iso2'], 'name' => $g['name'], 'slug' => Str::slug($g['name']), 'kind' => $g['kind'],
                'region' => $g['region'], 'is_featured' => (bool) $g['featured'],
            ]);
        }
    }

    private function industries(): void
    {
        foreach (require database_path('seeders/data/industries.php') as $i) {
            Industry::updateOrCreate(['slug' => $i['slug']], ['name' => $i['name'], 'summary' => $i['summary'], 'sort' => $i['sort']]);
        }
    }

    private function sources(): void
    {
        foreach ((array) config('intelligence_sources.sources') as $s) {
            $needsKey = isset($s['needs_key']) && ! config($s['needs_key']);
            $enabled = ($s['enabled'] ?? true) && ! $needsKey;

            $source = Source::firstOrNew(['key' => $s['key']]);
            $source->fill([
                'name' => $s['name'], 'kind' => $s['kind'], 'homepage_url' => $s['homepage'], 'terms_url' => $s['terms'],
                'licence' => $s['licence'], 'attribution' => $s['attribution'], 'allows_summary' => $s['summary'], 'allows_commercial' => $s['commercial'],
                'restrictions' => $s['restrictions'], 'fetcher' => $s['fetcher'], 'collect_every_minutes' => $s['every'], 'stale_after_hours' => $s['stale_hours'],
                'settings' => ($s['settings'] ?? []) + ['hosts' => $s['hosts']], 'is_enabled' => $enabled,
            ]);

            if (! $source->exists) {
                $source->status = $enabled ? 'new' : ($needsKey ? 'needs-key' : 'off');
            }

            if (! $enabled && $source->status === 'new') {
                $source->status = $needsKey ? 'needs-key' : 'off';
            }

            $source->save();
        }

        foreach ((array) config('intelligence_sources.waiting') as $w) {
            Source::updateOrCreate(['key' => $w['key']], [
                'name' => $w['name'], 'kind' => $w['kind'], 'homepage_url' => $w['homepage'], 'licence' => 'Waiting for written permission',
                'attribution' => '', 'restrictions' => $w['reason'], 'fetcher' => PendingPermissionFetcher::class,
                'collect_every_minutes' => 1440, 'stale_after_hours' => 720, 'is_enabled' => false, 'status' => 'waiting',
            ]);
        }
    }

    private function worldBankSeries(): void
    {
        $source = Source::where('key', 'world-bank')->firstOrFail();
        $industries = Industry::pluck('id', 'slug');

        foreach (require database_path('seeders/data/world_bank_indicators.php') as $i) {
            $indicator = Indicator::firstOrNew(['key' => $i['key']]);
            $indicator->fill([
                'source_id' => $source->id, 'source_code' => $i['code'], 'name' => $i['name'], 'unit' => $i['unit'], 'frequency' => $i['frequency'],
                'decimals' => $i['decimals'], 'good_direction' => $i['good'], 'topic' => $i['topic'], 'stale_after_days' => $i['stale_days'], 'caveat' => $i['caveat'],
            ]);

            if (! $indicator->exists) {
                $indicator->definition = 'The World Bank definition is added when this series is first collected.';
            }

            $indicator->save();
            $indicator->industries()->syncWithoutDetaching($industries->only($i['industries'])->values()->all());
        }
    }

    private function otherSeries(): void
    {
        $industries = Industry::pluck('id', 'slug');

        foreach ((array) config('intelligence_sources.series') as $s) {
            $source = Source::where('key', $s['source'])->firstOrFail();
            $indicator = Indicator::firstOrNew(['key' => $s['key']]);
            $indicator->fill([
                'source_id' => $source->id, 'source_code' => $s['code'], 'name' => $s['name'], 'definition' => $s['definition'], 'unit' => $s['unit'],
                'frequency' => $s['frequency'], 'decimals' => $s['decimals'], 'good_direction' => $s['good'], 'topic' => $s['topic'],
                'stale_after_days' => $s['stale_days'], 'caveat' => $s['caveat'], 'settings' => $s['settings'],
                'licence' => $source->licence, 'is_published' => true,
            ])->save();
            $indicator->industries()->syncWithoutDetaching($industries->only($s['industries'])->values()->all());
        }
    }
}
