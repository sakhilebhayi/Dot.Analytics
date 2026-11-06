<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Industry;
use App\Services\Intelligence\Reading\Reader;
use Illuminate\View\View;

class CountryController extends Controller
{
    public function index(Reader $reader): View
    {
        return view('intel.countries', [
            'matrix' => $reader->matrix((array) config('intelligence_home.matrix'), (array) config('intelligence_home.matrix_countries')),
            'regions' => Geography::where('kind', 'country')->orderBy('name')->get()->groupBy('region')
                ->sortBy(fn ($group, $region) => (str_contains((string) $region, 'Africa') ? '0' : '1').$region),
        ]);
    }

    public function show(Geography $geography, Reader $reader): View
    {
        $byTopic = $reader->countryFigures($geography);
        $all = Industry::orderBy('sort')->get();
        $coverage = $reader->industryCoverage($all, $geography);
        $industries = $all->map(fn (Industry $i) => ['industry' => $i] + $coverage[$i->slug]);

        return view('intel.country', [
            'geography' => $geography, 'byTopic' => $byTopic, 'industries' => $industries,
            'items' => $reader->items(8, null, $geography),
            'count' => collect($byTopic)->flatten(1)->count(),
        ]);
    }
}
