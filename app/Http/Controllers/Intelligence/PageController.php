<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Industry;
use App\Models\Intelligence\Item;
use App\Services\Intelligence\Ecosystem;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    public function method(): View
    {
        return view('intel.method');
    }

    public function ecosystem(Ecosystem $ecosystem): View
    {
        return view('intel.ecosystem', ['platforms' => $ecosystem->platforms()]);
    }

    public function sitemap(): Response
    {
        $urls = [route('intel.home'), route('intel.industries'), route('intel.countries'), route('intel.indicators'), route('intel.newsroom'), route('intel.briefings'), route('intel.sources'), route('intel.method'), route('intel.ecosystem')];

        foreach (Industry::orderBy('sort')->get() as $i) {
            $urls[] = route('intel.industry', $i);
        }

        foreach (Geography::where('kind', 'country')->whereHas('observations')->get() as $g) {
            $urls[] = route('intel.country', $g);
        }

        foreach (Indicator::where('is_published', true)->whereHas('observations')->get() as $i) {
            $urls[] = route('intel.indicator', $i);
        }

        foreach (Item::listed()->orderByDesc('published_at')->limit(500)->get() as $item) {
            $urls[] = route('intel.item', $item);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .collect($urls)->map(fn ($u) => '<url><loc>'.e($u).'</loc></url>')->implode('').'</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
