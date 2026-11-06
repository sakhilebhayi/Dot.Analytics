<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Intelligence\DataIssue;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Item;
use App\Models\Intelligence\Source;
use App\Services\Intelligence\Reading\Reader;
use Illuminate\View\View;

class SourceController extends Controller
{
    public function __invoke(Reader $reader): View
    {
        $series = Indicator::where('is_published', true)->whereHas('observations')->selectRaw('source_id, count(*) as n')->groupBy('source_id')->pluck('n', 'source_id');
        $items = Item::selectRaw('source_id, count(*) as n')->groupBy('source_id')->pluck('n', 'source_id');
        $sources = Source::where('status', '!=', 'waiting')->orderBy('kind')->orderBy('name')->get()->each(function (Source $s) use ($series, $items) {
            $s->series_count = (int) ($series[$s->id] ?? 0);
            $s->item_count = (int) ($items[$s->id] ?? 0);
        });

        return view('intel.sources', [
            'sources' => $sources,
            'waiting' => Source::where('status', 'waiting')->orderBy('name')->get(),
            'issues' => DataIssue::whereNull('resolved_at')->orderByRaw("case severity when 'high' then 0 when 'medium' then 1 else 2 end")->orderByDesc('last_seen_at')->limit(30)->get(),
            'holdings' => $reader->holdings(),
            'unpublished' => Indicator::where('is_published', false)->count(),
        ]);
    }
}
