<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Intelligence\Industry;
use App\Models\Intelligence\Source;
use App\Services\Intelligence\BriefingBuilder;
use App\Services\Intelligence\Ecosystem;
use App\Services\Intelligence\Reading\Reader;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Reader $reader, BriefingBuilder $briefings, Ecosystem $ecosystem): View
    {
        $za = $reader->geographyByCode('ZAF');
        $industries = Industry::orderBy('sort')->get()->map(function (Industry $industry) use ($reader, $za) {
            $cells = $za ? $reader->coverage($industry->indicators()->where('is_published', true)->get(), $za) : [];

            return ['industry' => $industry, 'cells' => $cells, 'grade' => $reader->grade($cells)];
        });

        return view('intel.home', [
            'figures' => $reader->keyFigures(4),
            'items' => $reader->items(8),
            'industries' => $industries,
            'matrix' => $reader->matrix((array) config('intelligence_home.matrix'), (array) config('intelligence_home.matrix_countries')),
            'moves' => $reader->signals(3)->map(fn ($s) => ['sentence' => $briefings->movement($s), 'href' => route('intel.indicator', [$s->indicator, 'place' => $s->geography->code]), 'why' => $s->indicator->source->name.' · '.$s->period->format('F Y')]),
            'infodot' => $ecosystem->infodotUrl(),
            'holdings' => $reader->holdings(),
            'waiting' => Source::where('status', 'waiting')->orderBy('name')->get(),
        ]);
    }
}
