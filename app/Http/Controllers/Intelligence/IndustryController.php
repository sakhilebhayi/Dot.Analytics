<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Industry;
use App\Services\Intelligence\Ecosystem;
use App\Services\Intelligence\Reading\Reader;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndustryController extends Controller
{
    public function index(Reader $reader): View
    {
        $za = $reader->geographyByCode('ZAF');

        return view('intel.industries', ['rows' => Industry::orderBy('sort')->get()->map(function (Industry $i) use ($reader, $za) {
            $cells = $za ? $reader->coverage($i->indicators()->where('is_published', true)->get(), $za) : [];

            return ['industry' => $i, 'cells' => $cells, 'grade' => $reader->grade($cells)];
        })]);
    }

    public function show(Request $request, Industry $industry, Reader $reader, Ecosystem $ecosystem): View
    {
        $place = Geography::where('code', strtoupper((string) $request->query('place', 'ZAF')))->where('kind', '!=', 'aggregate')->first()
            ?? $reader->geographyByCode('ZAF');
        $indicators = $industry->indicators()->where('is_published', true)->orderBy('name')->get();
        $cells = $place ? $reader->coverage($indicators, $place) : [];

        return view('intel.industry', [
            'industry' => $industry, 'place' => $place, 'indicators' => $indicators,
            'cells' => $cells, 'grade' => $reader->grade($cells),
            'figures' => $place ? $reader->industryFigures($industry, $place) : [],
            'items' => $reader->items(8, $industry, null),
            'directory' => $ecosystem->directoryUrl($industry),
            'directoryVerified' => $ecosystem->directoryUrl($industry, true),
            'places' => Geography::where('kind', 'country')->where('is_featured', true)->orderBy('name')->get(),
        ]);
    }
}
