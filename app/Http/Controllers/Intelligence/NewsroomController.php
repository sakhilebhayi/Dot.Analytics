<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Industry;
use App\Models\Intelligence\Item;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsroomController extends Controller
{
    public function index(Request $request): View
    {
        $text = fn (string $key): string => is_string($request->query($key)) ? mb_substr((string) $request->query($key), 0, 60) : '';
        $kind = in_array($request->query('kind'), ['announcement', 'regulatory', 'press-release', 'statistics-release'], true) ? $request->query('kind') : null;
        $filters = ['industry' => $text('industry'), 'place' => $text('place'), 'kind' => $kind];
        $industry = ! empty($filters['industry']) ? Industry::where('slug', $filters['industry'])->first() : null;
        $place = ! empty($filters['place']) ? Geography::where('code', strtoupper($filters['place']))->first() : null;

        $items = Item::listed()->with('source:id,name,key,allows_summary')
            ->when($industry, fn ($q) => $q->whereHas('industries', fn ($i) => $i->where('industries.id', $industry->id)))
            ->when($place, fn ($q) => $q->whereHas('geographies', fn ($g) => $g->where('geographies.id', $place->id)))
            ->when($filters['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->orderByDesc('published_at')->paginate(25)->withQueryString();

        return view('intel.newsroom', ['items' => $items, 'industry' => $industry, 'place' => $place, 'kind' => $kind, 'industries' => Industry::orderBy('sort')->get(), 'places' => Geography::where('kind', 'country')->where('is_featured', true)->orderBy('name')->get()]);
    }

    public function show(Item $item): View
    {
        abort_if($item->hidden_at !== null, 404);

        return view('intel.item', [
            'item' => $item->load('source', 'industries', 'geographies'),
            'related' => Item::listed()->where('id', '!=', $item->id)->whereHas('industries', fn ($q) => $q->whereIn('industries.id', $item->industries()->pluck('industries.id')))->orderByDesc('published_at')->limit(5)->get(),
        ]);
    }
}
