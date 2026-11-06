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
        $sources = Source::where('status', '!=', 'waiting')->orderBy('kind')->orderBy('name')->get()->each(function (Source $s) {
            $s->series_count = Indicator::where('source_id', $s->id)->where('is_published', true)->whereHas('observations')->count();
            $s->item_count = Item::where('source_id', $s->id)->count();
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
